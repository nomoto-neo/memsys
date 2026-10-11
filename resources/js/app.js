/*
 * 全画面で使うスクリプト。layouts/app.blade.php（訪問者向け）・
 * layouts/admin.blade.php（管理画面）の<head>から@viteで読み込む。
 * 特定の画面だけで使うもの（SortableJS・WYSIWYGエディタ・添付ファイルの
 * アップロードなど）は、その画面から@push('head-extra')で別に読み込む。
 *
 * type="module"で読み込まれるので、HTMLを読み終えた後に実行される
 * （DOMContentLoadedを待たなくても、画面の要素はすべて揃っている）。
 *
 * ■ フォームの補助
 * 1. 必須マークからrequired属性を付ける
 *    ラベルの中に.required-mark（必須マーク）があれば、そのlabelのfor属性が
 *    指す入力欄にrequired属性を付ける。「画面に必須マークが出ている」ことだけを
 *    見てブラウザの入力チェック（HTML5）を連動させるので、rules()の必須項目が
 *    増減しても、ここは変更不要。
 * 2. サーバー側のエラーがある項目の入力欄を赤くする
 *    文言が入っているエラー欄（.invalid-feedback[data-item]）のdata-itemを見て、
 *    同じフォームの中でname属性が「項目名」または「項目名[]」の入力欄すべてに
 *    is-invalidを付ける。文言の表示そのものは、レイアウトのCSSが行う。
 * 3. ブラウザの入力チェックで引っかかった内容を、同じエラー欄に出す
 *    ブラウザ標準の吹き出しは出さず、最初の項目にカーソルを移す。エラー欄が
 *    無い項目（パスワードの確認用など）は、それが最初の項目のときだけ
 *    ブラウザ標準の吹き出しに任せる。
 * 4. 入力し直したら、その項目のエラー表示（赤枠と文言）を消す
 *    後から追加される入力欄（添付ファイルの行など）にも効くよう、
 *    documentでまとめて受け取る。
 * 5. サーバー側のエラーがあるモーダルを開く
 *    モーダルの中のフォームを送信してエラーで戻ってきたとき、モーダルは閉じた状態で
 *    表示されるので、そのままではエラーの文言が見えない。文言が入っているエラー欄が
 *    モーダルの中にあれば、そのモーダルを開く。BootstrapのJavaScriptを読み込んで
 *    いない画面では何もしない。6のフォームでは画面を読み直さないので、これが働くのは
 *    6が使えなかったときだけ。
 * 6. クラス名ajax_submitの付いたフォームを、画面を読み直さずに送る
 *    <form class="ajax_submit">と書いたフォームは、Ajaxで送る。エラーは同じフォームのエラー欄に出し、
 *    うまくいったときだけ、サーバーが返した移動先へ移る。送っている間は画面全体を覆って、
 *    処理中であることを見せる。モーダルの中のフォームのためのもの。エラーのたびに画面を
 *    読み直すと、モーダルが閉じて開き直すことになるため。
 *    サーバーの側は、ふつうの送信と同じくリダイレクトを返せばよい。JSONへの読み替えは
 *    App\Http\Middleware\AjaxFormResponseが行う。
 * 7. フォームを送っている間、二重の送信を止めて、処理中の表示を出す
 *    どのフォームにも効く。目印は要らない。送信した瞬間から2回目の送信を止め、少し待っても
 *    画面が切り替わらなければ、画面全体を覆って処理中であることを見せる。確認コードのメールが
 *    2通届く、登録が2件できる、といったことを防ぐ。
 *    targetの付いたフォームは対象にしない。別の窓へ送るので、この画面は切り替わらず、
 *    覆ったままになってしまうため。同じ理由で、ファイルを返すものはフォームにせずリンクにするか、
 *    フォームにtargetを付ける。リンクでの移動には出さない。ブラウザが自分で読み込み中を見せるため。
 */

// 必須の入力が無いときの文言。ブラウザ標準の文言はブラウザごとに違うので、
// サーバー側（lang/ja/validation.phpのrequired）と同じ文言に揃える。
const REQUIRED_MESSAGE = 'この項目は必須です。';

// 入力欄と同じ項目（name属性が「項目名」または「項目名[]」）の入力欄をすべて返す。
const sameItemInputs = (scope, item) => {
    const name = CSS.escape(item);

    return scope.querySelectorAll(`[name="${name}"], [name="${name}[]"]`);
};

// 1. 必須マークからrequired属性を付ける
document.querySelectorAll('.required-mark').forEach((mark) => {
    const forId = mark.closest('label')?.getAttribute('for');
    if (forId) {
        document.getElementById(forId)?.setAttribute('required', 'required');
    }
});

