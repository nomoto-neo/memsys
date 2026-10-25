{{--
    SEO設定（Neobit\Core\Http\Controllers\SeoSettingsController）。サイト全体の既定と、記事を持たないページごとの
    タイトル・説明文・画像・検索に出さないを決める。空欄の既定は、設定ファイル（config/neobit.phpのseo）の値を使う。
    ルートの名前（$routes）は、アプリが付けた名前をコントローラーが渡す。ページは設定のseo.page_routesの順に並べ、
    pagesの下に番号で送る。ページはふだん閉じた枠で、ページ名をクリックすると開く。ページが増えても画面が長くならないため。
    閉じていても設定したページが分かるよう、保存してある値があれば目印を出す。入力エラーのあるページだけ開いて出す。
--}}
@extends(config('neobit.layouts.admin'))

@section('content')
<h1 class="h4 mb-3">SEO設定</h1>

<form method="POST" action="{{ route($routes['update']) }}">
    @csrf
    @method('PATCH')

    {{-- サイト全体の既定 --}}
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">サイト全体の既定</h2>
            <p class="small text-muted mb-3">空欄の項目は、設定ファイル（config/neobit.php の seo）の値を使います。</p>

            <div class="mb-3">
                <label for="seo-title-format" class="form-label">タイトルの形</label>
                <input type="text" id="seo-title-format" name="title_format" value="{{ $titleFormat }}" class="form-control"
                       placeholder="{{ $configTitleFormat }}">
                <div class="invalid-feedback" data-item="title_format">{{ $errors->first('title_format') }}</div>
                <div class="form-text">{title} はページのタイトル、{site} はサイト名に置き換わります。{title} は必ず入れてください。</div>
            </div>

            <div class="mb-3">
                <label for="seo-description" class="form-label">既定の説明文</label>
                <textarea id="seo-description" name="description" rows="3" class="form-control"
                          placeholder="{{ $configDescription !== '' ? $configDescription : '空欄なら説明文のタグを出しません' }}">{{ $description }}</textarea>
                <div class="invalid-feedback" data-item="description">{{ $errors->first('description') }}</div>
                <div class="form-text">記事の無いページと、本文の無い記事で使います。300文字まで。</div>
            </div>

            <div class="mb-3">
                <label for="seo-image" class="form-label">既定のシェアの画像のURL</label>
                <input type="text" id="seo-image" name="image" value="{{ $image }}" class="form-control"
                       placeholder="{{ $configImage !== '' ? $configImage : '例：/images/ogp.png' }}">
                <div class="invalid-feedback" data-item="image">{{ $errors->first('image') }}</div>
                <div class="form-text">記事に画像が無いときに使います。/ から始まるサイトの中のパスか、http(s):// から始まるURLです。</div>
            </div>

            <div>
                <label for="seo-site-verification" class="form-label">Google Search Console の確認タグ</label>
                <input type="text" id="seo-site-verification" name="site_verification" value="{{ $siteVerification }}" class="form-control">
                <div class="invalid-feedback" data-item="site_verification">{{ $errors->first('site_verification') }}</div>
                <div class="form-text">Search Console が示す &lt;meta name="google-site-verification" …&gt; をそのまま貼り付けてもかまいません。content の値だけを保存します。</div>
            </div>
        </div>
    </div>

    {{-- 記事を持たないページごとの設定 --}}
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">ページごとの設定</h2>
            @if ($pages === [])
                <p class="small text-muted mb-0">設定の seo.page_routes にルートを並べると、ここに出ます。</p>
            @else
                <p class="small text-muted mb-3">記事を持たないページのタイトル・説明文・画像です。空欄なら上の既定を使います。</p>
                @foreach ($pages as $page)
                    @php($prefix = 'pages.'.$page['index'])
                    @php($name = 'pages['.$page['index'].']')
                    <details class="border rounded p-2 mb-2" @if ($errors->hasAny([$prefix.'.title', $prefix.'.description', $prefix.'.image', $prefix.'.noindex'])) open @endif>
                        <summary>
                            {{ $page['label'] }}
                            @if ($page['url'] !== null)
                                <span class="small text-muted ms-2">{{ $page['url'] }}</span>
                            @endif
                            {{-- 閉じていても、設定したページが分かるようにする --}}
                            @if ($page['savedNoindex'])
                                <span class="badge text-bg-secondary ms-2">検索に出さない</span>
                            @elseif ($page['saved'])
                                <span class="badge text-bg-info ms-2">設定あり</span>
                            @endif
                        </summary>
                        <input type="hidden" name="{{ $name }}[route]" value="{{ $page['route'] }}">

                        <div class="mt-2 mb-2">
                            <label for="seo-page-{{ $page['index'] }}-title" class="form-label small">タイトル</label>
                            <input type="text" id="seo-page-{{ $page['index'] }}-title" name="{{ $name }}[title]" value="{{ $page['title'] }}" class="form-control">
                            <div class="invalid-feedback" data-item="{{ $prefix }}.title">{{ $errors->first($prefix.'.title') }}</div>
                            <div class="form-text">60文字まで。</div>
                        </div>

                        <div class="mb-2">
                            <label for="seo-page-{{ $page['index'] }}-description" class="form-label small">説明文</label>
                            <textarea id="seo-page-{{ $page['index'] }}-description" name="{{ $name }}[description]" rows="2" class="form-control">{{ $page['description'] }}</textarea>
                            <div class="invalid-feedback" data-item="{{ $prefix }}.description">{{ $errors->first($prefix.'.description') }}</div>
                        </div>

                        <div class="mb-2">
                            <label for="seo-page-{{ $page['index'] }}-image" class="form-label small">シェアの画像のURL</label>
                            <input type="text" id="seo-page-{{ $page['index'] }}-image" name="{{ $name }}[image]" value="{{ $page['image'] }}" class="form-control">
                            <div class="invalid-feedback" data-item="{{ $prefix }}.image">{{ $errors->first($prefix.'.image') }}</div>
                        </div>

                        <div class="form-check">
                            {{-- チェックを外したときも値が届くよう、先に0を置く --}}
                            <input type="hidden" name="{{ $name }}[noindex]" value="0">
                            <input type="checkbox" id="seo-page-{{ $page['index'] }}-noindex" name="{{ $name }}[noindex]" value="1" class="form-check-input"
                                   @checked($page['noindex'])>
                            <label for="seo-page-{{ $page['index'] }}-noindex" class="form-check-label">検索に出さない（サイトマップにも載せません）</label>
                        </div>
                    </details>
                @endforeach
            @endif
        </div>
    </div>

    <button type="submit" class="btn btn-primary">保存する</button>
</form>
@endsection
