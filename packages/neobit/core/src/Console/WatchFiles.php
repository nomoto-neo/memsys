<?php

namespace Neobit\Core\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Neobit\Core\Mail\MailTemplateException;
use Neobit\Core\Mail\TemplatedMail;
use Neobit\Core\Security\FileWatcher;
use RuntimeException;
use Throwable;

/**
 * 改ざん監視のコマンド。php artisan app:watch-filesで動く。判断はNeobit\Core\Security\FileWatcherにあり、
 * このコマンドは、知らせるものがあれば通知のメールを送るだけ。
 *
 * Laravelのスケジューラー（apacheのcron）には入れず、コードを更新する利用者（webapp）のcrontabから、
 * 5分ごとと、1日1回の--fullで動かす。記録をその利用者だけが書けるようにするため。
 * 宛先は、設定のfile_watch.toか、無ければエラーの通知と同じ宛先（.envのERROR_NOTIFY_TO）。
 * 鍵のファイルの設定（file_watch.release_file）が空なら、何もしない。
 *
 * ■ 手で動かすとき
 * --init  今の状態を基準にする。サーバーで最初に1回、webappで実行する
 * --full  全部のハッシュ値を比べ直し、vendor/も見る
 * --accept  今知らせているファイルを、中身を確かめた上で基準に取り込む。一覧を出して確認してから取り込む。devappで実行する
 *
 * ■ 管理画面の承認
 * --approve  管理画面の「改ざん監視」で承認した変化を取り込み、承認のメールを送る。devappのcrontabから毎分動かす。
 *            承認の目印が無ければ、ロックも押さえずにすぐ終わる。5分ごとの回でも、目印があれば先に取り込むので、
 *            毎分の行は承認を早く取り込むためのもの。無くても5分以内には取り込む
 */
#[Signature('app:watch-files {--init : 今の状態を基準にする} {--full : 全部のハッシュ値を比べ直し、vendor/も見る} {--accept : 今知らせているファイルを、確かめて基準に取り込む} {--approve : 管理画面で承認した変化を取り込む}')]
#[Description('ファイルの改ざんを確かめ、見つけたら通知のメールを送る')]
class WatchFiles extends Command
{
    /** メールに並べる、種類ごとの見出し */
    private const KIND_LABELS = [
        'changed' => '変更',
        'added' => '追加',
        'deleted' => '削除',
    ];

    public function handle(): int
    {
        if ((string) config('neobit.file_watch.release_file') === '') {
            $this->info('鍵のファイル（file_watch.release_file）が設定されていないので、改ざん監視はしません。');

            return self::SUCCESS;
        }

        $watcher = app(FileWatcher::class);

        // 管理画面の承認は毎分動くので、承認の目印が無ければ、ロックも押さえずにすぐ終わる
        if ($this->option('approve') && $watcher->pendingApproval() === null) {
            return self::SUCCESS;
        }

        // 前の回が動いている間は、何もせずに終わる（5分ごとの回と--fullの回が重なったとき）。承認は次の分に拾う
        if (! $watcher->lock()) {
            $this->info('前の回が動いているので、何もせずに終わります。');

            return self::SUCCESS;
        }

        try {
            return $this->watch($watcher);
        } finally {
            $watcher->unlock();
        }
    }

