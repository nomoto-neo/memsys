<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 会員・企業・一斉メールのテーブルの列を、中身が分かる名前と持ち方にそろえる。
 *
 * - t_members.birthdateをbirthday、t_companies.representativeをceo_nameに変える。
 *   ceo_nameは代表者の名前で、株式会社に限らず、団体の代表者も入れる
 * - 状態の列（t_bulk_mails.status・t_companies.status）を、文字列から数値に変える。
 *   意味は列挙型（App\Enums\BulkMailStatus・CompanyStatus）に書いてある。今の文字列は、同じ状態の番号に書き換える
 */
return new class extends Migration
{
    /** 状態の列。テーブル => [今の文字列 => 番号] */
    private const STATUS_VALUES = [
        't_bulk_mails' => [
            'sending' => 1,
            'finished' => 2,
        ],
        't_companies' => [
            'pending' => 1,
            'approved' => 2,
            'suspended' => 9,
        ],
    ];

    public function up(): void
    {
        Schema::table('t_members', function (Blueprint $table) {
            $table->renameColumn('birthdate', 'birthday');
        });

        Schema::table('t_companies', function (Blueprint $table) {
            $table->renameColumn('representative', 'ceo_name');
        });

        // 文字列を番号に書き換えてから、列の型を変える
        foreach (self::STATUS_VALUES as $tableName => $values) {
            foreach ($values as $from => $to) {
                DB::table($tableName)->where('status', $from)->update(['status' => (string) $to]);
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedTinyInteger('status')->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::STATUS_VALUES as $tableName => $values) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('status', 20)->change();
            });

            foreach ($values as $to => $from) {
                DB::table($tableName)->where('status', (string) $from)->update(['status' => $to]);
            }
        }

        Schema::table('t_companies', function (Blueprint $table) {
            $table->renameColumn('ceo_name', 'representative');
        });

        Schema::table('t_members', function (Blueprint $table) {
            $table->renameColumn('birthday', 'birthdate');
        });
    }
};
