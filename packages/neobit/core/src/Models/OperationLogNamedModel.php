<?php

namespace Neobit\Core\Models;

use Illuminate\Support\Collection;

/**
 * 操作ログに、操作した人や対象として残すモデルの印。モデルは、Relation::enforceMorphMap()に
 * 別名を登録しておく。操作ログには名前を持たせないので、画面に出すときに、このメソッドで引く。
 */
interface OperationLogNamedModel
{
    /**
     * 画面に出すための、今の名前。[id => 名前]で返す。
     * 削除したものも名前を出したいときは、削除済みも含めて探す。
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, string>
     */
    public static function operationLogNames(array $ids): Collection;
}
