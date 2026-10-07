<?php

namespace App\Support;

use App\Mail\TemplatedMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * ログに書いたエラーを、開発者にメールで知らせる。config/logging.phpのerror_notifyのチャンネルで、
 * ほかのログと一緒にstackに入れて使う。ログを見に行かなくても、本番で起きたことに気付けるようにするため。
 * 宛先はERROR_NOTIFY_TOに、カンマで区切って複数書ける。
 * 入力値はメールに記載しない。詳しくはサーバーのログで見る。
 *
 * ■ どこまで知らせるか
 * .envのERROR_NOTIFY_LEVELに書いたレベルと、それより重いものを知らせる。空なら知らせない。
 * 環境で切り替えるのではなく、このレベルだけで切り替える。手元の開発では空にしておく。
 * - critical  処理が止まった障害。処理されなかった例外はbootstrap/app.phpでcriticalにしている
 * - error     処理は続いたが運用に支障が出るもの。メールが送れなかった、鍵の設定が誤っているなど
 * - warning   動いてはいるが気になるもの。Cloudflareが応答せず判定を素通りしたなど
 * 404や入力エラーのように利用者の操作で普通に起きるものは、Laravelが例外を報告しないので届かない。
 *
 * ■ 件名
 * 「【要確認：サイト名】レベル：内容」。頭の「【要確認：サイト名】」は全部の通知で同じなので、
 * 受け取る人がメールの振り分けに使える。レベルは、一覧で急ぎかどうかを見分けるために載せる。
 * 内容は、例外なら種類、それ以外なら文言の頭。文言の頭の「クラス名: 」は、件名には出さない。
 *
 * ■ 間引き
 * 同じ内容の通知はERROR_NOTIFY_INTERVALの分数の間に1通だけ送る。
 * 同じ内容とは、例外なら種類と原因の場所、それ以外ならレベルと文言が同じもの。
 * 原因の場所は、スタックトレースの中で最初に出てくる自分たちのコードの行。
 * 間引いた場合は、間隔が過ぎて次に同じものが起きたときの通知に間引いた件数を記載する。
 * 間引きの記録はファイルのキャッシュに置く。DBが落ちたときにも間引けるようにするため。
 *
 * ■ 通知が送れないとき
 * SMTPが落ちているなどで送れなければ、そのことをログに残すだけにする。
 * このエラー通知自体を送れなかったというエラーについては報告しない。
 */
final class ErrorNotifyHandler extends AbstractProcessingHandler
{
    // 間引きの記録のキーの頭
    private const CACHE_PREFIX = 'error_notify:';

    // 件名に載せる内容の幅（半角で数えた文字数）。これより長ければ切る
    private const TITLE_WIDTH = 60;

    // メールに載せるスタックトレースの行数
    private const TRACE_LINES = 15;

    // 通知を送っている最中か。この通知自体を送れなかったというエラーを、また通知しないための目印
    private static bool $sending = false;

    // $levelは知らせる最低のレベル、$toはカンマ区切りの宛先、$intervalMinutesは同じ内容を送る間隔
    public function __construct(
        int|string|Level $level,
        private readonly string $to,
        private readonly int $intervalMinutes,
    ) {
        parent::__construct($level);
    }

    // ログの1件を受け取り、間引きの間隔を過ぎていればメールで知らせる
    protected function write(LogRecord $record): void
    {
        $recipients = array_filter(array_map('trim', explode(',', $this->to)));

        // 通知を送っている最中に書かれたログと、宛先が無いときは知らせない
        if (self::$sending || $recipients === []) {
            return;
        }

        self::$sending = true;

        try {
            $exception = $record->context['exception'] ?? null;
            $exception = $exception instanceof Throwable ? $exception : null;

            // 同じ内容の通知を間引く。間隔の中なら件数だけを数えて送らない
            $suppressed = $this->throttle($record, $exception);

            if ($suppressed === null) {
                return;
            }

            Mail::send(new TemplatedMail('error_notify', $this->mailVariables($record, $exception, $recipients, $suppressed)));
        } catch (Throwable $e) {
            // 送れなかったことはログに残すだけ。$sendingが立っているので、このログは通知しない
            Log::error('ErrorNotifyHandler: エラーの通知メールを送れませんでした。', ['message' => $e->getMessage()]);
        } finally {
            self::$sending = false;
        }
    }

