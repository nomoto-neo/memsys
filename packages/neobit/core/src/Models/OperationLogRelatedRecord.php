<?php

namespace Neobit\Core\Models;

/**
 * 操作ログの行に、件数や内訳を持たせている別の記録（CSVのダウンロードの記録、一斉メールの
 * 送信の記録など）の印。どの操作がどの記録を持つかは、操作の種類の列挙型が決める
 * （Neobit\Core\Enums\OperationLogActionType::relatedKey()）。
 */
interface OperationLogRelatedRecord
{
    /** 操作ログの画面とCSVに出す、件数を1行にまとめた文字。 */
    public function operationLogSummary(): string;
}