// 2. サーバー側のエラーがある項目の入力欄を赤くする
document.querySelectorAll('.invalid-feedback[data-item]').forEach((feedback) => {
    if (feedback.textContent.trim() === '') {
        return;
    }
    sameItemInputs(feedback.closest('form') ?? document, feedback.dataset.item).forEach((el) => {
        el.classList.add('is-invalid');
    });
});

// 5. サーバー側のエラーがあるモーダルを開く
// 1度に開けるモーダルは1つなので、エラーのある最初の1つだけを開く。
const invalidModal = [...document.querySelectorAll('.modal .invalid-feedback[data-item]')]
    .find((feedback) => feedback.textContent.trim() !== '')
    ?.closest('.modal');

if (invalidModal && window.bootstrap) {
    window.bootstrap.Modal.getOrCreateInstance(invalidModal).show();
}

// 3. ブラウザの入力チェックで引っかかった内容を、同じエラー欄に出す
// invalidイベントは親要素へ伝わらないので、キャプチャ（第3引数true）でdocumentが先に受け取る。
// 1回の送信で引っかかった項目の分だけ、画面の上から順に同じ処理の中で続けて届くので、
// 最初の1件かどうかの印は、次の処理（setTimeout）で戻す。
let firstHandled = false;
document.addEventListener('invalid', (event) => {
    const input = event.target;
    const isFirst = !firstHandled;
    if (isFirst) {
        firstHandled = true;
        setTimeout(() => {
            firstHandled = false;
        });
    }

    if (!input.name) {
        return;
    }

    const scope = input.form ?? document;
    const item = input.name.replace(/\[\]$/, '');
    const feedback = scope.querySelector(`.invalid-feedback[data-item="${CSS.escape(item)}"]`);

    sameItemInputs(scope, item).forEach((el) => {
        el.classList.add('is-invalid');
    });

    if (!feedback) {
        // エラー欄が無い項目は、最初の1件ならブラウザ標準の吹き出しに任せる（カーソルもブラウザが移す）。
        // 2件目以降は赤枠だけにする（吹き出しを許すと、カーソルがそちらへ移ってしまう）。
        if (!isFirst) {
            event.preventDefault();
        }

        return;
    }

    feedback.textContent = input.validity.valueMissing ? REQUIRED_MESSAGE : input.validationMessage;

    // ブラウザ標準の吹き出しを止める。止めるとカーソルも移らないので、最初の項目にだけ自分で移す。
    event.preventDefault();
    if (isFirst) {
        input.focus();
    }
}, true);

// 4. 入力し直したら、その項目のエラー表示を消す
// inputイベントは、文字の入力のほか、セレクトボックス・ラジオボタン・チェックボックスの変更でも起きる。
document.addEventListener('input', (event) => {
    const input = event.target;
    if (!input.name || !input.classList.contains('is-invalid')) {
        return;
    }

    const scope = input.form ?? document;
    const item = input.name.replace(/\[\]$/, '');

    sameItemInputs(scope, item).forEach((el) => {
        el.classList.remove('is-invalid');
    });

    const feedback = scope.querySelector(`.invalid-feedback[data-item="${CSS.escape(item)}"]`);
    if (feedback) {
        feedback.textContent = '';
    }
});

// 6. クラス名ajax_submitの付いたフォームを、画面を読み直さずに送る

// 通信そのものに失敗したときや、セッションが切れていたときの文言。項目ごとのエラー欄には
// 出せないので、フォームの先頭に出す。
const AJAX_FAILED_MESSAGE = '送信できませんでした。画面を読み込み直してから、もう一度お試しください。';

// 送っている間、画面全体を覆う表示。押せるものを全部隠すので、二重の送信も防げる。
// モーダル（z-indexは1055）より手前に出す。
const busyOverlay = document.createElement('div');
busyOverlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center bg-dark bg-opacity-50';
busyOverlay.style.zIndex = '2000';
busyOverlay.innerHTML = '<div class="spinner-border text-light" role="status"><span class="visually-hidden">処理中です</span></div>';

