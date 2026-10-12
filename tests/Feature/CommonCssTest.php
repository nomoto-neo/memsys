<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 全部の画面で使うCSS（resources/css/app.css）。訪問者向け・管理画面・企業会員の3つの
 * レイアウトが読み込む。
 * 確認コードの入力欄には、見本の文字「000000」を出す。
 */
class CommonCssTest extends TestCase
{
    /** 共通のCSSを読み込む<link>の形 */
    private const CSS_LINK = '#<link[^>]+/build/assets/app-[^"]+\.css#';

    public function test_every_layout_loads_the_common_css(): void
    {
        // 訪問者向け・企業会員・管理画面のレイアウトの画面
        foreach (['/password/reset', '/company/password/reset', route('admin.login')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertMatchesRegularExpression(self::CSS_LINK, $html, $url);
        }
    }

    public function test_code_input_has_a_placeholder(): void
    {
        foreach (['/password/reset', '/company/password/reset'] as $url) {
            $this->get($url)->assertOk()->assertSee('placeholder="000000"', false);
        }
    }

    public function test_common_css_has_the_rules_every_screen_relies_on(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        // 見本の文字の濃さ
        $this->assertMatchesRegularExpression('/::placeholder\s*\{\s*opacity:\s*0\.5\s*!important;/', $css);

        // エラー欄の表示、読み取り専用の表示、WYSIWYGの本文、削除済みの行
        foreach ([
            '.invalid-feedback:not(:empty)',
            '.form-control[readonly]',
            '.form-check-input:disabled:checked',
            '.wysiwyg-content img',
            '.table > tbody > tr.row-deleted > td',
        ] as $selector) {
            $this->assertStringContainsString($selector.' {', $css, $selector);
        }
    }
}
