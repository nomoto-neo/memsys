/*
 * パスキーのログイン・登録・削除の確認。ログイン画面（auth/login・admin/auth/login）と
 * パスキーの一覧画面（_passkeys.blade.php）から@push('head-extra')で読み込む。
 * ブラウザ側の処理は@laravel/passkeys（npm）に任せ、ここでは画面の要素との
 * つなぎ込みと、エラーの文言だけを扱う。
 *
 * サーバーとの通信（POST）のCSRFトークンは、@laravel/passkeysが
 * <meta name="csrf-token">から読む。読み込む画面には、この<meta>を一緒に置く。
 *
 * 1. data-passkey-login（ログイン画面）
 *    ボタンを押すと、data-options-urlからオプションを受け取り、端末でパスキーを
 *    選んでもらい、結果をdata-submit-urlへ送る。成功したら、サーバーが返した
 *    移動先へ移る。data-rememberには「ログイン状態を保持する」のチェックボックスの
 *    idを書く（押した時点のチェックの状態を送る）。
 *    パスキーに対応していないブラウザでは、ボタンごと出さない（hiddenのまま）。
 * 2. data-passkey-register（パスキーの一覧画面、本人確認の後）
 *    ボタンを押すと、端末でパスキーを作ってもらい、結果をサーバーへ送る。
 *    成功したら画面を読み込み直す（完了のメッセージはサーバーがセッションに入れている）。
 *    パスキーの名前はサーバーが決めるので、ここからは送らない。
 * 3. data-passkey-unsupported / data-passkey-supported（パスキーの一覧画面）
 *    パスキーに対応していないブラウザでは、案内を出して登録の操作を隠す。
 * 4. form[data-confirm]（パスキーの削除）
 *    送信の前に、data-confirmの文言で確認する。
 */
import {
    InvalidDomainError,
    NotSupportedError,
    Passkeys,
    PasskeyExistsError,
    UserCancelledError,
} from '@laravel/passkeys';

// 画面に出すエラーの文言。
const messageFor = (error) => {
    if (error instanceof UserCancelledError) {
        return 'パスキーの操作がキャンセルされたか、時間切れになりました。';
    }
    if (error instanceof NotSupportedError) {
        return 'このブラウザはパスキーに対応していません。';
    }
    if (error instanceof PasskeyExistsError) {
        return 'この端末のパスキーは、既に登録されています。';
    }
    if (error instanceof InvalidDomainError) {
        return 'このアドレスでは、パスキーを使えません。';
    }

    // サーバーが返した文言（日本語）はそのまま出す。それ以外（ログインの有効期限切れ・
    // 画面の有効期限切れなどで返る英語の文言や、通信の失敗）は、共通の案内にする。
    const text = error?.message ?? '';
    if (/[\u3040-\u30ff\u4e00-\u9fff]/.test(text)) {
        return text;
    }

    return 'パスキーの処理に失敗しました。画面を再読み込みして、もう一度お試しください。';
};

// ボタンを押してから結果が出るまで、ボタンを押せないようにして、文言を消す。
const run = async (box, task) => {
    const button = box.querySelector('button');
    const message = box.querySelector('[data-passkey-message]');

    button.disabled = true;
    if (message) {
        message.textContent = '';
    }

    try {
        await task();
    } catch (error) {
        if (message) {
            message.textContent = messageFor(error);
        }
        button.disabled = false;
    }
};

const supported = Passkeys.isSupported();

// 1. ログイン画面
document.querySelectorAll('[data-passkey-login]').forEach((box) => {
    if (!supported) {
        return;
    }
    box.hidden = false;

    const remember = document.getElementById(box.dataset.remember ?? '');

    box.querySelector('button').addEventListener('click', () => run(box, async () => {
        const response = await Passkeys.verify({
            routes: {
                options: box.dataset.optionsUrl,
                submit: box.dataset.submitUrl,
            },
            remember: () => remember?.checked ?? false,
        });

        window.location.href = response.redirect ?? '/';
    }));
});

// 2. パスキーの登録
document.querySelectorAll('[data-passkey-register]').forEach((box) => {
    box.querySelector('button').addEventListener('click', () => run(box, async () => {
        await Passkeys.register({
            name: '',
            routes: {
                options: box.dataset.optionsUrl,
                submit: box.dataset.submitUrl,
            },
        });

        window.location.reload();
    }));
});

// 3. 対応していないブラウザの案内
if (!supported) {
    document.querySelectorAll('[data-passkey-unsupported]').forEach((el) => {
        el.hidden = false;
    });
    document.querySelectorAll('[data-passkey-supported]').forEach((el) => {
        el.hidden = true;
    });
}

// 4. 削除の確認
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});
