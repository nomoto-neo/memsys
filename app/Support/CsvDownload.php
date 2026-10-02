<?php

namespace App\Support;

use App\Enums\CsvEncoding;
use App\Models\CsvDownloadLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSVダウンロードの共通処理。
 *
 * コントローラーは、CSVに出す項目の一覧（csvColumns()）を用意し、ルートから呼ばれる
 * 入口のメソッドでdownloadCsv()を呼ぶだけでよい。どんなCSVができるかが呼び出しの
 * 1か所で分かるよう、省略できる引数も含めて全部書くことにしている。
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
 * ■ 使う側のコントローラーが用意するもの
 * - csvColumns()                    CSVに出す項目。見出し => 値の場所（書き方は下記）。書いた順に列が並ぶ。
 * - csvCustomColumn($key, $record)  「@名前」と書いた項目の値を返す（使うときだけ）。
 *
 * ■ csvColumns()の書き方
 * App\Support\CsvColumnSetのコメント参照（取り込みのCsvImportと共通）。書き間違いは、
 * 書き出しを始める前に例外にする。途中まで書かれた壊れたCSVを渡さないため。
 *
 * ■ downloadCsv()の引数
 * - $query          対象のモデルのクエリ。横展開などで使うリレーションはwith()で先読みしておく
 *                   （1件ずつ読みに行かないように）。
 * - $name           CSVの名前。ファイル名は「名前_年月日_時分.csv」、ダウンロード記録の名前にもなる。
 * - encoding:       CsvEncoding::Utf8Bom（既定）かCsvEncoding::Sjis（App\Enums\CsvEncoding）。
 * - header:         1行目に見出しを出すか（既定true）。falseのときは、横展開（見出しが「:*」で
 *                   終わる列）を使えない（書き出す前に例外）。
 * - escapeFormula:  Excelの数式として実行されうる値を無害にするか（既定true。下記）。
 *
 * ■ 検索条件
 * 同じコントローラーがSearchableListも使っていれば、一覧で今保存されている検索条件と
 * 並び順で絞り込み、全件を出す（ページ分けはしない）。使っていなければ条件なしで全件。
 *
 * ■ 件数が多いとき
 * lazy()で500件ずつ読みながら、そのままブラウザへ書き出す（streamDownload）。
 * 全件をメモリに載せないので、件数が多くてもメモリを使い切らない。lazy()は
 * with()の先読みも500件ごとに効き、並び順も保たれる。
 *
 * ■ 文字コード・書き出し方
 * - Shift_JISは、Windowsの拡張文字（①・㈱・髙など）を含むCP932（SJIS-win）で変換する。
 * - fputcsv()のエスケープ文字は空にしてRFC 4180どおりに出す。既定の「\」のままだと、
 *   Shift_JISの「ソ」「表」などの2バイト目（「\」と同じ0x5C）を特殊文字と誤って扱い、壊すことがある。
 * - 改行はExcelに合わせて\r\n。
 *
 * ■ 数式の無害化（escapeFormula）
 * 会員の名前のように利用者が入力した値が「=」「+」「-」「@」などで始まっていると、
 * Excelで開いたときに数式として実行されうる（CSVインジェクション）。先頭に「'」を付けて
 * 文字列として扱わせる。数値として読める値（-100など）はそのまま。Excelで開かずに
 * 他システムへ渡すCSVでは、「'」が値に残ってしまうので、escapeFormula: falseにする。
 *
 * ■ ダウンロード記録
 * 1回のダウンロードごとに、t_csv_download_logs（App\Models\CsvDownloadLog）へ
 * 名前・日時・操作者・検索条件・IPアドレス・書き出した件数を記録する。書き出す前に
 * 行を作り、書き出し終わったら件数を入れる（途中で中断されても、ダウンロードしようと
 * したことは残る）。
 */
trait CsvDownload
{
    /**
     * CSVに出す項目。見出し => 値の場所（書き方はこのファイル冒頭のコメント参照）。
     */
    abstract private function csvColumns(): array;

    private function downloadCsv(
        Builder $query,
        string $name,
        CsvEncoding $encoding = CsvEncoding::Utf8Bom,
        bool $header = true,
        bool $escapeFormula = true,
    ): StreamedResponse {
        // 書き出しを始める前に、項目の定義を解釈しておく（書き間違いはここで例外になる）
        $columns = new CsvColumnSet(
            $this->csvColumns(),
            customExport: method_exists($this, 'csvCustomColumn')
                ? fn (string $key, Model $record) => $this->csvCustomColumn($key, $record)
                : null,
        );

        // 一覧と同じ検索条件・並び順で絞り込む
        $conditions = [];
        if (method_exists($this, 'applyListConditions')) {
            [$conditions] = $this->applyListConditions($query);
        }

        // 連番・組の横展開の列の数を、絞り込んだデータの最大件数で決める
        $columns->prepareExport($query, $header);
        $headings = $columns->headings();

        // ダウンロード記録（件数は書き出し終わってから入れる）
        $log = CsvDownloadLog::create([
            'name' => $name,
            'operator_id' => Auth::id(),
            'row_count' => 0,
            'conditions' => $conditions,
            'ip' => request()->ip(),
        ]);

        $filename = $name.'_'.now()->format('Ymd_Hi').'.csv';
        $charset = $encoding === CsvEncoding::Sjis ? 'Shift_JIS' : 'UTF-8';

        return response()->streamDownload(function () use ($query, $columns, $headings, $encoding, $header, $escapeFormula, $log) {
            $out = fopen('php://output', 'w');

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

            $log->update(['row_count' => $count]);
        }, $filename, ['Content-Type' => "text/csv; charset={$charset}"]);
    }

    /**
     * 1行を書き出す。数式の無害化と文字コードの変換は、1セルずつ行う。
     */
    private function writeCsvRow($out, array $fields, CsvEncoding $encoding, bool $escapeFormula): void
    {
        foreach ($fields as $i => $field) {
            $field = (string) $field;

            // 数式として実行されうる文字で始まる値は、先頭に「'」を付けて文字列として扱わせる
            // （数値として読める値はそのまま）
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
