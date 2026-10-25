{{--
    パンくずリスト。訪問者の側のレイアウトの本文の上に置く。段はNeobit\Core\Seo\Seoが決める。
    先頭の「トップ」と末尾の今のページ名は自動で付き、途中の段はコントローラーがSeo::breadcrumb()で足す。
    トップページと、ページ名の無い画面では何も出さない。構造化データ（BreadcrumbList）は、neobit::_seo_headが出す。
--}}
@php($breadcrumbs = app(\Neobit\Core\Seo\Seo::class)->breadcrumbs())
@if ($breadcrumbs !== [])
    <nav aria-label="パンくずリスト">
        <ol class="breadcrumb">
            @foreach ($breadcrumbs as $breadcrumb)
                @if ($loop->last)
                    <li class="breadcrumb-item active" aria-current="page">{{ $breadcrumb['label'] }}</li>
                @elseif ($breadcrumb['url'] !== null)
                    <li class="breadcrumb-item"><a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a></li>
                @else
                    {{-- URLを省いた段は、文字だけ --}}
                    <li class="breadcrumb-item">{{ $breadcrumb['label'] }}</li>
                @endif
            @endforeach
        </ol>
    </nav>
@endif