    /** ロックを押さえた後の1回分。基準を作るか、知らせているファイルを取り込むか、確かめて知らせる */
    private function watch(FileWatcher $watcher): int
    {
        // 基準を作る
        if ($this->option('init')) {
            $count = $watcher->init();
            $this->info(number_format($count).'件のファイルを、改ざん監視の基準にしました。');
            foreach ($watcher->unreadable() as $directory) {
                $this->warn('開けないので、基準に入れられなかったディレクトリ：'.$directory);
            }

            return self::SUCCESS;
        }

        try {
            // 管理画面で承認した変化を取り込む
            if ($this->option('approve')) {
                return $this->approve($watcher);
            }

            // 知らせているファイルを、手で認めて取り込む
            if ($this->option('accept')) {
                return $this->accept($watcher);
            }

            // 管理画面の承認があれば、先に取り込む（毎分の行が無いサーバーでも取り込めるように）
            if ($watcher->pendingApproval() !== null) {
                $this->approve($watcher);
            }

            $result = $watcher->check((bool) $this->option('full'));
        } catch (RuntimeException $e) {
            // 基準が無い。サーバーを置いた後に、--initを忘れている
            Log::warning('WatchFiles: '.$e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // 件数を1項目1行で出す。見出しの短い順に並べて、見比べやすくする
        $this->info('判断待ち：'.$result['pending'].'件');
        $this->info('通常の更新：'.$result['accepted'].'件');
        $this->info('改ざんの疑い：'.count($result['alerts']).'件');
        $this->info('確かめられなかったディレクトリ：'.count($result['unreadable']).'件');
        $this->info('許可されていないアップロードファイル：'.count($result['dangers']).'件');

        if (! $result['notify']) {
            return self::SUCCESS;
        }

        return $this->notify($result['alerts'], $result['dangers'], $result['unreadable']);
    }

    /** 知らせているファイルの一覧を出し、確認できたら基準に取り込む。取り込んだらログに残す */
    private function accept(FileWatcher $watcher): int
    {
        $accepted = $watcher->acceptAlerts(function (array $alerts): bool {
            // メールと同じく、変更・追加・削除の順に並べる
            $kinds = array_keys(self::KIND_LABELS);
            usort($alerts, fn (array $a, array $b) => array_search($a['kind'], $kinds, true) <=> array_search($b['kind'], $kinds, true));

            $this->info('改ざんの疑い：'.count($alerts).'件');
            foreach ($alerts as $alert) {
                $this->line('  '.self::KIND_LABELS[$alert['kind']].'  '.$alert['label'].'（'.date('Y-m-d H:i', $alert['time']).'）');
            }

            return $this->confirm('この'.count($alerts).'件を、通常の更新として基準に取り込みますか？');
        });

        if ($accepted === []) {
            $this->info('取り込む改ざんの疑いはありません。');
        } elseif ($accepted === null) {
            $this->info('取り込みませんでした。');
        } else {
            Log::info('WatchFiles: 改ざんの疑いを、手で認めて基準に取り込みました。', ['files' => array_column($accepted, 'label')]);
            $this->info(count($accepted).'件を基準に取り込みました。');
        }

        return self::SUCCESS;
    }

    /** 管理画面で承認した変化を取り込み、承認のメールを送る。取り込んだものが無ければ、メールは送らない */
    private function approve(FileWatcher $watcher): int
    {
        $approval = $watcher->applyApproval();
        if ($approval === null || $approval['items'] === []) {
            $this->info('取り込む承認はありません。');

            return self::SUCCESS;
        }

        $labels = array_column($approval['items'], 'label');
        Log::info("WatchFiles: {$approval['by']}さんが管理画面で承認した変更内容を、基準に取り込みました。", ['files' => $labels]);
        $this->info(count($labels).'件を基準に取り込みました。');

        $recipients = $this->recipients();
        if ($recipients === []) {
            $this->warn('宛先（file_watch.to・ERROR_NOTIFY_TO）が設定されていないので、メールは送りません。');

            return self::SUCCESS;
        }

        // 種類ごとの一覧
        $lists = [];
        foreach (array_keys(self::KIND_LABELS) as $kind) {
            $lists[$kind] = $this->lines(array_values(array_filter($approval['items'], fn (array $item) => $item['kind'] === $kind)));
        }

        try {
            Mail::send(new TemplatedMail('file_watch_approved', [
                'from_mail' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to' => implode(', ', $recipients),
                'app_name' => config('app.site_name'),
                'by' => $approval['by'],
                'count' => count($labels),
                'changed' => $lists['changed'],
                'added' => $lists['added'],
                'deleted' => $lists['deleted'],
                'requested_at' => date('Y-m-d H:i:s', $approval['requested_at']),
                'accepted_at' => now()->format('Y-m-d H:i:s'),
            ]));
        } catch (MailTemplateException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::critical('WatchFiles: 管理画面の承認を取り込みましたが、承認のメールを送れませんでした。', ['message' => $e->getMessage(), 'files' => $labels]);
            $this->error('メールを送れませんでした。'.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * 通知と承認のメールの宛先。設定のfile_watch.toか、無ければエラーの通知と同じ宛先
     *
     * @return list<string>
     */
    private function recipients(): array
    {
        $to = (string) (config('neobit.file_watch.to') ?: config('logging.channels.error_notify.handler_with.to'));

        return array_values(array_filter(array_map('trim', explode(',', $to))));
    }

    /**
     * 通知のメールを送る。送れなければ、criticalのログに残す（エラーの通知が設定されていれば、そちらでも届く）
     *
     * @param  list<array{label: string, kind: string, time: int}>  $alerts
     * @param  list<array{label: string, time: int}>  $dangers
     * @param  list<string>  $unreadable
     */
    private function notify(array $alerts, array $dangers, array $unreadable): int
    {
        $recipients = $this->recipients();
        if ($recipients === []) {
            Log::critical('WatchFiles: ファイルの改ざんを検知しましたが、宛先が無いのでメールを送れません。', ['files' => array_column($alerts, 'label')]);
            $this->warn('宛先（file_watch.to・ERROR_NOTIFY_TO）が設定されていないので、メールは送りません。');

            return self::FAILURE;
        }

        // 種類ごとの件数と一覧
        $counts = [];
        $lists = [];
        foreach (self::KIND_LABELS as $kind => $label) {
            $ofKind = array_values(array_filter($alerts, fn (array $alert) => $alert['kind'] === $kind));
            $counts[] = $label.' '.count($ofKind).'件';
            $lists[$kind] = $this->lines($ofKind);
        }

        try {
            Mail::send(new TemplatedMail('file_watch_alert', [
                'from_mail' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to' => implode(', ', $recipients),
                'app_name' => config('app.site_name'),
                'counts' => implode('・', $counts),
                'dangers' => $this->lines($dangers),
                'unreadable' => $unreadable !== [] ? '  '.implode("\n  ", $unreadable) : '  （なし）',
                'changed' => $lists['changed'],
                'added' => $lists['added'],
                'deleted' => $lists['deleted'],
            ]));
        } catch (MailTemplateException $e) {
            // テンプレートが無い、書き方が誤っている、は開発中の誤りなので、受け止めずに止める
            throw $e;
        } catch (Throwable $e) {
            Log::critical('WatchFiles: ファイルの改ざんを検知しましたが、通知のメールを送れませんでした。', ['message' => $e->getMessage()]);
            $this->error('メールを送れませんでした。'.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('通知のメールを送りました。');

        return self::SUCCESS;
    }

    /**
     * ファイルの一覧の文。1行に1つ、名前と時刻。無ければ「（なし）」
     *
     * @param  list<array{label: string, time: int}>  $files
     */
    private function lines(array $files): string
    {
        if ($files === []) {
            return '  （なし）';
        }

        return implode("\n", array_map(
            fn (array $file) => '  '.$file['label'].'（'.date('Y-m-d H:i', $file['time']).'）',
            $files,
        ));
    }
}
