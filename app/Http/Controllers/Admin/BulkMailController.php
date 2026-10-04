<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BulkMailStatus;
use App\Enums\OperationLogAction;
use App\Enums\CsvEncoding;
use App\Enums\CsvImportMode;
use App\Http\Controllers\Controller;
use App\Jobs\SendBulkMail;
use App\Models\BulkMail;
use App\Models\BulkMailTemplate;
use App\Rules\BulkMailPlaceholderRule;
use App\Support\AjaxFileUpload;
use App\Support\CsvImportResult;
use App\Support\CsvImportRow;
use App\Support\CsvImportSettings;
use App\Support\CsvReader;
use App\Support\FormFlow;
use App\Support\UploadFilePath;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * 一斉メールの送信。件名・本文・添付ファイルを入力し、宛先のCSVを選んで、確認画面を通って送る。
 * キューを使った送信の見本でもある。
 *
 * ■ 流れ
 * 1. 入力：登録した文面を選ぶと件名と本文の欄に入り、そこから直せる。添付ファイルは1つまで
 * 2. 確認：CSVを読んで全行を確かめ、件名・本文と宛先の件数とエラーの行を出す
 * 3. 送信：送信の記録を1件作り、宛先の数だけSendBulkMailのジョブを1つのバッチに積む
 * 4. 状況：バッチから送信済み・失敗・残りの件数を読んで出す。終わったら件数を記録に写す
 *
 * ■ 宛先のCSV
 * 「氏名,メールアドレス」の2列で、見出しの行は無い。会員のデータからではなくCSVから送るのは、
 * 会員でない人にも送れるようにするため。読み込みと検証はCsvReaderを借り、画面と流れはここで持つ。
 * 宛先は保存せず、ジョブの中にだけ置く。
 *
 * ■ 二重の送信
 * 送信中の一斉メールがあるあいだは、入力画面を開いてもその状況の画面に回し、新しく送らせない。
 * 2つの画面から同時に送信したときに備え、送信の実行はロックの中で確かめる。
 */
class BulkMailController extends Controller
{
    // ---- 共通処理（トレイト） ----

    use AjaxFileUpload;
    use CsvReader;
    use FormFlow;

    // ---- 一覧の設定 ----

    // 送信の履歴の一覧のルート名
    private const INDEX_ROUTE = 'admin.bulk-mails.index';

    // 履歴の1ページの件数
    private const PER_PAGE = 20;

    // ---- 送信の設定 ----

    // 本文の初期値。宛先の氏名の差し込みと敬称を1行目に入れておく
    private const DEFAULT_BODY = "{{\$name}} 様\n";

    // 宛先の上限の件数
    private const MAX_RECIPIENTS = 5000;

    // 確認画面に並べる宛先とエラーの行の数
    private const PREVIEW_ROWS = 30;

    // 確認画面の見本に差し込む氏名。CSVの1行目が読めなかったときに使う
    private const SAMPLE_NAME = '山田 太郎';

    // 送信を始めるときのロックの名前と、ロックを持っていてよい秒数
    private const SEND_LOCK = 'bulk_mail:send';

    private const SEND_LOCK_SECONDS = 60;

    // 送信中の状況の画面を、自動で読み直す間隔の秒数
    private const REFRESH_SECONDS = 5;

    // ---- 添付ファイルの設定 ----

    // アップロードの欄。添付ファイルは1つまで
    private const UPLOAD_FILES = [
        'attach' => 0,
    ];

    // 添付ファイルの大きさの上限(KB)。宛先の数だけ毎回送られるので、サイト全体の上限より小さくする
    private const ATTACH_MAX_KB = 2048;

    // 添付ファイルに使える拡張子
    private const ATTACH_TYPES = ['pdf'];

    // 送信の直前に読んだ宛先のCSV。additionalFields()で件数を決めるのに使う
    private ?CsvImportResult $sendingCsv = null;

    // ---- 宛先のCSVの定義 ----

    // CSVの列。見出しの行は無く、この順に並んでいる前提
    private const RECIPIENT_COLUMNS = [
        '氏名' => 'name',
        'メールアドレス' => 'email',
    ];

