{{--
    訪問者向けフォームのスパム対策の欄（App\Support\SpamGuard）。入力画面の<form>の中に
    @include('_spam_guard') と書くだけでよい。送信を受け取る側で SpamGuard::check() を呼ぶ。

    - ハニーポット：人には見えない入力欄。画面の外に出して隠し、Tabキーでも入らないように
      している（display:noneだと、見えない欄を読み飛ばすプログラムがあるため）。
      画面の読み上げソフトでも読まないよう、aria-hiddenを付けている
    - 表示した時刻：暗号化した値のhidden。表示し直すたびに新しい時刻になる
    - Cloudflare Turnstile：判定の枠。サイトキーが設定されていないとき（.envに無い）は出さない。
      data-refresh-expired="auto"で、トークンの期限（5分）が近づくと裏で自動的に取り直すので、
      入力に時間がかかっても期限切れにならない
--}}
<div style="position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden;" aria-hidden="true">
    <label for="{{ \App\Support\SpamGuard::HONEYPOT_FIELD }}">ホームページ（入力しないでください）</label>
    <input id="{{ \App\Support\SpamGuard::HONEYPOT_FIELD }}" type="text" name="{{ \App\Support\SpamGuard::HONEYPOT_FIELD }}"
           value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ \App\Support\SpamGuard::STARTED_FIELD }}" value="{{ \App\Support\SpamGuard::startedToken() }}">

@if (config('services.turnstile.site_key'))
    <div class="cf-turnstile mb-3"
         data-sitekey="{{ config('services.turnstile.site_key') }}"
         data-language="ja"
         data-refresh-expired="auto"></div>
    @push('head-extra')
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endpush
@endif
