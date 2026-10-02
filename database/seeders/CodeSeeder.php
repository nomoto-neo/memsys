<?php

namespace Database\Seeders;

use App\Models\Code;
use Illuminate\Database\Seeder;

class CodeSeeder extends Seeder
{
    /**
     * DBで管理するコード表（t_codes）のサンプル。
     *
     * コード表ごとに、今ある行を消してから入れ直すので、何度実行しても
     * 重複しない（管理画面で書き換えた内容は、このサンプルに戻る）。
     */
    public function run(): void
    {
        $tables = [
            'gender' => [
                '1' => '男性',
                '2' => '女性',
            ],
            'contact' => [
                '1' => 'LINE',
                '2' => '電話',
                '3' => 'メール',
            ],
        ];

        foreach ($tables as $type => $rows) {
            Code::where('type', $type)->delete();

            $order = 0;
            foreach ($rows as $code => $name) {
                Code::create([
                    'type' => $type,
                    'code' => (string) $code,
                    'name' => $name,
                    'sort_order' => $order++,
                ]);
            }
        }
    }
}
