<?php

namespace Neobit\Core\Models\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Neobit\Core\Enums\OperationLogActionType;
use ValueError;

/**
 * 操作ログの操作の種類（operation_logs.action）を、列挙型として読み書きする。
 * 列挙型は、config('neobit.operation_log_actions')に書いたものを順に試し、最初に値が合うものを使う。
 */
class OperationLogActionCast implements CastsAttributes
{
    /** 文字の値から、操作の種類の列挙型にする */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?OperationLogActionType
    {
        if ($value === null) {
            return null;
        }

        // 設定に書いた列挙型を順に試す
        foreach (config('neobit.operation_log_actions', []) as $class) {
            $case = $class::tryFrom($value);

            if ($case !== null) {
                return $case;
            }
        }

        throw new ValueError('"'.$value.'" は、操作ログの操作の種類にありません。');
    }

    /** 操作の種類の列挙型（か文字）から、DBに保存する文字の値にする */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof BackedEnum ? (string) $value->value : $value;
    }
}
