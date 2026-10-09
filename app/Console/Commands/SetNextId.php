<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * テーブルの、次に登録する行のid（連番の始まり）を決めるコマンド。
 * php artisan app:set-next-id t_members 100001 で動く。
 *
 * サイトを開ける前に、新しく登録する会員などのidを、区切りのよい番号から始めたいときに使う。
 * 既存のシステムのデータを元のidのまま取り込んだ後、新しい登録を別の範囲の番号にすれば、
 * 番号で見分けられる。
 *
 * ■ 使い方
 * - 番号を書かなければ、今の状態（いちばん大きいidと、次に振るid）を出すだけ
 * - 番号を書くと、確かめてから設定する。--forceを付けると確かめずに設定する
 *
 * ■ 決まり
 * - 番号は、今のいちばん大きいidより大きいこと。小さい番号を指定しても、DBは黙って
 *   「いちばん大きいidの次」から振るので、効いたように見えて効かない。ここで断る
 * - 一度大きい番号で登録されると、その下の番号には戻せない。サイトを開ける前に決める
 * - DBが覚える値なので、サーバーごとに実行する。開発サーバーで設定しても、本番には引き継がれない
 * - MariaDBとMySQLだけで使える
 */
#[Signature('app:set-next-id {table : テーブルの名前} {id? : 次に登録する行のid（書かなければ今の状態を出すだけ）} {--force : 確かめずに設定する}')]
#[Description('テーブルの、次に登録する行のid（連番の始まり）を決める')]
class SetNextId extends Command
{
    public function handle(): int
    {
        $table = (string) $this->argument('table');

        if (! in_array(DB::connection()->getDriverName(), ['mariadb', 'mysql'], true)) {
            $this->error('このコマンドは、MariaDBとMySQLだけで使えます。');

            return self::FAILURE;
        }

        // テーブルの名前はSQLにそのまま書くので、実際にあるテーブルだけを受け付ける
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            $this->error("テーブル「{$table}」が無いか、idの列がありません。");

            return self::FAILURE;
        }

        $maxId = (int) DB::table($table)->max('id');
        $this->table(['テーブル', 'いちばん大きいid', '次に振るid'], [[$table, $maxId, $this->nextId($table)]]);

        // 番号を書いていなければ、今の状態を出すだけ
        if ($this->argument('id') === null) {
            return self::SUCCESS;
        }

        $nextId = filter_var($this->argument('id'), FILTER_VALIDATE_INT);

        if ($nextId === false || $nextId <= $maxId) {
            $this->error("次に振るidは、今のいちばん大きいid（{$maxId}）より大きい整数にしてください。");

            return self::FAILURE;
        }

        // 戻せない操作なので、確かめる。--no-interactionで動かすときは、--forceが要る
        if (! $this->option('force') && ! $this->confirm("「{$table}」に次に登録する行のidを、{$nextId}にします。よろしいですか？")) {
            $this->info('取りやめました。');

            return self::SUCCESS;
        }

        DB::statement('ALTER TABLE '.DB::getQueryGrammar()->wrapTable($table).' AUTO_INCREMENT = '.$nextId);

        // 実際に効いたかを、DBから読み直して確かめる
        $actual = $this->nextId($table);

        if ($actual !== $nextId) {
            $this->error("設定できませんでした。次に振るidは、{$actual}のままです。");

            return self::FAILURE;
        }

        Log::info('次に登録する行のidを設定しました。', ['table' => $table, 'next_id' => $nextId]);
        $this->info("「{$table}」に次に登録する行のidを、{$nextId}にしました。");

        return self::SUCCESS;
    }

    /** DBが覚えている、次に振るid。SHOW TABLE STATUSは、テーブルの定義からその時点の値を返す */
    private function nextId(string $table): int
    {
        $status = DB::selectOne('SHOW TABLE STATUS WHERE Name = ?', [DB::getTablePrefix().$table]);

        return (int) $status->Auto_increment;
    }
}
