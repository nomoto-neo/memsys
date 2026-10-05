<?php

namespace Database\Seeders;

use App\Models\Code;
use Illuminate\Database\Seeder;

class CodeSeeder extends Seeder
{
    /**
     * DBで管理するコード表（t_codes）のサンプル。
     *
     * コード表ごとに、行が1つも無いときだけ入れるので、何度実行しても重複しない。
     * 行があるコード表には何もしない。管理画面で書き換えた内容や、足したり消したりした行を、
     * このサンプルに戻さないため。作り直したいときは、そのコード表の行を消してから実行する。
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
            // 行があるコード表は、そのままにする
            if (Code::where('type', $type)->exists()) {
                continue;
            }

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
