{{--
    管理画面の左のメニュー。layouts/admin.blade.phpが、ログインしているときに読み込む。
    案件ごとに項目を書き換えるのは、このファイルだけで済むようにしている。

    グループは [キー => [見出し, アイコン, 項目の一覧]]。キーはグループを見分ける名前（英字）、
    アイコンはBootstrap Iconsの名前（https://icons.getbootstrap.com/ から選ぶ。例：bi-people）。見出しが空のグループの項目は、アイコンを付けて見出しと並べる。
    項目は [ルート名, 強調するルートの形, 文言]。強調するルートの形はrequest()->routeIs()に渡すもので、
    一覧・登録・編集のどの画面にいても、そのコーナーの項目を強調するため。
    今の画面の項目があるグループを開いて出す。グループは<details>で、同じnameを付けているので、
    1つを開くとほかは閉じる（ブラウザーの働き）。
    ルートの無い項目と、項目が1つも残らないグループは出さない。画面を使わない案件は、routes/admin.phpの
    FrameworkRoutesの1行を消せば、メニューからも消える。
    管理者だけの項目は、管理者にだけ出す。実際に入れないようにするのはEnsureStaffIsManagerだが、
    入れないリンクは見せないようにする。
--}}
@php
    $staff = Auth::guard('admin')->user();

    $sections = [
        'top' => ['', 'bi-house-door', [
            ['admin.dashboard', 'admin.dashboard', 'ダッシュボード'],
        ]],
        'content' => ['コンテンツ', 'bi-file-earmark-text', [
            ['admin.news.index', 'admin.news.*', 'ニュース記事'],
            ['admin.categories.index', 'admin.categories.*', 'ニュースカテゴリー'],
            ['admin.pages.index', 'admin.pages.*', '固定ページ'],
        ]],
        'member' => ['会員', 'bi-people', [
            ['admin.members.index', 'admin.members.*', '個人会員'],
            ['admin.companies.index', 'admin.companies.*', '企業会員'],
        ]],
    ];

    if ($staff->isManager()) {
        // 管理者のとき
        $sections['operation'] = ['運用', 'bi-sliders', [
            ['admin.staff.index', 'admin.staff.*', 'スタッフ'],
            ['admin.bulk-mails.index', ['admin.bulk-mails.*', 'admin.bulk-mail-templates.*'], '一斉メール'],
            ['admin.newsletter-subscribers.index', 'admin.newsletter-subscribers.*', 'メルマガ希望者'],
            ['admin.codes.index', 'admin.codes.*', '項目見出し'],
            ['admin.seo-settings.index', 'admin.seo-settings.*', 'SEO設定'],
            ['admin.redirects.index', 'admin.redirects.*', 'リダイレクト'],
            ['admin.operation-logs.index', 'admin.operation-logs.*', '操作ログ'],
        ]];
        $sections['security'] = ['セキュリティ', 'bi-shield-lock', [
            ['admin.ip-blocks.index', 'admin.ip-blocks.*', 'IPブロック'],
            ['admin.access-restriction.index', 'admin.access-restriction.*', 'アクセス制限'],
            ['admin.basic-auth.index', 'admin.basic-auth.*', 'ベーシック認証'],
            ['admin.file-watch.index', 'admin.file-watch.*', '改ざん監視'],
        ]];
    } else {
        // 管理者でないときは、スタッフの一覧の代わりに自分の情報だけを出す
        $sections['account'] = ['アカウント', 'bi-person-circle', [
            [['admin.staff.show', $staff], 'admin.staff.*', '自分の情報'],
        ]];
    }
@endphp
<nav class="nb-menu" aria-label="管理画面のメニュー">
    @foreach ($sections as [$heading, $icon, $items])
        @php
            // ルートの無い項目は出さない。画面を使わない案件は、routes/admin.phpの1行を消すだけで、メニューからも消える
            $items = array_filter($items, fn (array $item) => Route::has(is_array($item[0]) ? $item[0][0] : $item[0]));
            $links = array_map(fn (array $item) => [
                'url' => is_array($item[0]) ? route(...$item[0]) : route($item[0]),
                'active' => request()->routeIs(...(array) $item[1]),
                'label' => $item[2],
            ], $items);
            $current = in_array(true, array_column($links, 'active'), true);
        @endphp
        {{-- 項目が1つも残らないグループは、見出しも出さない --}}
        @continue($links === [])
        @if ($heading === '')
            {{-- 見出しの無い項目。アイコンを付けて、グループの見出しと並べる --}}
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" class="nb-menu-link nb-menu-top {{ $link['active'] ? 'active' : '' }}" @if ($link['active']) aria-current="page" @endif>
                    <i class="bi {{ $icon }} nb-menu-icon" aria-hidden="true"></i>
                    <span class="nb-menu-label">{{ $link['label'] }}</span>
                </a>
            @endforeach
        @else
            <details class="nb-menu-group {{ $current ? 'is-current' : '' }}" name="nb-menu" @if ($current) open @endif>
                <summary class="nb-menu-heading">
                    <i class="bi {{ $icon }} nb-menu-icon" aria-hidden="true"></i>
                    <span class="nb-menu-label">{{ $heading }}</span>
                    <i class="bi bi-chevron-down nb-menu-chevron" aria-hidden="true"></i>
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
