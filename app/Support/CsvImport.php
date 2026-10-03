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
 * CSV取り込みの共通処理。ダウンロードのCsvDownloadと同じcsvColumns()の定義を使うので、
 * ダウンロードしたCSVを直してそのまま取り込める。
 *
 * 流れは「ファイルを選ぶ → 全行を検証して確認画面 → エラーが無ければ実行」。実行は全行を
 * 1つのトランザクションで反映し、1件でも失敗したら何も反映しない。
 * 検証のrules()、保存する項目のsaveFieldNames()とadditionalFields()、保存した後のafterSave()は、
 * 画面からの登録と更新のFormFlowとまったく同じ処理を通る。
 *
 * ■ コントローラーが用意するもの
 * - csvImportSettings()  取り込みの設定。CsvImportSettingsを返す。確認画面と実行の両方で使う
 * - csvColumns()         CSVの項目の定義。ダウンロードと共通で、書き方はCsvColumnSetにある
 * - rules()              検証のルール。取り込めるのはここにある項目だけ
 * - 追加と更新のCsvImportMode::SaveではFormFlowも使う。inputFromModel()やsaveFieldNames()など
 *
 * ■ 要るときだけ用意するもの。用意しなければ何もしない
 * - csvCustomImport($key, $value, $row)  @名前の列の取り込み。'import'に書いた項目名 => 値を返す
 * - validateCsvRows($result)             全行を1行ずつ検証した後の行をまたいだ確かめ。
 *                                         $row->addError()と$row->addWarning()で結果に足す。
 *                                         確認画面と実行の両方で呼ばれる
 * - afterCsvImportRow($row)              取り込みで1行保存するたびにトランザクションの中で呼ばれる
 * - afterCsvImport($result)              全件が確定して記録を残した後に呼ばれる。通知のメールや外部への連携など
 * - processCsvRows($result)              処理だけのCsvImportMode::Processで、検証済みの全行を
 *                                         トランザクションの中で処理する。完了の画面に出す
 *                                         メッセージを返し、nullなら「N件を処理しました。」
 *
 * ■ ルート
 * 取り込み画面・確認・実行の入口はこのトレイトにあるので、コントローラーには書かない。
 * ルート名はcsvImportSettings()のrouteと、その後ろに「.confirm」「.execute」を付けたもの。
 * 終わったら取り込み画面に戻って結果のメッセージを出す。画面の「一覧へ戻る」は、
 * コントローラーにSearchableListの一覧のルート名のINDEX_ROUTEがあれば出し、?backで
 * 一覧の検索条件とページを戻す。
 *
 *     Route::get('/members/csv-import', [MemberController::class, 'csvImport'])->name('members.csv-import');
 *     Route::post('/members/csv-import/confirm', [MemberController::class, 'csvImportConfirm'])->name('members.csv-import.confirm');
 *     Route::post('/members/csv-import/execute', [MemberController::class, 'csvImportExecute'])->name('members.csv-import.execute');
 *
 * ■ 見出し
 * 列は見出しで見分ける。定義にある列がCSVに無ければその項目は更新せず、CSVにある列だけを更新する。
 * 定義に無い列と、rules()に無い項目や'import'の無い@名前のような取り込めない列は、無視して
 * 確認画面に警告を出す。header: falseの見出し無しのCSVは、csvColumns()の順に全部の列が並んでいる前提で読む。
 *
 * ■ 値の読み方
 * - 空欄はnull。画面からの入力と同じく前後の空白は取り除く
 * - 更新はinputFromModel()のDBの今の値に、追加はdefaultInput()にCSVの値を重ねて、rules()で
 *   検証する。処理だけのモードはCSVの値だけを検証する
 * - 一覧の表示名・日付・3桁の区切り・数式を無害にする「'」は、CsvColumnSetでダウンロードと逆向きに戻す
 * - 文字コードはファイル全体で、BOM付きUTF-8・UTF-8・Shift_JISの順に判定する
 *
 * ■ 確認から実行まで
 * - アップロードしたファイルは一時ディレクトリに置き、実行のときにもう一度読んで全行を
 *   検証し直してから反映する
 * - 確認画面を通った1回だけの実行にするため、確認画面ごとに使い捨ての合言葉confirm_tokenを
 *   セッションに持つ。お問い合わせのフォームと同じ考え方
 * - 確認のときに、CSVにあるidのデータごとの更新日時を一時ファイルの隣のJSONに控える。
 *   確認画面を見てから実行するまでにだれかが変更か削除をして1件でも変わっていたら、
 *   取り込み全体をエラーにし、どの行が変わったかもメッセージに出す
 * - CSVに更新日時の列があれば、行ごとにDBの更新日時と比べてDBの方が新しければ警告にする。
 *   ダウンロードしてから取り込むまでに、画面から変更された行のこと。更新日時の列は取り込まない
 * - 更新日時と最終更新者が取り込んだ人に書き換わらないよう、変更の無い行は保存しない
 *
 * ■ 取り込みの記録
 * 取り込みが確定するたびに、CsvImportLogのt_csv_import_logsへ名前・日時・操作した人・
 * ファイル名・文字コード・件数・IPアドレスを残す。
 */
