<?php

namespace Neobit\Core\Csv;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Neobit\Core\Enums\CsvEncoding;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSVダウンロードの共通処理。
 *
 * コントローラーはCSVに出す項目の一覧のcsvColumns()を用意し、入口のメソッドでdownloadCsv()を
 * 呼ぶだけでよい。どんなCSVになるかが呼び出しの1か所で分かるよう、引数に既定の値は持たせず、
 * 名前付き引数で全部書く。
 *
 *     public function csv(): StreamedResponse
 *     {
 *         return $this->downloadCsv(
 *             query: Member::query()->with('editorStaff'),
 *             name: '会員一覧',
 *             encoding: CsvEncoding::Utf8Bom,
 *             header: true,
 *             escapeFormula: true,
 *         );
 *     }
 *
 * ■ コントローラーが用意するもの
 * - csvColumns()                    CSVに出す項目。見出し => 値の場所で、書いた順に列が並ぶ。
 *                                   書き方はCSV取り込みと共通で、CsvColumnSetにある
 * - csvCustomColumn($key, $record)  「@名前」と書いた項目の値。使うときだけ用意する
 * 書き間違いは書き出しを始める前に例外にする。途中まで書いた壊れたCSVを渡さないため。
 *
 * ■ downloadCsv()の引数
 * - query          対象のモデルのクエリ。横展開などで使うリレーションはwith()で先に読んでおく
 * - name           CSVの名前。ファイル名は「{name}_年月日_時分.csv」で、ダウンロードの記録の名前にもなる
 * - encoding       CsvEncoding::Utf8BomかCsvEncoding::Sjis
 * - header         1行目に見出しを出すか。出さないときは、見出しが「:*」で終わる横展開の列は使えない
 * - escapeFormula  Excelで数式として動く値を無害にするか
 *
 * ■ 絞り込みと件数
 * SearchableListも使っていれば、一覧の今の検索条件と並び順で全件を出す。
 * 500件ずつ読みながら書き出すので、件数が多くてもメモリを使い切らない。
 *
 * ■ 書き出し方とダウンロードの記録
 * 文字コード・BOM・数式の無害化・fputcsv()の書き方と、ダウンロードの記録・操作ログは、
 * CSVの共通の部品（Neobit\Core\Csv\CsvWriter）が行う。モデルではないデータを書き出すときは、
 * 行の配列を作ってCsvWriterを直接呼ぶ。
 */
trait CsvDownload
{
    /** CSVに出す項目。見出し => 値の場所 */
    abstract private function csvColumns(): array;

    /** CSVをダウンロードさせる。引数の意味はこのファイルの冒頭にある */
    private function downloadCsv(
        Builder $query,
        string $name,
        CsvEncoding $encoding,
        bool $header,
        bool $escapeFormula,
    ): StreamedResponse {
        // 「@名前」の項目の値を求める処理。コントローラーにcsvCustomColumn()があるときだけ
        $customExport = null;
        if (method_exists($this, 'csvCustomColumn')) {
            $customExport = fn (string $key, Model $record) => $this->csvCustomColumn($key, $record);
        }

        // 書き出しを始める前に項目の定義を読んでおく。書き間違いはここで例外になる
        $columns = new CsvColumnSet(
            $this->csvColumns(),
            customExport: $customExport,
        );

        // 一覧と同じ検索条件・並び順で絞り込む
        $conditions = [];
        if (method_exists($this, 'applyListConditions')) {
            [$conditions] = $this->applyListConditions($query);
        }

        // 連番・組の横展開の列の数を、絞り込んだデータの最大件数で決める
        $columns->prepareExport($query, $header);

        // 500件ずつ読みながら、1件ずつ行にする。書き出しが始まってから読むので、件数が多くてもメモリを使い切らない
        $rows = (function () use ($query, $columns) {
            foreach ($query->lazy(500) as $record) {
                yield $columns->exportRow($record);
            }
        })();

        // 書き出し方とダウンロードの記録は、CSVの共通の部品に任せる
        return CsvWriter::download(
            name: $name,
            rows: $rows,
            encoding: $encoding,
            headings: $header ? $columns->headings() : null,
            escapeFormula: $escapeFormula,
            conditions: $conditions,
        );
    }
}
