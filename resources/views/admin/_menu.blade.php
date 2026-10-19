{{--
    管理画面の左のメニュー。layouts/admin.blade.phpが、ログインしているときに読み込む。
    案件ごとに項目を書き換えるのは、このファイルだけで済むようにしている。

    項目は [ルート名, 強調するルートの形, 文言]。強調するルートの形はrequest()->routeIs()に渡すもので、
    一覧・登録・編集のどの画面にいても、そのコーナーの項目を強調するため。
    管理者だけの項目は、管理者にだけ出す。実際に入れないようにするのはEnsureStaffIsManagerだが、
    入れないリンクは見せないようにする。
--}}
@php
    $staff = Auth::guard('admin')->user();

    $sections = [
        '' => [
            ['admin.dashboard', 'admin.dashboard', 'ダッシュボード'],
        ],
        'コンテンツ' => [
            ['admin.news.index', 'admin.news.*', 'ニュース記事'],
            ['admin.categories.index', 'admin.categories.*', 'ニュースカテゴリー'],
            ['admin.pages.index', 'admin.pages.*', '固定ページ'],
        ],
        '会員' => [
            ['admin.members.index', 'admin.members.*', '個人会員'],
            ['admin.companies.index', 'admin.companies.*', '企業会員'],
        ],
    ];

    if ($staff->isManager()) {
        // 管理者のとき
        $sections['運用'] = [
            ['admin.staff.index', 'admin.staff.*', 'スタッフ'],
            ['admin.bulk-mails.index', ['admin.bulk-mails.*', 'admin.bulk-mail-templates.*'], '一斉メール'],
            ['admin.newsletter-subscribers.index', 'admin.newsletter-subscribers.*', 'メルマガ希望者'],
            ['admin.codes.index', 'admin.codes.*', '項目見出し'],
            ['admin.operation-logs.index', 'admin.operation-logs.*', '操作ログ'],
        ];
        $sections['セキュリティ'] = [
            ['admin.ip-blocks.index', 'admin.ip-blocks.*', 'IPブロック'],
            ['admin.access-restriction.index', 'admin.access-restriction.*', 'アクセス制限'],
        ];
    } else {
        // 管理者でないときは、スタッフの一覧の代わりに自分の情報だけを出す
        $sections['アカウント'] = [
            [['admin.staff.show', $staff], 'admin.staff.*', '自分の情報'],
        ];
    }
@endphp
<nav class="w-100 py-3">
    @foreach ($sections as $heading => $items)
        @if ($heading !== '')
            <div class="px-3 pt-3 pb-1 small fw-bold text-secondary">{{ $heading }}</div>
        @endif
        <div class="list-group list-group-flush">
            @foreach ($items as [$route, $pattern, $label])
                @php
                    $active = request()->routeIs(...(array) $pattern);
                    $url = is_array($route) ? route(...$route) : route($route);
                @endphp
                {{-- 見出しの下の項目は、見出しより1段右に下げる。Bootstrapのps-*には2remが無く、
                     ps-*は!important付きでstyleに勝つので、左の余白はstyleだけで決める --}}
                <a href="{{ $url }}" class="list-group-item list-group-item-action border-0 pe-3 py-2 {{ $active ? 'active' : '' }}" style="padding-left: {{ $heading === '' ? '1rem' : '2rem' }};" @if ($active) aria-current="page" @endif>
                    {{ $label }}
                </a>
            @endforeach
        </div>
    @endforeach
</nav>
