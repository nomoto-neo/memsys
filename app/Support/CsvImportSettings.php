<?php

namespace App\Support;

use App\Enums\CsvEncoding;
use App\Enums\CsvImportMode;
use Illuminate\Database\Eloquent\Builder;

/**
 * CSV取り込みの設定。コントローラーのcsvImportSettings()で作り、確認画面と実行の両方で使う。
 * どんな取り込みかが1か所で分かるよう、引数に既定の値は持たせず、名前付き引数で全部書く。
 *
 * - query          取り込み先のモデルのクエリ。CSVのidで探す範囲になる。処理だけのモードで
 *                  探すものが無ければnull
 * - name           画面の見出しと取り込みの記録の名前。例：'会員一覧'
 * - route          取り込み画面のルート名。確認は後ろに「.confirm」、実行は「.execute」を付けた名前。
 *                  取り込みが終わるとこの画面に戻って結果を出す
 * - labelColumn    エラーなどの「何行目」の後ろに添えて、どの行か見分けやすくする列。
 *                  例：'お名前'なら「2行目（山田太郎）」。文字列なら見出しで、整数なら1から数えて
 *                  左から何列目か。nullなら添えない
 * - mode           追加と更新のCsvImportMode::Saveか、処理だけのCsvImportMode::Process
 * - encoding       nullなら自動で判定する。指定するとその文字コードとして読む
 * - header         1行目が見出しか。falseなら、csvColumns()の順に全部の列が並んでいるものとして読む
 * - escapeFormula  ダウンロードで数式を無害にするために付けた、先頭の「'」を外すか
 * - allowInsert    idが空欄の追加の行を認めるか
 * - maxRows        データの行数の上限。nullなら無制限
 */
final class CsvImportSettings
{
    // 確認画面から実行までの間、取り込み途中のCSVを置くディスクとディレクトリ。
    // 古いものはTemporaryDataCleanerが消す。
    public const TMP_DISK = 'local';

    public const TMP_DIR = 'csv_import';

    // 引数の意味はこのクラスの説明にある。どれも省略できない
    public function __construct(
        public readonly ?Builder $query,
        public readonly string $name,
        public readonly string $route,
        public readonly string|int|null $labelColumn,
        public readonly CsvImportMode $mode,
        public readonly ?CsvEncoding $encoding,
        public readonly bool $header,
        public readonly bool $escapeFormula,
        public readonly bool $allowInsert,
        public readonly ?int $maxRows,
    ) {
    }
}