// フォームの先頭に出す、項目に結び付かないエラーの欄。無ければ作る。
const ajaxMessageBox = (form) => {
    let box = form.querySelector('.ajax_submit_message');
    if (!box) {
        box = document.createElement('div');
        box.className = 'alert alert-danger ajax_submit_message';
        box.hidden = true;
        (form.querySelector('.modal-body') ?? form).prepend(box);
    }

    return box;
};

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.classList.contains('ajax_submit')) {
        return;
    }

    // ふつうの送信（画面の読み直し）を止めて、Ajaxで送る
    event.preventDefault();

    // 前の送信のエラー表示を消す
    const messageBox = ajaxMessageBox(form);
    messageBox.hidden = true;
    form.querySelectorAll('.invalid-feedback[data-item]').forEach((feedback) => {
        feedback.textContent = '';
    });
    form.querySelectorAll('.is-invalid').forEach((el) => {
        el.classList.remove('is-invalid');
    });

    document.body.append(busyOverlay);
    let moving = false;

    try {
        // 宛先とCSRFトークンは、フォームに書いてあるものをそのまま使う。
        // PATCH・DELETEは、フォームの中の_methodでサーバーが読み替えるので、送り方はPOSTにする。
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Ajax-Form': '1',
                Accept: 'application/json',
            },
            body: new FormData(form),
        });
        const data = await response.json();

        if (response.ok && data.redirect) {
            // うまくいった。移動先の画面へ移る。画面が切り替わるまで、処理中の表示は消さない
            moving = true;
            window.location.assign(data.redirect);

            return;
        }

        if (response.status === 422 && data.errors) {
            // 項目ごとのエラーを、その項目のエラー欄に出す。エラー欄の無い項目の分は、先頭にまとめて出す。
            // 文言は、Laravelのvalidate()からは配列で、AjaxFormResponseからは文字で届く
            const others = [];

            Object.entries(data.errors).forEach(([item, value]) => {
                const message = Array.isArray(value) ? value[0] : value;
                const feedback = form.querySelector(`.invalid-feedback[data-item="${CSS.escape(item)}"]`);

                if (!feedback) {
                    others.push(message);

                    return;
                }

                feedback.textContent = message;
                sameItemInputs(form, item).forEach((el) => {
                    el.classList.add('is-invalid');
                });
            });

            if (others.length > 0) {
                messageBox.textContent = others.join(' ');
                messageBox.hidden = false;
            }

            return;
        }

        // セッション切れ（419）・回数の制限（429）・サーバーのエラーなど
        messageBox.textContent = AJAX_FAILED_MESSAGE;
        messageBox.hidden = false;
    } catch {
        // 通信の失敗や、JSONでない返事
        messageBox.textContent = AJAX_FAILED_MESSAGE;
        messageBox.hidden = false;
    } finally {
        if (!moving) {
            busyOverlay.remove();
        }
    }
});

// 7. フォームを送っている間、二重の送信を止めて、処理中の表示を出す

// 処理中の表示を出すまでの待ち時間（ミリ秒）。すぐ切り替わる画面では出さずに済ませて、
// 押すたびに画面がちらつかないようにする。
const BUSY_DELAY = 300;

// 送信して、画面が切り替わるのを待っているところか。この間は、ほかの送信を止める。
let submitting = false;

// 待っている状態をやめて、送信できる状態に戻す。
const stopWaiting = () => {
    submitting = false;
    busyOverlay.remove();
};

document.addEventListener('submit', (event) => {
    const form = event.target;

    // 6のフォームは、そちらの処理が表示を出す
    if (!(form instanceof HTMLFormElement) || form.classList.contains('ajax_submit')) {
        return;
    }

    // 別の窓やタブへ送るフォームは、この画面が切り替わらないので対象にしない。
    // 送信のボタンにformtargetが付いているときも同じ
    if (form.hasAttribute('target') || event.submitter?.hasAttribute('formtarget')) {
        return;
    }

    // 送信済みなら、2回目は送らない
    if (submitting) {
        event.preventDefault();

        return;
    }

    // ほかの処理（削除の確認で「キャンセル」を押したときなど）が送信を取りやめることがある。
    // 取りやめたかどうかは、全部の処理が済んだ後でないと分からないので、次の処理（setTimeout）で見る。
    setTimeout(() => {
        if (event.defaultPrevented) {
            return;
        }

        submitting = true;

        // 少し待っても画面が切り替わっていなければ、覆う。切り替われば、このタイマーごと消える
        setTimeout(() => {
            if (submitting) {
                document.body.append(busyOverlay);
            }
        }, BUSY_DELAY);
    });
});

// ブラウザの「戻る」で、送信した後の状態のまま画面が復元されることがある。覆ったままでは
// 何も押せないので、復元されたときは元に戻す。
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        stopWaiting();
    }
});

// Escキーで読み込みを止めたときは、画面が切り替わらないので、元に戻す。
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && submitting) {
        stopWaiting();
    }
});
