<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * フレームワークのテーブルの列の名前を、中身が分かる名前に変える。
 * code_tables.nameをlabel、ip_blocks.blocked_untilをexpires_at、seo_metas.imageをimage_urlにする。
 * SEO設定の値（settingsテーブル）のキーも、seo.imageをseo.image_url、seo.organization_logoをseo.organization_logo_url、
 * seo.pagesの中のimageをimage_urlに変える。
 * 新しく入れたプロジェクトは、最初から新しい名前で作られるので、古い名前があるときだけ変える。
 */
return new class extends Migration
{
    /** SEO設定の値を置くキャッシュのキー（Neobit\Core\Seo\SeoSettings） */
    private const SEO_CACHE_KEY = 'neobit-seo-settings';

    /** settingsテーブルのキー。古い名前 => 新しい名前 */
    private const SETTING_KEYS = [
        'seo.image' => 'seo.image_url',
        'seo.organization_logo' => 'seo.organization_logo_url',
    ];

    public function up(): void
    {
        $this->renameColumn('code_tables', 'name', 'label');
        $this->renameColumn('ip_blocks', 'blocked_until', 'expires_at');
        $this->renameColumn('seo_metas', 'image', 'image_url');

        // 期限の列の索引の名前も、新しく作ったときの名前にそろえる
        if (Schema::hasIndex('ip_blocks', 'ip_blocks_blocked_until_index')) {
            Schema::table('ip_blocks', function (Blueprint $table) {
                $table->renameIndex('ip_blocks_blocked_until_index', 'ip_blocks_expires_at_index');
            });
        }

        $this->renameSettings(self::SETTING_KEYS, 'image', 'image_url');
    }

    public function down(): void
    {
        $this->renameColumn('code_tables', 'label', 'name');
        $this->renameColumn('ip_blocks', 'expires_at', 'blocked_until');
        $this->renameColumn('seo_metas', 'image_url', 'image');

        if (Schema::hasIndex('ip_blocks', 'ip_blocks_expires_at_index')) {
            Schema::table('ip_blocks', function (Blueprint $table) {
                $table->renameIndex('ip_blocks_expires_at_index', 'ip_blocks_blocked_until_index');
            });
        }

        $this->renameSettings(array_flip(self::SETTING_KEYS), 'image_url', 'image');
    }

    /** 列の名前を変える。古い名前の列が無ければ、何もしない */
    private function renameColumn(string $tableName, string $from, string $to): void
    {
        if (! Schema::hasColumn($tableName, $from)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($from, $to) {
            $table->renameColumn($from, $to);
        });
    }

    /**
     * SEO設定の値のキーを変える。ページごとの値（seo.pages）は、ルートごとの中のキーを変える。
     * 画面がキャッシュから古い形を読まないよう、最後にキャッシュを消す。
     *
     * @param  array<string, string>  $keys  古いキー => 新しいキー
     */
    private function renameSettings(array $keys, string $pageFrom, string $pageTo): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach ($keys as $from => $to) {
            DB::table('settings')->where('key', $from)->update(['key' => $to]);
        }

        // ページごとの値は、ルート名 => [title, description, image, noindex] のJSON
        $pages = DB::table('settings')->where('key', 'seo.pages')->first();
        if ($pages !== null) {
            $values = (array) json_decode((string) $pages->value, true);
            foreach ($values as $routeName => $page) {
                if (is_array($page) && array_key_exists($pageFrom, $page)) {
                    $page[$pageTo] = $page[$pageFrom];
                    unset($page[$pageFrom]);
                    $values[$routeName] = $page;
                }
            }
            DB::table('settings')->where('id', $pages->id)
                ->update(['value' => json_encode($values, JSON_UNESCAPED_UNICODE)]);
        }

        Cache::forget(self::SEO_CACHE_KEY);
    }
};
