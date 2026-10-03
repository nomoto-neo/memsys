{{--
    お知らせの一覧の行（訪問者向け）。お知らせのコーナー（news/index）と
    TOPページ（welcome）の両方から呼ぶ。
    - $newsList：表示する記事（ページ分けした結果でも、件数を絞ったCollectionでもよい）
    - $emptyMessage：1件も無いときに出す文言
--}}
<div class="list-group mb-4">
    @forelse ($newsList as $news)
        <a href="{{ route('news.show', $news) }}" class="list-group-item list-group-item-action">
            <div class="d-flex justify-content-between align-items-center">
                {{-- タイトルの前に、幅70pxの画像枠を置く。一覧用画像があれば
                     枠の中に表示し、無ければ枠だけを空けておく（画像のある行と
                     無い行で、タイトルの開始位置が揃うようにするため）。
                     $news->list_image_urlはNewsモデルのアクセサ（未登録ならnull）。
                     画像自体は登録時にサーバー側で縮小済みで、ここでは表示幅を
                     指定しているだけ。flex-shrink-0は、タイトルが長いときに
                     枠のほうが押し縮められないようにするため。 --}}
                <span class="d-flex align-items-center gap-2">
                    <span class="d-inline-block flex-shrink-0" style="width: 70px;">
                        @if ($news->list_image_url)
                            <img src="{{ $news->list_image_url }}" alt="" style="width: 70px; height: auto;" class="rounded d-block">
                        @endif
                    </span>
                    <span>
                        {{ $news->title }}
                        {{-- 会員限定の記事は、ログイン中の会員にしか一覧に出ない --}}
                        @if ($news->members_only)
                            <span class="badge text-bg-warning">会員限定</span>
                        @endif
                    </span>
                </span>
                <span class="text-muted small text-nowrap ms-2">{{ $news->article_date->format('Y-m-d') }}</span>
            </div>
        </a>
    @empty
        <p class="text-muted">{{ $emptyMessage }}</p>
    @endforelse
</div>
