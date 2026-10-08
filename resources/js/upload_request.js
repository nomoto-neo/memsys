/*
 * AjaxFileUploadトレイトのuploadAjaxFile()へ、ファイルを1つ送る。
 * 一覧用画像・添付ファイルの欄(ajax_upload.js)と、WYSIWYGエディタの
 * 画像挿入(wysiwyg_suneditor.jsなど、エディタごとのファイル)の両方から使う。
 *
 * 送るのはファイル(file)と、どの欄宛てか(field)の2つ。CSRFトークンは、
 * 画面の<meta name="csrf-token">から読む。
 *
 * 戻り値は{ ok, data }で解決するPromise。okはHTTPのステータスが成功
 * (2xx)かどうか、dataはサーバーが返したJSON。成功ならdata.tmp_name・
 * data.origin_name・data.url(tmpに置いたファイルのURL)、失敗なら
 * data.message(利用者に見せるメッセージ)が入っている。通信そのものに
 * 失敗した場合はrejectされる。
 *
 * signalには、アップロードを途中で取り消すためのAbortSignalを渡せる(省略可)。
 */
function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

export function postUploadFile(uploadUrl, field, file, signal = undefined) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('field', field);

    return fetch(uploadUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
        signal,
    }).then((response) => response.json().then((data) => ({ ok: response.ok, data })));
}
