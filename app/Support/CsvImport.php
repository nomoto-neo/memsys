<?php

namespace App\Support;

use App\Enums\CsvEncoding;
use App\Enums\CsvImportMode;
use App\Models\CsvImportLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Illuminate\View\View;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * CSV取り込みの共通処理。ダウンロード（App\Support\CsvDownload）と同じ項目の定義
 * （csvColumns()）を使うので、ダウンロードしたCSVを直して、そのまま取り込める。
 *
 * 流れは「ファイルを選ぶ → 全行を検証して確認画面 → エラーが0件なら実行」。実行は全行を
 * 1つのトランザクションで反映し、1件でも失敗したら何も反映しない。
 * 検証（rules()）・保存する項目（saveFieldNames()）・追加で保存する項目（additionalFields()）・
 * 保存後の処理（afterSave()）は、画面からの登録・更新（FormFlow）とまったく同じ処理を通る。
 *
 * ■ 使う側のコントローラーが用意するもの
 * - csvImportSettings()   取り込みの設定（App\Support\CsvImportSettings）。確認画面と実行の両方で使う。
 * - csvColumns()          CSVの項目の定義（ダウンロードと共通。書き方はApp\Support\CsvColumnSet参照）。
 * - rules()               検証のルール。取り込めるのは、ここにある項目だけ。
 * - 追加・更新（CsvImportMode::Save）ではFormFlowも使うこと（inputFromModel()・saveFieldNames()など）。
 *
 * ■ 必要なときだけ用意するもの（用意しなければ何もしない）
 * - csvCustomImport($key, $value, $row)  「@名前」の列の取り込み。'import'に書いた項目名 => 値 を返す。
 * - validateCsvRows($result)             全行を1行ずつ検証した後の、行をまたいだチェック。
 *                                         $row->addError()・$row->addWarning()で結果に足す。
 *                                         確認画面と実行の両方で呼ばれる。
 * - afterCsvImportRow($row)              1行保存するたび（取り込みのときだけ。トランザクションの中）。
 * - afterCsvImport($result)              全件の確定と取り込み記録の後（通知メール・外部連携など）。
 * - processCsvRows($result)              処理だけのモード（CsvImportMode::Process）で、検証済みの全行を
 *                                         受け取って処理する（トランザクションの中）。完了の画面に出す
 *                                         メッセージを返す（nullなら「N件を処理しました。」）。
 *
 * ■ ルート
 * 入口（取り込み画面・確認・実行）はこのトレイトにあるので、コントローラーには書かない。
 * ルート名は、csvImportSettings()のrouteと、その後ろに「.confirm」「.execute」を付けたもの。
 * 取り込みが終わったら、取り込み画面に戻って結果のメッセージを出す。画面の「一覧へ戻る」は、
 * コントローラーにINDEX_ROUTE（SearchableListの一覧のルート名）があれば出す（?back付きで、
 * 一覧の検索条件とページを復元する）。
 *
 *     Route::get('/members/csv-import', [MemberController::class, 'csvImport'])->name('members.csv-import');
 *     Route::post('/members/csv-import/confirm', [MemberController::class, 'csvImportConfirm'])->name('members.csv-import.confirm');
 *     Route::post('/members/csv-import/execute', [MemberController::class, 'csvImportExecute'])->name('members.csv-import.execute');
 *
 * ■ 見出し
 * 列は見出しで判断する。定義にある列がCSVに無ければ、その項目は更新しない（CSVにある列だけ更新する）。
 * 定義に無い列・取り込めない列（rules()に無い項目、'import'の無い@名前など）は無視し、確認画面に
 * 警告を出す。見出し無しのCSV（header: false）は、csvColumns()の順に全部の列が並んでいる前提で読む。
 *
 * ■ 値の読み方
 * - 空欄はNULL。前後の空白は取り除く（画面からの入力と同じ）。
 * - 更新は「DBの今の値（inputFromModel()）にCSVの値を重ねたもの」、追加は「defaultInput()に
 *   CSVの値を重ねたもの」をrules()で検証する。処理だけのモードは、CSVの値だけを検証する。
 * - 一覧の表示名・日付・3桁区切り・数式の無害化の「'」は、ダウンロードと逆向きに戻す
 *   （App\Support\CsvColumnSet参照）。
 * - 文字コードはファイル全体で判定する（BOM付きUTF-8、UTF-8、Shift_JISの順）。
 *
 * ■ 確認から実行まで
 * - アップロードしたファイルはlocalディスクのcsv_import/に一時保存し、実行時にもう一度読んで、
 *   全行をもう一度検証してから反映する（1日以上前の一時ファイルは、次の取り込みのときに消す）。
 * - 確認画面を経由した1回だけの実行にするため、確認画面ごとの使い捨ての合言葉（confirm_token）を
 *   セッションに持つ（お問い合わせフォームと同じ考え方）。
 * - 確認時に、CSVに含まれるidのデータごとの更新日時を控えておき（一時ファイルの隣のJSON）、実行時に
 *   1件でも変わっていたら取り込み全体をエラーにする（確認画面を見てから実行するまでの間に、誰かが
 *   変更・削除した場合）。どの行が変わったかも、エラーのメッセージに出す。
 * - CSVに更新日時の列があれば、行ごとにDBの更新日時と比べ、DBの方が新しければ警告にする
 *   （ダウンロードしてから取り込むまでの間に、画面から変更された行。checkCsvImportUpdatedAt()参照）。
 *   更新日時の列は取り込まない（ダウンロードした時点の値のまま残しておく）。
 * - 変更の無い行は保存しない（更新日時と最終更新者が、取り込んだ人に書き換わらないように）。
 *
 * ■ 取り込み記録
 * 取り込みが確定するたびに、t_csv_import_logs（App\Models\CsvImportLog）へ、名前・日時・
 * 操作者・ファイル名・文字コード・件数・IPアドレスを記録する。
 */
