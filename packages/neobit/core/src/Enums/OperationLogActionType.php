<?php

namespace Neobit\Core\Enums;

use Neobit\Core\Models\OperationLog;

/**
 * 操作ログの操作の種類（operation_logs.action）にする列挙型の印。
 * フレームワークの操作はNeobit\Core\Enums\OperationLogActionが持ち、サイトに固有の操作は、
 * サイトの列挙型が、これを実装して足す。足した列挙型は、config('neobit.operation_log_actions')に書く。
 */
interface OperationLogActionType extends CodeTableEnum
{
    /**
     * その操作の行に、件数や内訳を持っている別の記録があれば、そのモデルのクラスと、記録のid。
     * 持たない操作なら[null, null]。画面に件数を出すとき、記録を種類ごとにまとめて読むのに使う。
     *
     * @return array{0: class-string|null, 1: int|null}
     */
    public function relatedKey(OperationLog $log): array;
}
