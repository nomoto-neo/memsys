<?php

namespace Neobit\Core\Models;

/**
 * 操作ログの行に、件数や内訳を持たせている別の記録（CSVのダウンロードの記録、一斉メールの
 * 送信の記録など）の印。どの操作がどの記録を持つかは、操作の種類の列挙型が決める
 * （Neobit\Core\Enums\OperationLogActionType::relatedKey()）。
 * 操作ログの画面は、記録の種類を知らずに、ここのメソッドが返すものを並べるだけにする。
 * アプリの記録（一斉メールなど）も、フレームワークの画面に同じ形で出せるようにするため。
 */
interface OperationLogRelatedRecord
{
    /** 操作ログの画面とCSVに出す、件数を1行にまとめた文字。 */
    public function operationLogSummary(): string;

    /**
     * 操作ログの画面の内訳の窓に出す、項目の名前 => 値。値は1行の文字か、複数行の文字の配列。
     * 配列が空なら「なし」と出す。
     *
     * @return array<string, string|string[]>
     */
    public function operationLogDetails(): array;

    /** 内訳の窓から開ける、記録の詳しい画面のURL。無ければnull */
    public function operationLogUrl(): ?string;
}
