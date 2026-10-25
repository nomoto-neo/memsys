<?php

namespace Neobit\Core\Seo;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\Setting;
use Neobit\Core\Operations\OperationRecorder;

/**
 * 管理画面の「SEO設定」の値。サイト全体の既定（タイトルの形・説明文・シェアの画像・Search Consoleの確認タグ・
 * 運営者の名前とロゴ）と、記事を持たないページごとのタイトル・説明文・画像・検索に出さない、を持つ。
 * 画面はNeobit\Core\Http\Controllers\SeoSettingsController、値を使うのはNeobit\Core\Seo\Seo。
 *
 * 画面で空欄にした既定は、設定ファイル（config/neobit.phpのseo）の値を使う。画面に出すページは、
 * 設定のseo.page_routes（ルート名 => 画面に出す名前）で決める。そこに無いルートの値は使わない。
 *
 * 訪問者の全部のページで読むので、画面の設定はキャッシュに置き、保存のたびに作り直す。設定のテーブルが
 * まだ無いとき（マイグレーションの前）は、画面の設定が無いものとして動く。
 */
final class SeoSettings
{
    // ---- 設定の置き場所 ----

    /** 画面の設定を置くキャッシュのキー */
    private const CACHE_KEY = 'neobit-seo-settings';

    /** サイト全体の既定の名前 => 画面の設定のキー（settingsテーブル） */
    private const DEFAULT_KEYS = [
        'title_format' => 'seo.title_format',
        'description' => 'seo.description',
        'image' => 'seo.image',
        'site_verification' => 'seo.site_verification',
        'organization_name' => 'seo.organization_name',
        'organization_logo' => 'seo.organization_logo',
    ];

    /** ページごとの設定のキー（settingsテーブル） */
    private const PAGES_KEY = 'seo.pages';

    // ---- 操作ログ ----

    /** サイト全体の既定の、操作ログに出す名前 */
    private const DEFAULT_LABELS = [
        'title_format' => 'タイトルの形',
        'description' => '既定の説明文',
        'image' => '既定のシェアの画像',
        'site_verification' => 'Search Consoleの確認タグ',
        'organization_name' => '運営者の名前',
        'organization_logo' => '運営者のロゴ',
    ];

    // ---- 値 ----

    /** タイトルの形。画面で空欄なら設定ファイルの値 */
    public static function titleFormat(): string
    {
        return self::settings()['title_format'] ?? (string) config('neobit.seo.title_format');
    }

    /** 既定の説明文。画面で空欄なら設定ファイルの値 */
    public static function description(): string
    {
        return self::settings()['description'] ?? (string) config('neobit.seo.description');
    }

    /** 既定のシェアの画像。画面で空欄なら設定ファイルの値。どちらも無ければnull */
    public static function image(): ?string
    {
        return self::settings()['image'] ?? config('neobit.seo.image');
    }

    /** Search Consoleの確認タグのcontentの値。無ければ空 */
    public static function siteVerification(): string
    {
        return (string) self::settings()['site_verification'];
    }

    /** 運営者の名前。構造化データで、記事を書いた側とサイトの運営者として出す。画面・設定ファイルとも空ならサイト名 */
    public static function organizationName(): string
    {
        $name = self::settings()['organization_name'] ?? (string) config('neobit.seo.organization_name');

        return $name !== '' ? $name : (string) config('app.site_name');
    }

    /** 運営者のロゴ。画面で空欄なら設定ファイルの値。どちらも無ければnull */
    public static function organizationLogo(): ?string
    {
        return self::settings()['organization_logo'] ?? config('neobit.seo.organization_logo');
    }

    /**
     * ページごとの設定。設定のseo.page_routesに無いルートや、画面で何も入れていないルートはnull。
     *
     * @return array{title: ?string, description: ?string, image: ?string, noindex: bool}|null
     */
    public static function page(string $routeName): ?array
    {
        if (! array_key_exists($routeName, self::pageRoutes())) {
            return null;
        }

        return self::settings()['pages'][$routeName] ?? null;
    }

    /** 画面に出す、記事を持たないページ。ルート名 => 画面に出す名前 */
    public static function pageRoutes(): array
    {
        return (array) config('neobit.seo.page_routes');
    }

