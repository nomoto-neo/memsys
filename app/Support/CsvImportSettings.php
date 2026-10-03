<?php

namespace App\Support;

use App\Enums\CsvEncoding;
use App\Enums\CsvImportMode;
use Illuminate\Database\Eloquent\Builder;

/**
 * CSV取り込み（App\Support\CsvImport）の設定。コントローラーのcsvImportSettings()で作る。
 * 確認画面と実行の両方で同じ設定を使うので、1か所にまとめている。
 * どんな取り込みかが1か所で分かるよう、省略できる引数も含めて全部書くことにしている。
 *
 * - query          取り込み先のモデルのクエリ。CSVのidで探すときの範囲になる。
 * - name           画面の見出し・取り込み記録の名前（'会員一覧'など）。
 * - route          取り込み画面のルート名。確認は「.confirm」、実行は「.execute」を後ろに付けた名前。
 *                  取り込みが終わると、この画面に戻って結果のメッセージを出す。
 * - labelColumn    確認画面などで「何行目」の後ろに添える列。どの行のデータか見分けやすくするため
 *                  （例: 'お名前' なら「2行目（山田太郎）」）。文字列なら見出し（見出し無しのCSVでは
 *                  csvColumns()に書いた見出し）、整数なら左から何列目か（1から数える）。
 *                  nullなら何も添えない。
 * - mode           CsvImportMode::Save（追加・更新）かCsvImportMode::Process（処理だけ）。
 * - encoding       nullなら自動判定（BOM／UTF-8／Shift_JIS）。指定すると、その文字コードとして読む。
 * - header         1行目が見出しか。falseなら、csvColumns()の順に全部の列が並んでいる前提で読む。
 * - escapeFormula  ダウンロードで数式の無害化に付けた先頭の「'」を外すか。
 * - allowInsert    idが空欄の行（追加）を認めるか。
 * - maxRows        データの行数の上限。nullなら無制限。
 */
final class CsvImportSettings
{
    // 取り込み途中のCSV（確認画面から実行までの間）を置くディスクとディレクトリ。古いものは
    // App\Support\TemporaryDataCleanerが消す。
    public const TMP_DISK = 'local';

    public const TMP_DIR = 'csv_import';

    public function __construct(
        public readonly Builder $query,
        public readonly string $name,
        public readonly string $route,
        public readonly string|int|null $labelColumn = null,
        public readonly CsvImportMode $mode = CsvImportMode::Save,
        public readonly ?CsvEncoding $encoding = null,
        public readonly bool $header = true,
        public readonly bool $escapeFormula = true,
        public readonly bool $allowInsert = false,
        public readonly ?int $maxRows = null,
    ) {
    }
}
