<?php

namespace Neobit\Core\Crud;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Neobit\Core\Rules\SameCountAsRule;
use Neobit\Core\Rules\UploadRequiredRule;
use Neobit\Core\Upload\UploadFieldDefinitions;
use Neobit\Core\Upload\UploadFilePath;

/**
 * 繰り返しの組。子テーブルの1行を「いくつかの項目（ファイルを含んでよい）の組」として、
 * 画面で増やしたり消したりしながら入力し、親と一緒に保存する。
 *
 * ■ コントローラーが用意するもの
 * - REPEATABLE_GROUPS：組の定義。書き方はRepeatableGroupDefinitionsにある
 * - 組の中のファイルの項目は、UPLOAD_FILESに'photos.*.image'のように書き、AjaxFileUploadも使う
 * - rules()：組の項目の要素のルールを「組の名前_項目.*」に書き、repeatableGroupRules()を+で足す
 *     'photos_caption.*' => ['nullable', 'string', 'max:200'],
 * - 親のモデルに、子テーブルへのHasManyのリレーション。子のモデルの$fillableに、組の項目と並び順の列
 * - ファイルの項目は、子テーブルに「列」（保存したファイル名）と「列_origin」（表示名）を作る
 *
 * FormFlowを使っていれば、値の組み立て・空の行の除き方・保存・削除はFormFlowがここを呼ぶ。
 * コントローラーの書き方は、組の有無で変わらない。
 *
 * ■ 画面から送る値
 * 行の並びをそろえた、列ごとの配列で送る（RepeatableGroupDefinitions::inputKeys()）。
 * 確認画面のhidden（_confirm_hidden）が、1段の配列しか持ち回れないため。
 *   photos_id[]・photos_caption[]・photos_image[]・photos_image_tmp[]・photos_image_origin[]・photos_image_del[]
 * あわせて、組が送られたことを示すphotos_groupを送る。行が1つも無いときは配列がどれも空になり、
 * 確認画面から先へは何も持ち回られないので、この印が無いと「組を空にする」と「組を送っていない」を見分けられない。
 *
 * ■ 保存
 * 子テーブルの行のidを画面に持ち回り、行のidを保って揃える。
 * - 届いたidのうち、この親の行のものは更新する。ほかの親の行のidや、2回目に出てきた同じidは、新しい行として扱う
 * - idの無い行は足す
 * - 届かなかった行は消す
 * - 並び順の列があれば、画面の並びの番号を入れる
 * 組に'delete_insert' => trueを書くと、今の行を全部消して、画面の順に入れ直す。行のidは保たないが、
 * idの順が画面の順になるので、並び順の列が要らない。ほかのテーブルから子の行を参照するときは使わない。
 * ファイルの項目は、今のファイル名が「その行の今の値」と一致するときだけ残す。hiddenを書き換えて、
 * ほかの欄のファイルを指させないため。使わなくなったファイルは、トランザクションが確定した後に消す。
 * CSV取り込みでは、組を保存しない（組の列を取り込む仕組みはまだ無い）。
 */
trait RepeatableGroup
{
    /** コントローラーの全部の組の定義。組の名前 => 定義 */
    private function repeatableGroupDefinitions(): array
    {
        return RepeatableGroupDefinitions::of(static::class);
    }

    /**
     * 組のhiddenと行の数を確かめるルール。コントローラーのrules()に+で足して使う。
     * 組の項目の要素のルール（'photos_caption.*'など）は、コントローラーのrules()に書く。
     */
    public function repeatableGroupRules(): array
    {
        $rules = [];

        foreach ($this->repeatableGroupDefinitions() as $group => $def) {
            $keys = RepeatableGroupDefinitions::inputKeys($def);

            // 列ごとの配列は、どれも行の数だけの要素で揃っていること。画面は全部を1組で増減させるので、
            // 揃っていなければhiddenが書き換えられている
            foreach ($keys as $key) {
                $rules[$key] = ['array', new SameCountAsRule(array_values(array_diff($keys, [$key])))];
            }

            // 行の数の上限と下限。空の行は検証の前に除いてあるので、配列の要素数がそのまま行の数
            $rules["{$group}_id"][] = 'max:'.$def['max'];
            if ($def['min'] > 0) {
                $rules["{$group}_id"][] = 'min:'.$def['min'];
            }

            $rules["{$group}_id.*"] = ['nullable', 'integer'];
            $rules[RepeatableGroupDefinitions::markerKey($group)] = ['nullable', 'in:1'];

            // ファイルの項目の4本の配列の要素は、アップロードの欄と同じ形
            foreach ($def['files'] as $field) {
                $base = "{$group}_{$field}";
                $rules["{$base}.*"] = ['nullable', 'regex:'.UploadFilePath::SAFE_FILENAME];
                $rules["{$base}_tmp.*"] = ['nullable', 'regex:'.UploadFilePath::SAFE_FILENAME];
                $rules["{$base}_origin.*"] = ['nullable', 'string', 'max:255'];
                $rules["{$base}_del.*"] = ['nullable', 'in:0,1'];
            }
        }

        return $rules;
    }

