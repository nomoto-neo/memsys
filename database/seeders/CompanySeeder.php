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
     * 見本の企業会員を作る。承認済みの企業と、その担当者1人。それと、既存のシステムから
     * 移した企業の見本を1件。
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

        // 既存のシステムから移した企業の見本。初回のログインでの登録
        // （App\Http\Controllers\Company\FirstLoginSetupController）を試すためのもの。
        // 最初の担当者は、氏名とメールアドレスが空。ログインは、企業ID「legacy」・担当者ID「admin」・
        // パスワード「testtest」。登録の画面で照合する電話番号は「03-9876-5432」。
        // 登録が済むと担当者IDが変わるので、担当者が1人もいないときだけ作る
        $legacy = Company::firstOrCreate(
            ['code' => 'legacy'],
            [
                'name' => '株式会社移行見本',
                'tel' => '03-9876-5432',
                'status' => CompanyStatus::Approved,
            ]
        );

        if (! $legacy->users()->exists()) {
            CompanyUser::create([
                'company_id' => $legacy->id,
                'login_id' => 'admin',
                'password' => Hash::make('testtest'),
            ]);
        }
    }
}