trait CsvImport
{
    // アップロードできるCSVファイルの大きさの上限(KB)。サーバーのupload_max_filesizeと
    // post_max_sizeもこれ以上にしておく
    private const CSV_IMPORT_MAX_KB = 10240;

    // 確認画面に並べる行の数の上限。エラーや警告の行と、処理だけのモードで読んだ行
    private const CSV_IMPORT_PREVIEW_ROWS = 30;

    // 確認画面の項目ごとの変更件数で、開いて見られる変更の例の数
    private const CSV_IMPORT_CHANGE_EXAMPLES = 5;

    // 取り込み途中のCSVの置き場所はCsvImportSettings::TMP_DISKとTMP_DIR。置いたままにしてよい
    // 時間はTemporaryDataCleaner::MAX_AGE_HOURS。

    // ---- ルートから呼ばれる入口 ----

    // 取り込み画面。CSVファイルを選ぶ。
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

    // 確認画面。アップロードされたCSVを全行検証して結果を出す。
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

        // 確認画面を開いたまま別のファイルを選び直したなら、前の一時ファイルを消す
        $sessionKey = $this->csvImportSessionKey($settings);
        if ($previous = session()->pull($sessionKey)) {
            $disk->delete([$previous['path'], $previous['path'].'.snapshot.json']);
        }

        // 一時ディレクトリに置いて全行を検証する
        $file = $request->file('csv_file');
        $path = $file->storeAs(CsvImportSettings::TMP_DIR, Str::random(40).'.csv', CsvImportSettings::TMP_DISK);
        $filename = $file->getClientOriginalName();

        $result = $this->analyzeCsvImport($settings, $disk->path($path), $filename, $settings->encoding);
        $token = null;

