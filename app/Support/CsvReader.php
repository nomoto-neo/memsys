<?php

namespace App\Support;

use App\Enums\CsvEncoding;
use App\Enums\CsvImportMode;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use InvalidArgumentException;

/**
 * CSVを読んで確かめる部分。CsvImportの取り込み画面が使うほか、一斉メールのように
 * 自分の画面を持つ機能が、CSVの読み込みと検証だけを借りるときにも使う。
 *
 * ■ 受け持つこと
 * - 文字コードの判定、見出しと定義の突き合わせ、1行ずつの検証。結果はCsvImportResultで返す
 * - 確認画面から実行までCSVを一時ディレクトリに置き、合言葉で1回だけ実行させる仕組み
 * 画面・ルート・保存の流れは持たない。それは使う側が決める。
 *
 * ■ 使い方
 * CSVの項目の定義と行の検証ルールは引数で渡す。コントローラーのrules()を画面の入力の検証に
 * 使っていても、CSVの行には別のルールを渡せる。
 *
 *     $result = $this->readCsv(
 *         settings: $settings,
 *         columnDefinitions: self::RECIPIENT_COLUMNS,
 *         rulesFor: fn (?Model $record) => self::RECIPIENT_RULES,
 *         path: $path,
 *         filename: $filename,
 *         encoding: $settings->encoding,
 *     );
 *
 * 確認画面ではstoreCsvForConfirm()で一時ディレクトリに置いてrememberCsv()で合言葉を受け取り、
 * 実行ではpullCsv()で取り出して読み直す。読み直すのは、確認画面の後にデータが変わることがあるため。
 */
trait CsvReader
{
    // アップロードできるCSVファイルの大きさの上限(KB)。サーバーのupload_max_filesizeと
    // post_max_sizeもこれ以上にしておく
    private const CSV_FILE_MAX_KB = 10240;

    // CSVファイルの欄の検証ルール
    private function csvFileRules(): array
    {
        return ['required', 'file', 'extensions:csv,txt', 'max:'.self::CSV_FILE_MAX_KB];
    }

    // ---- 確認画面から実行まで ----

    /**
     * アップロードされたCSVを一時ディレクトリに置き、その場所を返す。
     * 同じ画面で前に置いたファイルが残っていれば消す。確認画面を開いたまま別のファイルを
     * 選び直したときのため。置いたままの古いファイルもついでに片付ける。
     */
    private function storeCsvForConfirm(UploadedFile $file, string $sessionKey): string
    {
        TemporaryDataCleaner::csvImportFiles();

        if ($previous = session()->pull($sessionKey)) {
            $this->deleteCsvFiles($previous['path']);
        }

        return $file->storeAs(CsvImportSettings::TMP_DIR, Str::random(40).'.csv', CsvImportSettings::TMP_DISK);
    }

    // 一時ディレクトリに置いたファイルの、サーバー上の絶対パス
    private function csvFilePath(string $path): string
    {
        return Storage::disk(CsvImportSettings::TMP_DISK)->path($path);
    }

    // CSVの隣に置く控えのファイルの場所。行の数だけ大きくなる控えは、セッションではなくここに置く
    private function csvSidecarPath(string $path): string
    {
        return $path.'.json';
    }

    /**
     * 実行のための状態をセッションに覚え、合言葉を返す。合言葉は確認画面のhiddenに入れる。
     * $stateには、少なくともpath・filename・encodingを入れる。ほかに使う側の値も入れてよい。
     */
    private function rememberCsv(string $sessionKey, array $state): string
    {
        $token = Str::random(40);
        session()->put($sessionKey, ['token' => $token] + $state);

        return $token;
    }

    /**
     * 覚えておいた状態を取り出す。合言葉は1回だけ使えるので、取り出したらセッションから消える。
     * 合言葉が合わないか一時ファイルが無ければ、ファイルを消してnullを返す。
     * 二重の送信、古い確認画面からの送信、セッション切れのどれか。
     */
    private function pullCsv(string $sessionKey, ?string $token): ?array
    {
        $state = session()->pull($sessionKey);

        if (! $state) {
            return null;
        }

        $disk = Storage::disk(CsvImportSettings::TMP_DISK);

        if (! hash_equals($state['token'], (string) $token) || ! $disk->exists($state['path'])) {
            $this->deleteCsvFiles($state['path']);

            return null;
        }

        return $state;
    }

