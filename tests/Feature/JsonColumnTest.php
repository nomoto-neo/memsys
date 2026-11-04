<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\CsvDownloadLog;
use Neobit\Core\Models\OperationLog;
use Neobit\Core\Models\Setting;
use Tests\TestCase;

/**
 * フレームワークのテーブルのJSONの列。日本語を日のような表記に変えずに、そのまま保存する。
 */
class JsonColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_value_keeps_japanese(): void
    {
        Setting::put('seo.description', '既定の説明文');

        $this->assertSame('"既定の説明文"', DB::table('settings')->where('key', 'seo.description')->value('value'));
        $this->assertSame(['seo.description' => '既定の説明文'], Setting::values(['seo.description']));
    }

    public function test_operation_log_changed_fields_keeps_japanese(): void
    {
        $log = OperationLog::create([
            'action' => OperationLogAction::SeoSettingsChange,
            'changed_fields' => ['既定の説明文'],
        ]);

        $this->assertSame('["既定の説明文"]', DB::table('operation_logs')->where('id', $log->id)->value('changed_fields'));
        $this->assertSame(['既定の説明文'], $log->fresh()->changed_fields);
    }

    public function test_csv_download_log_conditions_keeps_japanese(): void
    {
        $log = CsvDownloadLog::create([
            'name' => '会員一覧',
            'conditions' => ['free_word' => '山田'],
        ]);

        $this->assertSame('{"free_word":"山田"}', DB::table('csv_download_logs')->where('id', $log->id)->value('conditions'));
        $this->assertSame(['free_word' => '山田'], $log->fresh()->conditions);
    }
}
