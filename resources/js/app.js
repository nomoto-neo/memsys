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
