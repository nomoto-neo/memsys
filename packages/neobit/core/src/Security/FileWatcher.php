<?php

namespace Neobit\Core\Security;

use Carbon\CarbonInterface;
use Closure;
use RuntimeException;

/**
 * 改ざん監視。通常の運用では変わらないファイルが変わった・増えた・消えたことと、アップロードの置き場所に置かれた
 * 受け付けていないファイルを見つける。app:watch-filesコマンドが5分ごとに呼び、見つけたら通知のメールを送る。
 *
 * ■ 動かす権限
 * コードを更新する利用者（webapp）のcrontabから動かす。基準などの記録（bootstrap/cache/file-watch.json）は
 * その利用者だけが書けるので、Webの側から侵入した攻撃者（apacheの権限）は記録を書き換えられない。
 *
 * ■ 比べ方
 * 毎回はctimeと大きさだけを比べ、違いのあったファイルだけハッシュ値（sha256）を取り直す。ハッシュ値が基準と
 * 同じなら変化なし。--fullのときは全部のハッシュ値を比べ直し、vendor/も見る。時刻はmtimeではなくctimeを使う。
 * mtimeはtouch -dで書き換えられるが、ctimeはrootでなければ書き換えられないため。
 *
 * ■ 通常の更新の見分け方
 * 鍵のファイル（設定のfile_watch.release_file）のctimeを、気付いた分だけ全部残す。見えた鍵どうしの間が
 * 30分以内なら1つの更新の期間にまとめ、期間は最初の鍵の20分前から最後の鍵の15分後まで。前の余白が
 * 長いのは、監視の1回の間に鍵が2度更新されて、前の更新が見えないとき（最大で監視の間隔の分）を吸収するため。
 * 期間に入るctimeのファイルは、通常の更新として基準に取り込み、期間と一緒に記録する（1日1回の報告に載せる）。
 * 期間に入らない変化は、そのctimeから15分たつまで待つ。送る順番で、鍵が後から届くことがあるため。
 * たっても期間に入らなければ、改ざんとして知らせる。知らせたファイルは基準に取り込まず、直るまで知らせ続ける。
 *
 * ■ アップロードの置き場所
 * アップロードのたびにファイルが増えるので、基準とは比べない。代わりに毎回、公開の置き場所（storage/app/public。
 * /storage/のURLで外から開ける）に、次のファイルが無いかを確かめ、見つけたら待たずに知らせる。
 * - PHPとして動く拡張子と、.htaccess・.user.ini（置かれるとPHPを動かす設定にされるもの）
 * - アップロードの設定（upload.image_types・attach_types）と、設定のfile_watch.public_upload_extraに無い拡張子のもの。
 *   JavaScript・HTML・SVGなどを、一覧に書かずに全部拾うため。画面からのアップロードでは置かれ得ないので、あれば
 *   改ざんの疑いが強い
 * 非公開の置き場所（storage/app/private）は見ない。Webから直接開けないので、置かれても動かせないうえ、
 * Laravelが作った本人（apache）しか読めない権限（0700・0600）で作られ、この監視の利用者には開けないため。
 *
 * ■ 開けないディレクトリ
 * 監視する場所の中に開けないディレクトリがあっても、止まらずに飛ばす。改ざんを隠す場所になり得るので、
 * 黙っては飛ばさず、知らせる一覧に「確かめられなかったディレクトリ」として載せる。その下のファイルは、
 * 消えたものとしては扱わない。
 *
 * ■ 同時に動かない
 * 5分ごとの回と--fullの回が重なると、記録の書き込みで片方の結果が消える。記録の隣のロックのファイルを押さえ、
 * 前の回が動いている間は、次の回は何もせずに終わる（lock()）。
 *
 * ■ 限界
 * サーバーそのもの（root）を乗っ取られたら、同じサーバーの上の監視は信用できない。外から監視するサービスと組み合わせる。
 */
final class FileWatcher
{
    // ---- 記録 ----

    /** 基準などの記録のファイル（basePathの下） */
    private const STATE_FILE = 'bootstrap/cache/file-watch.json';

    /** 同時に動かないよう押さえる、ロックのファイル（basePathの下） */
    private const LOCK_FILE = 'bootstrap/cache/file-watch.lock';

    // ---- 通常の更新の見分け方（秒） ----

    /** 期間の、最初の鍵の前の余白。後の余白（15分）に監視の間隔（5分）を足したもの */
    private const BEFORE = 1200;

