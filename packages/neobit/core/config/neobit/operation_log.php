<?php

use Neobit\Core\Enums\OperationLogAction;

/**
 * 操作ログ（Neobit\Core\Models\OperationLog）と、1日1回の報告のメール（Neobit\Core\Operations\OperationLogReport）の既定の値。
 * アプリのconfig/neobit.phpのoperation_logの節に書いたキーが、これに優先する。
 */
return [

    // 操作の種類（operation_logs.action）にする列挙型。Neobit\Core\Enums\OperationLogActionTypeを実装する。
    // 操作のコード表（operation_log_action）も、ここに書いた順に合わせて作る。
    // サイトの操作を足すときは、フレームワークの列挙型の後ろに並べる
    'actions' => [
        OperationLogAction::class,
    ],

    // 操作した人と対象の種類（operation_logs.operator_type・target_type）に、画面の名前を付ける列挙型。
    // 値はAppServiceProviderのenforceMorphMap()の名前、label()が画面の名前。フレームワークが、ここに書いた順に
    // 合わせて、コード表operation_log_modelを作る。書いていない種類は、種類の名前のまま出る
    'models' => [],

    // 操作ログの画面のルート名。日々の報告のメールに、この画面のURLを載せる。nullなら載せない
    'route' => null,

    // 報告のメールの宛先。カンマ区切りで複数書ける。空なら送らない。
    // エラーの通知（ERROR_NOTIFY_TO）は開発者、こちらは管理者と、読む人が違うので分けている
    'report_to' => (string) env('OPERATION_REPORT_TO', ''),

    // 報告のメールで、「気になる点」として挙げる目安の数。これ以上なら挙げる。
    // サイトの規模（スタッフや会員の数、1日の操作の量）に合わせて、プロジェクトごとに決める。
    // 0にすると、その項目は見ない。アプリが書くと丸ごと置き換わるので、3つとも書く
    'report_thresholds' => [
        // 1人が、1時間に開いた詳細の件数
        'views_per_hour' => 50,
        // 1人が、1日にCSVをダウンロードした回数
        'csv_downloads_per_day' => 5,
        // 1日のログインの失敗の件数
        'login_failures_per_day' => 10,
    ],

];
