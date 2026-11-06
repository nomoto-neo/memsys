<?php

namespace Neobit\Core\Operations;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * 画面（Blade）とJS・CSSを、元のファイルが変わったときに作り直す。デザイナーが送った後に、シェルで
 * view:cacheやnpm run buildを流さずに済むようにする。neobit:rebuildコマンドが、コードを更新する利用者（devapp）の
 * crontabから毎分呼ぶ。コンパイル済みの画面をWebの利用者（apache）に書かせない守りは、そのまま保つ。
 *
 * ■ 変わったかどうか
 * 元のファイルの一覧（パス・大きさ・日時）からしるし（ハッシュ値）を作り、前に作り直したときのしるし
 * （STATE_FILE）と比べる。足した・直した・消した・名前を変えた、のどれでも変わる。
 * 初めて動いたとき（しるしの記録が無いとき）は、作り直さずにしるしだけを記録する。送った後の手順で作り直しは済んでいるため。
 *
 * ■ 画面
 * 登録された画面の場所（view:cacheと同じ）の全部のBladeを、仮のディレクトリ（bootstrap/cache/views_next）に
 * コンパイルし、できあがったらbootstrap/cache/viewsと入れ替える。今の置き場所で作り直すと、中身の変わらない画面で
 * コンパイラが日時を更新しようとし（touch()）、持ち主でない利用者が作ったファイルでは失敗するため。
 * 元のファイルが無くなった画面のコンパイル済みファイルは、新しい置き場所に作られないので残らない。
 * 送ってから作り直すまでの間は、サーバーのconfig('view.check_cache_timestamps')をfalseにして、前の画面を出す。
 *
 * ■ JS・CSS
 * npm run buildを、仮のディレクトリ（public/build_next）へ出し、できあがったらpublic/buildと入れ替える。
 * Viteは作る前に出し先を空にするので、public/buildへ直接作ると、作っている間は画面の見た目と動きが崩れるため。
 * package.json・package-lock.jsonが変わったときは作らない。npm ciで外からパッケージを取ってくるのは、人が行う。
 *
 * ■ 失敗したとき
 * 入れ替えずに前のものを残し、エラーのログに書く（エラーの通知のメールで届く）。しるしは記録するので、
 * 元のファイルがまた変わるまでは作り直さない。毎分失敗し続けないため。
 */
final class AssetRebuilder
{
    // ---- 置き場所 ----

    /** しるしの記録（basePathの下）。Webの利用者には書かせない */
    private const STATE_FILE = 'bootstrap/cache/rebuild.json';

    /** 同時に動かないよう押さえる、ロックのファイル（basePathの下） */
    private const LOCK_FILE = 'bootstrap/cache/rebuild.lock';

    /** JS・CSSの出し先と、仮の出し先と、入れ替えるときに前のものを一時的に置く名前（publicの下） */
    private const BUILD_DIR = 'build';

    private const BUILD_NEXT_DIR = 'build_next';

    private const BUILD_OLD_DIR = 'build_old';

    /** 画面の仮の置き場所と、入れ替えるときに前のものを一時的に置く名前（コンパイル済みの画面の置き場所の後ろに付ける） */
    private const VIEWS_NEXT_SUFFIX = '_next';

    private const VIEWS_OLD_SUFFIX = '_old';

    // ---- 見る元のファイル ----

    /** JS・CSSの元のファイル（basePathの下）。フレームワークのものは、Composerが置いたvendor/neobit/coreの下 */
    private const ASSET_SOURCES = [
        'resources/js',
        'resources/css',
        'vendor/neobit/core/resources/js',
        'vendor/neobit/core/resources/css',
        'vite.config.js',
    ];

    /** 変わったらnpm ciが要るファイル（basePathの下） */
    private const PACKAGE_FILES = [
        'package.json',
        'package-lock.json',
    ];

    /** ビルドが失敗したときに、ログに残す出力の行数 */
    private const FAILED_OUTPUT_LINES = 20;

    /** ディレクトリの名前の変更を、つかまれていて失敗したときにやり直す回数と、間に待つマイクロ秒 */
    private const MOVE_TRIES = 5;

    private const MOVE_WAIT_MICROSECONDS = 200000;

    /** npm run buildにかけてよい秒数 */
    private const BUILD_TIMEOUT_SECONDS = 600;

    /** ロックのファイルのハンドル。押さえている間だけ持つ */
    private $lockHandle = null;

    public function __construct(
        private readonly string $basePath,
        private readonly string $publicPath,
    ) {
    }