    /** 期間の、最後の鍵の後の余白 */
    private const AFTER = 900;

    /** 鍵どうしの間がこれ以内なら、1つの更新の期間にまとめる */
    private const KEY_GAP = 1800;

    /** 期間に入らない変化を、知らせるまで待つ長さ */
    private const WAIT = 900;

    /** 鍵の履歴を残す長さ。待っている変化の判断に要る分より長く */
    private const KEEP_KEYS = 86400 * 2;

    /** 通常の更新の記録を残す長さ。1日1回の報告に載せる分より長く */
    private const KEEP_ACCEPTED = 86400 * 8;

    // ---- 通知 ----

    /** 同じ顔ぶれの通知を送り直す間隔 */
    private const ALERT_INTERVAL = 3600;

    // ---- 監視する場所 ----

    /** 監視する場所（basePathの下）。公開ディレクトリは、public_path()から別に求める */
    public const DEFAULT_PATHS = [
        'app',
        'packages',
        'config',
        'routes',
        'bootstrap',
        'database',
        'resources',
        'lang',
        'code',
        'artisan',
        'composer.json',
        'composer.lock',
    ];

    /** 監視しない場所（basePathの下）。運用中に正しく変わるもの */
    private const EXCLUDE = ['bootstrap/cache'];

    /** 公開ディレクトリの中で、監視しないもの。アップロードの置き場所へのリンクと、開発のときの目印 */
    private const PUBLIC_EXCLUDE = ['storage', 'hot'];

    /** --fullのときだけ監視する場所 */
    private const FULL_ONLY = ['vendor'];

