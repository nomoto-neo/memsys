<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * 動作確認用のサンプルカテゴリーを3件作る。
     *
     * StaffSeederと同じくupdateOrCreate()にしているので、何度実行しても
     * 同じ名前のカテゴリーが重複して増えることはない。display_orderは
     * ここで指定した順（重要なお知らせ→新商品→イベント）で並ぶ。
     */
    public function run(): void
    {
        $names = ['重要なお知らせ', '新商品', 'イベント'];

        foreach ($names as $order => $name) {
            Category::updateOrCreate(
                ['name' => $name],
                ['display_order' => $order]
            );
        }
    }
}
