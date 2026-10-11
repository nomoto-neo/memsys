{{--
    パスキーの登録の案内。パスワードでログインした直後の画面に、1回だけ出す。
    各レイアウトのメッセージ欄から@includeする。出すかどうかはApp\Support\PasskeySuggestionが決めて、
    パスキーの一覧画面のルート名をセッションに仮置きする。
--}}
@if (session('passkeySuggest') && Route::has(session('passkeySuggest')))
    <div class="alert alert-info d-flex justify-content-between align-items-center gap-3">
        <div>
            <strong>パスキーを登録しませんか</strong><br>
            パスキーを登録すると、次回から、この端末のロック解除（指紋・顔・PIN など）だけでログインできます。
        </div>
        <a href="{{ route(session('passkeySuggest')) }}" class="btn btn-sm btn-primary text-nowrap">パスキーを登録する</a>
    </div>
@endif
