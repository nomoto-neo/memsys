<?php

namespace App\Support;

use App\Models\TrustedDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 一時データの後片付け。使い終わった後も残り続けるものを消す。
 *
 * | 一時データ                         | 置き場所                         | 消すもの                          |
 * |------------------------------------|----------------------------------|-----------------------------------|
 * | アップロード直後の一時ファイル     | localディスクのtmp/              | MAX_AGE_HOURSより古いファイル     |
 * | CSV取り込みの作業用ファイル        | localディスクのcsv_import/       | MAX_AGE_HOURSより古いファイル     |
 * | 試行制限の回数などのキャッシュ     | cacheテーブル・cache_locksテーブル | 期限の切れた行                  |
 * | 「このデバイスを記憶する」の記録   | trusted_devicesテーブル          | 期限（expires_at）の切れた行      |
 *
 * ■ 呼ぶところ
 * - スケジューラー（routes/console.php）から1時間ごとに、app:cleanup-temporary-dataコマンド
 *   （App\Console\Commands\CleanupTemporaryData）がall()を呼ぶ。これが本来の片付け
 * - 一時ファイルは、アップロード（AjaxFileUpload）とCSV取り込み（CsvImport）のたびにも
 *   uploadTmpFiles()・csvImportFiles()を呼ぶ。サーバーのcronが動いていなくても、
 *   一時ファイルが溜まり続けないようにするための控え
 *
 * ■ キャッシュとtrusted_devicesの「期限の切れた行」
 * どちらも行ごとに期限を持っていて、期限が切れた行は使われない。ただし、もう一度
 * 読まれない限り行は消えないので（Laravelのdatabaseのキャッシュは、読んだときに
 * 期限切れなら消すだけ）、試行制限のように毎回違うキー（IPアドレスなど）で作られる
 * 行が溜まっていく。期限そのものは各機能が決めているので、ここでは期限が切れた行を
 * 消すだけで、MAX_AGE_HOURSは使わない。
 * キャッシュをdatabase以外（file・redisなど）にしている場合は、それぞれの仕組みで
 * 期限切れが消えるので、何もしない。
 *
 * パスワード再設定のpassword_reset_tokensテーブルは、使っていないので対象にしない
 * （パスワードの再設定はメールの確認コード。App\Support\MemberVerificationCode）。
 */
final class TemporaryDataCleaner
{
    // 一時ファイルを置いたままにしてよい時間。確認画面やCSV取り込みの確認を、
    // これより長く開いたままにすると、ファイルが無くなってやり直しになる。
    public const MAX_AGE_HOURS = 24;

    /**
     * すべての一時データを片付け、消した件数を返す（名前 => 件数）。
     */
    public static function all(): array
    {
        return [
            'アップロードの一時ファイル' => self::uploadTmpFiles(),
            'CSV取り込みの作業用ファイル' => self::csvImportFiles(),
            '期限の切れたキャッシュ' => self::expiredCache(),
            '期限の切れた信頼済み端末' => self::expiredTrustedDevices(),
        ];
    }

    /**
     * アップロード直後の一時ファイル（App\Support\UploadFilePath::TMP_DIR）のうち、古いもの。
     */
    public static function uploadTmpFiles(): int
    {
        return self::deleteOldFiles(UploadFilePath::TMP_DISK, UploadFilePath::TMP_DIR);
    }

    /**
     * CSV取り込みの作業用ファイル（App\Support\CsvImportSettings::TMP_DIR）のうち、古いもの。
     */
    public static function csvImportFiles(): int
    {
        return self::deleteOldFiles(CsvImportSettings::TMP_DISK, CsvImportSettings::TMP_DIR);
    }

    /**
     * databaseのキャッシュ（試行制限の回数など）のうち、期限の切れた行。
     */
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

    /**
     * 「このデバイスを記憶する」「この端末を信頼する」の記録のうち、期限の切れた行
     * （会員・スタッフの両方。App\Support\TrustedDeviceManager）。
     */
    public static function expiredTrustedDevices(): int
    {
        return TrustedDevice::query()->where('expires_at', '<=', now())->delete();
    }

    /**
     * ディスクのディレクトリの直下にある、MAX_AGE_HOURSより古いファイルを消す。
     */
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
