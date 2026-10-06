<?php

namespace App\Support;

use App\Models\CompanyInvitation;
use App\Models\Inquiry;
use App\Models\OperationLog;
use App\Models\TrustedDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 一時データの後片付け。使い終わった後も残り続けるものと、残す日数を過ぎたものを消す。
 *
 * アップロード直後の一時ファイル（storage/app/private/tmp）
 *   消すもの：MAX_AGE_HOURSより古いファイル
 *
 * CSV取り込みの作業用ファイル（storage/app/private/csv_import）
 *   消すもの：MAX_AGE_HOURSより古いファイル
 *
 * 試行制限の回数などのキャッシュ（cache・cache_locksテーブル）
 *   消すもの：期限の切れた行
 *
 * 「このデバイスを記憶する」の記録（trusted_devicesテーブル）
 *   消すもの：期限の切れた行
 *
 * 企業会員の担当者の招待（t_company_invitationsテーブル）
 *   消すもの：期限の切れた行
 *
 * 操作ログ（t_operation_logsテーブル）
 *   消すもの：config('logging.operation_log_days')の日数より古い行
 *
 * お問い合わせ（t_inquiriesテーブルと添付ファイル）
 *   消すもの：config('app.inquiry_keep_days')の日数より古い行と、その添付ファイル。
 *   日数を決めていなければ消さない
 *
 * ■ 呼ぶところ
 * - スケジューラーから1時間ごとに、app:cleanup-temporary-dataコマンドがall()を呼ぶ。
 *   これが本来の片付け
 * - 一時ファイルはアップロードとCSV取り込みのたびにも消す。サーバーのcronが動いていなくても
 *   溜まり続けないようにするための控え
 *
 * ■ 期限の切れた行
 * キャッシュと信頼済みの端末の記録は行ごとに期限を持ち、期限の切れた行は使われない。
 * ただ、もう一度読まれない限り消えないので、試行制限のように毎回違うキーで作られる行が
 * 溜まっていく。期限は各機能が決めているので、ここでは期限の切れた行を消すだけで
 * MAX_AGE_HOURSは使わない。キャッシュをDB以外に置いているときは何もしない。
 * パスワード再設定用のpassword_reset_tokensテーブルは、使っていないので対象にしない。
 */
final class TemporaryDataCleaner
{
    // 一時ファイルを置いたままにしてよい時間。確認画面やCSV取り込みの確認をこれより長く
    // 開いたままにすると、ファイルが無くなってやり直しになる。
    public const MAX_AGE_HOURS = 24;

    // 保存期間を過ぎたお問い合わせを、1回の問い合わせで読む件数
    private const INQUIRY_CHUNK = 100;

    // すべての一時データを片付け、消した件数を名前 => 件数で返す。
    public static function all(): array
    {
        return [
            'アップロードの一時ファイル' => self::uploadTmpFiles(),
            'CSV取り込みの作業用ファイル' => self::csvImportFiles(),
            '期限の切れたキャッシュ' => self::expiredCache(),
            '期限の切れた信頼済み端末' => self::expiredTrustedDevices(),
            '期限の切れた担当者の招待' => self::expiredCompanyInvitations(),
            '保存期間を過ぎた操作ログ' => self::oldOperationLogs(),
            '保存期間を過ぎたお問い合わせ' => self::oldInquiries(),
        ];
    }

    // UploadFilePath::TMP_DIRに置いた、アップロード直後の一時ファイルのうち古いもの。
    public static function uploadTmpFiles(): int
    {
        return self::deleteOldFiles(UploadFilePath::TMP_DISK, UploadFilePath::TMP_DIR);
    }

    // CsvImportSettings::TMP_DIRに置いた、CSV取り込みの作業用ファイルのうち古いもの。
    public static function csvImportFiles(): int
    {
        return self::deleteOldFiles(CsvImportSettings::TMP_DISK, CsvImportSettings::TMP_DIR);
    }

    // 試行制限の回数などを入れたdatabaseのキャッシュのうち、期限の切れた行。
    public static function expiredCache(): int
    {
        $store = config('cache.stores.'.config('cache.default'));

        if (($store['driver'] ?? null) !== 'database') {
            return 0;
        }

        $connection = DB::connection($store['connection'] ?? null);
        $now = now()->getTimestamp();

        $deleted = $connection->table($store['table'] ?? 'cache')->where('expiration', '<=', $now)->delete();
        $deleted += $connection->table($store['lock_table'] ?? 'cache_locks')->where('expiration', '<=', $now)->delete();

        return $deleted;
    }

    // TrustedDeviceManagerの「このデバイスを記憶する」「この端末を信頼する」の記録のうち、
    // 期限の切れた行。会員とスタッフの両方の分。
    public static function expiredTrustedDevices(): int
    {
        return TrustedDevice::query()->where('expires_at', '<=', now())->delete();
    }

    // 企業会員の担当者の招待（App\Support\CompanyInvitationManager）のうち、期限の切れた行。
    public static function expiredCompanyInvitations(): int
    {
        return CompanyInvitation::query()->where('expires_at', '<=', now())->delete();
    }

    // 操作ログ（App\Support\OperationRecorder）のうち、残す日数を過ぎた行。
    public static function oldOperationLogs(): int
    {
        return OperationLog::query()
            ->where('created_at', '<', now()->subDays(config('logging.operation_log_days')))
            ->delete();
    }

    /**
     * お問い合わせのうち、残す日数（.envのINQUIRY_KEEP_DAYS）を過ぎた行と、その添付ファイル。
     * 個人情報を、要らなくなった後も持ち続けないようにするため。日数を決めていなければ、何も消さない。
     * 1件ずつは操作ログに残さず、消した件数だけを返す。
     */
    public static function oldInquiries(): int
    {
        $days = config('app.inquiry_keep_days');

        if ($days === null) {
            return 0;
        }

        $deleted = 0;

        Inquiry::query()
            ->where('created_at', '<', now()->subDays($days))
            ->chunkById(self::INQUIRY_CHUNK, function ($inquiries) use (&$deleted) {
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

    // ディスクのディレクトリの直下にある、MAX_AGE_HOURSより古いファイルを消す。
    private static function deleteOldFiles(string $diskName, string $directory): int
    {
        $disk = Storage::disk($diskName);
        $cutoff = now()->subHours(self::MAX_AGE_HOURS)->getTimestamp();
        $deleted = 0;

        foreach ($disk->files($directory) as $path) {
            if ($disk->lastModified($path) < $cutoff && $disk->delete($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
