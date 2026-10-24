<?php

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Seeder;

/**
 * 動作確認用のダミー会員を30人登録するSeeder。1人目には、経歴の見本を3行入れる。
 *
 * 実行方法（他のSeederを巻き込まず、これだけ流す）：
 *   php artisan db:seed --class=MemberSeeder
 *
 * 何度も実行するとその都度30人ずつ増える（email重複はFakerの
 * unique()が防ぐが、既存の本物の会員データと衝突する可能性は
 * ゼロではないので、本番のDBには流さないこと）。
 */
class MemberSeeder extends Seeder
{
    public function run(): void
    {
        // 退会は会員の行を物理削除する方式なので、退会済みの会員は作らない。
        $members = Member::factory()->count(30)->create();

        // 経歴（学歴・職歴）の見本。履歴書のPDFの学歴・職歴の欄に出るのを確かめられるよう、1人目にだけ入れる
        $members->first()->careers()->createMany([
            ['year' => 2016, 'month' => 4, 'content' => '〇〇大学 経済学部 入学'],
            ['year' => 2020, 'month' => 3, 'content' => '〇〇大学 経済学部 卒業'],
            ['year' => 2020, 'month' => 4, 'content' => '株式会社〇〇 入社'],
        ]);
    }
}
