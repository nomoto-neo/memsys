/*
 * WYSIWYG欄(textarea.wysiwyg)を、CKEditor5に置き換える。
 *
 * エディタの種類ごとに違うのはこのファイルだけ。summernoteなど別の
 * エディタにするときは、このファイルの代わりにそのエディタ用のファイル
 * (wysiwyg_summernote.jsなど)を作り、画面から読み込むファイルを
 * 差し替える。サーバー側(AjaxFileUploadトレイト・HtmlSanitizer)と
 * Blade(textareaのclass="wysiwyg"・data-upload-url)は、エディタが
 * 変わっても同じまま使える。
 *
 * エディタに挿入した画像は、一覧用画像などと同じAjaxアップロード
 * (textareaのdata-upload-url)へ送る。fieldにはtextareaのname(例: body)を
 * 送り、サーバー側はそれをコントローラーのWYSIWYG_FIELDSで引いて横幅を
 * 決める。サーバーはtmpに保存したURLを返し、エディタはそれを<img src>に
 * して本文に入れる(正式な保存先へ移すのは、登録/更新の確定時。詳しくは
 * App\Support\AjaxFileUploadの「WYSIWYG欄の画像」参照)。
 */
import {
    Autoformat,
    BlockQuote,
    Bold,
    ClassicEditor,
    Essentials,
    Heading,
    Image,
    ImageCaption,
    ImageStyle,
    ImageTextAlternative,
    ImageToolbar,
    ImageUpload,
    Indent,
    Italic,
    Link,
    List,
    MediaEmbed,
    Paragraph,
    PasteFromOffice,
    PictureEditing,
    Table,
    TableToolbar,
    TextTransformation,
} from 'ckeditor5';
import translations from 'ckeditor5/translations/ja.js';
import 'ckeditor5/ckeditor5.css';
import { postUploadFile } from './upload_request.js';

/*
 * エディタに入れる機能と、ツールバーの並び。
 *
 * CKEditorは、使う機能を自分で選んで組む。以前の「できあいの組み合わせ」の
 * パッケージ(@ckeditor/ckeditor5-build-classic)は更新が止まり、既知の脆弱性が
 * 残ったままなので使わない。ここの中身は、そのパッケージと同じ機能と並びにしてある。
 * 機能を足すときは、上のimportとPLUGINSに足し、ツールバーに出すならTOOLBARにも足す。
 * 本文に新しいタグや属性が出るようになるので、App\Support\HtmlSanitizerの許可も合わせる。
 */
const PLUGINS = [
    Essentials,
    Autoformat,
    Bold,
    Italic,
    BlockQuote,
    Heading,
    Image,
    ImageCaption,
    ImageStyle,
    ImageTextAlternative,
    ImageToolbar,
    ImageUpload,
    Indent,
    Link,
    List,
    MediaEmbed,
    Paragraph,
    PasteFromOffice,
    PictureEditing,
    Table,
    TableToolbar,
    TextTransformation,
];

const TOOLBAR = [
    'undo',
    'redo',
    '|',
    'heading',
    '|',
    'bold',
    'italic',
    '|',
    'link',
    'uploadImage',
    'insertTable',
    'blockQuote',
    'mediaEmbed',
    '|',
    'bulletedList',
    'numberedList',
    'outdent',
    'indent',
];

/*
 * CKEditorの画像のアップロードは「アップロードアダプター」という部品に
 * 任せる作りになっている。標準で入っているもの(CKFinder用など)は
 * このサーバーの送受信の形式に合わないので、ここで自前のものを用意し、
 * FileRepositoryプラグインに登録している。
 */
class WysiwygUploadAdapter {
    constructor(loader, uploadUrl, field) {
        this.loader = loader;
        this.uploadUrl = uploadUrl;
        this.field = field;
        this.controller = null;
    }

    // CKEditorが呼ぶ。{ urls: { default: 画像のURL }, ... }で解決する
    // Promiseを返す。urls以外に入れた値(ここではalt)は、アップロード完了時の
    // uploadCompleteイベントで受け取れる(下のsetAltOnUpload()参照)。
    // 失敗時にrejectした文字列は、CKEditorがそのまま利用者に表示する。
    upload() {
        return this.loader.file.then((file) => {
            this.controller = new AbortController();

            return postUploadFile(this.uploadUrl, this.field, file, this.controller.signal)
                .then(({ ok, data }) => {
                    if (! ok) {
                        return Promise.reject(data.message || 'アップロードに失敗しました。');
                    }

                    return { urls: { default: data.url }, alt: data.origin_name };
                });
        });
    }

    // アップロード中に画像が取り消されたときにCKEditorが呼ぶ。
    abort() {
        if (this.controller) {
            this.controller.abort();
        }
    }
}

/*
 * アップロードした画像のaltに、元のファイル名を入れる。
 *
 * CKEditorは画像をaltの無い<img>として挿入するので、そのまま保存すると
 * altは空になる(HtmlSanitizerのAttr.DefaultImageAlt参照)。そこで、
 * アップロードが完了した時点で、アダプターが返したalt(元のファイル名)を
 * 画像に設定する。
 * 画像の「代替テキスト」ボタンで、後から書き換えることもできる。
 */
function setAltOnUpload(editor) {
    if (! editor.plugins.has('ImageUploadEditing')) {
        return;
    }

    editor.plugins.get('ImageUploadEditing').on('uploadComplete', (evt, { data, imageElement }) => {
        if (! data.alt) {
            return;
        }

        editor.model.change((writer) => {
            writer.setAttribute('alt', data.alt, imageElement);
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea.wysiwyg').forEach((textarea) => {
        const uploadUrl = textarea.dataset.uploadUrl;
        const field = textarea.name;

        function uploadAdapterPlugin(editor) {
            editor.plugins.get('FileRepository').createUploadAdapter =
                (loader) => new WysiwygUploadAdapter(loader, uploadUrl, field);
        }

        ClassicEditor.create(textarea, {
            // CKEditorを、オープンソースのライセンス(GPL)で使う印。44版から必須。
            // 商用のライセンスを買ったサイトでは、そのキーに差し替える。
            licenseKey: 'GPL',
            // ボタンの説明やメニューを日本語にする。ほかの言語にするときは、上のimportの
            // ファイル(translations/ja.js)を、その言語のものに差し替える。
            translations: [translations],
            plugins: PLUGINS,
            toolbar: TOOLBAR,
            extraPlugins: [uploadAdapterPlugin],
            // エディタで選べる画像の種類。サーバー側の許可
            // (AjaxFileUpload::ALLOW_IMAGE_TYPES)に合わせている。
            // 最終的な判定はサーバー側で行うので、ここは利用者が
            // 無駄にアップロードしないための案内にすぎない。
            image: {
                upload: {
                    types: ['jpeg', 'png', 'webp'],
                },
                // 画像を選んだときに出る小さなツールバー
                toolbar: [
                    'imageStyle:inline',
                    'imageStyle:block',
                    'imageStyle:side',
                    '|',
                    'toggleImageCaption',
                    'imageTextAlternative',
                ],
            },
            // 表を選んだときに出る小さなツールバー
            table: {
                contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'],
            },
        }).then((editor) => {
            setAltOnUpload(editor);

            // CKEditor5は元の<textarea>の値を自動では更新しないので、
            // 送信時にeditor.getData()を明示的に書き戻す。
            textarea.closest('form').addEventListener('submit', () => {
                textarea.value = editor.getData();
            });
        }).catch((error) => console.error(error));
    });
});