    /**
     * 組の中の必須のファイルの欄（UPLOAD_FILESの'required' => true）のルール。FormFlowが検証のときに、
     * rules()の結果に足す。空の行は除いてあるので、残っている行のどれにもファイルを求める。
     * 今のファイルは、その行（この親の行のidで、同じidの2回目ではないもの）の今の値と照らす。保存のときと同じ見分け方
     */
    private function repeatableGroupRequiredRules(Request $request, ?Model $record): array
    {
        $rules = [];

        // 組ごとの、必須のファイルの欄の列
        $requiredColumns = [];
        foreach (UploadFieldDefinitions::of(static::class) as $upload) {
            if ($upload['kind'] === 'group' && $upload['required']) {
                $requiredColumns[$upload['group']][] = $upload['column'];
            }
        }

        foreach ($this->repeatableGroupDefinitions() as $group => $def) {
            if (! isset($requiredColumns[$group])) {
                continue;
            }

            // この親の今の行。新規登録では無い
            $relation = $record?->{$def['relation']}();
            $current = $relation ? $relation->get()->keyBy($relation->getRelated()->getKeyName()) : collect();

            $usedIds = [];
            $ids = $request->input("{$group}_id", []);

            foreach (array_values(is_array($ids) ? $ids : []) as $i => $id) {
                // 行の見分け方は、保存のとき（saveRepeatableGroup()）と同じ
                $isCurrentRow = filled($id) && isset($current[$id]) && ! in_array((int) $id, $usedIds, true);
                $row = $isCurrentRow ? $current[$id] : null;
                if ($row !== null) {
                    $usedIds[] = (int) $row->getKey();
                }

                foreach ($requiredColumns[$group] as $column) {
                    $base = "{$group}_{$column}";
                    $rules["{$base}.{$i}"] = [new UploadRequiredRule("{$base}_tmp.{$i}", "{$base}_del.{$i}", $row?->getRawOriginal($column))];
                }
            }
        }

        return $rules;
    }

    /**
     * 送られた組から、全部の項目が空の行を除き、配列を詰めてリクエストに戻す。FormFlowが検証の前に呼ぶ。
     * 確認画面へ進むときと保存のときの両方がここを通るので、どちらも同じ行になる。
     * 空とは、ファイルではない項目がどれも空で、ファイルの項目がどれも「今のファイルも新しいファイルも無いか、
     * 削除の印が立っている」こと。idだけがある行も空とみなし、保存のときに消える。
     */
    private function dropEmptyRepeatableRows(Request $request): void
    {
        foreach ($this->repeatableGroupDefinitions() as $group => $def) {
            // 組が送られていなければ触らない
            if (! $request->has(RepeatableGroupDefinitions::markerKey($group))) {
                continue;
            }

            $keys = RepeatableGroupDefinitions::inputKeys($def);
            $columns = [];
            foreach ($keys as $key) {
                $value = $request->input($key, []);
                $columns[$key] = is_array($value) ? array_values($value) : [];
            }

            $kept = array_fill_keys($keys, []);
            $count = max(array_map('count', $columns));

            for ($i = 0; $i < $count; $i++) {
                if ($this->isEmptyRepeatableRow($def, $columns, $i)) {
                    continue;
                }

                foreach ($keys as $key) {
                    $kept[$key][] = $columns[$key][$i] ?? null;
                }
            }

            $request->merge($kept);
        }
    }

    /** 列ごとの配列の$i行目が、全部の項目が空の行か */
    private function isEmptyRepeatableRow(array $def, array $columns, int $i): bool
    {
        $group = $def['group'];

        // ファイルではない項目に、1つでも値があれば空ではない
        foreach ($def['texts'] as $field) {
            if (filled($columns["{$group}_{$field}"][$i] ?? null)) {
                return false;
            }
        }

        // ファイルの項目に、新しいファイルか、消さない今のファイルがあれば空ではない
        foreach ($def['files'] as $field) {
            $base = "{$group}_{$field}";
            $hasTmp = filled($columns["{$base}_tmp"][$i] ?? null);
            $keepsFile = filled($columns[$base][$i] ?? null) && ($columns["{$base}_del"][$i] ?? null) != '1';

            if ($hasTmp || $keepsFile) {
                return false;
            }
        }

        return true;
    }

