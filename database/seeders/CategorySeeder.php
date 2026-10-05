<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * 動作確認用のサンプルカテゴリーを3件作る。
     *
     * StaffSeederと同じくfirstOrCreate()にしているので、何度実行しても
     * 同じ名前のカテゴリーが重複して増えることはない。すでにあるカテゴリーには何もしないので、
     * 管理画面で並び替えた順番も、シーダーの値に戻らない。新しく作るカテゴリーのdisplay_orderは、
     * ここで指定した順（重要なお知らせ→新商品→イベント）になる。
     */
    public function run(): void
    {
        $names = ['重要なお知らせ', '新商品', 'イベント'];

        foreach ($names as $order => $name) {
            Category::firstOrCreate(
                ['name' => $name],
                ['display_order' => $order]
            );
        }
    }
}