    // 覚えておいた文字コードの名前を、読み直すときの指定に戻す
    private function csvEncodingOf(?string $name): CsvEncoding
    {
        return $name === 'Shift_JIS' ? CsvEncoding::Sjis : CsvEncoding::Utf8Bom;
    }

    // 一時ディレクトリのCSVと、その控えのファイルを消す
    private function deleteCsvFiles(string $path): void
    {
        Storage::disk(CsvImportSettings::TMP_DISK)->delete([$path, $this->csvSidecarPath($path)]);
    }

    // ---- 読み込みと検証 ----
    /**
     * CSVファイルを読んで全行を検証した結果を返す。確認画面と実行の両方でここを通す。
     *
     * @param  array  $columnDefinitions  CSVの項目の定義。書き方はCsvColumnSetにある
     * @param  Closure(?Model): array  $rulesFor  行の検証ルール。新規の行ならnull、更新の行なら対象のレコードを受け取る
     */
    private function readCsv(CsvImportSettings $settings, array $columnDefinitions, Closure $rulesFor, string $path, string $filename, ?CsvEncoding $encoding): CsvImportResult
    {
        $result = new CsvImportResult($filename);
        $isSave = $settings->mode === CsvImportMode::Save;

        // 追加と更新では、CSVのidで探すためのクエリが要る
        if ($isSave && $settings->query === null) {
            throw new InvalidArgumentException('CSV取り込み（追加・更新）には、CsvImportSettingsのqueryが必要です。');
        }

        $model = $settings->query?->getModel();
        $keyName = $model?->getKeyName() ?? '';

        // 追加と更新で取り込む、AjaxFileUploadのアップロードの欄。エディタの欄は除く
        $uploadFields = [];
        if ($isSave && method_exists($this, 'uploadFieldDefinitions')) {
            foreach ($this->uploadFieldDefinitions() as $def) {
                if ($def['kind'] !== 'wysiwyg') {
                    $uploadFields[$def['field']] = $def['kind'];
                }
            }
        }

        // @名前の列の取り込みは、コントローラーにcsvCustomImport()があるときだけ
        $customImport = null;
        if (method_exists($this, 'csvCustomImport')) {
            $customImport = fn (string $key, ?string $value, CsvImportRow $row) => $this->csvCustomImport($key, $value, $row);
        }

        $columns = new CsvColumnSet(
            $columnDefinitions,
            $uploadFields,
            customImport: $customImport,
        );

        // 定義の書き間違いは、ファイルを読む前に例外にする
        if ($isSave && $columns->keyHeading($keyName) === null) {
            throw new InvalidArgumentException("CSV取り込み（追加・更新）には、csvColumns()に{$keyName}の列が必要です。");
        }
        if (! $settings->header) {
            $columns->assertNoExpansion();
        }

        // 文字コードを判定して行に分ける
        [$text, $result->encoding] = $this->decodeCsvFile((string) file_get_contents($path), $encoding);
        if ($text === null) {
            $result->errors[] = match ($encoding) {
                CsvEncoding::Utf8Bom => '文字コードがUTF-8のファイルではありません。',
                CsvEncoding::Sjis => '文字コードがShift_JISのファイルではありません。',
                null => '文字コードを判別できません（UTF-8・Shift_JISのどちらでもないか、混在しています）。',
            };

            return $result;
        }

        $records = $this->parseCsvText($text);

        // 見出しを定義と突き合わせる。見出し無しなら定義の順に並んでいる前提
        $rules = $rulesFor(null);
        $importable = array_values(array_filter(array_map('strval', array_keys($rules)), fn ($k) => ! str_contains($k, '.')));
        $updatedAt = $model?->usesTimestamps() ? $model->getUpdatedAtColumn() : null;
        $refPaths = $updatedAt ? [$updatedAt] : [];

        if ($settings->header) {
            if ($records === []) {
                $result->errors[] = 'CSVに見出しの行がありません。';

                return $result;
            }

            [, $headingCells] = array_shift($records);
            $result->headings = array_map(fn ($cell) => $this->normalizeCsvCell($cell, false) ?? '', $headingCells);
            $mapping = $columns->mapHeadings($result->headings, $importable, $keyName, $refPaths);
        } else {
            $result->headings = $columns->definedHeadings();
            $mapping = $columns->mapByOrder($importable, $keyName, $refPaths);
        }

        // ファイル全体のエラーがあれば行は見ない
        $result->errors = array_merge($result->errors, $mapping['errors']);
        $result->warnings = $mapping['warnings'];

        if ($isSave && $mapping['key'] === null) {
            $result->errors[] = "「{$columns->keyHeading($keyName)}」の列がありません。";
        }
        if ($records === []) {
            $result->errors[] = 'データの行がありません。';
        }
        if ($settings->maxRows !== null && count($records) > $settings->maxRows) {
            $result->errors[] = sprintf('データの行数（%d行）が上限（%d行）を超えています。', count($records), $settings->maxRows);
        }
        if ($result->errors !== []) {
            return $result;
        }

        // CSVのidのデータをまとめて読んでおく
        $found = [];
        if ($mapping['key'] !== null) {
            $ids = [];
            foreach ($records as [, $cells]) {
                $id = $columns->readKey($this->normalizeCsvCell($cells[$mapping['key']] ?? null, $settings->escapeFormula), $keyName);
                if ($id !== null && ctype_digit($id)) {
                    $ids[] = $id;
                }
            }
            foreach (array_chunk(array_unique($ids), 1000) as $chunk) {
                foreach ((clone $settings->query)->reorder()->whereKey($chunk)->get() as $record) {
                    $found[(string) $record->getKey()] = $record;
                }
            }
        }

        // エラーのメッセージに出す項目の名前
        $labels = [];
        foreach ($importable as $field) {
            $labels[$field] = $columns->fieldLabel($field);
        }
        $attributes = [];
        foreach ($labels as $field => $label) {
            $attributes[$field] = $label;
            $attributes["{$field}.*"] = $label;
        }

        $keyHeading = $columns->keyHeading($keyName);
        $seenIds = [];

        // 1行ずつ検証する
        foreach ($records as [$line, $cells]) {
            $row = new CsvImportRow($line);
            $result->rows[] = $row;

            $cells = array_map(fn ($cell) => $this->normalizeCsvCell($cell, $settings->escapeFormula), $cells);
            foreach ($result->headings as $column => $heading) {
                if ($heading !== '') {
                    $row->cells[$heading] = $cells[$column] ?? null;
                }
            }

            // どの行のデータか見分けるための値
            if (is_int($settings->labelColumn)) {
                // 整数なら左から何列目か
                $row->setLabel($cells[$settings->labelColumn - 1] ?? null);
            } elseif ($settings->labelColumn !== null) {
                // 文字列なら見出し
                $row->setLabel($row->cell($settings->labelColumn));
            } else {
                $row->setLabel(null);
            }

            if (! $settings->header && count($cells) !== count($result->headings)) {
                $row->addError(sprintf('列の数が%d個あります（%d個にしてください）。', count($cells), count($result->headings)));

                continue;
            }

            // キーの列からどのデータの行かを決める
            if ($mapping['key'] !== null) {
                $id = $columns->readKey($cells[$mapping['key']] ?? null, $keyName);

                if ($id === null) {
                    // 空欄は追加の行。追加できない取り込みならエラー
                    if (! $settings->allowInsert) {
                        $row->addError('空欄です（この取り込みでは追加はできません）。', $keyHeading);

                        continue;
                    }
                } elseif (! ctype_digit($id)) {
                    $row->addError('形が正しくありません。', $keyHeading);

                    continue;
                } elseif (isset($seenIds[$id])) {
                    $row->addError("同じ値が{$seenIds[$id]}行目にもあります。", $keyHeading);

                    continue;
                } elseif (! isset($found[$id])) {
                    $row->addError('該当するデータがありません（削除された可能性があります）。', $keyHeading);

                    continue;
                } else {
                    // 更新の行
                    $seenIds[$id] = $line;
                    $row->record = $found[$id];
                }
            }

            // 動作とCSVの値を重ねる今の値。処理だけのモードは重ねない
            if (! $isSave) {
                $row->action = 'process';
                $base = [];
            } elseif ($row->record) {
                // 更新はDBの値
                $row->action = 'update';
                $base = $this->inputFromModel($row->record);
            } else {
                // 追加は初期値
                $row->action = 'insert';
                $base = $this->defaultInput();
            }
            $uploadsNow = $isSave ? $this->csvImportCurrentUploads($row->record, $uploadFields) : [];
            $current = $base + $uploadsNow;

            // セルを読む
            $row->values = $columns->readRow($cells, $mapping['map'], $current, $row);
            if ($row->hasErrors()) {
                continue;
            }

            if ($isSave) {
                $this->checkCsvImportUploads($row, $uploadFields, $uploadsNow, $labels);
                if ($row->hasErrors()) {
                    continue;
                }
            }

            // 検証する入力。複数のアップロードの欄は、画面のフォームと同じ4本の配列の形にそろえる
            $input = $row->values + $base;
            foreach ($uploadFields as $field => $kind) {
                if ($kind === 'repeatable' && array_key_exists($field, $input)) {
                    $count = count($input[$field]);
                    $input["{$field}_tmp"] = array_fill(0, $count, null);
                    $input["{$field}_del"] = array_fill(0, $count, null);
                }
            }
            $row->input = $input;

            // 行の検証ルールで検証する
            $validator = Validator::make($input, $rulesFor($row->record), [], $attributes);
            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $key => $messages) {
                    $field = explode('.', $key)[0];
                    foreach ($messages as $message) {
                        $row->addError($message, $labels[$field] ?? null);
                    }
                }

                continue;
            }

