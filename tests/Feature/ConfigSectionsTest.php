<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * フレームワークの設定の混ぜ方（Neobit\Core\NeobitServiceProvider）。
 * 既定の値はパッケージの機能ごとのファイルにあり、アプリのconfig/neobit.phpの節に書いたキーが優先する。
 */
class ConfigSectionsTest extends TestCase
{
    public function test_keys_in_the_app_section_win_and_others_come_from_the_default(): void
    {
        // 見本のサイトが書いたキー
        $this->assertSame('layouts.admin', config('neobit.layouts.admin'));
        $this->assertTrue(config('neobit.seo.redirects'));

        // 同じ節で、書いていないキーは既定の値
        $this->assertSame(120, config('neobit.seo.description_length'));
        $this->assertSame(['App\\Enums\\'], config('neobit.code_table.enum_namespaces'));
    }

    public function test_sections_the_app_does_not_write_come_from_the_default(): void
    {
        $this->assertSame('app/admin', config('neobit.route.admin_path'));
        $this->assertSame('.version', config('neobit.file_watch.release_file'));
        $this->assertSame(120, config('neobit.security.admin_requests_per_minute'));
        $this->assertSame(10240, config('neobit.upload.max_kb'));
        $this->assertSame(365, config('neobit.cleanup.operation_log_days'));
        $this->assertStringContainsString('required-mark', config('neobit.form.required_mark.admin'));
    }

    public function test_wordpress_is_null_unless_the_app_writes_the_section(): void
    {
        // 節があることが「WordPressを置く」の印なので、既定の値を持たない
        $this->assertNull(config('neobit.wordpress'));
    }

    public function test_old_top_level_keys_are_gone(): void
    {
        $this->assertNull(config('neobit.admin_path'));
        $this->assertNull(config('upload'));
        $this->assertNull(config('cleanup'));
        $this->assertNull(config('form'));
        $this->assertNull(config('app.trusted_proxies'));
    }
}
