<?php

namespace Neobit\Core\Operations;

use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\PrunableBatchRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToRetrieveMetadata;
use Neobit\Core\Csv\CsvImportSettings;
use Neobit\Core\Models\OperationLog;
use Neobit\Core\Models\TrustedDevice;
use Neobit\Core\Security\IpBlocker;
use Neobit\Core\Team\TeamInvitationManager;
use Neobit\Core\Upload\UploadFilePath;

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
 * 「この端末を信頼する」の記録（trusted_devicesテーブル）
 *   消すもの：期限の切れた行
 *
 * 操作ログ（operation_logsテーブル）
 *   消すもの：config('cleanup.operation_log_days')の日数より古い行
 *
 * 送れなかったキューのジョブ（failed_jobsテーブル）
 *   消すもの：config('cleanup.failed_job_days')の日数より古い行
 *
 * 終わったジョブのバッチ（job_batchesテーブル）
 *   消すもの：終わってからconfig('cleanup.failed_job_days')の日数より古い行
 *
 * 招待（team_invitationsテーブル。Neobit\Core\Team\TeamInvitationManager）
 *   消すもの：期限の切れた行
 *
 * IPブロック管理で止めているIPアドレス（ip_blocksテーブル。Neobit\Core\Security\IpBlocker）
 *   消すもの：期限の過ぎた行
 *
 * ■ プロジェクトの片付け
 * 業務のデータの片付けは、このクラスには書かない。config/cleanup.phpのtasksに、
 * 名前と呼ぶメソッドを書くと、all()が上の片付けの後に呼ぶ。お問い合わせが
 * これに当たる。残す日数（days）を書いた片付けには、その日数を引数で渡す。
 *
 * ■ 呼ぶところ
 * - スケジューラーから1時間ごとに、app:cleanup-temporary-dataコマンドがallExclusively()を呼ぶ。
 *   これが本来の片付け
 * - cronの動かないサーバー（config('neobit.cron_enabled')がfalse）では、同じ予定を、サイトへのアクセスを
 *   契機にNeobit\Core\Operations\RequestSchedulerが動かす
 * - 一時ファイルはアップロードとCSV取り込みのたびにも消す。サーバーのcronが動いていなくても
 *   溜まり続けないようにするための控え
 *
 * ■ 期限の切れた行
 * キャッシュ・信頼済みの端末の記録・招待は行ごとに期限を持ち、期限の切れた行は使われない。
 * ただ、もう一度読まれない限り消えないので、試行制限のように毎回違うキーで作られる行が
 * 溜まっていく。期限は各機能が決めているので、ここでは期限の切れた行を消すだけで
 * MAX_AGE_HOURSは使わない。キャッシュをDB以外に置いているときは何もしない。
 * パスワード再設定用のpassword_reset_tokensテーブルは、使っていないので対象にしない。
 */
final class TemporaryDataCleaner
{
    /**
     * 一時ファイルを置いたままにしてよい時間。確認画面やCSV取り込みの確認をこれより長く
     * 開いたままにすると、ファイルが無くなってやり直しになる。
     */
    public const MAX_AGE_HOURS = 24;

    /** 片付けを1本だけにするロックの名前 */
    private const LOCK_KEY = 'neobit_temporary_data_cleanup';

    /**
     * ロックを持ち続けてよい秒数。片付けの途中で処理が止まっても、この時間がたてばロックが外れ、
     * 次の回の片付けが動ける。
     */
    private const LOCK_SECONDS = 3600;

    /**
     * all()を、ほかの片付けと重ならないように動かす。消した件数を名前 => 件数で返し、
     * ほかの片付けが動いていて取りやめたときはnullを返す。
     * 予定から動いた片付けのコマンドと、手で動かしたコマンドが同時に動くと、
     * 同じファイルを消しにいってぶつかるので、ロックで1本だけにする。
     * 何かを消したときだけログに残す。何もしなかった回は、1時間ごとに同じ行が並ばないよう残さない。
     */
    public static function allExclusively(): ?array
    {
        $counts = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS)->get(fn () => self::all());

        // ほかの片付けがロックを持っているとき
        if ($counts === false) {
            return null;
        }

        if (array_sum($counts) > 0) {
            Log::info('一時データを消しました。', $counts);
        }

