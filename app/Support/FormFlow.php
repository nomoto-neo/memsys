<?php

namespace App\Support;

use App\Enums\OperationLogAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 入力・確認・保存と削除の共通処理。管理画面の登録・編集と、お問い合わせフォームで使う。
 *
 * ルートから呼ばれる入口のメソッドはコントローラーに残し、その中の検証・確認画面の値の
 * 組み立て・保存の手順だけを受け持つ。入口を残すのは、ルートとメソッドを1対1に保つためと、
 * URLのidからモデルを探して渡す仕組みが、引数にNews $newsのような型が無いと働かないため。
 * 確認画面を挟まないコーナーは、store()・update()からそのままsaveData()を呼べばよい。
 *
 * ■ コントローラーが必ず用意するもの
 * - rules($record)            検証のルール。$recordは新規登録ならnull、更新なら対象のモデル。
 *                             使わないなら引数なしのrules()でよい
 * - saveFieldNames($validated, $record)
 *                             保存する列の一覧。ここに書いた列だけを保存する
 * - inputFromModel($record)   モデルの今の値から画面に渡す$inputを作る。詳細・編集の画面が
 *                             無いコーナーでは要らない
 *
 * ■ 必要なときだけ用意するもの
 * - defaultInput()                    新規登録の初期値
 * - prepareInput($validated)          検証の後、確認画面と保存の前の整形。例：safe_html()
 * - additionalFields($validated, $record)
 *                                     入力値をそのまま使わずに保存する列。例：ハッシュ値にした
 *                                     パスワード、操作したスタッフのid。saveFieldNames()と同じ列は
 *                                     こちらの値が勝つ
 * - afterSave($record, $validated, $changedFields)
 *                                     保存の直後の処理。例：関連テーブルのsync()。$changedFieldsは
 *                                     更新で値が変わった列で、列の名前 => 変わる前の値。使わなければ
 *                                     引数に書かなくてよい。新規登録とCSV取り込みでは空
 * - savedLogAction($created)          操作ログに残す操作の種類。登録・更新とは別の名前で残したい
 *                                     コーナーで書く。例：一斉メールの送信
 * - beforeDelete($record)             削除の直前の処理。例：関連テーブルのdetach()
 * 何もしない版がこのトレイトにあり、コントローラーに同じ名前で書けばそちらが使われる。
 *
 * 必ず用意するものをabstractにしていないのは、abstractにするとコントローラーの引数の型を
 * Modelにしなければならず、News $newsのように書けなくなるため。書き忘れると、呼ばれたときに
 * メソッドが無いというエラーになる。
 *
 * ■ トランザクション
 * saveData()とdeleteData()は、本体・アップロード・afterSave()・beforeDelete()の中の書き込みまで
 * 1つのトランザクションで行う。afterSave()などの中で自分でトランザクションを書く必要は無い。
 *
 * ■ 操作ログ
 * saveData()とdeleteData()は、操作ログを書く（App\Support\OperationRecorder）。
 * 更新では、値が変わった列の名前も残す。値は残さない。コントローラーに書くものは無い。
 * 誰もログインしていない訪問者の保存も、操作した人を空にして残す。
 *
 * ■ アップロード
 * コントローラーがAjaxFileUploadも使っていれば、$inputの組み立てと保存のときにその処理も呼ぶ。
 * コントローラーの書き方はアップロードの有無で変わらない。
 */
trait FormFlow
{
    // rules()から必須マークの配列を作る。$alsoRequiredの意味はrequired_fields()と同じ。
    // 例：新規登録のpassword_confirmationは、$this->requiredFields(null, ['password_confirmation'])
    private function requiredFields(?Model $record = null, array $alsoRequired = []): array
    {
        return required_fields($this->rules($record), $alsoRequired);
    }

    // 入力値を検証してprepareInput()で整形して返す。確認画面と保存のどちらもここを通るので、
    // 確認画面に出る値と保存される値は必ず一致する。
    private function validatedInput(Request $request, ?Model $record = null): array
    {
        return $this->prepareInput($request->validate($this->rules($record)));
    }

    // 確認画面に渡す$input。表示にもhiddenにもこれを使う。アップロードの項目は組み立て直した値を使う
    private function confirmInput(Request $request, ?Model $record = null): array
    {
        $validated = $this->validatedInput($request, $record);

        return $this->uploadInput($record, $validated) + $validated + $this->emptyInput($record);
    }

    /**
     * 新規登録・詳細・編集の画面に渡す$input。$recordがnullなら新規登録。
     * $sourceは入力し直している値で、確認画面から戻ったときや入力エラーのときのold()。
     * 優先するのは$source、モデルの今の値か初期値、アップロードの項目の順。
     * パスワードのように再表示したくない項目は、呼ぶ側で$sourceから外して渡す。
     */
    private function formInput(?Model $record, array $source = []): array
    {
        $base = $record === null ? $this->defaultInput() : $this->inputFromModel($record);

        return $source + $base + $this->uploadInput($record, $source) + $this->emptyInput($record);
    }

