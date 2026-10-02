/*
 * WYSIWYG欄(textarea.wysiwyg)を、summernoteに置き換える。
 *
 * wysiwyg_ckeditor.jsと同じ役割の、summernote版。どちらか一方を画面から
 * 読み込む。サーバー側(AjaxFileUploadトレイト・HtmlSanitizer)と
 * Blade(textareaのclass="wysiwyg"・data-upload-url)は、どちらのエディタでも
 * 同じまま使える。
 *
 * このファイルを使うときに必要なもの(手順の詳細はプロジェクトの資料
 * reference/wysiwyg-summernote-switch.md 参照):
 * - jQuery 3系(layouts/admin.blade.phpのコメントを外す)。jQuery 4系では
 *   summernoteが動かないという報告がある
 * - summernoteのCSS・JavaScript(Bootstrap 5用のsummernote-bs5)と日本語の
 *   言語ファイル。画面の@push('head-extra')でCDNから読み込む
 * - vite.config.jsのinputにこのファイルを追加する
 *
 * エディタに挿入した画像は、一覧用画像などと同じAjaxアップロード
 * (textareaのdata-upload-url)へ送る。fieldにはtextareaのname(例: body)を
 * 送り、サーバー側はそれをコントローラーのWYSIWYG_FIELDSで引いて横幅を
 * 決める。サーバーはtmpに保存したURLを返し、それを<img src>にして本文に
 * 入れる(正式な保存先へ移すのは、登録/更新の確定時。詳しくは
 * App\Support\AjaxFileUploadの「WYSIWYG欄の画像」参照)。
 */
import { postUploadFile } from './upload_request.js';

// エディタで選べる画像の種類。サーバー側の許可
// (AjaxFileUpload::ALLOW_IMAGE_TYPES)に合わせている。最終的な判定は
// サーバー側で行うので、ここは利用者が無駄にアップロードしないための
// 案内にすぎない。
const ACCEPT_IMAGE_TYPES = 'image/jpeg,image/png,image/webp';

document.addEventListener('DOMContentLoaded', () => {
    const textareas = document.querySelectorAll('textarea.wysiwyg');

    if (textareas.length === 0) {
        return;
    }

    const $ = window.jQuery;

    // jQueryやsummernoteの読み込み忘れは、エディタが出ないだけで気づきにくい
    // ので、コンソールに理由を出しておく。
    if (! $ || ! $.fn || ! $.fn.summernote) {
        console.error('wysiwyg_summernote.js: jQueryまたはsummernoteが読み込まれていません。');
        return;
    }

    textareas.forEach((textarea) => {
        const uploadUrl = textarea.dataset.uploadUrl;
        const field = textarea.name;
        const $note = $(textarea);

        $note.summernote({
            lang: 'ja-JP',
            height: 300,
            acceptImageFileTypes: ACCEPT_IMAGE_TYPES,
            callbacks: {
                // 画像の挿入(ボタン・ドラッグ&ドロップ・貼り付け)のたびに
                // 呼ばれる。これを指定しないと、summernoteは画像をbase64の
                // まま本文に埋め込み、保存時にHtmlSanitizerがdata:のsrcを
                // 取り除くので、画像が黙って消える。
                onImageUpload(files) {
                    Array.from(files).forEach((file) => {
                        postUploadFile(uploadUrl, field, file)
                            .then(({ ok, data }) => {
                                if (! ok) {
                                    alert(data.message || 'アップロードに失敗しました。');
                                    return;
                                }

                                // altに元のファイル名を入れる(入れないとaltは
                                // 空になる。wysiwyg_ckeditor.jsのsetAltOnUpload()
                                // と同じ)。
                                $note.summernote('insertImage', data.url, ($image) => {
                                    $image.attr('alt', data.origin_name);
                                });
                            })
                            .catch(() => {
                                alert('アップロードに失敗しました。');
                            });
                    });
                },
            },
        });

        // 送信時に本文を<textarea>へ書き戻す。ソース表示(コードビュー)の
        // まま送信されると、コードビューで直した内容が反映されないことが
        // あるので、先に通常の表示へ戻してから取り出す。
        textarea.closest('form').addEventListener('submit', () => {
            if ($note.summernote('codeview.isActivated')) {
                $note.summernote('codeview.toggle');
            }

            textarea.value = $note.summernote('code');
        });
    });
});
