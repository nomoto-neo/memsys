<?php

use App\Enums\AppOperationLogAction;
use App\Enums\CodeType;
use Neobit\Core\Enums\OperationLogAction;

// 操作ログの操作の種類にする列挙型。フレームワークの操作と、このサイトの操作
$operationLogActions = [
    OperationLogAction::class,
    AppOperationLogAction::class,
];

return [

    /*
    |--------------------------------------------------------------------
    | コード表
    |--------------------------------------------------------------------
    |
    | コード表の読み込み（Neobit\Core\Code\CodeTable）に渡す、プロジェクトの設定。
    |
    */

    // DBのコード表（t_codes）のコード名の一覧。ここに載せた名前は、DBから読む
    'db_code_types' => array_column(CodeType::cases(), 'value'),

    // 複数の列挙型を1つにまとめるコード表。コード名 => 列挙型の一覧
    'code_table_merge' => [
        'operation_log_action' => $operationLogActions,
    ],

    /*
    |--------------------------------------------------------------------
    | 操作ログ
    |--------------------------------------------------------------------
    |
    | 操作ログ（Neobit\Core\Models\OperationLog）に渡す、プロジェクトの設定。
    |
    */

    // 操作の種類（operation_logs.action）にする列挙型。Neobit\Core\Enums\OperationLogActionTypeを実装する
    'operation_log_actions' => $operationLogActions,

];
