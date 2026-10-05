<?php

namespace Database\Seeders;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CompanySeeder extends Seeder
{
    /**
     * 見本の企業会員を1件作る。承認済みの企業と、その担当者1人。
     *
     * 企業IDは、決まった値（sample）にしている。何度シーダーを実行しても、同じ企業を使い回すため。
     * 新しく登録した企業の企業IDは、idと同じ番号になる（App\Models\Company）。
     *
     * ログインは、企業ID「sample」・担当者ID「tanto」・パスワード「testtest」。
     * 2段階目の確認コードは、担当者のメールアドレスに届く。手元で試すときは、emailを
     * 自分が受け取れるアドレスに書き換えてから実行する。
     */
    public function run(): void
    {
        $company = Company::updateOrCreate(
            ['code' => 'sample'],
            [
                'name' => '株式会社サンプル',
                'kana' => 'カブシキガイシャサンプル',
                'representative' => '見本 一郎',
                'zip' => '100-0001',
                'prefecture' => 13,
                'address' => '千代田区千代田1-1',
                'tel' => '03-1234-5678',
                'url' => 'https://example.com/',
                'status' => CompanyStatus::Approved,
            ]
        );

        CompanyUser::updateOrCreate(
            ['company_id' => $company->id, 'login_id' => 'tanto'],
            [
                'name' => '担当 花子',
                'email' => 'tanto@example.com',
                'password' => Hash::make('testtest'),
            ]
        );
    }
}