    /** 同時に動かないよう、ロックのファイルを押さえる。前の回が押さえていればfalse */
    public function lock(): bool
    {
        $handle = fopen($this->basePath.'/'.self::LOCK_FILE, 'c');
        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if ($handle !== false) {
                fclose($handle);
            }

            return false;
        }
        $this->lockHandle = $handle;

        return true;
    }

    /** 押さえたロックのファイルを離す */
    public function unlock(): void
    {
        if ($this->lockHandle !== null) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    /**
     * 変わったものを作り直し、行ったことを文で返す。何も変わっていなければ空。
     *
     * @return list<string>
     */
    public function run(): array
    {
        $current = [
            'views' => $this->signature($this->viewPaths(), ['blade.php']),
            'assets' => $this->signature($this->existing(self::ASSET_SOURCES), []),
            'packages' => $this->signature($this->existing(self::PACKAGE_FILES), []),
        ];

        // 初めて動いたときは、しるしだけを記録する
        $saved = $this->savedState();
        if ($saved === null) {
            $this->saveState($current);

            return ['しるしを記録しました。次に元のファイルが変わったときから作り直します。'];
        }

        $done = [];

        // パッケージが変わったときは、JS・CSSは作らずに知らせる。npm ciは人が行う
        $packagesChanged = $current['packages'] !== ($saved['packages'] ?? null);
        if ($packagesChanged) {
            $message = 'package.json か package-lock.json が変わりました。npm ci と npm run build を流してください。JS・CSS は作り直していません。';
            Log::error($message);
            $done[] = $message;
        }

        if ($current['views'] !== ($saved['views'] ?? null)) {
            $done[] = $this->rebuildViews();
        }

        if (! $packagesChanged && $current['assets'] !== ($saved['assets'] ?? null)) {
            $done[] = $this->rebuildAssets();
        }

        // 失敗したときも記録する。元のファイルがまた変わるまでは作り直さない
        if ($done !== []) {
            $this->saveState($current);
        }

        return $done;
    }

    // ---- 画面 ----

    /** 仮のディレクトリに全部の画面をコンパイルし、できあがったら入れ替える。行ったことを文で返す */
    private function rebuildViews(): string
    {
        $started = microtime(true);
        $compiled = (string) config('view.compiled');
        $next = $compiled.self::VIEWS_NEXT_SUFFIX;
        $old = $compiled.self::VIEWS_OLD_SUFFIX;

        try {
            // 前の回の残りを片付ける
            File::deleteDirectory($next);
            File::deleteDirectory($old);
            File::ensureDirectoryExists($next);

            // 登録済みのBladeの命令を持ったまま、置き場所だけを仮のディレクトリにしたコンパイラ
            $compiler = clone app('blade.compiler');
            (function () use ($next) {
                $this->cachePath = $next;
            })->call($compiler);

            foreach ($this->viewPaths() as $path) {
                foreach (Finder::create()->in($path)->exclude('vendor')->name('*.blade.php')->files() as $file) {
                    $compiler->compile($file->getRealPath());
                }
            }

            // 置き場所の中の.gitignoreなど、コンパイル済みの画面でないファイルを写す
            foreach (File::files($compiled, true) as $file) {
                if ($file->getExtension() !== 'php') {
                    File::copy($file->getPathname(), $next.'/'.$file->getFilename());
                }
            }

            // 入れ替える。前のものは、名前を変えてから消す
            if (is_dir($compiled)) {
                $this->move($compiled, $old);
            }
            $this->move($next, $compiled);
            File::deleteDirectory($old);
        } catch (Throwable $e) {
            // 入れ替えの途中で止まったときは、前のものを戻す。仮のディレクトリは片付ける
            if (! is_dir($compiled) && is_dir($old)) {
                rename($old, $compiled);
            }
            File::deleteDirectory($next);
            $message = '画面の作り直しに失敗しました。直したBladeを確かめてください。（'.$e->getMessage().'）';
            Log::error($message);

            return $message;
        }

        $message = '画面を作り直しました。（'.round(microtime(true) - $started, 1).'秒）';
        Log::info($message);

        return $message;
    }

    /**
     * 登録された画面の場所。view:cacheと同じく、アプリの場所と、名前付きの場所（neobit::など）。
     * ほかの場所の中にある場所は、重ねて数えないよう外す
     *
     * @return list<string>
     */
    private function viewPaths(): array
    {
        $finder = app('view')->getFinder();
        $paths = array_values(array_unique(array_merge($finder->getPaths(), array_merge(...array_values($finder->getHints() ?: [[]])))));
        $paths = array_values(array_filter($paths, 'is_dir'));

        return array_values(array_filter($paths, function (string $path) use ($paths) {
            $real = realpath($path) ?: $path;
            foreach ($paths as $other) {
                $otherReal = realpath($other) ?: $other;
                if ($otherReal !== $real && str_starts_with($real, $otherReal.DIRECTORY_SEPARATOR)) {
                    return false;
                }
            }

            return true;
        }));
    }

    // ---- JS・CSS ----

    /** 仮のディレクトリにビルドし、できあがったら入れ替える。行ったことを文で返す */
    private function rebuildAssets(): string
    {
        $started = microtime(true);
        $build = $this->publicPath.'/'.self::BUILD_DIR;
        $next = $this->publicPath.'/'.self::BUILD_NEXT_DIR;
        $old = $this->publicPath.'/'.self::BUILD_OLD_DIR;

        // 前の回の残りを片付ける
        File::deleteDirectory($next);
        File::deleteDirectory($old);

        $result = Process::path($this->basePath)
            ->timeout(self::BUILD_TIMEOUT_SECONDS)
            ->run([(string) config('neobit.rebuild.npm'), 'run', 'build', '--', '--outDir', $next]);

        // 失敗したときは、前のものを残す
        if (! $result->successful() || ! is_file($next.'/manifest.json')) {
            File::deleteDirectory($next);
            $lines = array_slice(preg_split('/\R/', trim($result->output()."\n".$result->errorOutput())), -self::FAILED_OUTPUT_LINES);
            $message = 'JS・CSS の作り直しに失敗しました。前のものを残しています。'."\n".implode("\n", $lines);
            Log::error($message);

            return $message;
        }

        // 入れ替える。前のものは、名前を変えてから消す
        if (is_dir($build)) {
            $this->move($build, $old);
        }
        $this->move($next, $build);
        File::deleteDirectory($old);

        $message = 'JS・CSS を作り直しました。（'.round(microtime(true) - $started, 1).'秒）';
        Log::info($message);

        return $message;
    }

    /**
     * ディレクトリの名前を変える。Windowsでは、作ったばかりのファイルをウイルス対策などがつかんでいて失敗することがあるので、
     * 少し待ってやり直す。やり直しても失敗したら、例外を投げる
     */
    private function move(string $from, string $to): void
    {
        for ($try = 1; $try <= self::MOVE_TRIES; $try++) {
            if (@rename($from, $to)) {
                return;
            }
            usleep(self::MOVE_WAIT_MICROSECONDS);
        }

        throw new \RuntimeException("{$from} を {$to} へ移せませんでした。");
    }

    // ---- しるし ----

    /**
     * ファイルの一覧（パス・大きさ・日時）のしるし。$pathsはファイルかディレクトリ。$suffixesを書いたときは、
     * その終わりのファイルだけを見る
     *
     * @param  list<string>  $paths
     * @param  list<string>  $suffixes
     */
    private function signature(array $paths, array $suffixes): string
    {
        $lines = [];
        foreach ($paths as $path) {
            $files = is_dir($path) ? Finder::create()->in($path)->exclude('vendor')->files() : [new \SplFileInfo($path)];
            foreach ($files as $file) {
                $name = $file->getPathname();
                if ($suffixes !== [] && ! array_filter($suffixes, fn (string $suffix) => str_ends_with($name, '.'.$suffix))) {
                    continue;
                }
                $lines[] = $name."\t".$file->getSize()."\t".$file->getMTime();
            }
        }
        sort($lines);

        return hash('sha256', implode("\n", $lines));
    }

    /**
     * basePathの下のパスのうち、あるものの絶対のパス
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function existing(array $paths): array
    {
        $found = [];
        foreach ($paths as $path) {
            if (file_exists($this->basePath.'/'.$path)) {
                $found[] = $this->basePath.'/'.$path;
            }
        }

        return $found;
    }

    /** 前に作り直したときのしるし。記録が無ければnull */
    private function savedState(): ?array
    {
        $path = $this->basePath.'/'.self::STATE_FILE;
        if (! is_file($path)) {
            return null;
        }
        $state = json_decode((string) file_get_contents($path), true);

        return is_array($state) ? $state : null;
    }

    /** しるしを記録する。一時ファイルに書いてから名前を変え、途中で止まっても壊れた記録を残さない */
    private function saveState(array $state): void
    {
        File::replace($this->basePath.'/'.self::STATE_FILE, json_encode($state + ['at' => now()->toIso8601String()]));
    }
}
