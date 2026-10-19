<?php

namespace Neobit\Core\Csv;

use Illuminate\Support\Facades\Auth;
use Neobit\Core\Enums\CsvEncoding;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\CsvDownloadLog;
use Neobit\Core\Operations\OperationRecorder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSVの共通の書き出し方。行の値の配列を受け取り、サイトで決めた書き方でCSVにする。
 * 行の作り方は呼ぶ側が決める。モデルの一覧から作るときは、CsvDownloadがこれを呼ぶ。
 * モデルではないデータ（ジョブの中の宛先、集計の結果など）は、行の配列を作って直接呼ぶ。
 *
 *     return CsvWriter::download(
 *         name: '一斉メールの送れなかった宛先',
 *         rows: $rows,
 *         encoding: CsvEncoding::Utf8Bom,
 *         headings: null,
 *         escapeFormula: true,
 *         conditions: ['bulk_mail_id' => $bulkMail->id],
 *     );
 *
 * どんなCSVになるかが呼び出しの1か所で分かるよう、引数に既定の値は持たせず、名前付き引数で全部書く。
 *
 * ■ 取り出し方
 * - download()  ブラウザにダウンロードさせる。ダウンロードの記録と操作ログを残す
 * - fetch()     CSVの中身を文字列で返す。保存先やメールへの添付は、呼んだ側が決める。
 *               全件をメモリに組み立てるので、大きなCSVには向かない。人がダウンロードしたことには
 *               ならないので、ダウンロードの記録は残さない
 *
 * ■ 引数
 * - rows           行の並び。1行は値の配列で、書いた順に列が並ぶ。ジェネレーターを渡すと、
 *                  少しずつ読みながら書き出せる
 * - encoding       CsvEncoding::Utf8BomかCsvEncoding::Sjis
 * - headings       1行目に出す見出し。nullなら見出しの行を出さない
 * - escapeFormula  Excelで数式として動く値を無害にするか
 *
 * ■ 文字コードと書き方
 * - UTF-8は、Excelが文字コードを見分けられるよう、先頭にBOMを付ける
 * - Shift_JISは、①や髙のようなWindowsの文字も含むCP932で変換する
 * - fputcsv()のエスケープ文字は空にする。既定の「\」だとShift_JISの「ソ」「表」などを
 *   壊すことがあるため
 * - 改行はExcelに合わせて\r\n
 *
 * ■ 数式の無害化
 * 利用者が入力した値が「=」「+」「-」「@」などで始まっていると、Excelで開いたときに
 * 数式として動くことがある。先頭に「'」を付けて文字列として扱わせる。-100のような数値は
 * そのまま。Excelで開かずにほかのシステムへ渡すCSVでは「'」が残ってしまうので、falseにする。
 * 見出しはこちらで決めた文字列なので、無害化は掛けない。
 *
 * ■ ダウンロードの記録
 * download()は1回ごとに、名前・日時・操作者・条件・IPアドレス・件数をcsv_download_logsに残す。
 * 途中で止まってもダウンロードしようとしたことが残るよう、書き出す前に行を作り、件数は書き終えてから入れる。
 */
final class CsvWriter
{
    /** 数式として動きうる、値の先頭の文字 */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * CSVをダウンロードさせる。ダウンロードの記録と操作ログを残す。
     * ファイル名は「{name}_年月日_時分.csv」で、nameはダウンロードの記録の名前にもなる。
     *
     * @param  iterable<array>  $rows
     * @param  list<string>|null  $headings
     * @param  array  $conditions  ダウンロードの記録に残す条件。一覧の検索条件など
     */
    public static function download(
        string $name,
        iterable $rows,
        CsvEncoding $encoding,
        ?array $headings,
        bool $escapeFormula,
        array $conditions,
    ): StreamedResponse {
        // ダウンロードの記録。件数は書き出し終わってから入れる
        $log = CsvDownloadLog::create([
            'name' => $name,
            'operator_id' => Auth::id(),
            'row_count' => 0,
            'conditions' => $conditions,
            'ip' => request()->ip(),
        ]);

        // 操作ログ。条件と件数はダウンロードの記録が持つので、そのidでつなぐ
        OperationRecorder::record(OperationLogAction::CsvDownload, detail: ['name' => $name, 'csv_download_log_id' => $log->id]);

        $filename = $name.'_'.now()->format('Ymd_Hi').'.csv';

        return response()->streamDownload(function () use ($rows, $encoding, $headings, $escapeFormula, $log) {
            $out = fopen('php://output', 'w');
            $count = self::write($out, $rows, $encoding, $headings, $escapeFormula);
            fclose($out);

            // 書き出した件数を記録に入れる
            $log->update(['row_count' => $count]);
        }, $filename, ['Content-Type' => 'text/csv; charset='.self::charset($encoding)]);
    }

    /**
     * CSVの中身を文字列で返す。保存やメールへの添付など、使い道は呼んだ側が決める。
     *
     * @param  iterable<array>  $rows
     * @param  list<string>|null  $headings
     */
    public static function fetch(
        iterable $rows,
        CsvEncoding $encoding,
        ?array $headings,
        bool $escapeFormula,
    ): string {
        $out = fopen('php://temp', 'w+');
        self::write($out, $rows, $encoding, $headings, $escapeFormula);

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Content-Typeに書く文字コードの名前 */
    private static function charset(CsvEncoding $encoding): string
    {
        return $encoding === CsvEncoding::Sjis ? 'Shift_JIS' : 'UTF-8';
    }

    /**
     * BOM・見出し・行を、ストリームに書き出す。書き出した行の数を返す。見出しの行は数えない。
     *
     * @param  resource  $out
     */
    private static function write($out, iterable $rows, CsvEncoding $encoding, ?array $headings, bool $escapeFormula): int
    {
        // UTF-8は、Excelが文字コードを見分けられるよう先頭にBOMを付ける
        if ($encoding === CsvEncoding::Utf8Bom) {
            fwrite($out, "\xEF\xBB\xBF");
        }

        // 見出しはこちらで決めた文字列なので、数式の無害化は掛けない
        if ($headings !== null) {
            self::writeRow($out, $headings, $encoding, false);
        }

        $count = 0;
        foreach ($rows as $row) {
            self::writeRow($out, $row, $encoding, $escapeFormula);
            $count++;
        }

        return $count;
    }

    /**
     * 1行を書き出す。数式の無害化と文字コードの変換は1セルずつ行う。
     *
     * @param  resource  $out
     */
    private static function writeRow($out, array $fields, CsvEncoding $encoding, bool $escapeFormula): void
    {
        foreach ($fields as $i => $field) {
            $field = (string) $field;

            // 数式として動きうる文字で始まる値は、先頭に「'」を付けて文字列として扱わせる。数値はそのまま
            if ($escapeFormula && $field !== '' && ! is_numeric($field)
                && in_array($field[0], self::FORMULA_PREFIXES, true)) {
                $field = "'".$field;
            }

            if ($encoding === CsvEncoding::Sjis) {
                $field = mb_convert_encoding($field, 'SJIS-win', 'UTF-8');
            }

            $fields[$i] = $field;
        }

        fputcsv($out, $fields, ',', '"', '', "\r\n");
    }
}