    /**
     * 画面へ渡す$inputのうち、組の分。列ごとの配列を、どれも行の数だけの要素で揃えて返す。
     * $sourceに組の印があれば、入力し直していた値（確認画面から戻ったとき・入力エラーのとき・確認画面）から
     * 組み立てる。無ければ$modelの子テーブルの今の行から組み立てる。新規登録では行が無い。
     * 組の印は、いつも入れる。
     */
    public function repeatableGroupValues(?Model $model, array $source = []): array
    {
        $result = [];

        foreach ($this->repeatableGroupDefinitions() as $group => $def) {
            $keys = RepeatableGroupDefinitions::inputKeys($def);
            $marker = RepeatableGroupDefinitions::markerKey($group);

            // 入力し直していた値から。old()は要素数のずれで差し戻された入力のこともあるので、欠けを埋めて揃える
            if (array_key_exists($marker, $source)) {
                $columns = [];
                foreach ($keys as $key) {
                    $value = $source[$key] ?? [];
                    $columns[$key] = is_array($value) ? array_values($value) : [];
                }

                $count = count($columns["{$group}_id"]);
                foreach ($keys as $key) {
                    $result[$key] = array_pad(array_slice($columns[$key], 0, $count), $count, null);
                }

                $result[$marker] = '1';

                continue;
            }

            // 子テーブルの今の行から。新規登録では行が無い
            $rows = $model?->exists ? $this->repeatableGroupRows($model, $def) : [];
            foreach ($keys as $key) {
                $result[$key] = [];
            }

            foreach ($rows as $row) {
                $result["{$group}_id"][] = (string) $row->getKey();

                foreach ($def['texts'] as $field) {
                    $result["{$group}_{$field}"][] = $row->getRawOriginal($field);
                }

                foreach ($def['files'] as $field) {
                    $base = "{$group}_{$field}";
                    $result[$base][] = $row->getRawOriginal($field);
                    $result["{$base}_tmp"][] = null;
                    $result["{$base}_origin"][] = $row->getRawOriginal("{$field}_origin");
                    $result["{$base}_del"][] = '';
                }
            }

            $result[$marker] = '1';
        }

        return $result;
    }

    /** 子テーブルの今の行。並び順の列があればその順、無ければidの順。リレーションに書いた並び順は使わない */
    private function repeatableGroupRows(Model $model, array $def): Collection
    {
        $relation = $model->{$def['relation']}();
        $orderColumn = $def['order'] ?? $relation->getRelated()->getKeyName();

        return $relation->reorder()->orderBy($orderColumn)->get();
    }

    /**
     * 組を保存する。FormFlowが本体の保存とアップロードの確定の後に、同じトランザクションの中で呼ぶ。
     * 組の印が届いていない組と、CSV取り込みでは何もしない。
     */
    private function saveRepeatableGroups(Model $model, array $validated, bool $fromImport = false): void
    {
        if ($fromImport) {
            return;
        }

        foreach ($this->repeatableGroupDefinitions() as $group => $def) {
            if (($validated[RepeatableGroupDefinitions::markerKey($group)] ?? null) !== '1') {
                continue;
            }

            $this->saveRepeatableGroup($model, $def, $validated);
        }
    }

