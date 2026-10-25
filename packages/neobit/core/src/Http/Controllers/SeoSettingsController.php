<?php

namespace Neobit\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Neobit\Core\Seo\Seo;
use Neobit\Core\Seo\SeoSettings;
use Throwable;

/**
 * SEO設定の画面。サイト全体の既定（タイトルの形・説明文・シェアの画像・Search Consoleの確認タグ・運営者の名前とロゴ）と、
 * 記事を持たないページごとのタイトル・説明文・画像・検索に出さないを、運用する人が管理画面から変える。
 * 値の持ち方と使い方は、Neobit\Core\Seo\SeoSettingsにある。画面に出すページは、設定のseo.page_routesで決める。
 *
 * ルートはアプリが書く。管理者だけが入れるグループの中に、「頭.index」（GET）と「頭.update」（PATCH）の組で
 * 名前を付ける。頭はアプリが決めてよい。このコントローラーは、呼ばれたルートの名前から、組になるもう一方の名前を作る。
 *
 * 項目が少なく、間違えてもすぐ直せるので、確認画面は挟まない。
 */
class SeoSettingsController
{
    // ---- ルート ----

    /** 表示・保存のルートの名前の末尾 */
    private const ACTIONS = ['index', 'update'];

    // ---- 入力の形 ----

    /** Search Consoleの確認タグのcontentの値の形 */
    private const VERIFICATION_PATTERN = '/^[A-Za-z0-9_-]{1,100}$/';

    // ---- このコーナーの項目の定義 ----

    /** 入力の検証ルール。ページごとの値は、pagesの下に、page_routesの順の番号で届く */
    private function rules(): array
    {
        $image = ['nullable', 'string', 'max:255', 'regex:'.Seo::IMAGE_URL_PATTERN];

        return [
            'title_format' => ['nullable', 'string', 'max:100', 'regex:/\{title\}/'],
            'description' => ['nullable', 'string', 'max:300'],
            'image' => $image,
            'site_verification' => ['nullable', 'string', 'max:255'],
            'organization_name' => ['nullable', 'string', 'max:100'],
            'organization_logo' => $image,
            'pages' => ['nullable', 'array'],
            'pages.*.route' => ['required', 'string'],
            'pages.*.title' => ['nullable', 'string', 'max:60'],
            'pages.*.description' => ['nullable', 'string', 'max:300'],
            'pages.*.image' => $image,
            'pages.*.noindex' => ['nullable', 'in:0,1'],
        ];
    }

    /** 決まりの文言では分かりにくい項目のエラーの文言 */
    private function messages(): array
    {
        return [
            'title_format.regex' => 'タイトルの形には {title} を入れてください。',
        ];
    }

    // ---- 表示・保存 ----

    /** 今の設定。空欄のときに使う設定ファイルの値を、入力欄の見本に出す */
    public function index(Request $request): View
    {
        $settings = SeoSettings::settings();

        // ページごとの欄。入力エラーで戻ったときは、入れた値を出す。閉じた枠の目印は、保存してある値で決める
        $pages = [];
        foreach (array_keys(SeoSettings::pageRoutes()) as $index => $routeName) {
            $saved = $settings['pages'][$routeName] ?? [];
            $pages[] = [
                'index' => $index,
                'route' => $routeName,
                'label' => SeoSettings::pageRoutes()[$routeName],
                'url' => $this->pageUrl($routeName),
                'title' => old("pages.{$index}.title", $saved['title'] ?? null),
                'description' => old("pages.{$index}.description", $saved['description'] ?? null),
                'image' => old("pages.{$index}.image", $saved['image'] ?? null),
                'noindex' => (bool) old("pages.{$index}.noindex", $saved['noindex'] ?? false),
                'saved' => $saved !== [],
                'savedNoindex' => (bool) ($saved['noindex'] ?? false),
            ];
        }

        return view('neobit::admin.seo_settings.index', [
            'titleFormat' => old('title_format', $settings['title_format']),
            'description' => old('description', $settings['description']),
            'image' => old('image', $settings['image']),
            'siteVerification' => old('site_verification', $settings['site_verification']),
            'organizationName' => old('organization_name', $settings['organization_name']),
            'organizationLogo' => old('organization_logo', $settings['organization_logo']),
            'configTitleFormat' => (string) config('neobit.seo.title_format'),
            'configDescription' => (string) config('neobit.seo.description'),
            'configImage' => (string) config('neobit.seo.image'),
            // 運営者の名前は、設定ファイルも空ならサイト名
            'configOrganizationName' => (string) (config('neobit.seo.organization_name') ?: config('app.site_name')),
            'configOrganizationLogo' => (string) config('neobit.seo.organization_logo'),
            'pages' => $pages,
            'routes' => $this->routeNames($request),
        ]);
    }

    /** 保存。設定のseo.page_routesに無いルートの値は捨てる */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules(), $this->messages());

        // 確認タグは、<meta …>のタグを貼ってもよい。contentの値だけを取り出して、形を確かめる
        $siteVerification = SeoSettings::extractVerificationContent($validated['site_verification'] ?? null);
        if ($siteVerification !== null && ! preg_match(self::VERIFICATION_PATTERN, $siteVerification)) {
            throw ValidationException::withMessages(['site_verification' => '確認タグの形が正しくありません。']);
        }

        // ページごとの値を、ルート名 => 値にする
        $pages = [];
        foreach ($validated['pages'] ?? [] as $page) {
            if (array_key_exists($page['route'], SeoSettings::pageRoutes())) {
                $pages[$page['route']] = $page;
            }
        }

        SeoSettings::save([
            'title_format' => $validated['title_format'] ?? null,
            'description' => $validated['description'] ?? null,
            'image' => $validated['image'] ?? null,
            'site_verification' => $siteVerification,
            'organization_name' => $validated['organization_name'] ?? null,
            'organization_logo' => $validated['organization_logo'] ?? null,
        ], $pages);

        return redirect()->route($this->routeNames($request)['index'])
            ->with('status', 'SEO設定を保存しました。');
    }

    // ---- ルート ----

    /** ページのURL。引数の要るルートや、無いルートを書いたときはnullにして、画面にURLを出さない */
    private function pageUrl(string $routeName): ?string
    {
        try {
            return route($routeName);
        } catch (Throwable) {
            return null;
        }
    }

    /** 呼ばれたルートと組になるルートの名前。末尾 => 名前。「頭.index」から「頭.update」のように、末尾だけを替える */
    private function routeNames(Request $request): array
    {
        $prefix = Str::beforeLast((string) $request->route()?->getName(), '.');

        return array_combine(self::ACTIONS, array_map(fn (string $action) => $prefix.'.'.$action, self::ACTIONS));
    }
}
