<?php

namespace Neobit\Core\Security;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Neobit\Core\Http\Middleware\RestrictSiteAccess;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * 入口の見張りの状態。判断（Neobit\Core\Security\Gate）に渡す状態を作り、見張りのためにJSONへ書き出す。
 *
 * ■ 作る口は1つ
 * 状態はbuild()の1か所で作る。Laravelのミドルウェアはbuild()の結果をそのまま使い、見張りはそれを書き出した
 * ファイルを読む。ミドルウェアがファイルを読まないのは、ファイルが古いときの保険にするため。
 * DBの分（止めているIPアドレスと、画面で入れたアクセス制限）は、IpBlocker・AccessRestrictionのキャッシュを使い、
 * .envの分（許すIPアドレスと、信頼するプロキシ）は、毎回設定から読む。
 *
 * ■ 書き出し
 * storage/app/gate/の下に、state.jsonと、見張りが返す画面（404.html・maintenance.html）を置く。
 * JSONにするのは、apacheが書ける場所のファイルを、どの要求でもPHPのコードとして動かさないため。
 * 仮のファイルに書いてから名前を変え、読みかけの見張りに半端な中身を見せない。
 * IPブロックとアクセス制限を変えたときに書き出す（IpBlocker・AccessRestriction）。.envを変えたときは、
 * 設定のキャッシュより古くなったことにRejectBlockedIpが気付いて、書き出し直す（refreshIfStale()）。
 * 手で作り直すのは php artisan app:gate --export。
 */
class GateState
{
    // ---- 置き場所 ----

    /** 書き出すディレクトリ（storageの下） */
    private const DIRECTORY = 'app/gate';

    /** 状態のファイルの名前 */
    private const STATE_FILE = 'state.json';

    // ---- 書き出しの失敗の知らせ ----

    /** 書き出し直しに失敗したことを覚えておくキャッシュのキー。毎回のアクセスでやり直さないため */
    private const FAILED_KEY = 'neobit-gate-export-failed';

    /** 書き出し直しに失敗した後、やり直すまでの秒数 */
    private const FAILED_SECONDS = 300;

    // ---- 状態 ----

    /**
     * 判断に使う状態。形は Neobit\Core\Security\Gate の冒頭のコメントのとおり。
     *
     * @return array{blocks: list<array{0: string, 1: int|null}>, site: array{restricted: bool, ips: string[]}, admin: array{restricted: bool, ips: string[]}, admin_path: string, site_except: string[], trusted_proxies: string[]}
     */
    public static function build(): array
    {
        $settings = AccessRestriction::settings();

        return [
            'blocks' => IpBlocker::blockedList(),
            'site' => [
                'restricted' => $settings['site_restricted'],
                'ips' => AccessRestriction::siteIps(),
            ],
            'admin' => [
                'restricted' => $settings['admin_restricted'],
                'ips' => AccessRestriction::adminIps(),
            ],
            'admin_path' => self::adminPath(),
            'site_except' => self::siteExcept(),
            'trusted_proxies' => array_values((array) config('app.trusted_proxies', [])),
        ];
    }

    /** 管理画面のURLの頭。app/admin の形 */
    public static function adminPath(): string
    {
        return trim((string) config('neobit.admin_path'), '/');
    }

    // ---- 書き出し ----