    // ---- 画面の設定 ----

    /**
     * 画面で入れた設定。空欄の既定はnull。ページは、何か入れたルートだけを持つ。
     * DBを毎回読まないよう、キャッシュに置く。
     *
     * @return array{title_format: ?string, description: ?string, image: ?string, site_verification: ?string, organization_name: ?string, organization_logo: ?string, pages: array<string, array{title: ?string, description: ?string, image: ?string, noindex: bool}>}
     */
    public static function settings(): array
    {
        $defaults = array_fill_keys(array_keys(self::DEFAULT_KEYS), null) + ['pages' => []];

        // キャッシュに前の版の形が残っていても動くよう、足りない項目は既定の値で補う
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached + $defaults;
        }

        // 設定のテーブルがまだ無いとき（マイグレーションの前）は、画面の設定が無いものとして動く。
        // キャッシュには置かず、テーブルができたら読めるようにする
        try {
            $values = Setting::values([...array_values(self::DEFAULT_KEYS), self::PAGES_KEY]);
        } catch (QueryException) {
            return $defaults;
        }

        $settings = ['pages' => (array) ($values[self::PAGES_KEY] ?? [])];
        foreach (self::DEFAULT_KEYS as $name => $key) {
            $settings[$name] = $values[$key] ?? null;
        }

        Cache::forever(self::CACHE_KEY, $settings);

        return $settings;
    }

    /**
     * 画面の設定を保存して、操作ログに変えた項目の名前を残す。何も変わっていなければ、操作ログには残さない。
     * 空の値はnullにし、ページは何も入れていないルートを持たない。
     *
     * @param  array<string, ?string>  $defaults  サイト全体の既定。DEFAULT_KEYSの名前 => 値
     * @param  array<string, array{title?: ?string, description?: ?string, image?: ?string, noindex?: mixed}>  $pages  ルート名 => 値
     */
    public static function save(array $defaults, array $pages): void
    {
        $before = self::settings();

        $after = ['pages' => []];
        foreach (array_keys(self::DEFAULT_KEYS) as $name) {
            $after[$name] = self::nullIfBlank($defaults[$name] ?? null);
        }

        // ページは、何か入れたルートだけを持つ
        foreach ($pages as $routeName => $page) {
            $values = [
                'title' => self::nullIfBlank($page['title'] ?? null),
                'description' => self::nullIfBlank($page['description'] ?? null),
                'image' => self::nullIfBlank($page['image'] ?? null),
                'noindex' => ($page['noindex'] ?? null) == '1',
            ];
            if (array_filter($values) !== []) {
                $after['pages'][$routeName] = $values;
            }
        }

        foreach (self::DEFAULT_KEYS as $name => $key) {
            Setting::put($key, $after[$name]);
        }
        Setting::put(self::PAGES_KEY, $after['pages']);

        Cache::forget(self::CACHE_KEY);

        // 変えた項目の名前を集める。説明文などの値は長いので残さない
        $changed = [];
        foreach (self::DEFAULT_LABELS as $name => $label) {
            if ($before[$name] !== $after[$name]) {
                $changed[] = $label;
            }
        }
        foreach (self::pageRoutes() as $routeName => $label) {
            if (($before['pages'][$routeName] ?? null) !== ($after['pages'][$routeName] ?? null)) {
                $changed[] = $label;
            }
        }

        if ($changed !== []) {
            OperationRecorder::record(OperationLogAction::SeoSettingsChange, detail: [
                'changed' => '変えた項目：'.implode('・', $changed),
            ]);
        }
    }

    // ---- 入力の確かめ ----

    /**
     * Search Consoleの確認タグの入力から、contentの値を取り出す。Search Consoleが示す<meta …>のタグを
     * そのまま貼ったときはcontentの値を、値だけを入れたときはそのまま返す。空ならnull
     */
    public static function extractVerificationContent(?string $value): ?string
    {
        $value = trim((string) $value);

        if (preg_match('/content\s*=\s*["\']([^"\']*)["\']/i', $value, $matches)) {
            $value = trim($matches[1]);
        }

        return $value === '' ? null : $value;
    }

    /** 前後の空白を除き、空ならnull */
    private static function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