trait CsvImport
{
    // アップロードできるCSVファイルの大きさの上限(KB)。サーバーのupload_max_filesize・
    // post_max_sizeもこれ以上にしておくこと。
    private const CSV_IMPORT_MAX_KB = 10240;

    // 確認画面に並べる行数の上限（エラー・警告の行、処理だけのモードで読み込んだ行）。
    private const CSV_IMPORT_PREVIEW_ROWS = 30;

    // 確認画面の「項目ごとの変更件数」で、項目ごとに開いて見られる変更の例の数。
    private const CSV_IMPORT_CHANGE_EXAMPLES = 5;

    // 取り込み途中のCSVの置き場所は CsvImportSettings::TMP_DISK・TMP_DIR。置いたままにしてよい
    // 時間は App\Support\TemporaryDataCleaner::MAX_AGE_HOURS。

    // ---- ルートから呼ばれる入口 ----

    /**
     * 取り込み画面（CSVファイルを選ぶ）。
     */
    public function csvImport(): View
    {
        $settings = $this->csvImportSettings();

        return view('admin.csv_import.form', [
            'settings' => $settings,
            'isSave' => $settings->mode === CsvImportMode::Save,
            'encodingLabel' => match ($settings->encoding) {
                null => '自動で判定（BOM付きUTF-8・UTF-8・Shift_JIS）',
                CsvEncoding::Utf8Bom => 'UTF-8',
                CsvEncoding::Sjis => 'Shift_JIS',
            },
            'maxKb' => self::CSV_IMPORT_MAX_KB,
            'backUrl' => $this->csvImportBackUrl(),
        ]);
    }

