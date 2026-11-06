{{--
    管理画面の左のメニュー。layouts/admin.blade.phpが、ログインしているときに読み込む。
    案件ごとに項目を書き換えるのは、このファイルだけで済むようにしている。

    グループは [キー => [見出し, アイコン, 項目の一覧]]。キーはグループを見分ける名前（英字）、
    アイコンは24×24の線の絵のpathのd属性。見出しが空のグループの項目は、アイコンを付けて見出しと並べる。
    項目は [ルート名, 強調するルートの形, 文言]。強調するルートの形はrequest()->routeIs()に渡すもので、
    一覧・登録・編集のどの画面にいても、そのコーナーの項目を強調するため。
    今の画面の項目があるグループを開いて出す。グループは<details>で、同じnameを付けているので、
    1つを開くとほかは閉じる（ブラウザーの働き）。
    管理者だけの項目は、管理者にだけ出す。実際に入れないようにするのはEnsureStaffIsManagerだが、
    入れないリンクは見せないようにする。
--}}
@php
    $staff = Auth::guard('admin')->user();

    $sections = [
        'top' => ['', 'M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z', [
            ['admin.dashboard', 'admin.dashboard', 'ダッシュボード'],
        ]],
        'content' => ['コンテンツ', 'M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6', [
            ['admin.news.index', 'admin.news.*', 'ニュース記事'],
            ['admin.categories.index', 'admin.categories.*', 'ニュースカテゴリー'],
            ['admin.pages.index', 'admin.pages.*', '固定ページ'],
        ]],
        'member' => ['会員', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21v-1a6 6 0 0 1 12 0v1M16 3.5a4 4 0 0 1 0 7.5M22 21v-1a6 6 0 0 0-4-5.6', [
            ['admin.members.index', 'admin.members.*', '個人会員'],
            ['admin.companies.index', 'admin.companies.*', '企業会員'],
        ]],
    ];

    if ($staff->isManager()) {
        // 管理者のとき
        $sections['operation'] = ['運用', 'M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M14 4v4M8 10v4M16 16v4', [
            ['admin.staff.index', 'admin.staff.*', 'スタッフ'],
            ['admin.bulk-mails.index', ['admin.bulk-mails.*', 'admin.bulk-mail-templates.*'], '一斉メール'],
            ['admin.newsletter-subscribers.index', 'admin.newsletter-subscribers.*', 'メルマガ希望者'],
            ['admin.codes.index', 'admin.codes.*', '項目見出し'],
            ['admin.seo-settings.index', 'admin.seo-settings.*', 'SEO設定'],
            ['admin.redirects.index', 'admin.redirects.*', 'リダイレクト'],
            ['admin.operation-logs.index', 'admin.operation-logs.*', '操作ログ'],
        ]];
        $sections['security'] = ['セキュリティ', 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z', [
            ['admin.ip-blocks.index', 'admin.ip-blocks.*', 'IPブロック'],
            ['admin.access-restriction.index', 'admin.access-restriction.*', 'アクセス制限'],
            ['admin.basic-auth.index', 'admin.basic-auth.*', 'ベーシック認証'],
            ['admin.file-watch.index', 'admin.file-watch.*', '改ざん監視'],
        ]];
    } else {
        // 管理者でないときは、スタッフの一覧の代わりに自分の情報だけを出す
        $sections['account'] = ['アカウント', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21v-1a8 8 0 0 1 16 0v1', [
            [['admin.staff.show', $staff], 'admin.staff.*', '自分の情報'],
        ]];
    }
@endphp
<nav class="nb-menu" aria-label="管理画面のメニュー">
    @foreach ($sections as [$heading, $icon, $items])
        @php
            $links = array_map(fn (array $item) => [
                'url' => is_array($item[0]) ? route(...$item[0]) : route($item[0]),
                'active' => request()->routeIs(...(array) $item[1]),
                'label' => $item[2],
            ], $items);
            $current = in_array(true, array_column($links, 'active'), true);
        @endphp
        @if ($heading === '')
            {{-- 見出しの無い項目。アイコンを付けて、グループの見出しと並べる --}}
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" class="nb-menu-link nb-menu-top {{ $link['active'] ? 'active' : '' }}" @if ($link['active']) aria-current="page" @endif>
                    <svg class="nb-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                    <span class="nb-menu-label">{{ $link['label'] }}</span>
                </a>
            @endforeach
        @else
            <details class="nb-menu-group {{ $current ? 'is-current' : '' }}" name="nb-menu" @if ($current) open @endif>
                <summary class="nb-menu-heading">
                    <svg class="nb-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                    <span class="nb-menu-label">{{ $heading }}</span>
                    <svg class="nb-menu-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                </summary>
                <div class="nb-menu-items">
                    @foreach ($links as $link)
                        <a href="{{ $link['url'] }}" class="nb-menu-link {{ $link['active'] ? 'active' : '' }}" @if ($link['active']) aria-current="page" @endif>
                            <span class="nb-menu-label">{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </details>
        @endif
    @endforeach
</nav>