    /** 1つの組の子テーブルを、送られた行に揃える。使わなくなったファイルは、確定の後に消す */
    private function saveRepeatableGroup(Model $model, array $def, array $validated): void
    {
        $group = $def['group'];
        $relation = $model->{$def['relation']}();
        $keyName = $relation->getRelated()->getKeyName();

        // この親の今の行。id => 行
        $current = $relation->get()->keyBy($keyName);
        $filesBefore = $this->repeatableGroupFilenames($current, $def);

        $keptIds = [];
        $usedIds = [];
        $count = count($validated["{$group}_id"] ?? []);

        for ($i = 0; $i < $count; $i++) {
            // この親の行のidで、まだ使っていないものだけを今の行として扱う
            $id = $validated["{$group}_id"][$i] ?? null;
            $isCurrentRow = filled($id) && isset($current[$id]) && ! in_array((int) $id, $usedIds, true);
            $row = $isCurrentRow ? $current[$id] : null;
            if ($row !== null) {
                $usedIds[] = (int) $row->getKey();
            }

            $values = [];

            foreach ($def['texts'] as $field) {
                $values[$field] = $validated["{$group}_{$field}"][$i] ?? null;
            }

            foreach ($def['files'] as $field) {
                [$values[$field], $values["{$field}_origin"]] = $this->repeatableGroupFileValue($model, $row, $def, $field, $validated, $i);
            }

            // 並び順の列があれば、画面の並びの番号
            if ($def['order'] !== null) {
                $values[$def['order']] = count($keptIds);
            }

            if ($def['delete_insert']) {
                // 作り直す保存。今の行は下でまとめて消すので、どの行も新しく入れる
                $keptIds[] = (int) $relation->create($values)->getKey();
            } elseif ($row !== null) {
                $row->update($values);
                $keptIds[] = (int) $row->getKey();
            } else {
                $keptIds[] = (int) $relation->create($values)->getKey();
            }
        }

        // 届かなかった行を消す（作り直す保存では、入れ直す前の行がすべて当たる）。
        // 条件を足すとリレーションの問い合わせが変わるので、新しく作ったものに足す
        $model->{$def['relation']}()->whereNotIn($keyName, $keptIds)->delete();

        // 使わなくなったファイルを、トランザクションが確定した後に消す
        $filesAfter = $this->repeatableGroupFilenames($model->{$def['relation']}()->get(), $def);
        $this->deleteRepeatableGroupFilesAfterCommit($model, array_diff_key($filesBefore, $filesAfter));
    }

    /**
     * 1行のファイルの項目の、保存する[ファイル名, 表示名]。
     * 新しいファイルがあれば、一時ファイルを保存先へ移してその名前にする。
     * 削除の印が立っているか、今のファイル名が「その行の今の値」と一致しなければ空にする。
     * どちらでもなければ、その行の今のファイルと表示名のまま。
     */
    private function repeatableGroupFileValue(Model $model, ?Model $row, array $def, string $field, array $validated, int $i): array
    {
        $base = "{$def['group']}_{$field}";
        $tmp = $validated["{$base}_tmp"][$i] ?? null;

        // 新しいファイル。移せなければ（一時ファイルが片付けで消えたなど）、無かったときと同じく下へ進む
        if ($tmp !== null && $tmp !== '') {
            $filename = $this->moveTmpToFinal($model, $base, $tmp);

            if ($filename !== null) {
                return [$filename, ($validated["{$base}_origin"][$i] ?? null) ?: null];
            }
        }

        $currentFile = $row?->getRawOriginal($field);
        $sent = $validated[$base][$i] ?? null;
        $deleted = ($validated["{$base}_del"][$i] ?? null) == '1';

        if ($deleted || $currentFile === null || $sent !== $currentFile) {
            return [null, null];
        }

        return [$currentFile, $row->getRawOriginal("{$field}_origin")];
    }

    /** 行の並びの、ファイルの項目のファイル名。ファイル名 => 画面から送る名前（ディスクを決めるのに使う） */
    private function repeatableGroupFilenames(iterable $rows, array $def): array
    {
        $filenames = [];

        foreach ($rows as $row) {
            foreach ($def['files'] as $field) {
                $filename = $row->getRawOriginal($field);
                if ($filename !== null && $filename !== '') {
                    $filenames[$filename] = "{$def['group']}_{$field}";
                }
            }
        }

        return $filenames;
    }

    /** ファイルを、トランザクションが確定した後に消す。取り消されたときに、残る行のファイルが無くならないようにするため */
    private function deleteRepeatableGroupFilesAfterCommit(Model $model, array $filenames): void
    {
        if ($filenames === []) {
            return;
        }

        $directory = UploadFilePath::directory($model::class, $model->getKey());

        DB::afterCommit(function () use ($model, $filenames, $directory) {
            foreach ($filenames as $filename => $field) {
                Storage::disk(UploadFilePath::disk($model::class, $field))->delete("{$directory}/{$filename}");
            }
        });
    }

    /** 親を消す前に呼び、組の子テーブルの行とファイルを消す。FormFlowが本体を消す前に呼ぶ */
    private function deleteRepeatableGroups(Model $model): void
    {
        foreach ($this->repeatableGroupDefinitions() as $def) {
            $relation = $model->{$def['relation']}();
            $filenames = $this->repeatableGroupFilenames($relation->get(), $def);

            $relation->delete();
            $this->deleteRepeatableGroupFilesAfterCommit($model, $filenames);
        }
    }
}
