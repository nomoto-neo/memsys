<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * フレームワークの設定の見本（packages/neobit/core/stubs/config/neobit.php）。
 * php artisan vendor:publish --tag=neobit-config で、アプリの config/ へ写すもの。
 */
class ConfigStubTest extends TestCase
{
    public function test_stub_as_it_is_overrides_nothing(): void
    {
        // 写したままの見本は、値を1つも持たない。持っていると、その値が既定値の代わりに固定される
        $this->assertSame([], eval('?>'.$this->stub()));
    }

    public function test_stub_shows_every_section(): void
    {
        // パッケージの既定の値のファイルと、WordPressの節を、全部見せていること
        $sections = array_map(fn (string $path) => basename($path, '.php'), glob(base_path('packages/neobit/core/config/neobit/*.php')));

        foreach ([...$sections, 'wordpress'] as $section) {
            $this->assertStringContainsString("// '{$section}' => ", $this->stub(), "{$section}の節が見本にありません");
        }
    }

    /** 見本のファイルの中身 */
    private function stub(): string
    {
        return file_get_contents(base_path('packages/neobit/core/stubs/config/neobit.php'));
    }
}
