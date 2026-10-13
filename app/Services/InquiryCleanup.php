<?php

namespace App\Services;

use App\Models\Inquiry;
use Illuminate\Support\Facades\Storage;
use Neobit\Core\Upload\UploadFilePath;

/**
 * 残す日数を過ぎたお問い合わせの削除。個人情報を、要らなくなった後も持ち続けないようにするため。
 * 一時データの後片付け（Neobit\Core\Operations\TemporaryDataCleaner）が、config/cleanup.phpのtasksに
 * 書いた日数を渡して、1時間ごとに呼ぶ。
 * 1件ずつは操作ログに残さず、消した件数だけを返す。
 */
final class InquiryCleanup
{
    /** 1回の問い合わせで読む件数 */
    private const CHUNK = 100;

    /**
     * 作ってから$days日を過ぎたお問い合わせと、その添付ファイルを消す。消した件数を返す。
     * 日数がnullなら、何も消さない。日数を決めていないサイトでは、残し続ける。
     */
    public static function deleteOld(?int $days): int
    {
        if ($days === null) {
            return 0;
        }

        $deleted = 0;

        Inquiry::query()
            ->where('created_at', '<', now()->subDays($days))
            ->chunkById(self::CHUNK, function ($inquiries) use (&$deleted) {
                foreach ($inquiries as $inquiry) {
                    // 行を消してから、添付ファイルをレコードのディレクトリごと消す。先にファイルを消すと、
                    // 行を消せなかったときに、添付ファイルの無いお問い合わせが残るため
                    $inquiry->delete();

                    $directory = UploadFilePath::directory(Inquiry::class, $inquiry->id);
                    Storage::disk(UploadFilePath::PUBLIC_DISK)->deleteDirectory($directory);
                    Storage::disk(UploadFilePath::PRIVATE_DISK)->deleteDirectory($directory);

                    $deleted++;
                }
            });

        return $deleted;
    }
}