    /**
     * rules()にある項目の全部を、値をnullにして返す。$inputの最後に足して、値の無い項目も
     * キーだけは必ずあるようにする。画面で{{ $input['name'] }}のように、?? ''を付けずに
     * 書けるようにするため。チェックの無いチェックボックスのように、送られてこない項目もある。
     * 保存では、送られてこなかった項目とnullの項目を同じに扱うので、保存の結果は変わらない。
     */
    private function emptyInput(?Model $record): array
    {
        $empty = [];

        foreach (array_keys($this->rules($record)) as $key) {
            // 'prefecture.*'のような配列の要素のルールは、項目としては扱わない
            if (! str_contains((string) $key, '.')) {
                $empty[$key] = null;
            }
        }

        return $empty;
    }

    // アップロードの項目の$input。AjaxFileUploadを使っていなければ空
    private function uploadInput(?Model $record, array $source = []): array
    {
        return method_exists($this, 'ajaxUploadInput') ? $this->ajaxUploadInput($record, $source) : [];
    }

    // 登録・更新の実行。新規登録はnew モデル()を、更新は対象のモデルを渡す
    private function saveData(Model $record, Request $request): void
    {
        // 保存の前にもう一度検証する。新規登録なら確認画面と同じくrules()にnullを渡す
        $validated = $this->validatedInput($request, $record->exists ? $record : null);

        DB::transaction(fn () => $this->saveValidated($record, $validated, $request->all()));
    }

    /**
     * 検証済みの値を保存する。トランザクションは呼ぶ側で始めておく。
     * 画面からの保存とCSV取り込みの両方がここを通るので、保存の中身はどちらも同じになる。
     *
     * $input       アップロードの確定に渡す入力。画面からなら$request->all()
     * $fromImport  CSV取り込みから呼ぶときtrue。アップロードのファイル名を変えることを認める
     */
    private function saveValidated(Model $record, array $validated, array $input, bool $fromImport = false): void
    {
        // saveFieldNames()の列だけを入力値から取り出し、additionalFields()の列も足して保存する。
        // 送られてこなかった列はnullで、同じ列はadditionalFields()の値が勝つ
        $attributes = [];
        foreach ($this->saveFieldNames($validated, $record) as $field) {
            $attributes[$field] = $validated[$field] ?? null;
        }

        $record->fill($this->additionalFields($validated, $record) + $attributes);

        // 新規登録か更新かと、この保存で値が変わる列を控えておく。列の名前 => 変わる前の値
        $created = ! $record->exists;
        $changed = [];
        foreach (array_keys($record->getDirty()) as $field) {
            $changed[$field] = $record->getOriginal($field);
        }

        $record->save();

        // アップロードしたファイルを確定する。必ず保存の直後に行う。新規登録では保存先を決める
        // idが要り、更新ではエディタの画像の片付けに保存で変わる前の本文を使うため
        if (method_exists($this, 'commitUploads')) {
            $beforeUploads = $record->getRawOriginal();

            $this->commitUploads($record, $input, $fromImport);

            // アップロードの確定で変わった列も足す。確定は列ごとに保存し直すので、前後の値を比べて拾う
            foreach ($record->getRawOriginal() as $field => $value) {
                if ($value !== ($beforeUploads[$field] ?? null)) {
                    $changed[$field] ??= $beforeUploads[$field] ?? null;
                }
            }
        }

        // 新規登録では全部の列が対象なので、変わった列は持たない。更新では、保存のたびに変わる列などを除く
        $loggable = $created ? [] : OperationRecorder::loggableFields($record, array_keys($changed));
        $changed = array_intersect_key($changed, array_flip($loggable));

        // CSV取り込みでは、変わった列を渡さない。1件ずつのお知らせなどを出さないため
        $this->afterSave($record, $validated, $fromImport ? [] : $changed);

        // 操作ログ。列の名前だけを残し、値は残さない。
        // CSV取り込みは、取り込み全体で1行をCsvImportが書くので、1件ずつは書かない
        if (! $fromImport) {
            OperationRecorder::record($this->savedLogAction($created), $record, array_keys($changed));
        }
    }

    // 削除の実行。関連データとアップロードを先に消してから本体を消す。
    // ファイルの実物は、トランザクションが確定した後に消える
    private function deleteData(Model $record): void
    {
        DB::transaction(function () use ($record) {
            $this->beforeDelete($record);

            if (method_exists($this, 'deleteAllUploads')) {
                $this->deleteAllUploads($record);
            }

            $record->delete();

            OperationRecorder::record(OperationLogAction::Delete, $record);
        });
    }

    // ---- 必要なときだけコントローラーで書き換える処理。ここでは何もしない ----

    // 新規登録の初期値
    private function defaultInput(): array
    {
        return [];
    }

    // 検証の後、確認画面と保存の前の整形
    private function prepareInput(array $validated): array
    {
        return $validated;
    }

    // 入力値をそのまま使わずに保存する列
    private function additionalFields(array $validated, Model $record): array
    {
        return [];
    }

    // 保存の直後の処理。$changedFieldsは、更新で値が変わった列の、列の名前 => 変わる前の値
    // （新規登録とCSV取り込みでは空）
    private function afterSave(Model $record, array $validated, array $changedFields = []): void
    {
    }

    // 操作ログに残す操作の種類。$createdは新規登録ならtrue
    private function savedLogAction(bool $created): OperationLogAction
    {
        return $created ? OperationLogAction::Create : OperationLogAction::Update;
    }

    // 削除の直前の処理
    private function beforeDelete(Model $record): void
    {
    }
}