            $row->validated = $validator->validated();

            // 追加と更新では、画面からの保存と同じく整形して変更の内容を求める
            if ($isSave) {
                if (method_exists($this, 'prepareInput')) {
                    $row->validated = $this->prepareInput($row->validated);
                }

                $this->csvImportChanges($row, $columns, $current, $uploadFields);
            }

            // ダウンロードした後に画面から変更された行
            if ($updatedAt && $row->record && isset($mapping['refs'][$updatedAt])) {
                $this->checkCsvImportUpdatedAt($row, $columns, $updatedAt, $cells[$mapping['refs'][$updatedAt]] ?? null, $result->headings[$mapping['refs'][$updatedAt]] ?? null);
            }
        }

        // 行をまたいだ確かめ
        $this->checkCsvImportUniqueness($result, $rules, $labels);

        if (method_exists($this, 'validateCsvRows')) {
            $this->validateCsvRows($result);
        }

        return $result;
    }

    /**
     * ファイルの中身をUTF-8の文字列にする。[文字列, 文字コードの名前]で、読めなければ[null, null]。
     * 文字コードはファイル全体で判定する。先頭の数行だけで判定すると、後ろに別の文字コードが
     * 混ざっていたときに文字化けしたまま取り込んでしまうため。
     */
    private function decodeCsvFile(string $bytes, ?CsvEncoding $encoding): array
    {
        // BOM付きUTF-8
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $body = substr($bytes, 3);

            return ($encoding !== CsvEncoding::Sjis && mb_check_encoding($body, 'UTF-8')) ? [$body, 'UTF-8'] : [null, null];
        }

        // UTF-8
        if ($encoding !== CsvEncoding::Sjis && mb_check_encoding($bytes, 'UTF-8')) {
            return [$bytes, 'UTF-8'];
        }

        // Shift_JIS
        if ($encoding !== CsvEncoding::Utf8Bom && mb_check_encoding($bytes, 'SJIS-win')) {
            return [mb_convert_encoding($bytes, 'UTF-8', 'SJIS-win'), 'Shift_JIS'];
        }

        return [null, null];
    }

    // CSVの文字列を行に分ける。[[何行目, セルの一覧], …]で、全部のセルが空欄の行は飛ばす。
    // セルの中の改行は行に数えないので、何行目かはExcelの行番号と一致する。
    private function parseCsvText(string $text): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);

        $records = [];
        $line = 0;

        // ダウンロードと同じく、エスケープ文字を空にしてRFC 4180のとおりに読む
        while (($cells = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $line++;

            if (array_filter($cells, fn ($cell) => $cell !== null && trim($cell) !== '') === []) {
                continue;
            }

            $records[] = [$line, $cells];
        }

        fclose($stream);

        return $records;
    }

    /**
     * セルの値をそろえる。画面からの入力でLaravelが行うのと同じく、前後の空白を除いて空ならnullにする。
     * $escapeFormulaなら、ダウンロードで数式を無害にするために付けた先頭の「'」を、付けたときと
     * 同じ条件のときだけ外す。
     */
    private function normalizeCsvCell(?string $cell, bool $escapeFormula): ?string
    {
        if ($cell === null) {
            return null;
        }

        $cell = preg_replace('~^[\s\x{FEFF}\x{200B}\x{200E}]+|[\s\x{FEFF}\x{200B}\x{200E}]+$~u', '', $cell) ?? $cell;

        if ($escapeFormula && str_starts_with($cell, "'") && strlen($cell) > 1) {
            $rest = substr($cell, 1);

            if (! is_numeric($rest) && in_array($rest[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                $cell = $rest;
            }
        }

        return $cell === '' ? null : $cell;
    }

    // アップロードの欄の今の値。1つだけの欄は{field}と{field}_origin、複数の欄は同じ名前の配列。
    private function csvImportCurrentUploads(?Model $record, array $uploadFields): array
    {
        $current = [];

        foreach ($uploadFields as $field => $kind) {
            if ($kind === 'single') {
                $current[$field] = $record?->{$field};
                $current["{$field}_origin"] = $record?->{"{$field}_origin"};
            } else {
                $rows = $record ? $record->{$field}()->orderBy('id')->get(['filename', 'original_name']) : collect();
                $current[$field] = $rows->pluck('filename')->all();
                $current["{$field}_origin"] = $rows->pluck('original_name')->all();
            }
        }

        return $current;
    }

    // アップロードの欄のファイル名を確かめる。今と違うファイル名はこのレコードの保存先に
    // あるものだけ使える。確かめるのはAjaxFileUpload::checkImportedUploadFilename()。
    private function checkCsvImportUploads(CsvImportRow $row, array $uploadFields, array $uploadsNow, array $labels): void
    {
        foreach ($uploadFields as $field => $kind) {
            $label = $labels[$field] ?? $field;

            if (! array_key_exists($field, $row->values)) {
                // 1つだけの欄で、ファイルが無いのに表示名の列だけがある
                if ($kind === 'single' && ($row->values["{$field}_origin"] ?? null) !== null && $uploadsNow[$field] === null) {
                    $row->addError('ファイルが無いので、表示名は入力できません。', $labels["{$field}_origin"] ?? $label);
                }

                continue;
            }

            // 1つだけの欄で、ファイル名が空欄なのに表示名がある
            if ($kind === 'single' && $row->values[$field] === null && ($row->values["{$field}_origin"] ?? null) !== null) {
                $row->addError('ファイル名が空欄です。', $label);

                continue;
            }

            $filenames = $kind === 'single' ? array_filter([$row->values[$field]]) : $row->values[$field];
            $now = (array) $uploadsNow[$field];

            foreach ($filenames as $filename) {
                // 今のファイルのままなら確かめない
                if (in_array($filename, $now, true)) {
                    continue;
                }

                // 追加の行はまだ保存先が無い
                if ($row->record === null) {
                    $row->addError("追加の行には、ファイル名（{$filename}）を指定できません。", $label);

                    continue;
                }

                if ($message = $this->checkImportedUploadFilename($row->record, $field, $filename)) {
                    $row->addError($message, $label);
                }
            }
        }
    }

    // 変更の内容を求める。CSVから読んだ項目ごとに今の値と比べる。
    // 変更の無い更新の行は、実行しても保存しない'unchanged'にする。
    private function csvImportChanges(CsvImportRow $row, CsvColumnSet $columns, array $current, array $uploadFields): void
    {
        foreach (array_keys($row->values) as $field) {
            // 複数のアップロードの欄の表示名はファイル名と一緒に比べる
            if (str_ends_with($field, '_origin') && ($uploadFields[substr($field, 0, -7)] ?? null) === 'repeatable') {
                continue;
            }

            // 追加の行は初期値と比べずに入れる値を全部見せるため、変更前を空欄にする
            $new = $row->validated[$field] ?? null;
            $old = $row->record !== null ? ($current[$field] ?? null) : null;

            // 複数のアップロードの欄は、「ファイル名（表示名）」の並びで比べる
            if (($uploadFields[$field] ?? null) === 'repeatable') {
                $pair = fn (array $names, array $origins) => array_map(
                    fn ($name, $i) => $name.(($origins[$i] ?? null) !== null ? "（{$origins[$i]}）" : ''),
                    $names,
                    array_keys($names),
                );
                $newList = $pair(array_values((array) $new), array_values((array) ($row->validated["{$field}_origin"] ?? [])));
                $oldList = $pair(array_values((array) $old), array_values((array) ($row->record !== null ? ($current["{$field}_origin"] ?? []) : [])));

                if ($newList !== $oldList) {
                    $row->changes[] = [$columns->fieldLabel($field), implode('、', $oldList), implode('、', $newList)];
                }

                continue;
            }

            if (CsvColumnSet::normalize($new) !== CsvColumnSet::normalize($old)) {
                $row->changes[] = [$columns->fieldLabel($field), $columns->displayValue($field, $old), $columns->displayValue($field, $new)];
            }
        }

        if ($row->record !== null && $row->changes === []) {
            $row->action = 'unchanged';
        }
    }

    /**
     * ダウンロードした時点のCSVの更新日時とDBの今の更新日時を比べ、DBの方が新しければ警告にする。
     * ExcelでCSVを保存すると日時が「2026/9/29 6:15」のような形になって秒が落ちる。そこで文字列では
     * 比べず、日時として読んでからCSVの値に書いてある秒・分・日までで比べる。秒が無ければ同じ分の
     * うちの変更は見分けられない。日時として読めない値は比べずに警告だけ出す。
     */
    private function checkCsvImportUpdatedAt(CsvImportRow $row, CsvColumnSet $columns, string $updatedAt, ?string $cell, ?string $heading): void
    {
        $now = $row->record->{$updatedAt};

        if ($cell === null || $now === null) {
            return;
        }

        $read = $columns->readDateTime($cell);

        if ($read === null) {
            $row->addWarning('日時として読めないので、ダウンロードした後に更新されたかどうかは確かめていません。', $heading);

            return;
        }

        // DBの日時をCSVに書いてある細かさまでにそろえて比べる
        [$csvTime, $precision] = $read;
        $dbTime = Carbon::parse($now)->format(match ($precision) {
            'second' => 'Y-m-d H:i:s',
            'minute' => 'Y-m-d H:i:00',
            'day' => 'Y-m-d 00:00:00',
        });

        if ($dbTime > $csvTime) {
            $row->addWarning("ダウンロードした後に更新されています（今の更新日時：{$columns->exportCell($row->record, $updatedAt)}）。取り込むと、その変更を上書きします。", $heading);
        }
    }

    /**
     * CSVの中での重複を確かめる。rules()にRule::uniqueかunique:がある項目で同じ値が2回以上
     * 出てきたら、それぞれの行をエラーにする。DBとの重複は1行ずつの検証で見ている。
     * DBの照合順序で同じ値とみなされることが多いので、大文字と小文字の違いは同じ値として扱う。
     */
    private function checkCsvImportUniqueness(CsvImportResult $result, array $rules, array $labels): void
    {
        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : (array) $fieldRules;
            $isUnique = array_filter($fieldRules, fn ($rule) => $rule instanceof Unique || (is_string($rule) && str_starts_with($rule, 'unique:')));

            if ($isUnique === []) {
                continue;
            }

            // 値ごとに行を集める
            $lines = [];
            foreach ($result->rows as $row) {
                $value = $row->values[$field] ?? null;
                if (is_string($value) && $value !== '') {
                    $lines[mb_strtolower($value)][] = $row;
                }
            }

            // 2行以上ある値は、それぞれの行にほかの行の番号を添えてエラーにする
            foreach ($lines as $rows) {
                if (count($rows) < 2) {
                    continue;
                }

                foreach ($rows as $row) {
                    $others = array_map(fn (CsvImportRow $other) => $other->line, array_filter($rows, fn ($other) => $other !== $row));
                    $row->addError('同じ値が'.implode('・', $others).'行目にもあります。', $labels[$field] ?? null);
                }
            }
        }
    }
}