    /**
     * 状態をファイルに書き出す。書き出せなかったときは、その理由を返す。書き出せたらnull。
     * $withPagesがtrueのときと、画面のファイルがまだ無いときは、見張りが返す画面も作り直す。
     * 画面は状態の後に作り、作れなくても状態の書き出しは成功とする。案件が足したエラーの画面が
     * コマンドから描けないときなどに、状態の写しまで古いまま残さないため。
     */
    public static function export(bool $withPages = false): ?string
    {
        // 状態を書き出す
        try {
            $directory = self::directory();
            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return "ディレクトリを作れません：{$directory}";
            }

            $state = self::build() + ['exported_at' => time()];
            $error = self::writeFile(self::STATE_FILE, (string) json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($error !== null) {
                return $error;
            }
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        // 画面は、文言やサイトの名前を変えたときだけ変わるので、毎回は作らない。作れなければ、ログに残すだけ
        if ($withPages || ! self::pagesExist()) {
            try {
                foreach (self::renderPages() as $status => $html) {
                    $error = self::writeFile(Gate::PAGES[$status], $html);
                    if ($error !== null) {
                        throw new RuntimeException($error);
                    }
                }
            } catch (Throwable $e) {
                Log::error('GateState: 入口の見張りが返す画面を作れませんでした。見張りは短い文だけを返します。'.$e->getMessage());
            }
        }

        return null;
    }

    /**
     * 状態を書き出す。書き出せなかったら、エラーのログに残す。IPブロックとアクセス制限を変えたときに呼ぶ。
     * 古い写しのままだと、見張りが解除したIPアドレスを止め続ける。止められた人の要求はLaravelに届かず、
     * Laravelの側では直せないので、知らせて人に直してもらう。
     */
    public static function exportOrReport(): void
    {
        $error = self::export();
        if ($error !== null) {
            Log::error("GateState: 入口の見張りの状態を書き出せませんでした。php artisan app:gate --export で書き出し直してください。{$error}");
        }
    }

    /**
     * 写しが無いか、設定のキャッシュより古ければ、書き出し直す。RejectBlockedIpが、毎回のアクセスで呼ぶ。
     * .envを変えてconfig:cacheをやり直したときに、許すIPアドレスや信頼するプロキシの変化を写しに移すため。
     * 設定のキャッシュが無いとき（手元・テスト）は、写しが無いときだけ書き出す。
     * 失敗したら、しばらくは書き出し直しもログも止める。攻撃の最中に、毎回のアクセスで書き出しを
     * やり直して重くしたり、ログを増やしたりしないため。
     */
    public static function refreshIfStale(): void
    {
        $stateFile = self::stateFile();
        $configCache = app()->getCachedConfigPath();
        $stateTime = @filemtime($stateFile);
        if ($stateTime !== false && (! is_file($configCache) || (int) @filemtime($configCache) <= $stateTime)) {
            return;
        }

        // 少し前に失敗していれば、やり直さない
        if (Cache::has(self::FAILED_KEY)) {
            return;
        }

        // 設定を変えたときは、サイトの名前などが変わっているかもしれないので、画面も作り直す
        $error = self::export(withPages: $stateTime !== false);
        if ($error !== null) {
            Cache::put(self::FAILED_KEY, true, self::FAILED_SECONDS);
            Log::error("GateState: 入口の見張りの状態を書き出せませんでした。php artisan app:gate --export で書き出し直してください。{$error}");
        }
    }

    // ---- 確かめ ----

    /** 書き出した状態。無いか読めなければnull */
    public static function read(): ?array
    {
        $json = @file_get_contents(self::stateFile());
        $state = $json === false ? null : json_decode($json, true);

        return is_array($state) ? $state : null;
    }

    /** 書き出した状態が、今の状態と同じか。書き出した日時は比べない */
    public static function isCurrent(): bool
    {
        $state = self::read();
        if ($state === null) {
            return false;
        }
        unset($state['exported_at']);

        // JSONを通した形にそろえてから比べる
        return $state === json_decode((string) json_encode(self::build()), true);
    }

    /** 状態のファイルのパス */
    public static function stateFile(): string
    {
        return self::directory().DIRECTORY_SEPARATOR.self::STATE_FILE;
    }

    // ---- 内側の処理 ----

    /** 書き出すディレクトリ */
    private static function directory(): string
    {
        return storage_path(self::DIRECTORY);
    }

    /** 書き出すディレクトリにファイルを書く。仮のファイルに書いてから名前を変える。書けなければ、その理由 */
    private static function writeFile(string $name, string $content): ?string
    {
        $path = self::directory().DIRECTORY_SEPARATOR.$name;
        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (@file_put_contents($temporary, $content) === false || ! @rename($temporary, $path)) {
            @unlink($temporary);

            return "書き込めません：{$path}";
        }

        return null;
    }

    /** 見張りが返す画面のファイルが、全部あるか */
    private static function pagesExist(): bool
    {
        foreach (Gate::PAGES as $name) {
            if (! is_file(self::directory().DIRECTORY_SEPARATOR.$name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 見張りが返す画面を、Laravelが返すものと同じ道筋で作る。止められていることに気付かせないため。
     * 404は、ルートの無いURLを開いたときと同じく、例外のハンドラーに描かせる。
     *
     * @return array<int, string>
     */
    private static function renderPages(): array
    {
        $notFound = app(ExceptionHandler::class)->render(Request::create('/'), new NotFoundHttpException());

        return [
            Gate::NOT_FOUND => (string) $notFound->getContent(),
            Gate::MAINTENANCE => view(RestrictSiteAccess::VIEW)->render(),
        ];
    }

    /**
     * サイトの制限を掛けないURL。アプリがbootstrap/app.phpで渡すものは、Laravelのミドルウェアの組み立てのときに
     * 決まるので、コマンドから書き出すときは、先に組み立てさせる。
     *
     * @return string[]
     */
    private static function siteExcept(): array
    {
        if (! app()->resolved(HttpKernel::class)) {
            app(HttpKernel::class);
        }

        return RestrictSiteAccess::exceptPaths();
    }
}
