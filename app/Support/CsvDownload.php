<?php

namespace App\Support;

use App\Enums\CsvEncoding;
use App\Enums\OperationLogAction;
use App\Models\CsvDownloadLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
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
 * - name           CSVの名前。ファイル名は「名前_年月日_時分.csv」で、ダウンロードの記録の名前にもなる
 * - encoding       CsvEncoding::Utf8BomかCsvEncoding::Sjis
 * - header         1行目に見出しを出すか。出さないときは、見出しが「:*」で終わる横展開の列は使えない
 * - escapeFormula  Excelで数式として動く値を無害にするか
 *
 * ■ 絞り込みと件数
 * SearchableListも使っていれば、一覧の今の検索条件と並び順で全件を出す。
 * 500件ずつ読みながら書き出すので、件数が多くてもメモリを使い切らない。
 *
 * ■ 文字コードと書き出し方
 * - Shift_JISは、①や髙のようなWindowsの文字も含むCP932で変換する
 * - fputcsv()のエスケープ文字は空にする。既定の「\」だとShift_JISの「ソ」「表」などを
 *   壊すことがあるため
 * - 改行はExcelに合わせて\r\n
 *
 * ■ 数式の無害化
 * 利用者が入力した値が「=」「+」「-」「@」などで始まっていると、Excelで開いたときに
 * 数式として動くことがある。先頭に「'」を付けて文字列として扱わせる。-100のような数値は
 * そのまま。Excelで開かずにほかのシステムへ渡すCSVでは「'」が残ってしまうので、falseにする。
 *
 * ■ ダウンロードの記録
 * 1回ごとに、名前・日時・操作者・検索条件・IPアドレス・件数をt_csv_download_logsに残す。
 * 途中で止まってもダウンロードしようとしたことが残るよう、書き出す前に行を作る。
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
        $headings = $columns->headings();

        // ダウンロードの記録。件数は書き出し終わってから入れる
        $log = CsvDownloadLog::create([
            'name' => $name,
            'operator_id' => Auth::id(),
            'row_count' => 0,
            'conditions' => $conditions,
            'ip' => request()->ip(),
        ]);

        // 操作ログ。検索条件と件数はダウンロードの記録が持つので、そのidでつなぐ
        OperationRecorder::record(OperationLogAction::CsvDownload, detail: ['name' => $name, 'csv_download_log_id' => $log->id]);

        $filename = $name.'_'.now()->format('Ymd_Hi').'.csv';
        $charset = $encoding === CsvEncoding::Sjis ? 'Shift_JIS' : 'UTF-8';

        // 500件ずつ読みながらそのままブラウザへ書き出す
        return response()->streamDownload(function () use ($query, $columns, $headings, $encoding, $header, $escapeFormula, $log) {
            $out = fopen('php://output', 'w');

            // UTF-8は、Excelが文字コードを見分けられるよう先頭にBOMを付ける
            if ($encoding === CsvEncoding::Utf8Bom) {
                fwrite($out, "\xEF\xBB\xBF");
            }

            if ($header) {
                // 見出しはこちらで決めた文字列なので、数式の無害化は掛けない
                $this->writeCsvRow($out, $headings, $encoding, false);
            }

            $count = 0;
            foreach ($query->lazy(500) as $record) {
                $this->writeCsvRow($out, $columns->exportRow($record), $encoding, $escapeFormula);
                $count++;
            }

            fclose($out);

            // 書き出した件数を記録に入れる
            $log->update(['row_count' => $count]);
        }, $filename, ['Content-Type' => "text/csv; charset={$charset}"]);
    }

    /** 1行を書き出す。数式の無害化と文字コードの変換は1セルずつ行う。 */
    private function writeCsvRow($out, array $fields, CsvEncoding $encoding, bool $escapeFormula): void
    {
        foreach ($fields as $i => $field) {
            $field = (string) $field;

            // 数式として動きうる文字で始まる値は、先頭に「'」を付けて文字列として扱わせる。数値はそのまま
            if ($escapeFormula && $field !== '' && ! is_numeric($field)
                && in_array($field[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
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