    /**
     * 確認画面。アップロードされたCSVを全行検証して、結果を表示する。
     */
    public function csvImportConfirm(Request $request): View
    {
        $settings = $this->csvImportSettings();

        $request->validate(
            ['csv_file' => ['required', 'file', 'extensions:csv,txt', 'max:'.self::CSV_IMPORT_MAX_KB]],
            [],
            ['csv_file' => 'CSVファイル'],
        );

        $disk = Storage::disk(CsvImportSettings::TMP_DISK);
        $this->cleanupCsvImportFiles();

        // 前に確認画面を開いたまま、別のファイルを選び直した場合は、前の一時ファイルを消す
        $sessionKey = $this->csvImportSessionKey($settings);
        if ($previous = session()->pull($sessionKey)) {
            $disk->delete([$previous['path'], $previous['path'].'.snapshot.json']);
        }

        $file = $request->file('csv_file');
        $path = $file->storeAs(CsvImportSettings::TMP_DIR, Str::random(40).'.csv', CsvImportSettings::TMP_DISK);
        $filename = $file->getClientOriginalName();

        $result = $this->analyzeCsvImport($settings, $disk->path($path), $filename, $settings->encoding);
        $token = null;

        if ($result->hasErrors()) {
            $disk->delete($path);
        } else {
            $token = Str::random(40);
            // 衝突チェックの控え（CSVのidごとの更新日時）は、行数に比例して大きくなるので、
            // セッションではなく一時ファイルの隣に置く
            $disk->put($path.'.snapshot.json', json_encode($this->csvImportSnapshot($settings, $result)));
            session()->put($sessionKey, [
                'token' => $token,
                'path' => $path,
                'filename' => $filename,
                'encoding' => $result->encoding,
            ]);
        }

        $isSave = $settings->mode === CsvImportMode::Save;

        return view('admin.csv_import.confirm', [
            'settings' => $settings,
            'result' => $result,
            'token' => $token,
            'isSave' => $isSave,
            'backUrl' => $this->csvImportBackUrl(),
            'previewRows' => self::CSV_IMPORT_PREVIEW_ROWS,
            // エラー・警告のある行（最初の30行まで）
            'issueRows' => array_slice(array_values(array_filter(
                $result->rows,
                fn (CsvImportRow $row) => $row->errors !== [] || $row->warnings !== [],
            )), 0, self::CSV_IMPORT_PREVIEW_ROWS),
            'issueCount' => count(array_filter($result->rows, fn (CsvImportRow $row) => $row->errors !== [] || $row->warnings !== [])),
            'changeSummary' => $isSave ? $this->csvImportChangeSummary($result) : [],
            // 処理だけのモードで読み込んだ内容（最初の30行まで）
            'processRows' => $isSave ? [] : array_slice($result->rowsOf('process'), 0, self::CSV_IMPORT_PREVIEW_ROWS),
        ]);
    }

