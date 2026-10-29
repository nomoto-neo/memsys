<?php

namespace Neobit\Core\Security;

use Carbon\CarbonInterface;
use Closure;
use RuntimeException;

/**
 * 改ざん監視。通常の運用では変わらないファイルが変わった・増えた・消えたことと、
 * 許可されていないアップロードファイルを見つける。app:watch-filesコマンドが5分ごとに呼び、見つけたら通知のメールを送る。
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
 * ■ 監視する場所
 * - DEFAULT_PATHSの場所（app・packages・config・routesなど）と、公開ディレクトリ（public_path()）の全部のファイル
 * - プロジェクトの直下の全部のファイル（.env・artisan・composer.jsonなど）。中身まで見る。.envはapacheからは
 *   読めないが、この監視の利用者は読める。記録に残すのはハッシュ値だけ
 * - プロジェクトの直下のディレクトリは、名前が増えた・消えたことだけを見る
 * - bootstrap/cacheの設定などのキャッシュ（config.phpなど）。サーバーのLaravelが実際に読むのは.envではなく
 *   ここなので、書き換えられるとすぐに効くため。デプロイの外でconfig:cacheをやり直したときは、鍵のファイルも更新する
 * 外すのは、この監視の記録とロックのファイル、鍵のファイル、テストでできるファイル。
 *
 * ■ 手で認める
 * 鍵を送り忘れた更新は、後から鍵だけを送っても期間に入らず、知らせ続ける（消えたファイルは送り直しでも直せない）。
 * 中身を確かめた人がacceptAlerts()（app:watch-files --accept）で認めると、そのとき知らせるファイルだけを基準に
 * 取り込み、「手で認めた更新」として記録に残す。待っている最中の変化は取り込まない。init()で基準を作り直すと、
 * それも黙って取り込み、通常の更新の記録も消えるため。
 *
 * ■ 管理画面の「改ざん監視」
 * 管理画面は、開くたびにinspect()でその場の状況を出す。apache（Web）で動くので記録は書かず、中身を読めない
 * ファイル（.envなど）は、最後のcronの結果（記録のlast_result）を使う。
 * 承認を押すと、承認の目印（storageの下のapproval.json）を置く（requestApproval()）。記録はapacheが書けないので、
 * 毎分のcron（app:watch-files --approve）がapplyApproval()で拾い、目印の一覧のうち、まだ疑いか判断待ちのものだけを
 * 基準に取り込む。承認した人の名前は、通常の更新の記録に残し、1日1回の報告と画面に出す。
 *
 * ■ アップロードの置き場所
 * アップロードのたびにファイルが増えるので、基準とは比べない。代わりに毎回、公開の置き場所（storage/app/public。
 * /storage/のURLで外から開ける）に、次のファイルが無いかを確かめ、見つけたら待たずに知らせる。
 * - PHPとして動く拡張子のもの
 * - アップロードの設定（upload.image_types・attach_types）と、設定のfile_watch.public_upload_extraに無い拡張子のもの。
 *   JavaScript・HTML・SVGなどを、一覧に書かずに全部拾うため。画面からのアップロードでは置かれ得ないので、あれば
 *   改ざんの疑いが強い
 * .htaccess・.user.ini（置かれるとPHPを動かす設定にされるもの）だけは、ほかのコードと同じく基準と比べる。
 * プラグインなどが置く正しいものもあるので、増えた・変わった・消えたときに、通常の更新か改ざんかを見分けて知らせる。
 * 前の版の基準には入っていないので、最初の回だけは知らせずに基準に取り込む。
 * WordPressを置く案件（設定のneobit.wordpressがある）では、WordPressのアップロードの置き場所（upload_path）も
 * 同じに扱い、基準と比べる走査から外す。外さないと、画像を上げるたびに追加として知らせるため。
 * 設定のwatch_excludeの場所（キャッシュのプラグインが書く場所など）は、何も見ない。前の基準に入っていた
 * ファイルは、消えたものとしては知らせず、黙って基準から外す。
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

    /** 管理画面で承認した目印（storageの下）。管理画面（apache）が書き、cron（devapp）が拾って消す */
    private const APPROVAL_FILE = 'app/file-watch/approval.json';

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
    ];

    /** 監視しないもの（basePathの下）。この監視の記録とロックのファイル */
    private const EXCLUDE = [
        'bootstrap/cache/file-watch.json',
        'bootstrap/cache/file-watch.json.tmp',
        'bootstrap/cache/file-watch.lock',
    ];

    /** プロジェクトの直下で、監視しないもの。サーバーでテストを動かしたときにできる */
    private const ROOT_IGNORE = ['.phpunit.cache', '.phpunit.result.cache'];

    /** 公開ディレクトリの中で、監視しないもの。アップロードの置き場所へのリンクと、開発のときの目印 */
    private const PUBLIC_EXCLUDE = ['storage', 'hot'];

    /** --fullのときだけ監視する場所 */
    private const FULL_ONLY = ['vendor'];

    /** アップロードの置き場所で見つけたら、すぐ知らせる拡張子。PHPとして動くもの */
    private const DANGER_EXTENSIONS = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'pht'];

    /** アップロードの置き場所で、基準と比べるファイルの名前。置かれるとPHPを動かす設定にされるもの */
    private const UPLOAD_CONFIG_NAMES = ['.htaccess', '.user.ini'];

    /** 公開の置き場所で、許可されていない拡張子でも知らせないファイルの名前。Laravelが最初から置くもの */
    private const PUBLIC_UPLOAD_IGNORE = ['.gitignore'];

    private readonly string $basePath;

    private readonly string $publicPath;

    private readonly string $publicUploadPath;

    private readonly Closure $ctime;

    /** 押さえているロックのファイル。押さえていなければnull */
    private mixed $lockHandle = null;

    /** この回で開けなかったディレクトリの名前 */
    private array $unreadable = [];

    /** この回で集めた、アップロードの置き場所のファイル。名前 => パス。まだ集めていなければnull */
    private ?array $uploadFiles = null;

    /** 記録を書かずに確かめているか（inspect()）。apacheで動くので、中身を読めないファイルがある */
    private bool $readOnly = false;

    /** 記録を書かずに確かめた回で、中身を読めなかったファイルの名前 */
    private array $unhashable = [];

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
        $this->uploadFiles = null;
        $files = [];
        foreach ($this->scan(true) as $label => $info) {
            $files[$label] = $info + ['hash' => hash_file('sha256', $info['path'])];
            unset($files[$label]['path']);
        }

        $key = $this->keyCtime();
        $this->save([
            'files' => $files,
            'root_dirs' => $this->rootDirectories(),
            'upload_config' => true,
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
        $this->uploadFiles = null;
        $state = $this->loadBaseline();
        $now = now()->getTimestamp();

        $sorted = $this->sort($state, $full, $now);

        $dangers = $this->dangers();
        sort($this->unreadable);
        $notify = $this->shouldNotify($state, $sorted['alerts'], $dangers, $this->unreadable, $now);

        // 管理画面が、中身を読めないファイルの分類に使う
        $state['last_result'] = $this->result($sorted['alerts'], $sorted['pendingList'], $dangers, $now);

        $this->save($state);

        return [
            'alerts' => $sorted['alerts'],
            'dangers' => $dangers,
            'unreadable' => $this->unreadable,
            'accepted' => $sorted['accepted'],
            'pending' => $sorted['pending'],
            'notify' => $notify,
        ];
    }

    /**
     * 今知らせるファイルを、手で認めて基準に取り込む。app:watch-files --acceptが呼ぶ。
     * その場で確かめ直し、知らせるものがあれば$confirmに一覧を渡して聞く。認められたら取り込んで、その一覧を返す。
     * 知らせるものが無ければ空、断られたらnull。どちらも基準は変えず、通知の控えもそのままにする。
     * 待っている最中の変化・許可されていないアップロードファイル・確かめられなかったディレクトリは取り込まない。
     *
     * @param  Closure(list<array{label: string, kind: string, time: int}>): bool  $confirm
     * @return list<array{label: string, kind: string, time: int}>|null
     */
    public function acceptAlerts(Closure $confirm): ?array
    {
        $this->unreadable = [];
        $this->uploadFiles = null;
        $state = $this->loadBaseline();
        $now = now()->getTimestamp();

        // 通常の更新の期間に入るものは、いつもどおり取り込む
        $sorted = $this->sort($state, false, $now);

        // 知らせるものが無いか、断られたら、疑いは取り込まずに終わる
        if ($sorted['alerts'] === [] || ! $confirm($sorted['alerts'])) {
            $this->save($state);

            return $sorted['alerts'] === [] ? [] : null;
        }

        // 疑いを基準に取り込み、手で認めた更新として記録する
        foreach ($sorted['alertCandidates'] as $label => $candidate) {
            $this->absorb($state, $label, $candidate);
        }
        $state['accepted'][] = ['manual' => true, 'by' => null, 'from' => $now, 'to' => $now, 'files' => array_column($sorted['alerts'], 'label')];

        // 通知の控えを消し、次に別の疑いが出たらすぐ知らせる
        $state['last_alert'] = null;

        $this->save($state);

        return $sorted['alerts'];
    }

    /**
     * 記録を書かずに、その場の状況を確かめる。管理画面（apache）が開くたびに呼ぶ。基準がまだ無ければnull。
     * apacheが中身を読めないファイル（.envなど）は、最後のcronの結果（last_result）の分類を使う。
     *
     * @return array{at: int, alerts: list<array{label: string, kind: string, time: int}>, pending: list<array{label: string, kind: string, time: int}>, dangers: list<array{label: string, time: int}>, unreadable: list<string>}|null
     */
    public function inspect(): ?array
    {
        $this->unreadable = [];
        $this->uploadFiles = null;
        $this->unhashable = [];
        $state = $this->load();
        if ($state === null) {
            return null;
        }
        $now = now()->getTimestamp();
        $last = $state['last_result'] ?? [];

        // 記録の写しで比べて分け、記録は書かない
        $this->readOnly = true;
        try {
            $sorted = $this->sort($state, false, $now);
            $dangers = $this->dangers();
        } finally {
            $this->readOnly = false;
        }

        // 中身を読めなかったファイルは、最後のcronの分類に従う
        $alerts = $sorted['alerts'];
        $pending = $sorted['pendingList'];
        foreach ((array) ($last['alerts'] ?? []) as $entry) {
            if (in_array($entry['label'], $this->unhashable, true)) {
                $alerts[] = $entry;
            }
        }
        foreach ((array) ($last['pending'] ?? []) as $entry) {
            if (in_array($entry['label'], $this->unhashable, true)) {
                $pending[] = $entry;
            }
        }
        sort($this->unreadable);

        return $this->result($alerts, $pending, $dangers, $now);
    }

    /**
     * 承認する一覧のしるし。管理画面が、画面に出した一覧と、押したときの一覧が同じかを確かめるのに使う。
     * 一覧をそのまま送ると、ファイルが多いとき（WordPressの更新など）にPHPの受け取れる数（max_input_vars）を超えて、
     * 黙って一部だけになるため
     *
     * @param  list<array{label: string, kind: string, time: int}>  $items
     */
    public static function signature(array $items): string
    {
        $keys = array_map(fn (array $item) => $item['kind'].':'.$item['label'], $items);
        sort($keys);

        return hash('sha256', implode("\n", $keys));
    }

    /**
     * 管理画面で承認した一覧を、承認の目印に書く。次の毎分のcron（applyApproval()）が拾う。前の目印があれば上書きする
     *
     * @param  list<array{label: string, kind: string, time: int}>  $items
     */
    public function requestApproval(array $items, string $by): void
    {
        $path = storage_path(self::APPROVAL_FILE);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $approval = ['by' => $by, 'at' => now()->getTimestamp(), 'items' => array_values($items)];
        file_put_contents($path.'.tmp', json_encode($approval, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        rename($path.'.tmp', $path);
    }

    /**
     * まだcronが拾っていない承認の目印。無ければnull
     *
     * @return array{by: string, at: int, items: list<array{label: string, kind: string, time: int}>}|null
     */
    public function pendingApproval(): ?array
    {
        $json = @file_get_contents(storage_path(self::APPROVAL_FILE));
        $approval = $json === false ? null : json_decode($json, true);

        return is_array($approval) && isset($approval['by'], $approval['at'], $approval['items']) ? $approval : null;
    }

    /**
     * 承認の目印を拾って、基準に取り込む。app:watch-files --approveが毎分呼ぶ。目印が無ければnull。
     * 目印の一覧のうち、今も同じ種類の改ざんの疑いか判断待ちのものだけを取り込む。承認の後に増えた変化や、
     * 一覧に無い変化は取り込まない。取り込んだら目印を消し、取り込んだ一覧と承認した人を返す。
     *
     * @return array{by: string, requested_at: int, items: list<array{label: string, kind: string, time: int}>}|null
     */
    public function applyApproval(): ?array
    {
        $approval = $this->pendingApproval();
        if ($approval === null) {
            return null;
        }

        $this->unreadable = [];
        $this->uploadFiles = null;
        $state = $this->loadBaseline();
        $now = now()->getTimestamp();
        $sorted = $this->sort($state, false, $now);

        // 承認した一覧に、種類も同じで入っているものだけを取り込む
        $requested = array_map(fn (array $item) => $item['kind'].':'.$item['label'], $approval['items']);
        $approved = [];
        foreach ($sorted['alertCandidates'] + $sorted['pendingCandidates'] as $label => $candidate) {
            if (in_array($candidate['kind'].':'.$label, $requested, true)) {
                $this->absorb($state, $label, $candidate);
                $approved[] = ['label' => $label, 'kind' => $candidate['kind'], 'time' => $candidate['time']];
            }
        }

        // 承認した人の名前と一緒に記録し、通知の控えを消して、次に別の疑いが出たらすぐ知らせる
        if ($approved !== []) {
            $state['accepted'][] = ['manual' => true, 'by' => (string) $approval['by'], 'from' => $now, 'to' => $now, 'files' => array_column($approved, 'label')];
            $state['last_alert'] = null;
        }

        // 管理画面のための、この回の結果。取り込んだものは外す
        $approvedLabels = array_column($approved, 'label');
        $remaining = fn (array $entries) => array_values(array_filter($entries, fn (array $entry) => ! in_array($entry['label'], $approvedLabels, true)));
        $dangers = $this->dangers();
        sort($this->unreadable);
        $state['last_result'] = $this->result($remaining($sorted['alerts']), $remaining($sorted['pendingList']), $dangers, $now);

        $this->save($state);
        @unlink(storage_path(self::APPROVAL_FILE));

        return ['by' => (string) $approval['by'], 'requested_at' => (int) $approval['at'], 'items' => $approved];
    }

    /**
     * 記録にある、取り込んだ更新の全部（8日分）。新しい順。管理画面の「最近取り込んだ更新」に出す。
     * 手で認めた更新はmanualがtrueで、管理画面で承認したものはbyに承認した人の名前、サーバーで認めたものはnull
     *
     * @return list<array{from: int, to: int, files: list<string>, manual?: bool, by?: string|null}>
     */
    public function recentlyAccepted(): array
    {
        $entries = (array) ($this->load()['accepted'] ?? []);
        usort($entries, fn (array $a, array $b) => $b['from'] <=> $a['from']);

        return $entries;
    }

    /**
     * $dateの日にかかる、通常の更新の期間ごとの一覧。手で認めた更新はmanualがtrue。1日1回の報告のメールに載せる。記録が無ければ空
     *
     * @return list<array{from: int, to: int, files: list<string>, manual?: bool}>
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
     * 基準と比べて、変わった・増えた・消えたファイルを、通常の更新・待つ・知らせるに分ける。
     * 通常の更新は、ここで基準に取り込む。知らせるものと待つものは、手で認めて取り込めるよう、比べた中身も返す。
     *
     * @return array{alerts: list<array{label: string, kind: string, time: int}>, alertCandidates: array<string, array>, pendingList: list<array{label: string, kind: string, time: int}>, pendingCandidates: array<string, array>, accepted: int, pending: int}
     */
    private function sort(array &$state, bool $full, int $now): array
    {
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

            // ハッシュ値が同じなら変化なし。ctimeと大きさだけを新しくする（権限だけを変えたときなど）。
            // 記録を書かずに確かめているとき、中身を読めないファイルは、呼んだ側が最後のcronの結果を使う
            $hash = $this->hashOf($info['path']);
            if ($hash === false) {
                $this->unhashable[] = $label;

                continue;
            }
            if ($hash === $base['hash']) {
                $state['files'][$label]['ctime'] = $info['ctime'];
                $state['files'][$label]['size'] = $info['size'];

                continue;
            }
            $candidates[$label] = ['kind' => 'changed', 'time' => $info['ctime'], 'info' => $info + ['hash' => $hash]];
        }

        // アップロードの置き場所の.htaccessなどは、前の版の基準に入っていないので、最初の回だけは知らせずに基準に取り込む
        if (! isset($state['upload_config'])) {
            foreach ($candidates as $label => $candidate) {
                if ($candidate['kind'] === 'added' && $this->isUploadConfig($label)) {
                    $this->absorb($state, $label, $candidate);
                    unset($candidates[$label]);
                }
            }
            $state['upload_config'] = true;
        }

        // WordPressのアップロードの置き場所などの見ない場所は、前の基準に入っていても、消えたものにせず黙って外す
        foreach (array_keys($state['files']) as $label) {
            if ($this->isExcluded($label, $this->wordpressIgnored()) && ! $this->isUploadConfig($label)) {
                unset($state['files'][$label]);
            }
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

        // 直下のディレクトリは、名前が増えた・消えたことだけを見る。名前は末尾に/を付けて、ファイルと分ける。
        // 前の版の基準で直下の一覧が無ければ、今の一覧を知らせずに基準にする
        $rootDirectories = $this->rootDirectories();
        $state['root_dirs'] ??= $rootDirectories;
        foreach (array_diff($rootDirectories, $state['root_dirs']) as $name) {
            $candidates[$name.'/'] = ['kind' => 'added', 'time' => ($this->ctime)($this->basePath.'/'.$name), 'info' => null, 'directory' => $name];
        }
        foreach (array_diff($state['root_dirs'], $rootDirectories) as $name) {
            $state['deleted_seen'][$name.'/'] ??= $now;
            $candidates[$name.'/'] = ['kind' => 'deleted', 'time' => $state['deleted_seen'][$name.'/'], 'info' => null, 'directory' => $name];
        }
        $state['deleted_seen'] = array_intersect_key($state['deleted_seen'], array_filter($candidates, fn (array $c) => $c['kind'] === 'deleted'));

        // 通常の更新か、待つか、知らせるか
        $alerts = [];
        $alertCandidates = [];
        $pendingList = [];
        $pendingCandidates = [];
        $accepted = 0;
        foreach ($candidates as $label => $candidate) {
            $period = $this->periodOf($candidate['time'], $periods);

            if ($period !== null) {
                // 通常の更新。基準に取り込み、期間と一緒に記録する
                $this->accept($state, $label, $candidate, $period);
                $accepted++;
            } elseif ($now - $candidate['time'] < self::WAIT) {
                // 鍵が後から届くかもしれないので、まだ待つ
                $pendingList[] = ['label' => $label, 'kind' => $candidate['kind'], 'time' => $candidate['time']];
                $pendingCandidates[$label] = $candidate;
            } else {
                $alerts[] = ['label' => $label, 'kind' => $candidate['kind'], 'time' => $candidate['time']];
                $alertCandidates[$label] = $candidate;
            }
        }
        $state['accepted'] = array_values(array_filter($state['accepted'], fn (array $entry) => $entry['to'] >= $now - self::KEEP_ACCEPTED));

        return [
            'alerts' => $alerts,
            'alertCandidates' => $alertCandidates,
            'pendingList' => $pendingList,
            'pendingCandidates' => $pendingCandidates,
            'accepted' => $accepted,
            'pending' => count($pendingList),
        ];
    }

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
        $this->absorb($state, $label, $candidate);

        // 期間の記録は、最初の鍵で見分ける。続けて更新すると、期間の終わりが延びる。手で認めた記録には鍵が無い
        foreach ($state['accepted'] as $index => $entry) {
            if (($entry['key'] ?? null) === $period[2]) {
                $state['accepted'][$index]['to'] = $period[1];
                $state['accepted'][$index]['files'][] = $label;

                return;
            }
        }
        $state['accepted'][] = ['key' => $period[2], 'from' => $period[0], 'to' => $period[1], 'files' => [$label]];
    }

    /** 変わった・増えた・消えたファイルを、基準に取り込む */
    private function absorb(array &$state, string $label, array $candidate): void
    {
        if (isset($candidate['directory'])) {
            // 直下のディレクトリは、名前の一覧を直す
            $state['root_dirs'] = $candidate['kind'] === 'deleted'
                ? array_values(array_diff($state['root_dirs'], [$candidate['directory']]))
                : [...$state['root_dirs'], $candidate['directory']];
            unset($state['deleted_seen'][$label]);
        } elseif ($candidate['kind'] === 'deleted') {
            unset($state['files'][$label], $state['deleted_seen'][$label]);
        } else {
            $info = $candidate['info'];
            $state['files'][$label] = [
                'ctime' => $info['ctime'],
                'size' => $info['size'],
                'hash' => $info['hash'] ?? $this->hashOf($info['path']),
            ];
        }
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
            $this->collect($this->basePath.'/'.$path, $path, [...self::EXCLUDE, ...$this->wordpressIgnored()], $files);
        }
        $publicExclude = array_map(fn (string $name) => 'public/'.$name, self::PUBLIC_EXCLUDE);
        $this->collect($this->publicPath, 'public', [...$publicExclude, ...$this->wordpressIgnored()], $files);

        // 直下のファイル。中身まで見る
        foreach ($this->rootEntries() as $name => $isDirectory) {
            if (! $isDirectory) {
                $this->collect($this->basePath.'/'.$name, $name, $this->wordpressIgnored(), $files);
            }
        }

        // アップロードの置き場所の.htaccessなど。PHPを動かす設定にされるので、中身を基準と比べる
        foreach ($this->uploadFiles() as $label => $file) {
            if ($this->isUploadConfig($label)) {
                $files[$label] = ['path' => $file, 'ctime' => ($this->ctime)($file), 'size' => (int) filesize($file)];
            }
        }
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

    /**
     * プロジェクトの直下の名前 => ディレクトリか。シンボリックリンク・鍵のファイル・テストでできるものは外す。
     * 直下を開けなければ、空にして$this->unreadableに残す
     *
     * @return array<string, bool>
     */
    private function rootEntries(): array
    {
        $entries = @scandir($this->basePath);
        if ($entries === false) {
            $this->unreadable[] = '.';

            return [];
        }

        $ignore = [...self::ROOT_IGNORE, (string) config('neobit.file_watch.release_file')];
        $result = [];
        foreach ($entries as $entry) {
            $path = $this->basePath.'/'.$entry;
            if ($entry === '.' || $entry === '..' || in_array($entry, $ignore, true) || is_link($path)) {
                continue;
            }
            $result[$entry] = is_dir($path);
        }

        return $result;
    }

    /**
     * プロジェクトの直下のディレクトリの名前
     *
     * @return list<string>
     */
    private function rootDirectories(): array
    {
        $directories = array_keys(array_filter($this->rootEntries()));
        sort($directories);

        return $directories;
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
     * 許可されていないアップロードファイル。名前と、置かれた時刻（ctime）。決まりはクラスの説明のとおり
     *
     * @return list<array{label: string, time: int}>
     */
    private function dangers(): array
    {
        // 公開の置き場所で受け付ける拡張子。アップロードの設定と、運用で置く分
        $allowed = array_map('strtolower', [
            ...(array) config('upload.image_types'),
            ...(array) config('upload.attach_types'),
            ...(array) config('neobit.file_watch.public_upload_extra'),
        ]);

        $dangers = [];
        foreach ($this->uploadFiles() as $label => $file) {
            // .htaccessなどは、基準と比べる（scan()）
            if ($this->isUploadConfig($label)) {
                continue;
            }

            $name = basename($file);
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            // PHPとして動くものと、許可されていない拡張子のもの
            $runnable = in_array($extension, self::DANGER_EXTENSIONS, true);
            $unexpected = ! in_array($name, self::PUBLIC_UPLOAD_IGNORE, true) && ! in_array($extension, $allowed, true);
            if (! $runnable && ! $unexpected) {
                continue;
            }

            $dangers[] = ['label' => $label, 'time' => ($this->ctime)($file)];
        }
        usort($dangers, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return $dangers;
    }

    /**
     * アップロードの置き場所のファイル。名前 => パス。公開の置き場所（storage/app/public）と、WordPressのアップロードの
     * 置き場所。watch_excludeの場所は外す。scan()とdangers()の両方で使うので、1回の確かめで1回だけたどる
     *
     * @return array<string, string>
     */
    private function uploadFiles(): array
    {
        if ($this->uploadFiles !== null) {
            return $this->uploadFiles;
        }

        $places = ['storage/app/public' => $this->publicUploadPath];
        $wordpressUpload = $this->wordpressUploadPath();
        if ($wordpressUpload !== null) {
            $places[$wordpressUpload] = $this->basePath.'/'.$wordpressUpload;
        }

        $this->uploadFiles = [];
        foreach ($places as $label => $path) {
            if (is_dir($path)) {
                $this->uploadFiles += $this->walk($path, $label, $this->wordpressExcluded());
            }
        }

        return $this->uploadFiles;
    }

    /** アップロードの置き場所の、基準と比べる.htaccessなどの名前か */
    private function isUploadConfig(string $label): bool
    {
        if (! in_array(basename($label), self::UPLOAD_CONFIG_NAMES, true)) {
            return false;
        }

        return $this->isExcluded($label, array_filter(['storage/app/public', $this->wordpressUploadPath()]));
    }

    /** WordPressのアップロードの置き場所（basePathからのパス）。WordPressを置かないか、設定が空ならnull */
    private function wordpressUploadPath(): ?string
    {
        $path = trim((string) config('neobit.wordpress.upload_path'), '/');

        return is_array(config('neobit.wordpress')) && $path !== '' ? $path : null;
    }

    /**
     * WordPressを置くときに、基準と比べる走査から外す場所（basePathからのパス）。アップロードの置き場所とwatch_exclude
     *
     * @return list<string>
     */
    private function wordpressIgnored(): array
    {
        return array_values(array_filter([$this->wordpressUploadPath(), ...$this->wordpressExcluded()], fn (?string $path) => $path !== null));
    }

    /**
     * 設定のwatch_excludeの場所（basePathからのパス）。基準とも、許可されていないアップロードファイルの確かめとも比べない。
     * キャッシュのプラグインが書く場所など、何が置かれるかを決められない場所に使う
     *
     * @return list<string>
     */
    private function wordpressExcluded(): array
    {
        if (! is_array(config('neobit.wordpress'))) {
            return [];
        }

        $paths = array_map(fn ($path) => trim((string) $path, '/'), (array) config('neobit.wordpress.watch_exclude'));

        return array_values(array_filter($paths, fn (string $path) => $path !== ''));
    }

    /**
     * ファイルのハッシュ値。記録を書かずに確かめているときは、読めなければfalse（apacheが読めない.envなど）。
     * cronで読めないときは、今までどおり例外にして気付けるようにする
     */
    private function hashOf(string $path): string|false
    {
        return $this->readOnly ? @hash_file('sha256', $path) : hash_file('sha256', $path);
    }

    /**
     * 1回の確かめの結果。記録のlast_resultと、inspect()が返す形
     *
     * @return array{at: int, alerts: list<array{label: string, kind: string, time: int}>, pending: list<array{label: string, kind: string, time: int}>, dangers: list<array{label: string, time: int}>, unreadable: list<string>}
     */
    private function result(array $alerts, array $pending, array $dangers, int $now): array
    {
        return ['at' => $now, 'alerts' => $alerts, 'pending' => $pending, 'dangers' => $dangers, 'unreadable' => $this->unreadable];
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

    /** 確かめに使う記録を読む。まだ基準が無ければ、--initを案内する例外 */
    private function loadBaseline(): array
    {
        return $this->load() ?? throw new RuntimeException('改ざん監視の基準がありません。先に php artisan app:watch-files --init を実行してください。');
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
