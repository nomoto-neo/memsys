{{--
    記事ごとのSEOの欄（タイトル・説明文・シェアの画像のURL・検索に出さない）。各コーナーの登録・編集の画面の
    入力欄（_fields）に1行置く。コントローラーにはNeobit\Core\Seo\SeoFields、モデルにはHasSeoMetaを付ける。
    $readonlyの値によって、次の2通りに切り替わる。

    - $readonlyが空（create/edit）：普段は閉じた「SEOの設定」の枠。値かエラーがあれば、開いた状態で出す。
      空欄のときに使う値を、欄の薄い字で案内する
    - $readonlyが空でない（confirm/show）：入れた値だけを並べる。全部空なら「（自動）」

    受け取る変数:
    - $input     画面の入力値。seo_title・seo_description・seo_image・seo_noindexが入っている
    - $readonly  ' readonly'または''
--}}
@php
    $seoTitle = $input['seo_title'] ?? null;
    $seoDescription = $input['seo_description'] ?? null;
    $seoImage = $input['seo_image'] ?? null;
    $seoNoindex = ($input['seo_noindex'] ?? null) == '1';
    $seoFilled = filled($seoTitle) || filled($seoDescription) || filled($seoImage) || $seoNoindex;
    $seoErrors = $errors->hasAny(['seo_title', 'seo_description', 'seo_image', 'seo_noindex']);
@endphp
@if ($readonly)
    <div class="mb-3">
        <div class="form-label d-block">SEOの設定</div>
        @if ($seoFilled)
            <dl class="row small mb-0">
                @if (filled($seoTitle))
                    <dt class="col-sm-3">タイトル</dt><dd class="col-sm-9">{{ $seoTitle }}</dd>
                @endif
                @if (filled($seoDescription))
                    <dt class="col-sm-3">説明文</dt><dd class="col-sm-9">{{ $seoDescription }}</dd>
                @endif
                @if (filled($seoImage))
                    <dt class="col-sm-3">シェアの画像</dt><dd class="col-sm-9">{{ $seoImage }}</dd>
                @endif
                @if ($seoNoindex)
                    <dt class="col-sm-3">検索</dt><dd class="col-sm-9">検索に出さない</dd>
                @endif
            </dl>
        @else
            <div class="text-muted small">（自動）</div>
        @endif
    </div>
@else
    <details class="mb-3 border rounded p-2" @if ($seoFilled || $seoErrors) open @endif>
        <summary class="form-label mb-0">SEOの設定（空欄なら自動）</summary>

        <div class="mt-2">
            <label for="seo_title" class="form-label small mb-1">SEOのタイトル</label>
            <input id="seo_title" type="text" name="seo_title" class="form-control form-control-sm" maxlength="60"
                   placeholder="空欄なら記事のタイトル" value="{{ $seoTitle }}">
            <div class="invalid-feedback" data-item="seo_title">{{ $errors->first('seo_title') }}</div>
        </div>

        <div class="mt-2">
            <label for="seo_description" class="form-label small mb-1">説明文</label>
            <textarea id="seo_description" name="seo_description" rows="3" class="form-control form-control-sm" maxlength="300"
                      placeholder="空欄なら本文の先頭">{{ $seoDescription }}</textarea>
            <div class="invalid-feedback" data-item="seo_description">{{ $errors->first('seo_description') }}</div>
        </div>

        <div class="mt-2">
            <label for="seo_image" class="form-label small mb-1">シェアの画像のURL</label>
            <input id="seo_image" type="text" name="seo_image" class="form-control form-control-sm" maxlength="255"
                   placeholder="空欄なら記事の画像（無ければサイトの既定）。例：/uploads/… か https://…" value="{{ $seoImage }}">
            <div class="invalid-feedback" data-item="seo_image">{{ $errors->first('seo_image') }}</div>
        </div>

        <div class="form-check mt-2">
            {{-- チェックを外したときも値が届くよう、先に0を置く --}}
            <input type="hidden" name="seo_noindex" value="0">
            <input id="seo_noindex" type="checkbox" name="seo_noindex" value="1" class="form-check-input" @checked($seoNoindex)>
            <label for="seo_noindex" class="form-check-label small">検索に出さない（noindex）</label>
        </div>
    </details>
@endif