    // CSVの1行の検証ルール。氏名は件名に差し込むので、メールの見出しを崩す改行は許さない
    private const RECIPIENT_RULES = [
        'name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
        'email' => ['required', 'email:rfc', 'max:255'],
    ];

    // ---- このコーナーの項目の定義 ----

    // 入力の検証ルール。件名に改行があるとメールの見出しとして読まれてしまうので、改行は許さない
    private function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:200', 'not_regex:/[\r\n]/', new BulkMailPlaceholderRule()],
            'body' => ['required', 'string', 'max:20000', new BulkMailPlaceholderRule()],
        ] + $this->ajaxUploadRules();
    }

    // 保存する項目
    private function saveFieldNames(array $validated, BulkMail $bulkMail): array
    {
        return ['subject', 'body'];
    }

    // 入力値ではない保存する項目。宛先の件数とCSVのファイル名は、送信の直前に読んだCSVから決める
    private function additionalFields(array $validated, BulkMail $bulkMail): array
    {
        return [
            'csv_filename' => $this->sendingCsv->filename,
            'recipient_count' => count($this->sendingCsv->rows),
            'status' => BulkMailStatus::Sending,
            'operator_id' => Auth::id(),
        ];
    }

    // 新規の入力の初期値
    private function defaultInput(): array
    {
        return ['body' => self::DEFAULT_BODY];
    }

    // 宛先のCSVの読み込みの設定
    private function recipientCsvSettings(): CsvImportSettings
    {
        return new CsvImportSettings(
            query: null,
            name: '一斉メールの宛先',
            route: 'admin.bulk-mails.create',
            labelColumn: 1,
            mode: CsvImportMode::Process,
            encoding: null,
            header: false,
            escapeFormula: false,
            allowInsert: false,
            maxRows: self::MAX_RECIPIENTS,
        );
    }

    // 操作ログに残す操作の種類。送信の記録を1件作るのが、送信の始まりなので、
    // 「登録」ではなく「一斉メールの送信」として残す。
    private function savedLogAction(bool $created): OperationLogAction
    {
        return OperationLogAction::BulkMailSend;
    }

    // ---- 履歴 ----

    // 送信の履歴。新しいものから並べる
    public function index(): View
    {
        return view('admin.bulk_mails.index', [
            'bulkMails' => BulkMail::orderByDesc('id')->paginate(self::PER_PAGE),
        ]);
    }

    // 1件の送信の状況。送信中なら自動で読み直す
    public function show(BulkMail $bulkMail): View
    {
        return view('admin.bulk_mails.show', [
            'bulkMail' => $bulkMail,
            'counts' => $this->sendCounts($bulkMail),
            'refreshSeconds' => $bulkMail->status === BulkMailStatus::Sending ? self::REFRESH_SECONDS : null,
        ]);
    }

    // ---- 送信 ----

    // 入力画面。送信中の一斉メールがあれば、その状況の画面に回す
    public function create(): View|RedirectResponse
    {
        if ($sending = BulkMail::sending()->first()) {
            return redirect()->route('admin.bulk-mails.show', $sending)
                ->with('error', '送信中の一斉メールがあるため、終わるまで新しく送れません。');
        }

        return view('admin.bulk_mails.create', [
            'input' => $this->formInput(null, old()),
            'required' => $this->requiredFields(null, ['csv_file']),
            'templates' => BulkMailTemplate::orderBy('title')->get(),
            'attachMaxKb' => self::ATTACH_MAX_KB,
            'attachTypes' => self::ATTACH_TYPES,
        ]);
    }

    // 確認画面。入力とCSVを確かめ、CSVにエラーが無ければ送信のための合言葉を持たせる
    public function confirm(Request $request): View|RedirectResponse
    {
        if ($sending = BulkMail::sending()->first()) {
            return redirect()->route('admin.bulk-mails.show', $sending)
                ->with('error', '送信中の一斉メールがあるため、終わるまで新しく送れません。');
        }

        $request->validate(['csv_file' => $this->csvFileRules()], [], ['csv_file' => '宛先のCSVファイル']);
        $input = $this->confirmInput($request);
        $this->checkAttachLimit($input['attach_tmp'] ?? null);

        // CSVを一時ディレクトリに置いて全行を読む
        $settings = $this->recipientCsvSettings();
        $sessionKey = $this->sessionKey();
        $file = $request->file('csv_file');
        $path = $this->storeCsvForConfirm($file, $sessionKey);
        $result = $this->readRecipients($path, $file->getClientOriginalName(), $settings->encoding);
        $token = null;

        if ($result->hasErrors()) {
            // エラーがあれば送らせないので、一時ファイルを消す
            $this->deleteCsvFiles($path);
        } else {
            // エラーが無ければ、送信のための合言葉を持つ
            $token = $this->rememberCsv($sessionKey, [
                'path' => $path,
                'filename' => $result->filename,
                'encoding' => $result->encoding,
            ]);
        }

        $issueRows = array_filter($result->rows, fn (CsvImportRow $row) => $row->errors !== []);

        // 件名と本文は、1行目の宛先の氏名を差し込んだ見本で見せる
        $sampleName = $result->rows[0]->validated['name'] ?? self::SAMPLE_NAME;

        return view('admin.bulk_mails.confirm', [
            'input' => $input,
            'sampleName' => $sampleName,
            'sampleSubject' => BulkMail::fillName($input['subject'], $sampleName),
            'sampleBody' => BulkMail::fillName($input['body'], $sampleName),
            'result' => $result,
            'token' => $token,
            'issueRows' => array_slice($issueRows, 0, self::PREVIEW_ROWS),
            'issueCount' => count($issueRows),
            'previewRows' => array_slice($result->rows, 0, self::PREVIEW_ROWS),
            'previewLimit' => self::PREVIEW_ROWS,
        ]);
    }

    // 確認画面からの「戻る」。CSVはファイルの欄に戻せないので、選び直してもらう
    public function back(Request $request): RedirectResponse
    {
        return redirect()->route('admin.bulk-mails.create')
            ->withInput($request->except('_token', 'confirm_token'));
    }

    /**
     * 送信の実行。送信の記録を1件作り、宛先の数だけジョブを1つのバッチに積む。
     * 2つの画面から同時に押されても1つしか送らないよう、送信中があるかの確かめから積み終わるまでを
     * ロックの中で行う。
     */
    public function store(Request $request): RedirectResponse
    {
        return Cache::lock(self::SEND_LOCK, self::SEND_LOCK_SECONDS)->block(10, function () use ($request) {
            // 送信中の一斉メールがあれば、重ねて送らない
            if ($sending = BulkMail::sending()->first()) {
                return redirect()->route('admin.bulk-mails.show', $sending)
                    ->with('error', '送信中の一斉メールがあるため、終わるまで新しく送れません。');
            }

            // 確認画面の合言葉は1回だけ使える。二重の送信や古い確認画面からの送信を防ぐ
            $state = $this->pullCsv($this->sessionKey(), $request->input('confirm_token'));

            if ($state === null) {
                return redirect()->route('admin.bulk-mails.create')
                    ->with('error', '確認の有効期限が切れています。もう一度入力してください。');
            }

            // CSVを読み直す。確認画面の後に一時ファイルが変わっていないかも、ここで確かめ直す
            try {
                $result = $this->readRecipients($state['path'], $state['filename'], $this->csvEncodingOf($state['encoding']));
            } finally {
                $this->deleteCsvFiles($state['path']);
            }

            if ($result->hasErrors()) {
                return redirect()->route('admin.bulk-mails.create')
                    ->with('error', '宛先のCSVにエラーがあります。もう一度入力してください。');
            }

            $this->checkAttachLimit($request->input('attach_tmp'));

            // 送信の記録を作り、添付ファイルを確定する
            $this->sendingCsv = $result;
            $bulkMail = new BulkMail();
            $this->saveData($bulkMail, $request);

            // 宛先の数だけジョブを積む。積めなければ記録を完了にして、送信中のまま残さない
            try {
                $batch = $this->dispatchSendJobs($bulkMail, $result);
            } catch (Throwable $e) {
                $bulkMail->update(['status' => BulkMailStatus::Finished, 'finished_at' => now()]);

                throw $e;
            }

            $bulkMail->update(['batch_id' => $batch->id]);

            return redirect()->route('admin.bulk-mails.show', $bulkMail)
                ->with('status', "{$bulkMail->recipient_count}件の送信を始めました。");
        });
    }

    // ---- CSVの検証 ----

    // 宛先のCSVを読んで全行を確かめる
    private function readRecipients(string $path, string $filename, ?CsvEncoding $encoding): CsvImportResult
    {
        return $this->readCsv(
            settings: $this->recipientCsvSettings(),
            columnDefinitions: self::RECIPIENT_COLUMNS,
            rulesFor: fn (?Model $record) => self::RECIPIENT_RULES,
            path: $this->csvFilePath($path),
            filename: $filename,
            encoding: $encoding,
        );
    }

    // 全行を確かめた後に、同じメールアドレスが2回以上あればエラーにする。同じ人に2通届かないように
    private function validateCsvRows(CsvImportResult $result): void
    {
        $firstLines = [];

        foreach ($result->rows as $row) {
            $email = mb_strtolower((string) ($row->validated['email'] ?? ''));

            if ($email === '') {
                continue;
            }

            if (isset($firstLines[$email])) {
                $row->addError("同じメールアドレスが{$firstLines[$email]}行目にもあります。", 'メールアドレス');
            } else {
                $firstLines[$email] = $row->line;
            }
        }
    }

    // 添付ファイルの大きさと種類を確かめる。サイト全体の上限より厳しい、一斉メールだけの上限
    private function checkAttachLimit(?string $tmpName): void
    {
        if (! $tmpName) {
            return;
        }

        $disk = Storage::disk(UploadFilePath::TMP_DISK);
        $tmpPath = UploadFilePath::TMP_DIR.'/'.$tmpName;
        $extension = strtolower(pathinfo($tmpName, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ATTACH_TYPES, true)) {
            throw ValidationException::withMessages([
                'attach' => '添付ファイルは'.implode('・', self::ATTACH_TYPES).'だけです。',
            ]);
        }

        if ($disk->exists($tmpPath) && $disk->size($tmpPath) > self::ATTACH_MAX_KB * 1024) {
            throw ValidationException::withMessages([
                'attach' => '添付ファイルは'.self::ATTACH_MAX_KB.'KBまでです。',
            ]);
        }
    }

    // ---- キュー ----

    /**
     * 宛先の数だけSendBulkMailのジョブを、1つのバッチにして積む。失敗した宛先があっても
     * ほかは送り続ける。全部が終わったら、送れた件数と失敗した件数を記録に写して完了にする。
     */
    private function dispatchSendJobs(BulkMail $bulkMail, CsvImportResult $result): Batch
    {
        $jobs = array_map(
            fn (CsvImportRow $row) => new SendBulkMail($bulkMail->id, $row->validated['name'], $row->validated['email']),
            $result->rows,
        );
        $bulkMailId = $bulkMail->id;

        return Bus::batch($jobs)
            ->name("一斉メール {$bulkMailId}")
            ->allowFailures()
            ->finally(function (Batch $batch) use ($bulkMailId) {
                BulkMail::whereKey($bulkMailId)->update([
                    'status' => BulkMailStatus::Finished,
                    'sent_count' => $batch->totalJobs - $batch->pendingJobs,
                    'failed_count' => $batch->failedJobs,
                    'finished_at' => now(),
                ]);
            })
            ->dispatch();
    }

    // 送信済み・失敗・残りの件数。送信中はバッチから読み、終わっていれば記録に写した件数を使う
    private function sendCounts(BulkMail $bulkMail): array
    {
        $batch = $bulkMail->status === BulkMailStatus::Sending ? $bulkMail->batch() : null;

        if ($batch === null) {
            return [
                'sent' => $bulkMail->sent_count,
                'failed' => $bulkMail->failed_count,
                'remaining' => 0,
            ];
        }

        // バッチの残りの件数には、失敗した件数も入っている
        return [
            'sent' => $batch->totalJobs - $batch->pendingJobs,
            'failed' => $batch->failedJobs,
            'remaining' => $batch->pendingJobs - $batch->failedJobs,
        ];
    }

    // ---- セッション ----

    // 確認から送信までの状態を持つセッションのキー
    private function sessionKey(): string
    {
        return 'bulk_mail_send';
    }
}