    /** アップロードの置き場所で見つけたら、すぐ知らせる拡張子。PHPとして動くもの */
    private const DANGER_EXTENSIONS = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'pht'];

    /** アップロードの置き場所で見つけたら、すぐ知らせるファイルの名前。置かれるとPHPを動かす設定にされるもの */
    private const DANGER_NAMES = ['.htaccess', '.user.ini'];

    /** 公開の置き場所で、受け付けていない拡張子でも知らせないファイルの名前。Laravelが最初から置くもの */
    private const PUBLIC_UPLOAD_IGNORE = ['.gitignore'];

    private readonly string $basePath;

    private readonly string $publicPath;

    private readonly string $publicUploadPath;

    private readonly Closure $ctime;

    /** 押さえているロックのファイル。押さえていなければnull */
    private mixed $lockHandle = null;

    /** この回で開けなかったディレクトリの名前 */
    private array $unreadable = [];

    /**
     * 場所とctimeの読み方は、テストで差し替える。手元のWindowsでは、ctimeがファイルを作った日時になるため。
     * ふだんは何も渡さない
     */
    public function __construct(
        ?string $basePath = null,
        ?string $publicPath = null,
        ?string $publicUploadPath = null,
        ?Closure $ctime = null,
    ) {
        $this->basePath = rtrim($basePath ?? base_path(), '/\\');
        $this->publicPath = rtrim($publicPath ?? public_path(), '/\\');
        $this->publicUploadPath = rtrim($publicUploadPath ?? storage_path('app/public'), '/\\');
        $this->ctime = $ctime ?? fn (string $path): int => (int) filectime($path);
    }

    // ---- 入口 ----

    /** 同時に動かないよう、ロックのファイルを押さえる。前の回が押さえていればfalse */
    public function lock(): bool
    {
        $path = $this->basePath.'/'.self::LOCK_FILE;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $handle = fopen($path, 'c');
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
     * 直前のinit()かcheck()で、開けなかったディレクトリの名前
     *
     * @return list<string>
     */
    public function unreadable(): array
    {
        return $this->unreadable;
    }

    /** 今の状態を基準にする。サーバーで最初に1回、webappで実行する。基準にしたファイルの数を返す */
    public function init(): int
    {
        $this->unreadable = [];
        $files = [];
        foreach ($this->scan(true) as $label => $info) {
            $files[$label] = $info + ['hash' => hash_file('sha256', $info['path'])];
            unset($files[$label]['path']);
        }

        $key = $this->keyCtime();
        $this->save([
            'files' => $files,
            'keys' => $key !== null ? [$key] : [],
            'deleted_seen' => [],
            'accepted' => [],
            'last_alert' => null,
        ]);

        return count($files);
    }

    /**
     * 1回分を確かめる。--fullなら全部のハッシュ値を比べ直し、vendor/も見る。
     * notifyがtrueなら、呼んだ側が通知のメールを送る（同じ顔ぶれは1時間に1回に間引く）。
     *
     * @return array{alerts: list<array{label: string, kind: string, time: int}>, dangers: list<array{label: string, time: int}>, unreadable: list<string>, accepted: int, pending: int, notify: bool}
     */
    public function check(bool $full = false): array
    {
        $this->unreadable = [];
        $state = $this->load();
        if ($state === null) {
            throw new RuntimeException('改ざん監視の基準がありません。先に php artisan app:watch-files --init を実行してください。');
        }
        $now = now()->getTimestamp();

        // 鍵のファイルのctimeを、気付いた分だけ残す
        $key = $this->keyCtime();
        if ($key !== null && ! in_array($key, $state['keys'], true)) {
            $state['keys'][] = $key;
        }
        $state['keys'] = array_values(array_filter($state['keys'], fn (int $time) => $time >= $now - self::KEEP_KEYS));
        sort($state['keys']);
        $periods = $this->periods($state['keys']);

        // 基準と比べて、変わった・増えた・消えたファイルを集める
        $candidates = [];
        $current = $this->scan($full);
        foreach ($current as $label => $info) {
            $base = $state['files'][$label] ?? null;
            if ($base === null) {
                $candidates[$label] = ['kind' => 'added', 'time' => $info['ctime'], 'info' => $info];

                continue;
            }
            if (! $full && $base['ctime'] === $info['ctime'] && $base['size'] === $info['size']) {
                continue;
            }

            // ハッシュ値が同じなら変化なし。ctimeと大きさだけを新しくする（権限だけを変えたときなど）
            $hash = hash_file('sha256', $info['path']);
            if ($hash === $base['hash']) {
                $state['files'][$label]['ctime'] = $info['ctime'];
                $state['files'][$label]['size'] = $info['size'];

                continue;
            }
            $candidates[$label] = ['kind' => 'changed', 'time' => $info['ctime'], 'info' => $info + ['hash' => $hash]];
        }

        // 消えたファイル。消えた時刻は分からないので、最初に気付いた時刻を使う。vendor/は--fullのときだけ見る。
        // 開けなかったディレクトリの下は、消えたのか見えないだけか分からないので、消えたものにしない
        foreach ($state['files'] as $label => $base) {
            if (isset($current[$label]) || (! $full && $this->isFullOnly($label)) || $this->isExcluded($label, $this->unreadable)) {
                continue;
            }
            $state['deleted_seen'][$label] ??= $now;
            $candidates[$label] = ['kind' => 'deleted', 'time' => $state['deleted_seen'][$label], 'info' => null];
        }
        $state['deleted_seen'] = array_intersect_key($state['deleted_seen'], array_filter($candidates, fn (array $c) => $c['kind'] === 'deleted'));

        // 通常の更新か、待つか、知らせるか
        $alerts = [];
        $accepted = 0;
        $pending = 0;
        foreach ($candidates as $label => $candidate) {
            $period = $this->periodOf($candidate['time'], $periods);

            if ($period !== null) {
                // 通常の更新。基準に取り込み、期間と一緒に記録する
                $this->accept($state, $label, $candidate, $period);
                $accepted++;
            } elseif ($now - $candidate['time'] < self::WAIT) {
                // 鍵が後から届くかもしれないので、まだ待つ
                $pending++;
            } else {
                $alerts[] = ['label' => $label, 'kind' => $candidate['kind'], 'time' => $candidate['time']];
            }
        }
        $state['accepted'] = array_values(array_filter($state['accepted'], fn (array $entry) => $entry['to'] >= $now - self::KEEP_ACCEPTED));

        $dangers = $this->dangers();
        sort($this->unreadable);
        $notify = $this->shouldNotify($state, $alerts, $dangers, $this->unreadable, $now);

        $this->save($state);

        return [
            'alerts' => $alerts,
            'dangers' => $dangers,
            'unreadable' => $this->unreadable,
            'accepted' => $accepted,
            'pending' => $pending,
            'notify' => $notify,
        ];
    }

    /**
     * $dateの日にかかる、通常の更新の期間ごとの一覧。1日1回の報告のメールに載せる。記録が無ければ空
     *
     * @return list<array{from: int, to: int, files: list<string>}>
     */
    public function acceptedOn(CarbonInterface $date): array
    {
        $state = $this->load();
        if ($state === null) {
            return [];
        }

        $from = $date->copy()->startOfDay()->getTimestamp();
        $to = $from + 86400;

        return array_values(array_filter($state['accepted'], fn (array $entry) => $entry['to'] >= $from && $entry['from'] < $to));
    }

    // ---- 判断 ----

    /**
     * 鍵のctimeの並びから、更新の期間の一覧を作る。鍵どうしの間が30分以内なら1つにまとめる。
     *
     * @param  list<int>  $keys  昇順
     * @return list<array{0: int, 1: int, 2: int}>  [期間の始め, 期間の終わり, 最初の鍵]
     */
    private function periods(array $keys): array
    {
        $periods = [];
        foreach ($keys as $key) {
            $last = array_key_last($periods);
            if ($last !== null && $key - ($periods[$last][1] - self::AFTER) <= self::KEY_GAP) {
                $periods[$last][1] = $key + self::AFTER;
            } else {
                $periods[] = [$key - self::BEFORE, $key + self::AFTER, $key];
            }
        }

        return $periods;
    }

    /** 時刻が入る、更新の期間。無ければnull */
    private function periodOf(int $time, array $periods): ?array
    {
        foreach ($periods as $period) {
            if ($time >= $period[0] && $time <= $period[1]) {
                return $period;
            }
        }

        return null;
    }

    /** 通常の更新を、基準に取り込み、期間の記録に足す */
    private function accept(array &$state, string $label, array $candidate, array $period): void
    {
        if ($candidate['kind'] === 'deleted') {
            unset($state['files'][$label], $state['deleted_seen'][$label]);
        } else {
            $info = $candidate['info'];
            $state['files'][$label] = [
                'ctime' => $info['ctime'],
                'size' => $info['size'],
                'hash' => $info['hash'] ?? hash_file('sha256', $info['path']),
            ];
        }

        // 期間の記録は、最初の鍵で見分ける。続けて更新すると、期間の終わりが延びる
        foreach ($state['accepted'] as $index => $entry) {
            if ($entry['key'] === $period[2]) {
                $state['accepted'][$index]['to'] = $period[1];
                $state['accepted'][$index]['files'][] = $label;

                return;
            }
        }
        $state['accepted'][] = ['key' => $period[2], 'from' => $period[0], 'to' => $period[1], 'files' => [$label]];
    }

    /**
     * 通知のメールを送るか。知らせるものが無ければ送らず、控えを消す。顔ぶれが変わればすぐ、同じなら1時間ごとに送る
     *
     * @param  list<array{label: string, kind: string, time: int}>  $alerts
     * @param  list<array{label: string, time: int}>  $dangers
     * @param  list<string>  $unreadable
     */
    private function shouldNotify(array &$state, array $alerts, array $dangers, array $unreadable, int $now): bool
    {
        if ($alerts === [] && $dangers === [] && $unreadable === []) {
            $state['last_alert'] = null;

            return false;
        }

        $signature = hash('sha256', json_encode([
            array_map(fn (array $alert) => $alert['kind'].':'.$alert['label'], $alerts),
            array_column($dangers, 'label'),
            $unreadable,
        ]));
        $last = $state['last_alert'];
        if ($last !== null && $last['signature'] === $signature && $now - $last['at'] < self::ALERT_INTERVAL) {
            return false;
        }

        $state['last_alert'] = ['signature' => $signature, 'at' => $now];

        return true;
    }

    // ---- ファイル ----

    /**
     * 監視するファイルの、名前 => パス・ctime・大きさ。名前は、basePathの下はその相対のパス、公開ディレクトリの下は
     * 「public/」から始まる相対のパス。シンボリックリンクはたどらない。
     *
     * @return array<string, array{path: string, ctime: int, size: int}>
     */
    private function scan(bool $full): array
    {
        $paths = (array) config('neobit.file_watch.paths', self::DEFAULT_PATHS);
        if ($full) {
            $paths = [...$paths, ...self::FULL_ONLY];
        }

        $files = [];
        foreach ($paths as $path) {
            $this->collect($this->basePath.'/'.$path, $path, self::EXCLUDE, $files);
        }
        $this->collect($this->publicPath, 'public', array_map(fn (string $name) => 'public/'.$name, self::PUBLIC_EXCLUDE), $files);
        ksort($files);

        return $files;
    }

    /** ファイルかディレクトリを、名前 => 中身にして$filesに足す。$excludeの名前とその下は飛ばす */
    private function collect(string $path, string $label, array $exclude, array &$files): void
    {
        if (is_link($path) || $this->isExcluded($label, $exclude)) {
            return;
        }
        if (is_file($path)) {
            $files[$label] = ['path' => $path, 'ctime' => ($this->ctime)($path), 'size' => (int) filesize($path)];

            return;
        }
        if (! is_dir($path)) {
            return;
        }

        foreach ($this->walk($path, $label, $exclude) as $child => $file) {
            $files[$child] = ['path' => $file, 'ctime' => ($this->ctime)($file), 'size' => (int) filesize($file)];
        }
    }

    /**
     * ディレクトリの下のファイルを、たどって集める。名前 => パス。シンボリックリンクはたどらず、$excludeの名前とその下は飛ばす。
     * 開けないディレクトリは、止まらずに飛ばし、名前を$this->unreadableに残す
     *
     * @return array<string, string>
     */
    private function walk(string $directory, string $label, array $exclude = []): array
    {
        $entries = @scandir($directory);
        if ($entries === false) {
            $this->unreadable[] = $label;

            return [];
        }

        $files = [];
        foreach ($entries as $entry) {
            $path = $directory.'/'.$entry;
            $child = $label.'/'.$entry;
            if ($entry === '.' || $entry === '..' || is_link($path) || $this->isExcluded($child, $exclude)) {
                continue;
            }

            if (is_dir($path)) {
                $files += $this->walk($path, $child, $exclude);
            } elseif (is_file($path)) {
                $files[$child] = $path;
            }
        }

        return $files;
    }

    /** 名前が、監視しない場所か、その下か */
    private function isExcluded(string $label, array $exclude): bool
    {
        foreach ($exclude as $prefix) {
            if ($label === $prefix || str_starts_with($label, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /** --fullのときだけ見る場所の名前か */
    private function isFullOnly(string $label): bool
    {
        return $this->isExcluded($label, self::FULL_ONLY);
    }

    /**
     * アップロードの置き場所に置かれた、受け付けていないファイル。名前と、置かれた時刻（ctime）。決まりはクラスの説明のとおり
     *
     * @return list<array{label: string, time: int}>
     */
    private function dangers(): array
    {
        if (! is_dir($this->publicUploadPath)) {
            return [];
        }

        // 公開の置き場所で受け付ける拡張子。アップロードの設定と、運用で置く分
        $allowed = array_map('strtolower', [
            ...(array) config('upload.image_types'),
            ...(array) config('upload.attach_types'),
            ...(array) config('neobit.file_watch.public_upload_extra'),
        ]);

        $dangers = [];
        foreach ($this->walk($this->publicUploadPath, 'storage/app/public') as $label => $file) {
            $name = basename($file);
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            // PHPとして動くものと、受け付けていない拡張子のもの
            $runnable = in_array($extension, self::DANGER_EXTENSIONS, true) || in_array($name, self::DANGER_NAMES, true);
            $unexpected = ! in_array($name, self::PUBLIC_UPLOAD_IGNORE, true) && ! in_array($extension, $allowed, true);
            if (! $runnable && ! $unexpected) {
                continue;
            }

            $dangers[] = ['label' => $label, 'time' => ($this->ctime)($file)];
        }
        usort($dangers, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return $dangers;
    }

    /** 鍵のファイルのctime。設定が空か、ファイルが無ければnull */
    private function keyCtime(): ?int
    {
        $name = (string) config('neobit.file_watch.release_file');
        $path = $this->basePath.'/'.$name;

        return $name !== '' && is_file($path) ? ($this->ctime)($path) : null;
    }

    // ---- 記録の読み書き ----

    /** 記録を読む。まだ基準が無ければnull */
    private function load(): ?array
    {
        $path = $this->basePath.'/'.self::STATE_FILE;
        if (! is_file($path)) {
            return null;
        }

        $state = json_decode((string) file_get_contents($path), true);

        return is_array($state) ? $state : null;
    }

    /** 記録を書く。読んでいる途中に半端なファイルを読まないよう、隣に書いてから置き換える */
    private function save(array $state): void
    {
        $path = $this->basePath.'/'.self::STATE_FILE;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path.'.tmp', json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        rename($path.'.tmp', $path);
    }
}
