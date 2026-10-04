{{--
    訪問者向けフォームのスパム対策の欄（App\Support\SpamGuard）。入力画面の<form>の中に
    @include('_spam_guard') と書くだけでよい。送信を受け取る側で SpamGuard::check() を呼ぶ。

    - ハニーポット：人には見えない入力欄。画面の外に出して隠し、Tabキーでも入らないように
      している（display:noneだと、見えない欄を読み飛ばすプログラムがあるため）。
      画面の読み上げソフトでも読まないよう、aria-hiddenを付けている
    - 表示した時刻：暗号化した値のhidden。表示し直すたびに新しい時刻になる
    - Cloudflare Turnstile：判定の枠。サイトキーが設定されていないとき（.envに無い）は出さない。
      トークンの期限（5分）が近づくと裏で自動的に取り直すので、入力に時間がかかっても
      期限切れにならない
    - 判定が終わるまでの案内：枠が出て判定が終わるまで時間がかかることがあるので、その間は
      案内の文を出して、フォームの送信ボタンを押せなくする。下の<script>が切り替える
--}}
<div style="position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden;" aria-hidden="true">
    <label for="{{ \App\Support\SpamGuard::HONEYPOT_FIELD }}">ホームページ（入力しないでください）</label>
    <input id="{{ \App\Support\SpamGuard::HONEYPOT_FIELD }}" type="text" name="{{ \App\Support\SpamGuard::HONEYPOT_FIELD }}"
           value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ \App\Support\SpamGuard::STARTED_FIELD }}" value="{{ \App\Support\SpamGuard::startedToken() }}">

@if (config('services.turnstile.site_key'))
    <div class="mb-3">
        <div data-spam-guard-widget data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
        {{-- 判定を待っている間と、判定できなかったときの案内。どちらも<script>が出し入れする --}}
        <div class="form-text" data-spam-guard-message="waiting" hidden>ロボットによる送信でないことを確認しています。しばらくお待ちください。</div>
        <div class="form-text text-danger" data-spam-guard-message="failed" hidden>ロボットによる送信でないことを確認できませんでした。ページを読み込み直すか、広告を止める拡張機能を切ってお試しください。</div>
    </div>
    <script>
        (() => {
            // 判定が終わらないときに、案内を「確認できませんでした」に変えるまでの秒数
            const WAIT_SECONDS = 10;

            const box = document.currentScript.previousElementSibling;
            const form = box.closest('form');
            const widget = box.querySelector('[data-spam-guard-widget]');

            // 判定に通ってトークンを受け取っている間だけtrue
            let passed = false;
            let waitTimer = null;

            // 案内の文を1つだけ出す。nullを渡すと両方隠す
            const showMessage = (name) => {
                box.querySelectorAll('[data-spam-guard-message]').forEach((message) => {
                    message.hidden = message.dataset.spamGuardMessage !== name;
                });
            };

            // 送信ボタンを押せなくする・戻す。disabled属性はフォームの側が別の条件
            // （同意のチェックなど）で使うので触らず、Bootstrapのdisabledクラスで行う
            const blockButtons = (blocked) => {
                form.querySelectorAll('button[type="submit"]').forEach((button) => {
                    button.classList.toggle('disabled', blocked);
                    button.toggleAttribute('aria-disabled', blocked);
                });
            };

            // 判定を待っている。枠が出る前と、期限が切れてトークンを取り直している間
            const wait = () => {
                passed = false;
                blockButtons(true);
                showMessage('waiting');
                clearTimeout(waitTimer);
                waitTimer = setTimeout(fail, WAIT_SECONDS * 1000);
            };

            // 判定できなかった。Cloudflareのスクリプトを読めないときと、待つ秒数を過ぎたとき。
            // この後で判定に通れば、pass()で送信できるようになる
            const fail = () => {
                passed = false;
                blockButtons(true);
                showMessage('failed');
                clearTimeout(waitTimer);
            };

            // 判定に通った
            const pass = () => {
                passed = true;
                blockButtons(false);
                showMessage(null);
                clearTimeout(waitTimer);
            };

            // Enterキーでの送信も止める。フォームのほかの送信時の処理（二重送信の防止など）が
            // 動かないよう、それらより先に受け取って打ち切る
            form.addEventListener('submit', (event) => {
                if (! passed) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
            }, true);

            // Cloudflareのスクリプトを読み込み、読めたら枠を出す。読めなかったことを
            // 受け取れるよう、<script>のタグを書かずにここで読み込む
            const script = document.createElement('script');
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
            script.async = true;
            script.onload = () => {
                window.turnstile.render(widget, {
                    sitekey: widget.dataset.sitekey,
                    language: 'ja',
                    'refresh-expired': 'auto',
                    callback: pass,
                    'expired-callback': wait,
                    'error-callback': fail,
                });
            };
            script.onerror = fail;
            document.head.appendChild(script);

            wait();
        })();
    </script>
@endif