        return $counts;
    }

    /** すべての一時データを片付け、消した件数を名前 => 件数で返す。 */
    public static function all(): array
    {
        $counts = [
            'アップロードの一時ファイル' => self::uploadTmpFiles(),
            'CSV取り込みの作業用ファイル' => self::csvImportFiles(),
            '期限の切れたキャッシュ' => self::expiredCache(),
            '期限の切れた信頼済み端末' => self::expiredTrustedDevices(),
            '保存期間を過ぎた操作ログ' => self::oldOperationLogs(),
            '保存期間を過ぎた、送れなかったジョブ' => self::oldFailedJobs(),
            '保存期間を過ぎた、終わったジョブのバッチ' => self::oldJobBatches(),
            '期限の切れた招待' => TeamInvitationManager::deleteExpired(),
            '期限の過ぎたIPブロック' => IpBlocker::deleteExpired(),
        ];

        // プロジェクトの片付け。残す日数を書いた片付けには、その日数を渡す
        foreach ((array) config('cleanup.tasks') as $name => $task) {
            $arguments = array_key_exists('days', $task) ? [$task['days']] : [];

            $counts[$name] = (int) call_user_func_array($task['run'], $arguments);
        }

        return $counts;
    }

    /** UploadFilePath::TMP_DIRに置いた、アップロード直後の一時ファイルのうち古いもの。 */
    public static function uploadTmpFiles(): int
    {
        return self::deleteOldFiles(UploadFilePath::TMP_DISK, UploadFilePath::TMP_DIR);
    }

    /** CsvImportSettings::TMP_DIRに置いた、CSV取り込みの作業用ファイルのうち古いもの。 */
    public static function csvImportFiles(): int
    {
        return self::deleteOldFiles(CsvImportSettings::TMP_DISK, CsvImportSettings::TMP_DIR);
    }

    /** 試行制限の回数などを入れたdatabaseのキャッシュのうち、期限の切れた行。 */
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
     * TrustedDeviceManagerの「この端末を信頼する」の記録のうち、
     * 期限の切れた行。会員とスタッフの両方の分。
     */
    public static function expiredTrustedDevices(): int
    {
        return TrustedDevice::query()->where('expires_at', '<=', now())->delete();
    }

    /**
     * 操作ログ（Neobit\Core\Operations\OperationRecorder）のうち、config('cleanup.operation_log_days')の日数を過ぎた行。
     * 日数が設定に無いときは何もしない。設定のキャッシュが古くて項目がまだ無いときに、
     * 全部の行を消してしまわないようにするため。
     */
    public static function oldOperationLogs(): int
    {
        $days = (int) config('cleanup.operation_log_days');

        if ($days <= 0) {
            return 0;
        }

        return OperationLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * 試し直しても送れなかったキューのジョブ（failed_jobsテーブル）のうち、
     * config('cleanup.failed_job_days')の日数を過ぎた行。
     * 失敗したジョブをDB以外に置いているときと、日数が設定に無いときは何もしない。
     */
    public static function oldFailedJobs(): int
    {
        $failed = config('queue.failed');

        $days = (int) config('cleanup.failed_job_days');

        if ($days <= 0 || ! str_starts_with((string) ($failed['driver'] ?? ''), 'database')) {
            return 0;
        }

        return DB::connection($failed['database'] ?? null)
            ->table($failed['table'] ?? 'failed_jobs')
            ->where('failed_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * ジョブのバッチ（job_batchesテーブル）のうち、終わってからconfig('cleanup.failed_job_days')の日数を過ぎた行。
     * バッチの行は、送っている間の進み具合と、送れなかったジョブを送り直す間だけ使うので、
     * 送り直せる日数と同じだけ残す。バッチをDB以外に置いているときと、日数が設定に無いときは何もしない。
     */
    public static function oldJobBatches(): int
    {
        $days = (int) config('cleanup.failed_job_days');
        $repository = app(BatchRepository::class);

        if ($days <= 0 || ! $repository instanceof PrunableBatchRepository) {
            return 0;
        }

        return $repository->prune(now()->subDays($days));
    }

    /**
     * ディスクのディレクトリの直下にある、MAX_AGE_HOURSより古いファイルを消す。
     * 一覧を取った後に、ほかの片付けが同じファイルを消していることがある。アップロードのたびの片付けと
     * 1時間ごとの片付けが重なったときなど。そのファイルは飛ばして、残りの片付けを続ける。
     */
    private static function deleteOldFiles(string $diskName, string $directory): int
    {
        $disk = Storage::disk($diskName);
        $cutoff = now()->subHours(self::MAX_AGE_HOURS)->getTimestamp();
        $deleted = 0;

        foreach ($disk->files($directory) as $path) {
            // 時刻が読めないのは、先に消されたとき
            try {
                $lastModified = $disk->lastModified($path);
            } catch (UnableToRetrieveMetadata) {
                continue;
            }

            if ($lastModified < $cutoff && $disk->delete($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
