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
 */
#[Signature('app:watch-files {--init : 今の状態を基準にする} {--full : 全部のハッシュ値を比べ直し、vendor/も見る}')]
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

        $watcher = new FileWatcher();

        // 前の回が動いている間は、何もせずに終わる（5分ごとの回と--fullの回が重なったとき）
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

    /** ロックを押さえた後の1回分。基準を作るか、確かめて知らせる */
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
            $result = $watcher->check((bool) $this->option('full'));
        } catch (RuntimeException $e) {
            // 基準が無い。サーバーを置いた後に、--initを忘れている
            Log::warning('WatchFiles: '.$e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('通常の更新 %d件・判断待ち %d件・改ざんの疑い %d件・アップロードの置き場所の受け付けていないファイル %d件・確かめられなかったディレクトリ %d件',
            $result['accepted'], $result['pending'], count($result['alerts']), count($result['dangers']), count($result['unreadable'])));

        if (! $result['notify']) {
            return self::SUCCESS;
        }

        return $this->notify($result['alerts'], $result['dangers'], $result['unreadable']);
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
        $to = (string) (config('neobit.file_watch.to') ?: config('logging.channels.error_notify.with.to'));
        $recipients = array_filter(array_map('trim', explode(',', $to)));

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
