<?php

namespace App\Support;

use App\Enums\CsvEncoding;
use App\Enums\CsvImportMode;
use App\Models\CsvImportLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use LogicException;
use Throwable;

/**
 * CSV取り込みの共通処理。ダウンロードのCsvDownloadと同じcsvColumns()の定義を使うので、
 * ダウンロードしたCSVを直してそのまま取り込める。
 * このトレイトは取り込み画面と保存の流れを受け持ち、CSVの読み込みと検証はCsvReaderが行う。
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
    use CsvReader;

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
            'maxKb' => self::CSV_FILE_MAX_KB,
            'backUrl' => $this->csvImportBackUrl(),
        ]);
    }

    // 確認画面。アップロードされたCSVを全行検証して結果を出す。
    public function csvImportConfirm(Request $request): View
    {
        $settings = $this->csvImportSettings();

        $request->validate(
            ['csv_file' => $this->csvFileRules()],
            [],
            ['csv_file' => 'CSVファイル'],
        );

        // 一時ディレクトリに置いて全行を検証する
        $sessionKey = $this->csvImportSessionKey($settings);
        $file = $request->file('csv_file');
        $path = $this->storeCsvForConfirm($file, $sessionKey);
        $filename = $file->getClientOriginalName();

        $result = $this->readImportCsv($settings, $path, $filename, $settings->encoding);
        $token = null;

        if ($result->hasErrors()) {
            // エラーがあれば実行させないので、一時ファイルを消す
            $this->deleteCsvFiles($path);
        } else {
            // エラーが無ければ、更新日時の控えを一時ファイルの隣に置き、実行のための合言葉を持つ
            Storage::disk(CsvImportSettings::TMP_DISK)->put(
                $this->csvSidecarPath($path),
                json_encode($this->csvImportSnapshot($settings, $result)),
            );
            $token = $this->rememberCsv($sessionKey, [
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
        $state = $this->pullCsv($this->csvImportSessionKey($settings), $request->input('confirm_token'));

        if ($state === null) {
            // 取り込んだ後の確認画面からもう一度押した、別のファイルを確認し直した後に前の確認画面から
            // 押した、ログインし直すなどでセッションが切れた、のどれか
            return redirect()->route($settings->route)
                ->with('error', '確認の有効期限が切れています。もう一度CSVファイルを選んでください。');
        }

        $disk = Storage::disk(CsvImportSettings::TMP_DISK);

        try {
            if (! $disk->exists($this->csvSidecarPath($state['path']))) {
                throw new CsvImportException('一時保存したCSVファイルが見つかりません。もう一度CSVファイルを選んでください。');
            }

            // 全行を検証し直す。確認画面の後にデータが変わってエラーになることもある
            $result = $this->readImportCsv($settings, $state['path'], $state['filename'], $this->csvEncodingOf($state['encoding']));

            if ($result->hasErrors()) {
                throw new CsvImportException('確認画面を表示した後にデータが変わり、エラーになる行があります。もう一度CSVファイルを選んで確認してください。');
            }

            // 確認画面を出した後に画面などから変更か削除されたデータの行があれば、全体をエラーにする
            $snapshot = (array) json_decode((string) $disk->get($this->csvSidecarPath($state['path'])), true);
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
            $this->deleteCsvFiles($state['path']);
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

    // コントローラーのcsvColumns()とrules()で、CSVを読んで全行を検証する
    private function readImportCsv(CsvImportSettings $settings, string $path, string $filename, ?CsvEncoding $encoding): CsvImportResult
    {
        return $this->readCsv(
            settings: $settings,
            columnDefinitions: $this->csvColumns(),
            rulesFor: fn (?Model $record) => $this->rules($record),
            path: $this->csvFilePath($path),
            filename: $filename,
            encoding: $encoding,
        );
    }

    // 確認から実行までの変更を見つけるための控え。CSVにあるidのデータのid => DBの更新日時の文字列。
    // モデルが更新日時を持たなければid => ''で、削除されたかだけを見る。処理だけのモードでは空。
    private function csvImportSnapshot(CsvImportSettings $settings, CsvImportResult $result): array
    {
        if ($settings->query === null) {
            return [];
        }

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

    // ---- セッション ----

    // 確認の状態を持つセッションのキー。キーの「.」は階層の区切りになるので、ルート名の「.」は置き換える。
    private function csvImportSessionKey(CsvImportSettings $settings): string
    {
        return 'csv_import_'.str_replace('.', '_', $settings->route);
    }
}
