<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * デフォルトで入っていた User::factory()->create(...) は
     * もう存在しないUserモデルを参照していたため削除。
     * ステップ③（会員一覧・検索）でダミー会員データを投入する
     * シーダーをここに追加する予定。
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);
        $this->call(StaffSeeder::class);
        $this->call(CodeSeeder::class);
        $this->call(CompanySeeder::class);
        $this->call(PageSeeder::class);
    }
}