        if ($result->hasErrors()) {
            // エラーがあれば実行させないので、一時ファイルを消す
            $disk->delete($path);
        } else {
            // エラーが無ければ、実行のための合言葉と更新日時の控えを持つ。
            // 控えは行の数だけ大きくなるので、セッションではなく一時ファイルの隣に置く
            $token = Str::random(40);
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
            // エラーか警告のある行の最初の30行まで
            'issueRows' => array_slice(array_values(array_filter(
                $result->rows,
                fn (CsvImportRow $row) => $row->errors !== [] || $row->warnings !== [],
            )), 0, self::CSV_IMPORT_PREVIEW_ROWS),
            'issueCount' => count(array_filter($result->rows, fn (CsvImportRow $row) => $row->errors !== [] || $row->warnings !== [])),
            'changeSummary' => $isSave ? $this->csvImportChangeSummary($result) : [],
            // 処理だけのモードで読んだ内容の最初の30行まで
            'processRows' => $isSave ? [] : array_slice($result->rowsOf('process'), 0, self::CSV_IMPORT_PREVIEW_ROWS),
        ]);
    }

    // 確認画面の「取り込む」から実行する。
    public function csvImportExecute(Request $request): RedirectResponse
    {
        $settings = $this->csvImportSettings();

        // 確認画面の合言葉は1回だけ使える。二重の送信や古い確認画面からの送信を防ぐ
        $state = session()->pull($this->csvImportSessionKey($settings));

        if (! $state || ! hash_equals($state['token'], (string) $request->input('confirm_token'))) {
            if ($state) {
                Storage::disk(CsvImportSettings::TMP_DISK)->delete([$state['path'], $state['path'].'.snapshot.json']);
            }

            // 取り込んだ後の確認画面からもう一度押した、別のファイルを確認し直した後に前の確認画面から
            // 押した、ログインし直すなどでセッションが切れた、のどれか
            return redirect()->route($settings->route)
                ->with('error', '確認の有効期限が切れています。もう一度CSVファイルを選んでください。');
        }

        $disk = Storage::disk(CsvImportSettings::TMP_DISK);

        try {
            if (! $disk->exists($state['path']) || ! $disk->exists($state['path'].'.snapshot.json')) {
                throw new CsvImportException('一時保存したCSVファイルが見つかりません。もう一度CSVファイルを選んでください。');
            }

            // 全行を検証し直す。確認画面の後にデータが変わってエラーになることもある
            $encoding = $state['encoding'] === 'Shift_JIS' ? CsvEncoding::Sjis : CsvEncoding::Utf8Bom;
            $result = $this->analyzeCsvImport($settings, $disk->path($state['path']), $state['filename'], $encoding);

            if ($result->hasErrors()) {
                throw new CsvImportException('確認画面を表示した後にデータが変わり、エラーになる行があります。もう一度CSVファイルを選んで確認してください。');
            }

            // 確認画面を出した後に画面などから変更か削除されたデータの行があれば、全体をエラーにする
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

        // 取り込みの記録
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

        // processCsvRows()がメッセージを返さなければ件数を出す
        if ($message === null) {
            if ($isSave) {
                $message = sprintf('%s：追加 %d件・更新 %d件を取り込みました（変更なし %d件）。',
                    $settings->name, $result->count('insert'), $result->count('update'), $result->count('unchanged'));
            } else {
                $message = sprintf('%s：%d件を処理しました。', $settings->name, count($result->rows));
            }
        }

        $redirect = redirect()->route($settings->route)->with('status', $message);

        // 取り込みは確定しているので、この後の例外で取り込みの結果を失わないようにする
        try {
            $this->afterCsvImport($result);
        } catch (Throwable $e) {
            report($e);
            $redirect->with('error', '取り込み後の処理でエラーが発生しました（取り込み自体は完了しています）。管理者に連絡してください。');
        }

        return $redirect;
    }

    // ---- 要るときだけコントローラーで書き換える処理。ここでは何もしない ----

    // 全行を1行ずつ検証した後に、行をまたいだ確かめをする
    private function validateCsvRows(CsvImportResult $result): void
    {
    }

    // 取り込みで1行保存するたびに、トランザクションの中で呼ばれる
    private function afterCsvImportRow(CsvImportRow $row): void
    {
    }

    // 全件が確定して記録を残した後に呼ばれる
    private function afterCsvImport(CsvImportResult $result): void
    {
    }

    // 処理だけのモードで、検証済みの全行を処理する。このモードでは必ずコントローラーに用意する
    private function processCsvRows(CsvImportResult $result): ?string
    {
        throw new LogicException('処理だけのモード（CsvImportMode::Process）では、コントローラーにprocessCsvRows()を用意してください。');
    }

    // ---- 検証 ----

    // CSVファイルを読んで全行を検証した結果を返す。確認画面と実行の両方がここを通る。
    private function analyzeCsvImport(CsvImportSettings $settings, string $path, string $filename, ?CsvEncoding $encoding): CsvImportResult
    {
        $result = new CsvImportResult($filename);
        $isSave = $settings->mode === CsvImportMode::Save;
        $model = $settings->query->getModel();
        $keyName = $model->getKeyName();

        // AjaxFileUploadのアップロードの欄。エディタの欄は除く
        $uploadFields = [];
        if (method_exists($this, 'uploadFieldDefinitions')) {
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
            $this->csvColumns(),
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

            // rules()で検証する
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

            // ダウンロードした後に画面から変更された行
            if ($updatedAt && $row->record && isset($mapping['refs'][$updatedAt])) {
                $this->checkCsvImportUpdatedAt($row, $columns, $updatedAt, $cells[$mapping['refs'][$updatedAt]] ?? null, $result->headings[$mapping['refs'][$updatedAt]] ?? null);
            }
        }

        // 行をまたいだ確かめ
        $this->checkCsvImportUniqueness($result, $rules, $labels);

        $this->validateCsvRows($result);

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

    // 確認から実行までの変更を見つけるための控え。CSVにあるidのデータのid => DBの更新日時の文字列。
    // モデルが更新日時を持たなければid => ''で、削除されたかだけを見る。
    private function csvImportSnapshot(CsvImportSettings $settings, CsvImportResult $result): array
    {
        $model = $settings->query->getModel();
        $updatedAt = $model->usesTimestamps() ? $model->getUpdatedAtColumn() : null;
        $ids = array_values(array_filter(array_map(fn (CsvImportRow $row) => $row->record?->getKey(), $result->rows)));
        $snapshot = [];

        foreach (array_chunk($ids, 1000) as $chunk) {
            $columns = array_filter([$model->getKeyName(), $updatedAt]);

            foreach ((clone $settings->query)->reorder()->whereKey($chunk)->get($columns) as $record) {
                // 書式やタイムゾーンの違いで食い違わないよう、Carbonを通さずDBから読んだままの文字列で比べる
                $snapshot[(string) $record->getKey()] = $updatedAt ? (string) $record->getRawOriginal($updatedAt) : '';
            }
        }

        return $snapshot;
    }

    // 確認のときと今の控えを比べ、更新日時が変わったか削除されたデータの行を返す。
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

    // 全行を1つのトランザクションで反映する。完了の画面に出すメッセージを返し、nullなら決まった文言。
    private function runCsvImport(CsvImportSettings $settings, CsvImportResult $result): ?string
    {
        return DB::transaction(function () use ($settings, $result) {
            // 処理だけのモードはコントローラーに任せる
            if ($settings->mode === CsvImportMode::Process) {
                return $this->processCsvRows($result);
            }

            // 追加と更新の行を画面からの登録と同じ処理で保存する。変更の無い行は保存しない
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
     * 項目ごとの変更件数。正常な行は1行ずつ並べず、項目ごとに更新で変わる件数と追加で入る件数と、
     * 開いて見られる最初の数件の例だけを出す。Excelで電話番号の先頭の0が落ちたり別の列に貼り付けたり
     * して、直したつもりの無い項目がたくさん変わっていることに、行の数に関係なく気づけるようにするため。
     *
     * @return array<string, array{update: int, insert: int, examples: list<array{string, string, string, string}>}>
     *     見出し => 更新の件数、追加の件数、[何行目, 動作, 変更前, 変更後]の例
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

    // 画面の「一覧へ戻る」のURL。コントローラーにINDEX_ROUTEが無ければnullで、ボタンを出さない。
    private function csvImportBackUrl(): ?string
    {
        return defined('self::INDEX_ROUTE') ? route(self::INDEX_ROUTE, ['back']) : null;
    }

    // ---- 一時ファイル・セッション ----

    // 確認の状態を持つセッションのキー。キーの「.」は階層の区切りになるので、ルート名の「.」は置き換える。
    private function csvImportSessionKey(CsvImportSettings $settings): string
    {
        return 'csv_import_'.str_replace('.', '_', $settings->route);
    }

    // 確認画面を開いたまま実行しなかったなどで置いたままの、古い一時ファイルを消す。本来はスケジューラーが
    // 1時間ごとに消すが、cronが動いていなくても溜まり続けないようここでも消す。
    private function cleanupCsvImportFiles(): void
    {
        TemporaryDataCleaner::csvImportFiles();
    }
}
