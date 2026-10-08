/*
 * WYSIWYG欄(textarea.wysiwyg)を、SunEditorに置き換える。
 *
 * エディタの種類ごとに違うのはこのファイルだけ。wysiwyg_summernote.jsと同じ役割の、
 * SunEditor版。どちらか一方を画面から読み込む。サーバー側(AjaxFileUploadトレイト・
 * HtmlSanitizer)とBlade(textareaのclass="wysiwyg"・data-upload-url)は、どちらの
 * エディタでも同じまま使える。
 *
 * SunEditorはnpmのパッケージ(suneditor。MITライセンス)で、ほかのパッケージにもjQueryにも
 * 頼らない。見た目のCSSもここで読み込むので、画面には@viteでこのファイルを書くだけでよい。
 *
 * エディタに挿入した画像は、一覧用画像などと同じAjaxアップロード
 * (textareaのdata-upload-url)へ送る。fieldにはtextareaのname(例: body)を
 * 送り、サーバー側はそれをコントローラーのWYSIWYG_FIELDSで引いて横幅を
 * 決める。サーバーはtmpに保存したURLを返し、それを<img src>にして本文に
 * 入れる(正式な保存先へ移すのは、登録/更新の確定時。詳しくは
 * App\Support\AjaxFileUploadの「エディタの欄の画像」参照)。
 */
import suneditor from 'suneditor';
import {
    align,
    backgroundColor,
    blockquote,
    blockStyle,
    fontColor,
    hr,
    image,
    link,
    list_bulleted,
    list_numbered,
    table,
} from 'suneditor/plugins';
import ja from 'suneditor/langs/ja';
import 'suneditor/css/editor';
import 'suneditor/css/contents';
import { postUploadFile } from './upload_request.js';

/*
 * エディタに入れる機能と、ツールバーの並び。
 *
 * SunEditorは、使う機能(プラグイン)を選んで組む。太字や元に戻すのような基本のボタンは
 * 本体に入っていて、PLUGINSに書くのは、見出し・表・画像のような追加の機能だけ。
 * 機能を足すときは、上のimportとPLUGINSに足し、TOOLBARにボタンの名前を足す。
 * 本文に新しいタグや属性が出るようになるので、App\Support\HtmlSanitizerの許可も合わせる。
 * 動画の埋め込み(video)は入れていない。SunEditorは動画をpositionの指定で配置するが、
 * HtmlSanitizerがstyleのpositionを許可していないので、保存すると崩れるため。
 */
const PLUGINS = [
    blockStyle,
    fontColor,
    backgroundColor,
    align,
    list_bulleted,
    list_numbered,
    link,
    image,
    table,
    blockquote,
    hr,
];

const TOOLBAR = [
    ['undo', 'redo'],
    ['blockStyle'],
    ['bold', 'underline', 'italic', 'strike'],
    ['fontColor', 'backgroundColor'],
    ['removeFormat'],
    ['align', 'list_bulleted', 'list_numbered', 'outdent', 'indent'],
    ['link', 'image', 'table', 'blockquote', 'hr'],
    ['fullScreen', 'showBlocks', 'codeView'],
];

// エディタで選べる画像の種類。サーバー側の許可
// (AjaxFileUpload::ALLOW_IMAGE_TYPES)に合わせている。最終的な判定は
// サーバー側で行うので、ここは利用者が無駄にアップロードしないための
// 案内にすぎない。
const ACCEPT_IMAGE_TYPES = 'image/jpeg,image/png,image/webp';

/*
 * 画像を本文に入れる直前にSunEditorが呼ぶ処理を作る。画像の挿入(ボタン・ドラッグ&ドロップ・
 * 貼り付け)のたびに呼ばれる。
 *
 * 画像の画面でURLを指定したときは、何も送らずに、そのURLのまま本文に入れる。サーバー側は、
 * srcが一時ファイルでもこのレコードの保存先でもない画像には触らない。
 *
 * SunEditorの標準の送信は、送る項目の名前と返事の形がこのサーバーと合わない。送り先を
 * 指定しないと、画像をbase64のまま本文に埋め込み、保存時にHtmlSanitizerがdata:のsrcを
 * 取り除くので、画像が黙って消える。そこで、ここで自前で送り、返ってきたURLを
 * 「URLを指定した画像」として本文に入れる。大きさや配置は、画像の画面で選んだ値(info)が
 * そのまま使われる。
 */
function createImageUploader(uploadUrl, field) {
    return async function onImageUploadBefore({ $, info }) {
        // URLを指定した画像。trueを返すと、SunEditorがそのまま本文に入れる
        if (info.url) {
            return true;
        }

        $.ui.showLoading();

        try {
            for (const file of Array.from(info.files)) {
                const { ok, data } = await postUploadFile(uploadUrl, field, file);

                if (! ok) {
                    $.ui.alertOpen(data.message || 'アップロードに失敗しました。', 'error');

                    // falseを返すと、画像の画面は閉じずに残る
                    return false;
                }

                // altが空なら、元のファイル名を入れる(入れないとaltは空になる。
                // wysiwyg_summernote.jsと同じ)。画像の画面で、後から書き換えられる。
                $.plugins.image.uploadService.urlUpload({
                    ...info,
                    url: data.url,
                    alt: info.alt || data.origin_name,
                    files: { name: data.origin_name, size: file.size },
                });
            }
        } catch {
            $.ui.alertOpen('アップロードに失敗しました。', 'error');

            return false;
        } finally {
            $.ui.hideLoading();
        }

        // 何も返さないと、SunEditorは自分では送らずに、画像の画面だけを閉じる
    };
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea.wysiwyg').forEach((textarea) => {
        const editor = suneditor.create(textarea, {
            plugins: PLUGINS,
            buttonList: TOOLBAR,
            // ボタンの説明やメニューを日本語にする。ほかの言語にするときは、上のimportの
            // ファイル(langs/ja)を、その言語のものに差し替える。
            lang: ja,
            minHeight: '250px',
            image: {
                acceptedFormats: ACCEPT_IMAGE_TYPES,
            },
            events: {
                onImageUploadBefore: createImageUploader(textarea.dataset.uploadUrl, textarea.name),
            },
        });

        // SunEditorは元の<textarea>の値を自動では更新しないので、
        // 送信時に本文を明示的に書き戻す。
        textarea.closest('form').addEventListener('submit', () => {
            textarea.value = editor.$.html.get();
        });
    });
});