    /**
     * 実行（確認画面の「取り込む」から）。
     */
    public function csvImportExecute(Request $request): RedirectResponse
    {
        $settings = $this->csvImportSettings();

        // 確認画面の合言葉は1回だけ使える（二重送信や、古い確認画面からの送信を防ぐ）
        $state = session()->pull($this->csvImportSessionKey($settings));

        if (! $state || ! hash_equals($state['token'], (string) $request->input('confirm_token'))) {
            if ($state) {
                Storage::disk(CsvImportSettings::TMP_DISK)->delete([$state['path'], $state['path'].'.snapshot.json']);
            }

            // 取り込み済みの確認画面からもう一度押した、別のCSVファイルを確認し直した後に前の確認画面から
            // 押した、ログインし直すなどでセッションが切れた、のどれか
            return redirect()->route($settings->route)
                ->with('error', '確認の有効期限が切れています。もう一度CSVファイルを選んでください。');
        }

        $disk = Storage::disk(CsvImportSettings::TMP_DISK);

        try {
            if (! $disk->exists($state['path']) || ! $disk->exists($state['path'].'.snapshot.json')) {
                throw new CsvImportException('一時保存したCSVファイルが見つかりません。もう一度CSVファイルを選んでください。');
            }

            $encoding = $state['encoding'] === 'Shift_JIS' ? CsvEncoding::Sjis : CsvEncoding::Utf8Bom;
            $result = $this->analyzeCsvImport($settings, $disk->path($state['path']), $state['filename'], $encoding);

            if ($result->hasErrors()) {
                throw new CsvImportException('確認画面を表示した後にデータが変わり、エラーになる行があります。もう一度CSVファイルを選んで確認してください。');
            }

            // 確認画面を表示した後に、画面などから変更・削除されたデータの行
            $snapshot = (array) json_decode((string) $disk->get($state['path'].'.snapshot.json'), true);
            $changedRows = $this->csvImportChangedRows($result, $snapshot, $this->csvImportSnapshot($settings, $result));
            if ($changedRows !== []) {
                throw new CsvImportException(sprintf(
                    '確認画面を表示した後に、対象のデータが変更または削除されました（%s%s）。何も取り込んでいません。もう一度CSVファイルを選んで確認してください。',
                    implode('、', array_map(fn (CsvImportRow $row) => $row->lineLabel(), array_slice($changedRows, 0, 5))),
                    count($changedRows) > 5 ? sprintf('ほか%d行', count($changedRows) - 5) : '',
                ));
            }

            $message = $this->runCsvImport($settings, $result);
        } catch (CsvImportException $e) {
            return redirect()->route($settings->route)->with('error', $e->getMessage());
        } finally {
            $disk->delete([$state['path'], $state['path'].'.snapshot.json']);
        }

        $isSave = $settings->mode === CsvImportMode::Save;

        CsvImportLog::create([
            'name' => $settings->name,
            'operator_id' => Auth::id(),
            'filename' => $result->filename,
            'encoding' => $result->encoding,
            'row_count' => count($result->rows),
            'inserted_count' => $isSave ? $result->count('insert') : 0,
            'updated_count' => $isSave ? $result->count('update') : 0,
            'unchanged_count' => $isSave ? $result->count('unchanged') : 0,
            'ip' => $request->ip(),
        ]);

        $message ??= $isSave
            ? sprintf('%s：追加 %d件・更新 %d件を取り込みました（変更なし %d件）。',
                $settings->name, $result->count('insert'), $result->count('update'), $result->count('unchanged'))
            : sprintf('%s：%d件を処理しました。', $settings->name, count($result->rows));

        $redirect = redirect()->route($settings->route)->with('status', $message);

        // 取り込みは確定済みなので、ここで起きた例外で取り込みの結果を失わないようにする
        try {
            $this->afterCsvImport($result);
        } catch (Throwable $e) {
            report($e);
            $redirect->with('error', '取り込み後の処理でエラーが発生しました（取り込み自体は完了しています）。管理者に連絡してください。');
        }

        return $redirect;
    }

    // ---- 必要なときだけコントローラーで書き換える処理（ここでは何もしない） ----

    private function validateCsvRows(CsvImportResult $result): void
    {
    }

    private function afterCsvImportRow(CsvImportRow $row): void
    {
    }

    private function afterCsvImport(CsvImportResult $result): void
    {
    }

    private function processCsvRows(CsvImportResult $result): ?string
    {
        throw new LogicException('処理だけのモード（CsvImportMode::Process）では、コントローラーにprocessCsvRows()を用意してください。');
    }

    // ---- 検証 ----

