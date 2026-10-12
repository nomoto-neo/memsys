<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 入力欄の見本の文字（placeholder）。確認コードの欄には「000000」を出し、濃さは、
 * 全部の画面で読み込む共通のCSS（resources/css/app.css）でそろえる。
 */
class PlaceholderTest extends TestCase
{
    public function test_code_input_has_a_placeholder_and_the_common_css_is_loaded(): void
    {
        // 訪問者向けと企業会員のレイアウトの画面。どちらも、確認コードの欄がある
        foreach (['/password/reset', '/company/password/reset'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('placeholder="000000"', $html, $url);
            $this->assertMatchesRegularExpression('#<link[^>]+/build/assets/app-[^"]+\.css#', $html, $url);
        }
    }

    public function test_admin_layout_loads_the_common_css(): void
    {
        $html = $this->get(route('admin.login'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<link[^>]+/build/assets/app-[^"]+\.css#', $html);
    }

    public function test_common_css_makes_placeholders_lighter(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression('/::placeholder\s*\{\s*opacity:\s*0\.5\s*!important;/', $css);
    }
}
