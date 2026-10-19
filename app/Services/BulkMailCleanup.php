<?php

namespace App\Services;

use App\Enums\BulkMailStatus;
use App\Models\BulkMail;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Neobit\Core\Upload\UploadFilePath;

/**
 * 残す日数を過ぎた一斉メールの送信の記録の削除。
 * 一時データの後片付け（Neobit\Core\Operations\TemporaryDataCleaner）が、config/cleanup.phpのtasksに
 * 書いた日数を渡して、1時間ごとに呼ぶ。
 * 1件ずつは操作ログに残さず、消した件数だけを返す。
 */
final class BulkMailCleanup
{
    /** 1回の問い合わせで読む件数 */
    private const CHUNK = 100;

    /**
     * config/cleanup.phpのtasksに書いた、送信の記録を残す日数。画面に出すために読む。
     * tasksに書いていないか、日数がnullなら、消さずに残し続けるのでnullを返す。
     */
    public static function days(): ?int
    {
        foreach ((array) config('cleanup.tasks') as $task) {
            if (($task['run'] ?? null) === [self::class, 'deleteOld']) {
                return $task['days'] ?? null;
            }
        }

        return null;
    }

    /**
     * 送り終えてから$days日を過ぎた送信の記録と、その添付ファイル・ジョブのバッチの行を消す。
     * 消した件数を返す。日数がnullなら、何も消さない。送信中の記録は消さない。
     */
    public static function deleteOld(?int $days): int
    {
        if ($days === null) {
            return 0;
        }

        $deleted = 0;

        BulkMail::query()
            ->where('status', BulkMailStatus::Finished)
            ->where('finished_at', '<', now()->subDays($days))
            ->chunkById(self::CHUNK, function ($bulkMails) use (&$deleted) {
                foreach ($bulkMails as $bulkMail) {
                    // 行を消してから、添付ファイルをレコードのディレクトリごと消す。先にファイルを消すと、
                    // 行を消せなかったときに、添付ファイルの無い記録が残るため
                    $bulkMail->delete();

                    $directory = UploadFilePath::directory(BulkMail::class, $bulkMail->id);
                    Storage::disk(UploadFilePath::PUBLIC_DISK)->deleteDirectory($directory);
                    Storage::disk(UploadFilePath::PRIVATE_DISK)->deleteDirectory($directory);

                    // 送信の進み具合を持っていたバッチの行も消す。件数は送り終えたときに記録へ写してあり、もう要らない
                    if ($bulkMail->batch_id !== null) {
                        Bus::findBatch($bulkMail->batch_id)?->delete();
                    }

                    $deleted++;
                }
            });

        return $deleted;
    }
}