    /**
     * 同じ内容の通知を間引く。送ってよければ、前の通知の後に間引いた件数を返す。
     * 間隔の中で送らないときはnullを返す。間隔が0以下なら間引かない。
     */
    private function throttle(LogRecord $record, ?Throwable $exception): ?int
    {
        if ($this->intervalMinutes <= 0) {
            return 0;
        }

        // 例外は種類と原因の場所で、それ以外はレベルと文言で同じ内容かを見分ける
        if ($exception !== null) {
            $signature = $exception::class.'|'.$this->originOf($exception);
        } else {
            $signature = $record->level->getName().'|'.$record->message;
        }

        $key = self::CACHE_PREFIX.sha1($signature);
        $cache = Cache::store('file');

        // 間隔の中で初めてなら送り、それまでに間引いた件数を取り出す
        if ($cache->add($key, true, now()->addMinutes($this->intervalMinutes))) {
            return (int) $cache->pull($key.':suppressed', 0);
        }

        // 間隔の中で2回目からは数えるだけ。件数は次に送るまで残しておく
        $cache->add($key.':suppressed', 0, now()->addDays(7));
        $cache->increment($key.':suppressed');

        return null;
    }

    // 通知のメールのテンプレートに渡す値。入力値は記載しない
    private function mailVariables(LogRecord $record, ?Throwable $exception, array $recipients, int $suppressed): array
    {
        $variables = [
            'from_mail' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'to' => implode(', ', $recipients),
            'app_name' => config('app.site_name'),
            'app_env' => config('app.env'),
            'level' => $record->level->getName(),
            'datetime' => $record->datetime->format('Y-m-d H:i:s'),
            'message' => $record->message,
            'suppressed' => $suppressed,
            'where' => $this->whereItHappened(),
            'title' => '',
            'context' => '',
            'exception_class' => '',
            'location' => '',
            'trace' => '',
        ];

        if ($exception !== null) {
            // 例外なら、件名に種類を使い、種類と原因の場所とスタックトレースの頭を載せる
            $variables['title'] = class_basename($exception);
            $variables['exception_class'] = $exception::class;
            $variables['location'] = $this->originOf($exception);
            $trace = array_slice(explode("\n", $exception->getTraceAsString()), 0, self::TRACE_LINES);
            $variables['trace'] = $this->relativePath(implode("\n", $trace));
        } else {
            // 例外でなければ、件名に文言の頭を使う。文言の頭の「クラス名: 」と終わりの句点は除く
            $title = rtrim(preg_replace('/^\w+: /', '', $record->message), '。');
            $variables['title'] = mb_strimwidth($title, 0, self::TITLE_WIDTH, '…');
        }

        // Log::error()などに添えた情報。例外はほかの欄に出すので除く
        $context = array_diff_key($record->context, ['exception' => true]);

        if ($context !== []) {
            $variables['context'] = (string) json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        return $variables;
    }

    // どこで起きたか。画面ならメソッドとURLとIPとログイン中の人、コマンドならそのコマンド
    private function whereItHappened(): string
    {
        if (app()->runningInConsole()) {
            return 'コマンド：'.implode(' ', $_SERVER['argv'] ?? []);
        }

        // 問い合わせの部分には入力値が入ることがあるので、URLはパスまでにする
        $request = request();
        $lines = [$request->method().' '.$request->url(), 'IP：'.$request->ip()];

        // ログイン中の人。セッションが読めないなどで分からなければ書かない
        try {
            if (auth('admin')->id() !== null) {
                $lines[] = 'スタッフ：'.auth('admin')->id();
            }
            if (auth('web')->id() !== null) {
                $lines[] = '会員：'.auth('web')->id();
            }
            if (auth('company')->id() !== null) {
                $lines[] = '企業の担当者：'.auth('company')->id();
            }
        } catch (Throwable) {
        }

        return implode("\n", $lines);
    }

    /**
     * 例外の原因の場所を返す。例外が投げられた行からスタックトレースをたどり、最初に出てくる自分たちのコードの行にする。
     * SQLのエラーのように、どこで起きてもフレームワークの同じ行から投げられる例外を、原因ごとに見分けるため。
     * 自分たちのコードを通っていなければ、例外が投げられた行を返す。
     */
    private function originOf(Throwable $exception): string
    {
        $thrownAt = ['file' => $exception->getFile(), 'line' => $exception->getLine()];

        // 自分たちのコードとはしないもの。vendorの下と、どの処理も通る入口のファイル
        $vendor = base_path('vendor').DIRECTORY_SEPARATOR;
        $entries = [public_path('index.php'), base_path('artisan')];

        foreach ([$thrownAt, ...$exception->getTrace()] as $frame) {
            $file = $frame['file'] ?? null;

            // ファイルの無い行（PHPの内部の呼び出し）は飛ばす
            if ($file === null) {
                continue;
            }

            if (str_starts_with($file, base_path().DIRECTORY_SEPARATOR) && ! str_starts_with($file, $vendor) && ! in_array($file, $entries, true)) {
                return $this->relativePath($file).':'.($frame['line'] ?? 0);
            }
        }

        return $this->relativePath($thrownAt['file']).':'.$thrownAt['line'];
    }

    // サーバーの中の場所を、プロジェクトからの相対パスにする
    private function relativePath(string $text): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $text);
    }
}
