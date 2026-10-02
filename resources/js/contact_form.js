/*
 * resources/views/contact/create.blade.php・confirm.blade.php用の
 * スクリプト。次の3つの独立した挙動をまとめている。
 *
 * 1. 郵便番号(id="zip")：入力のたびに数字だけを取り出し、7桁に
 *    なったら"123-4567"の形に自動整形する。7桁になった時点の値が
 *    前回検索した値と違っていれば、zipcloud（日本郵便の郵便番号
 *    データを配信している無料の外部API）で住所を検索し、都道府県・
 *    市区町村・町域をフォームへ自動入力する。
 *
 *    外部APIなので、通信できない環境（デモ環境がインターネットに
 *    出られない設定になっている場合等）では検索は失敗するが、
 *    catch()で握りつぶして何もしない＝手入力を妨げないだけにしている。
 *    別のAPIに差し替えたい場合はlookupAddress()だけを書き換えればよい。
 *
 * 2. 同意チェックボックス(id="agree")：チェックが入っている間だけ、
 *    「確認画面へ進む」ボタン(id="contact_submit")を有効にする。
 *    サーバー側でも同じ内容をrules()の'agree' => ['accepted']で検証して
 *    いるので、ここが実質の最終防衛ラインというわけではない
 *    （JavaScript無効の環境やdevtoolsでの改ざんに対する保険）。
 *
 * 3. 二重送信防止（data-guard-double-submit属性の付いたフォーム）：
 *    submitされた瞬間に、そのフォーム内のsubmitボタンをdisabledにする。
 *    disabledにするのは「submitイベントが発生した後」なので、今まさに
 *    始まったこの1回の送信自体は止めない。連打やダブルクリックで
 *    同じ内容がもう一度サーバーへ届くのを防ぐのが目的（確認画面の
 *    「送信する」を2回押すと、t_inquiriesの行と通知メールが2重に
 *    なってしまう、という問題への対策）。
 *
 *    ただしこれはJavaScriptが動く環境でしか効かない保険に過ぎない。
 *    本当の防御（confirm画面を経由した1回きりの送信であることの保証）は
 *    サーバー側のconfirm_tokenの仕組み（App\Http\Controllers\
 *    ContactController::issueConfirmToken()・hasValidConfirmToken()）が
 *    担っている。
 */
(() => {
    function setupZipLookup() {
        const zipInput = document.getElementById('zip');

        if (! zipInput) {
            return;
        }

        const prefectureSelect = document.getElementById('prefecture');
        const cityInput = document.getElementById('city');
        const addressOtherInput = document.getElementById('address_other');

        // 直近に住所検索を行った、ハイフンを除いた7桁の値。
        // 同じ値のままでは（一度消してまた同じ番号を入力し直した等）
        // 検索し直さない。
        let lastLookedUp = zipInput.value.replace(/\D/g, '');

        function applyAddress(prefectureName, city, town) {
            if (prefectureSelect && prefectureName) {
                const option = Array.from(prefectureSelect.options)
                    .find((o) => o.textContent === prefectureName);
                if (option) {
                    prefectureSelect.value = option.value;
                }
            }
            if (cityInput) {
                cityInput.value = city || '';
            }
            if (addressOtherInput) {
                addressOtherInput.value = town || '';
            }
        }

        function lookupAddress(digits) {
            fetch(`https://zipcloud.ibsnet.co.jp/api/search?zipcode=${digits}`)
                .then((response) => response.json())
                .then((json) => {
                    const result = json.results && json.results[0];
                    if (result) {
                        applyAddress(result.address1, result.address2, result.address3);
                    }
                })
                .catch(() => {
                    // 通信できなくても、郵便番号欄の入力自体は妨げない。
                });
        }

        zipInput.addEventListener('input', () => {
            const digits = zipInput.value.replace(/\D/g, '').slice(0, 7);
            zipInput.value = digits.length === 7
                ? `${digits.slice(0, 3)}-${digits.slice(3)}`
                : digits;

            if (digits.length === 7 && digits !== lastLookedUp) {
                lastLookedUp = digits;
                lookupAddress(digits);
            }
        });
    }

    function setupAgreeCheckbox() {
        const agreeCheckbox = document.getElementById('agree');
        const submitButton = document.getElementById('contact_submit');

        if (! agreeCheckbox || ! submitButton) {
            return;
        }

        const syncSubmitState = () => {
            submitButton.disabled = ! agreeCheckbox.checked;
        };

        agreeCheckbox.addEventListener('change', syncSubmitState);
        syncSubmitState();
    }

    function setupSubmitOnceGuard() {
        document.querySelectorAll('form[data-guard-double-submit]').forEach((form) => {
            form.addEventListener('submit', () => {
                form.querySelectorAll('button[type="submit"]').forEach((button) => {
                    button.disabled = true;
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        setupZipLookup();
        setupAgreeCheckbox();
        setupSubmitOnceGuard();
    });
})();
