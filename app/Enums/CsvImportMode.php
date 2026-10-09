<?php

namespace App\Enums;

/**
 * CSV取り込みの動作（App\Support\CsvImportSettingsで指定する）。
 */
enum CsvImportMode
{
    /**
     * CSVの1行を1件のデータとして追加・更新する。検証・保存は画面からの登録・更新と
     * 同じ処理（FormFlow）を通る。
     */
    case Save;

    /**
     * モデルには保存せず、検証済みの全行をコントローラーのprocessCsvRows()に渡す
     * （入金データの消し込み、ポイントの一括付与など）。
     */
    case Process;
}