    /**
     * CSVファイルを読み、全行を検証した結果を返す。確認画面と実行の両方がここを通る。
     */
    private function analyzeCsvImport(CsvImportSettings $settings, string $path, string $filename, ?CsvEncoding $encoding): CsvImportResult
    {
        $result = new CsvImportResult($filename);
        $isSave = $settings->mode === CsvImportMode::Save;
        $model = $settings->query->getModel();
        $keyName = $model->getKeyName();

        $uploadFields = [];
        if (method_exists($this, 'uploadFieldDefinitions')) {
            foreach ($this->uploadFieldDefinitions() as $def) {
                if ($def['kind'] !== 'wysiwyg') {
                    $uploadFields[$def['field']] = $def['kind'];
                }
            }
        }

        $columns = new CsvColumnSet(
            $this->csvColumns(),
            $uploadFields,
            customImport: method_exists($this, 'csvCustomImport')
                ? fn (string $key, ?string $value, CsvImportRow $row) => $this->csvCustomImport($key, $value, $row)
                : null,
        );

        // 定義の書き間違いは、ファイルを読む前に例外にする
        if ($isSave && $columns->keyHeading($keyName) === null) {
            throw new InvalidArgumentException("CSV取り込み（追加・更新）には、csvColumns()に{$keyName}の列が必要です。");
        }
        if (! $settings->header) {
            $columns->assertNoExpansion();
        }

        // 文字コード
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

        // 見出し
        $rules = $this->rules(null);
        $importable = array_values(array_filter(array_map('strval', array_keys($rules)), fn ($k) => ! str_contains($k, '.')));
        $updatedAt = $model->usesTimestamps() ? $model->getUpdatedAtColumn() : null;
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

        // CSVのidのデータを、まとめて読んでおく
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

        foreach ($records as [$line, $cells]) {
            $row = new CsvImportRow($line);
            $result->rows[] = $row;

            $cells = array_map(fn ($cell) => $this->normalizeCsvCell($cell, $settings->escapeFormula), $cells);
            foreach ($result->headings as $column => $heading) {
                if ($heading !== '') {
                    $row->cells[$heading] = $cells[$column] ?? null;
                }
            }

            // どの行のデータか見分けるための値（見出しなら見出しで、整数なら左から何列目かで探す）
            $row->setLabel(is_int($settings->labelColumn)
                ? ($cells[$settings->labelColumn - 1] ?? null)
                : ($settings->labelColumn !== null ? $row->cell($settings->labelColumn) : null));

            if (! $settings->header && count($cells) !== count($result->headings)) {
                $row->addError(sprintf('列の数が%d個あります（%d個にしてください）。', count($cells), count($result->headings)));

                continue;
            }

            // どのデータの行か
            if ($mapping['key'] !== null) {
                $id = $columns->readKey($cells[$mapping['key']] ?? null, $keyName);

                if ($id === null) {
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
                    $seenIds[$id] = $line;
                    $row->record = $found[$id];
                }
            }

            $row->action = $isSave ? ($row->record ? 'update' : 'insert') : 'process';

            // 今の値（更新はDBの値、追加は初期値。処理だけのモードは重ねない）
            $base = $isSave ? ($row->record ? $this->inputFromModel($row->record) : $this->defaultInput()) : [];
            $uploadsNow = $isSave ? $this->csvImportCurrentUploads($row->record, $uploadFields) : [];
            $current = $base + $uploadsNow;

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

            // 検証する入力。複数のアップロード項目は、画面のフォームと同じ4本の配列の形にそろえる
            $input = $row->values + $base;
            foreach ($uploadFields as $field => $kind) {
                if ($kind === 'repeatable' && array_key_exists($field, $input)) {
                    $count = count($input[$field]);
                    $input["{$field}_tmp"] = array_fill(0, $count, null);
                    $input["{$field}_del"] = array_fill(0, $count, null);
                }
            }
            $row->input = $input;

            $validator = Validator::make($input, $this->rules($row->record), [], $attributes);
            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $key => $messages) {
                    $field = explode('.', $key)[0];
                    foreach ($messages as $message) {
                        $row->addError($message, $labels[$field] ?? null);
                    }
                }

                continue;
            }

            $validated = $validator->validated();
            $row->validated = method_exists($this, 'prepareInput') ? $this->prepareInput($validated) : $validated;

            if ($isSave) {
                $this->csvImportChanges($row, $columns, $current, $uploadFields);
            }

