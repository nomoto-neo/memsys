<?php

use App\Services\InquiryCleanup;

return [

    /*
    |--------------------------------------------------------------------
    | 一時データの後片付け
    |--------------------------------------------------------------------
    |
    | 使い終わった後も残り続けるものを、何日残すかと、プロジェクトで足す片付け。
    | 片付けは、スケジューラーから1時間ごとに動く（app:cleanup-temporary-data。
    | Neobit\Core\Operations\TemporaryDataCleaner）。
    |
    */

    // 操作ログ（operation_logs。Neobit\Core\Operations\OperationRecorder）を残す日数
    'operation_log_days' => 365,

    // 試し直しても送れなかったキューのジョブ（failed_jobs）を残す日数。この間なら、
    // php artisan queue:retry all で送り直せる。一斉メールのジョブには宛先の氏名と
    // メールアドレスが入っているので、残し続けない
    'failed_job_days' => 7,

    // プロジェクトの片付け。フレームワークの片付けの後に呼ばれる。名前 => 片付けの内容。
    // 名前は、片付けのコマンドの表とログに出る。
    // - run   呼ぶメソッド。[クラス, メソッド]。消した件数を返す
    // - days  残す日数。書くと、runのメソッドに引数で渡す。行ごとに期限を持つものには書かない
    'tasks' => [
        '保存期間を過ぎたお問い合わせ' => [
            'run' => [InquiryCleanup::class, 'deleteOld'],
            // nullなら、消さずに残し続ける。日数は、サイトの運用で決める
            'days' => null,
        ],
    ],

];
