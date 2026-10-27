<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Neobit\Core\Rules\NotReservedPathRule;
use Neobit\Core\Rules\SlugRule;
use Tests\TestCase;

/**
 * slug（URLの名前）の検証ルール（Neobit\Core\Rules\SlugRule・NotReservedPathRule）。
 * 固定ページの画面からの確かめは、PageTestにある。
 */
class SlugRuleTest extends TestCase
{
    private function passes(string $value, array $rules): bool
    {
        return Validator::make(['slug' => $value], ['slug' => $rules])->passes();
    }

    public function test_slug_rule_accepts_only_url_friendly_names(): void
    {
        foreach (['about', 'about-us', 'about_us', '2026', 'a1'] as $slug) {
            $this->assertTrue($this->passes($slug, [new SlugRule()]), $slug);
        }

        // 大文字・空白・先頭のハイフンやアンダーバー・「/」は、1区切りの名前では通さない
        foreach (['About', 'about us', '-about', '_about', 'service/price', 'あいう'] as $slug) {
            $this->assertFalse($this->passes($slug, [new SlugRule()]), $slug);
        }

        // 通さないときの文は、通常のvalidation.regexの文
        $validator = Validator::make(['slug' => 'About'], ['slug' => [new SlugRule()]]);
        $this->assertSame('この項目の形式が正しくありません。', $validator->errors()->first('slug'));
    }

    public function test_nested_slug_rule_accepts_names_joined_with_slashes(): void
    {
        foreach (['service/price', 'service/price/2026', 'company_profile/top-message'] as $slug) {
            $this->assertTrue($this->passes($slug, [new SlugRule(nested: true)]), $slug);
        }

        // 先頭と末尾の「/」と「//」、区切りの先頭のハイフンは通さない
        foreach (['/about', 'about/', 'a//b', 'a/-b', 'a/_b'] as $slug) {
            $this->assertFalse($this->passes($slug, [new SlugRule(nested: true)]), $slug);
        }

        // ルートのwhere()に使うパターンと、検証のパターンは同じ
        $this->assertSame(1, preg_match('#^'.SlugRule::NESTED_PATTERN.'$#', 'service/price'));
    }

    public function test_reserved_path_rule_rejects_names_used_by_other_screens(): void
    {
        // ほかの画面のURLの最初の区切りと同じ名前と、その下に続く名前は断る
        foreach (['login', 'news', 'app', 'app/admin', 'news/archive'] as $slug) {
            $this->assertFalse($this->passes($slug, [new NotReservedPathRule()]), $slug);
        }

        $this->assertTrue($this->passes('aboutus', [new NotReservedPathRule()]));
        $this->assertTrue($this->passes('service/price', [new NotReservedPathRule()]));

        $validator = Validator::make(['slug' => 'login'], ['slug' => [new NotReservedPathRule()]]);
        $this->assertSame('この名前は、ほかの画面のURLで使っているので使えません。', $validator->errors()->first('slug'));
    }
}
