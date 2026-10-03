<?php

namespace App\Support;

use App\Models\TrustedDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 一時データの後片付け。使い終わった後も残り続けるものを消す。
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

    // すべての一時データを片付け、消した件数を名前 => 件数で返す。
    public static function all(): array
    {
        return [
            'アップロードの一時ファイル' => self::uploadTmpFiles(),
            'CSV取り込みの作業用ファイル' => self::csvImportFiles(),
            '期限の切れたキャッシュ' => self::expiredCache(),
            '期限の切れた信頼済み端末' => self::expiredTrustedDevices(),
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
