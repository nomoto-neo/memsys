<?php

namespace Neobit\Core\Http;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

/**
 * 公開ディレクトリに置かれたHTMLと古いPHPを、Laravelを通してから返す仕組み。
 * デザイナーが作った.htmlや、古い作りの.php（contact/index.phpなど）にも、全体のミドルウェア
 * （IPブロック・アクセス制限・リダイレクト・セキュリティのヘッダー）を掛けるため。
 *
 * public/.htaccessが、.html・.htm・.phpとディレクトリのURLを入口のindex.phpに回し、どのルートにも当たらなければ、
 * フォールバックのルート（Neobit\Core\Http\Controllers\PublicFileController）がここで探したファイルを返す。
 * .phpは、入口のindex.phpがhandle()の返したパスを、グローバルの場所でrequireする。
 * global $db;などのグローバル変数を当てにした書き方も、そのまま動かすため。
 *
 * ■ 返さないもの
 * - 公開ディレクトリの外と、.で始まる名前（..を含む）
 * - 入口のindex.php
 * - 公開のアップロードの置き場所（storage）と、Viteが作ったもの（build）の下。アップロードされた.phpを動かさないため
 * - Laravelの本体を公開ディレクトリの中に置くサーバー（app/system/）では、本体の中
 */
class PublicFiles
{
    /** Laravelを通して返すファイルの拡張子 */
    private const PAGE_EXTENSIONS = ['html', 'htm', 'php'];

    /** ディレクトリのURLで返すファイル。先に書いたものを優先する */
    private const INDEX_FILES = ['index.html', 'index.htm', 'index.php'];

    /** 公開ディレクトリの直下で、中を返さないディレクトリ */
    private const EXCLUDE = ['storage', 'build'];

    /**
     * 入口のindex.phpから呼ぶ。Laravelにリクエストを処理させ、ふつうの応答なら送って後片付けまで済ませ、nullを返す。
     * 古い.phpに当たったときは、ヘッダーを送り、後片付けを済ませ、動かす準備をしてから、そのファイルのパスを返す
     */
    public static function handle(Application $app, Request $request): ?string
    {
        $kernel = $app->make(Kernel::class);
        $response = $kernel->handle($request);

        // ふつうの応答
        if (! $response instanceof PublicScriptResponse) {
            $response->send();
            $kernel->terminate($request, $response);

            return null;
        }

        // Laravelの付けたヘッダーを先に送る。Content-Typeは、Apacheが直接動かしていたときと同じく、PHPと古い.phpに任せる
        $response->headers->remove('Content-Type');
        $response->sendHeaders();
        $kernel->terminate($request, $response);

        // 警告を例外に変えるLaravelの仕組みを外す。古い.phpは、警告を出しながら動くことが多いため
        restore_error_handler();
        restore_exception_handler();
        error_reporting((int) (get_cfg_var('error_reporting') ?: E_ALL));

        // Apacheが直接動かしたときと同じく、作業のディレクトリと、動いているファイルの名前をそのファイルにする。
        // actionに$_SERVER['PHP_SELF']を書いた、自分へ送るフォームのため
        $script = $response->script;
        chdir(dirname($script));
        $relative = str_replace('\\', '/', substr($script, strlen((string) realpath($app->publicPath()))));
        $_SERVER['SCRIPT_FILENAME'] = $script;
        $_SERVER['SCRIPT_NAME'] = $request->getBasePath().$relative;
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

        return $script;
    }

    /** URLのパスに当たる、返すファイル（.html・.htm・.php）の絶対パス。ディレクトリなら、その中のindex。無ければnull */
    public static function file(string $path): ?string
    {
        $target = self::locate($path);
        if ($target === null) {
            return null;
        }

        // ディレクトリなら、その中のindexを探す
        if (is_dir($target)) {
            $index = null;
            foreach (self::INDEX_FILES as $name) {
                if (is_file($target.DIRECTORY_SEPARATOR.$name)) {
                    $index = $target.DIRECTORY_SEPARATOR.$name;
                    break;
                }
            }
            if ($index === null) {
                return null;
            }
            $target = $index;
        }

        // 返す拡張子のファイルだけ。入口のindex.phpは返さない
        if (! is_file($target) || ! in_array(strtolower(pathinfo($target, PATHINFO_EXTENSION)), self::PAGE_EXTENSIONS, true)) {
            return null;
        }
        if ($target === realpath(public_path('index.php'))) {
            return null;
        }

        return $target;
    }

    /** URLのパスが、返してよい場所の実在するディレクトリか */
    public static function isDirectory(string $path): bool
    {
        $target = self::locate($path);

        return $target !== null && is_dir($target);
    }

    /** URLのパスに当たる、公開ディレクトリの中の実在するものの絶対パス。返さない場所ならnull */
    private static function locate(string $path): ?string
    {
        $relative = trim($path, '/');
        $segments = explode('/', $relative);

        // .で始まる名前（..を含む）と、中を返さないディレクトリは見ない
        foreach ($segments as $segment) {
            if (str_starts_with($segment, '.')) {
                return null;
            }
        }
        if (in_array($segments[0], self::EXCLUDE, true)) {
            return null;
        }

        // 公開ディレクトリの中にあるものだけ。シンボリックリンクで外へ出るものも返さない
        $public = realpath(public_path());
        $target = realpath(public_path($relative));
        if ($public === false || $target === false) {
            return null;
        }
        if ($target !== $public && ! str_starts_with($target, $public.DIRECTORY_SEPARATOR)) {
            return null;
        }

        // Laravelの本体を公開ディレクトリの中に置くサーバーでは、本体の中を返さない
        $base = realpath(base_path());
        if ($base !== false && str_starts_with($base, $public.DIRECTORY_SEPARATOR) && str_starts_with($target.DIRECTORY_SEPARATOR, $base.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $target;
    }
}
