<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * 動作確認用の固定ページを1件作る。/aboutus で開ける「会社概要」。
     *
     * firstOrCreate()にしているので、何度実行しても増えない。すでにあるページには何もしないので、
     * 管理画面で書き直した本文も、シーダーの値に戻らない。
     * 本文は、SunEditorが保存する形（表は<figure>で包み、セルの中は<div>）に合わせてある。
     */
    public function run(): void
    {
        Page::firstOrCreate(
            ['slug' => 'aboutus'],
            [
                'title' => '会社概要',
                'disp_flg' => true,
                'body' => <<<'HTML'
                    <h2>会社概要</h2>
                    <p>このページは、管理画面の「固定ページ一覧」から書き換えられます。本文は<strong>SunEditor</strong>で編集します。</p>
                    <figure class="se-flex-component se-input-component se-scroll-figure-x">
                        <table>
                            <tbody>
                                <tr><th><div>会社名</div></th><td><div>株式会社サンプル</div></td></tr>
                                <tr><th><div>所在地</div></th><td><div>東京都千代田区1-1-1</div></td></tr>
                                <tr><th><div>設立</div></th><td><div>2026年4月</div></td></tr>
                                <tr><th><div>事業内容</div></th><td><div>ウェブサイトの企画・制作・運用</div></td></tr>
                            </tbody>
                        </table>
                    </figure>
                    <blockquote><p>引用や、見出し・リスト・画像・表も、エディタのボタンから入れられます。</p></blockquote>
                    HTML,
            ],
        );
    }
}
