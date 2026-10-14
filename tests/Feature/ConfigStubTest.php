<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * フレームワークの設定の見本（packages/neobit/core/stubs/config/）。
 * php artisan vendor:publish --tag=neobit-config で、アプリの config/ へ写すもの。
 * 既定値（packages/neobit/core/config/）と同じ値に // を付けて見せているので、
 * 既定値を変えたのに見本を直し忘れると、見本が嘘になる。
 */
class ConfigStubTest extends TestCase
{
    /** 見本を持つ設定の名前 */
    private const CONFIG_NAMES = [
        'neobit',
        'upload',
        'cleanup',
    ];

    public function test_stub_with_comments_removed_equals_the_defaults(): void
    {
        foreach (self::CONFIG_NAMES as $name) {
            $defaults = require $this->corePath("config/{$name}.php");

            $this->assertSame($defaults, $this->evaluate($this->uncomment($this->stub($name))), "{$name}の見本が、既定値と違います。");
        }
    }

    public function test_stub_as_it_is_overrides_nothing(): void
    {
        // 写したままの見本は、値を1つも持たない。持っていると、その値が既定値の代わりに固定される
        foreach (self::CONFIG_NAMES as $name) {
            $this->assertSame([], $this->evaluate($this->stub($name)), "{$name}の見本に、// の付いていない値があります。");
        }
    }

    /** 見本のファイルの中身 */
    private function stub(string $name): string
    {
        return file_get_contents($this->corePath("stubs/config/{$name}.php"));
    }

    /**
     * 値の行の頭の // を外す。値の行は、// の後ろがキーや値（'）・配列の括弧（[ ]）・
     * クラス名（\）で始まる行。説明のコメントは、そのまま残す。
     */
    private function uncomment(string $code): string
    {
        return preg_replace('/^(\s*)\/\/ (\s*)(?=[\'\[\]\\\\])/m', '$1$2', $code);
    }

    /** 設定のファイルの中身を実行して、返した配列を受け取る */
    private function evaluate(string $code): array
    {
        return eval('?>'.$code);
    }

    private function corePath(string $path): string
    {
        return base_path('packages/neobit/core/'.$path);
    }
}