            // ダウンロードした後に、画面から変更された行
            if ($updatedAt && $row->record && isset($mapping['refs'][$updatedAt])) {
                $this->checkCsvImportUpdatedAt($row, $columns, $updatedAt, $cells[$mapping['refs'][$updatedAt]] ?? null, $result->headings[$mapping['refs'][$updatedAt]] ?? null);
            }
        }

        $this->checkCsvImportUniqueness($result, $rules, $labels);

        $this->validateCsvRows($result);

        return $result;
    }

    /**
     * ファイルの中身をUTF-8の文字列にする。[文字列, 文字コードの名前]、読めなければ[null, null]。
     * 文字コードの判定はファイル全体で行う（先頭の数行だけで判定すると、後ろの方に別の文字コードが
     * 混ざっていたときに文字化けしたまま取り込んでしまうため）。
     */
    private function decodeCsvFile(string $bytes, ?CsvEncoding $encoding): array
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $body = substr($bytes, 3);

            return ($encoding !== CsvEncoding::Sjis && mb_check_encoding($body, 'UTF-8')) ? [$body, 'UTF-8'] : [null, null];
        }

        if ($encoding !== CsvEncoding::Sjis && mb_check_encoding($bytes, 'UTF-8')) {
            return [$bytes, 'UTF-8'];
        }

        if ($encoding !== CsvEncoding::Utf8Bom && mb_check_encoding($bytes, 'SJIS-win')) {
            return [mb_convert_encoding($bytes, 'UTF-8', 'SJIS-win'), 'Shift_JIS'];
        }

        return [null, null];
    }

    /**
     * CSVの文字列を行に分ける。[[何行目, セルの一覧], …]。空の行（全部のセルが空欄の行を含む）は飛ばす。
     * 何行目かはCSVの行（レコード）で数える。セルの中の改行は行に数えないので、Excelの行番号と一致する。
     */
    private function parseCsvText(string $text): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);

        $records = [];
        $line = 0;

        // エスケープ文字は空にして、RFC 4180どおりに読む（ダウンロードと同じ理由）
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
     * セルの値をそろえる。前後の空白を除き、空ならnull（画面からの入力で、Laravelが行っている
     * TrimStrings・ConvertEmptyStringsToNullと同じ）。$escapeFormulaなら、ダウンロードで数式の
     * 無害化に付けた先頭の「'」を外す（付けたときと同じ条件のときだけ）。
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

    /**
     * アップロード項目の今の値（単数は{field}・{field}_origin、複数は同じ名前の並行配列）。
     */
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

    /**
     * アップロード項目のファイル名を確かめる（今と違うファイル名は、このレコードの保存先に
     * 実在するものだけ。AjaxFileUpload::checkImportedUploadFilename()参照）。
     */
    private function checkCsvImportUploads(CsvImportRow $row, array $uploadFields, array $uploadsNow, array $labels): void
    {
        foreach ($uploadFields as $field => $kind) {
            $label = $labels[$field] ?? $field;

            if (! array_key_exists($field, $row->values)) {
                // 単数の項目で、表示名の列だけがある
                if ($kind === 'single' && ($row->values["{$field}_origin"] ?? null) !== null && $uploadsNow[$field] === null) {
                    $row->addError('ファイルが無いので、表示名は入力できません。', $labels["{$field}_origin"] ?? $label);
                }

                continue;
            }

            if ($kind === 'single' && $row->values[$field] === null && ($row->values["{$field}_origin"] ?? null) !== null) {
                $row->addError('ファイル名が空欄です。', $label);

                continue;
            }

            $filenames = $kind === 'single' ? array_filter([$row->values[$field]]) : $row->values[$field];
            $now = (array) $uploadsNow[$field];

            foreach ($filenames as $filename) {
                if (in_array($filename, $now, true)) {
                    continue;
                }

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

    /**
     * 変更の内容を求める。CSVから読み取った項目ごとに、今の値と比べる。
     * 変更が無い更新の行は、'unchanged'（実行しても保存しない）にする。
     */
    private function csvImportChanges(CsvImportRow $row, CsvColumnSet $columns, array $current, array $uploadFields): void
    {
        foreach (array_keys($row->values) as $field) {
            // 複数のアップロード項目の表示名は、ファイル名と一緒に比べる
            if (str_ends_with($field, '_origin') && ($uploadFields[substr($field, 0, -7)] ?? null) === 'repeatable') {
                continue;
            }

            // 追加の行は、変更前を空欄にする（初期値との比較ではなく、入れる値を全部見せる）
            $new = $row->validated[$field] ?? null;
            $old = $row->record !== null ? ($current[$field] ?? null) : null;

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
     * CSVの更新日時（ダウンロードした時点の値）とDBの今の更新日時を比べ、DBの方が新しければ警告にする。
     * ExcelでCSVを保存すると日時が「2026/9/29 6:15」のような形に変わり、秒が落ちるので、文字列では比べず、
     * 日時として読んでから、CSVの値にある細かさ（秒・分・日）までで比べる。秒が無ければ、同じ分のうちの
     * 変更は見分けられない。日時として読めない値は、比べずに警告だけ出す。
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
     * CSVの中での重複。rules()でRule::unique（またはunique:）が付いている項目について、
     * 同じ値が2回以上出てきたら、それぞれの行をエラーにする（DBとの重複は1行ずつの検証で見ている）。
     * 大文字・小文字の違いは同じ値として扱う（DBの照合順序で同じ値とみなされることが多いため）。
     */
    private function checkCsvImportUniqueness(CsvImportResult $result, array $rules, array $labels): void
    {
        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : (array) $fieldRules;
            $isUnique = array_filter($fieldRules, fn ($rule) => $rule instanceof Unique || (is_string($rule) && str_starts_with($rule, 'unique:')));

            if ($isUnique === []) {
                continue;
            }

            $lines = [];
            foreach ($result->rows as $row) {
                $value = $row->values[$field] ?? null;
                if (is_string($value) && $value !== '') {
                    $lines[mb_strtolower($value)][] = $row;
                }
            }

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

    /**
     * 衝突チェック用の控え。CSVに含まれるidのデータの、id => 更新日時（DBの値の文字列そのまま）。
     * モデルが更新日時を持たなければ、id => ''（削除されたかどうかだけを見る）。
     */
    private function csvImportSnapshot(CsvImportSettings $settings, CsvImportResult $result): array
    {
        $model = $settings->query->getModel();
        $updatedAt = $model->usesTimestamps() ? $model->getUpdatedAtColumn() : null;
        $ids = array_values(array_filter(array_map(fn (CsvImportRow $row) => $row->record?->getKey(), $result->rows)));
        $snapshot = [];

        foreach (array_chunk($ids, 1000) as $chunk) {
            $columns = array_filter([$model->getKeyName(), $updatedAt]);

            foreach ((clone $settings->query)->reorder()->whereKey($chunk)->get($columns) as $record) {
                // Carbonを通さず、DBから読んだままの文字列で比べる（書式やタイムゾーンの違いで食い違わないように）
                $snapshot[(string) $record->getKey()] = $updatedAt ? (string) $record->getRawOriginal($updatedAt) : '';
            }
        }

        return $snapshot;
    }

    /**
     * 確認のときの控えと今の控えを比べ、更新日時が変わった・削除されたデータの行を返す。
     */
    private function csvImportChangedRows(CsvImportResult $result, array $before, array $now): array
    {
        $changedIds = [];
        foreach ($before as $id => $updatedAt) {
            if (! array_key_exists($id, $now) || $now[$id] !== $updatedAt) {
                $changedIds[(string) $id] = true;
            }
        }

        return array_values(array_filter(
            $result->rows,
            fn (CsvImportRow $row) => $row->record !== null && isset($changedIds[(string) $row->record->getKey()]),
        ));
    }

    // ---- 実行 ----

    /**
     * 全行を1つのトランザクションで反映する。戻り値は完了の画面に出すメッセージ（nullなら既定の文言）。
     */
    private function runCsvImport(CsvImportSettings $settings, CsvImportResult $result): ?string
    {
        return DB::transaction(function () use ($settings, $result) {
            if ($settings->mode === CsvImportMode::Process) {
                return $this->processCsvRows($result);
            }

            foreach ($result->rows as $row) {
                if (! in_array($row->action, ['insert', 'update'], true)) {
                    continue;
                }

                $record = $row->record ?? $settings->query->getModel()->newInstance();

                try {
                    $this->saveValidated($record, $row->validated, $row->input, true);
                } catch (UniqueConstraintViolationException $e) {
                    throw new CsvImportException("{$row->lineLabel()}：ほかのデータと重複する値があるため、取り込みを中止しました（何も取り込んでいません）。", 0, $e);
                } catch (QueryException $e) {
                    report($e);

                    throw new CsvImportException("{$row->lineLabel()}：データベースのエラーで取り込みを中止しました（何も取り込んでいません）。", 0, $e);
                }

                $row->record = $record;
                $this->afterCsvImportRow($row);
            }

            return null;
        });
    }

    // ---- 確認画面 ----

    /**
     * 項目ごとの変更件数。正常な行は1行ずつ並べず、項目ごとに「更新で変わる件数」「追加で入る件数」と、
     * 開いて見られる変更の例（最初のCSV_IMPORT_CHANGE_EXAMPLES件）だけを出す。
     * 直したつもりの無い項目が大量に変わっている（Excelで電話番号の先頭の0が落ちた、別の列に
     * 貼り付けた、など）ことに、行数に関係なく気づけるようにするため。
     * 戻り値: 見出し => ['update' => 件数, 'insert' => 件数, 'examples' => [[何行目（lineLabel()）, 動作, 変更前, 変更後], …]]
     */
    private function csvImportChangeSummary(CsvImportResult $result): array
    {
        $summary = [];

        foreach ($result->rows as $row) {
            if ($row->hasErrors() || ! in_array($row->action, ['insert', 'update'], true)) {
                continue;
            }

            foreach ($row->changes as [$label, $before, $after]) {
                $summary[$label] ??= ['update' => 0, 'insert' => 0, 'examples' => []];
                $summary[$label][$row->action]++;

                if (count($summary[$label]['examples']) < self::CSV_IMPORT_CHANGE_EXAMPLES) {
                    $summary[$label]['examples'][] = [$row->lineLabel(), $row->action, $before, $after];
                }
            }
        }

        return $summary;
    }

    /**
     * 画面の「一覧へ戻る」のURL。コントローラーにINDEX_ROUTEが無ければnull（ボタンを出さない）。
     */
    private function csvImportBackUrl(): ?string
    {
        return defined('self::INDEX_ROUTE') ? route(self::INDEX_ROUTE, ['back']) : null;
    }

    // ---- 一時ファイル・セッション ----

    private function csvImportSessionKey(CsvImportSettings $settings): string
    {
        // セッションのキーの「.」は階層の区切りになるので、ルート名の「.」は置き換える
        return 'csv_import_'.str_replace('.', '_', $settings->route);
    }

    /**
     * 置いたままになった古い一時ファイルを消す（確認画面を開いたまま実行しなかった場合など）。
     * 本来はスケジューラーが1時間ごとに消す（App\Support\TemporaryDataCleaner）。ここでも
     * 呼ぶのは、サーバーのcronが動いていなくても溜まり続けないようにするための控え。
     */
    private function cleanupCsvImportFiles(): void
    {
        TemporaryDataCleaner::csvImportFiles();
    }
}
