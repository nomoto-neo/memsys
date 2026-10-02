<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * WYSIWYGエディタで入力されたHTMLから、許可したタグ・属性以外を取り除く
 * （サニタイズする）。
 *
 * ■ なぜ必要か
 *
 * エディタが制限しているのは「エディタの画面から入力できるもの」だけで、
 * 開発者ツールやcurlで直接POSTすれば、エディタを通さずに任意のHTML
 * （<script>やonerror属性を含む）を送れてしまう。本文はエスケープせずに
 * {!! !!}で表示するので、サーバー側で何もしないと、スクリプトがそのまま
 * 公開ページや管理画面で実行される（保存型XSS）。管理画面で動いた場合は、
 * それを開いた管理者の権限で操作されてしまう（例: 管理者アカウントを
 * 勝手に作られる）。ブラウザ側の制限は安全対策にならないので、必ず
 * サーバー側でこのクラスを通す。
 *
 * ■ 使っているライブラリ
 *
 * PHPのHTMLサニタイズで最も広く使われているHTML Purifier
 * （ezyang/htmlpurifier）。導入は次のコマンド。
 *
 *   composer require ezyang/htmlpurifier
 *
 * Laravel用のラッパー（mews/purifier）もあるが、中身は同じHTML Purifierで、
 * 設定ファイルの公開などの手順が増えるだけなので、許可リストとその理由を
 * このファイル1か所にまとめる方を選んだ。
 *
 * ■ 許可の考え方
 *
 * 特定のエディタ（CKEditor・summernote）のツールバーから作れるものだけに
 * 絞るのではなく、「文章の見た目を整えるためのタグ・属性」は広く許可し、
 * 「スクリプトを動かせるもの・ページの見た目や動作を乗っ取れるもの・フォーム」を
 * 確実に取り除く、という方針にしている。どちらのエディタにもHTMLの
 * ソースを直接編集する機能があり、プラグインを足すこともあるので、
 * 許可リストを必要最低限にすると、運用中に正当な書式が黙って消えて
 * しまうため。
 *
 * 許可しているもの:
 * - 文章の構造: p, br, div, span, h1〜h6, blockquote, pre, code, hr,
 *   ul, ol, li, dl, dt, dd
 * - 文字の装飾: strong, b, em, i, u, s, strike, del, ins, sub, sup,
 *   small, big, font（color・face・size）, abbr, cite, q, kbd, samp, var, tt
 * - リンク: a（href・title・target）。URLはhttp・https・mailto・telと
 *   相対URLだけで、javascript:などは取り除かれる。targetは_blankだけを
 *   許可し、付いている場合はrel="noopener noreferrer"が自動で足される
 * - 画像: img（src・alt・title・width・height）。srcのURLはリンクと同じ
 *   制限（data:で始まる埋め込み画像も取り除かれる）
 * - 表: table, caption, colgroup, col, thead, tbody, tfoot, tr,
 *   th・td（colspan・rowspan）
 * - HTML5のfigure・figcaption（CKEditorが画像・表を包むのに使う）
 * - 動画の埋め込み: iframeは、srcがYouTube・Vimeoの埋め込み用URLの
 *   ものだけ（summernoteの「動画」ボタンが出力する）
 * - すべてのタグに付けられる属性: class（クラス名は制限しない）,
 *   style（下のALLOWED_CSSにあるプロパティだけ）, title
 *
 * 取り除かれるもの（主なもの）:
 * - script・style・link・meta・object・embed・form・input・button・
 *   上記以外のiframeなど、許可リストに無いタグ
 * - on〜で始まるイベント属性、id、data-〜属性など、許可リストに無い属性
 * - styleの中の、ALLOWED_CSSに無いプロパティ（positionで画面全体を
 *   覆う、background-imageで外部のURLを読み込ませる、などを防ぐ）
 *
 * 許可リストを変えたら、DEFINITION_REVを1つ上げること。
 */
final class HtmlSanitizer
{
    // CKEditor5が出力し、HTML Purifierが標準では知らないHTML5のタグ
    // （figure・figcaption）を定義に足しているので、その定義の識別子と版数。
    // 許可リストを変えたら、DEFINITION_REVを1つ上げること（HTML Purifierが
    // 定義をキャッシュしている場合に、古い定義が使われ続けないようにするため）。
    private const DEFINITION_ID = 'memsys-wysiwyg';

    private const DEFINITION_REV = 2;

    private const ALLOWED_ELEMENTS = [
        'p', 'br', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'pre', 'code', 'hr',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins', 'sub', 'sup',
        'small', 'big', 'font', 'abbr', 'cite', 'q', 'kbd', 'samp', 'var', 'tt',
        'a', 'img',
        'table', 'caption', 'colgroup', 'col', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'figure', 'figcaption',
        'iframe',
    ];

    // "*."で始まるものは、すべてのタグに付けられる属性。
    private const ALLOWED_ATTRIBUTES = [
        '*.class', '*.style', '*.title',
        'a.href', 'a.target',
        'img.src', 'img.alt', 'img.width', 'img.height',
        'font.color', 'font.face', 'font.size',
        'th.colspan', 'th.rowspan', 'td.colspan', 'td.rowspan',
        'col.span', 'colgroup.span',
        'iframe.src', 'iframe.width', 'iframe.height', 'iframe.frameborder',
    ];

    // style属性の中で使ってよいCSSのプロパティ。文字・段落・表・画像の
    // 見た目に関わるものだけで、position・display・background-image・
    // url()を書けるものなどは入れていない。HTML Purifierが知らない
    // プロパティを書くと警告になる（Laravelでは例外になる）ので、
    // 増やすときはHTML Purifierの対応しているプロパティか確かめること。
    private const ALLOWED_CSS = [
        'color', 'background-color',
        'font-family', 'font-size', 'font-weight', 'font-style', 'font-variant',
        'text-align', 'text-decoration', 'text-indent', 'text-transform',
        'letter-spacing', 'word-spacing', 'line-height', 'vertical-align', 'white-space',
        'width', 'height', 'float', 'clear',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'border', 'border-width', 'border-style', 'border-color',
        'border-top', 'border-right', 'border-bottom', 'border-left',
        'border-collapse',
        'list-style-type',
    ];

    private const ALLOWED_SCHEMES = ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true];

    // 埋め込みを許可するiframeのsrc。YouTube（プライバシー強化モードを含む）と
    // Vimeoの埋め込み用URLだけ。
    private const SAFE_IFRAME_REGEXP = '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%';

    private static ?HTMLPurifier $purifier = null;

    /**
     * サニタイズしたHTMLを返す。nullならnull、空文字なら空文字をそのまま返す。
     */
    public static function clean(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return self::purifier()->purify($html);
    }

    /**
     * HTML Purifierの設定の組み立ては重いので、1回のリクエストの中では
     * 1度だけ作って使い回す。
     */
    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        // Transitionalにしているのは、u・s・strike・fontなど、エディタが
        // 出力することのある古い書式のタグも許可するため。
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.AllowedElements', self::ALLOWED_ELEMENTS);
        $config->set('HTML.AllowedAttributes', self::ALLOWED_ATTRIBUTES);
        $config->set('CSS.AllowedProperties', self::ALLOWED_CSS);
        $config->set('URI.AllowedSchemes', self::ALLOWED_SCHEMES);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', self::SAFE_IFRAME_REGEXP);
        // altの無い<img>には、HTML Purifierがaltを補う。既定ではsrcの
        // ファイル名（サーバー上のランダムな名前）が入るので、空にする。
        // エディタでアップロードした画像には、元のファイル名がaltとして
        // 入っている（wysiwyg_*.js参照）。
        $config->set('Attr.DefaultImageAlt', '');

        // HTML Purifierは既定では定義のキャッシュをライブラリ自身の
        // ディレクトリ（vendor配下）に書こうとし、書き込めないと警告を出す。
        // 本文のサニタイズは保存時と表示時に呼ぶ程度で、毎回定義を組み立てても
        // 負荷は問題にならないので、キャッシュ自体を使わない設定にしている。
        $config->set('Cache.DefinitionImpl', null);

        $config->set('HTML.DefinitionID', self::DEFINITION_ID);
        $config->set('HTML.DefinitionRev', self::DEFINITION_REV);

        // figure・figcaptionはHTML5で追加されたタグで、HTML Purifierは
        // 標準では知らないので、ここで定義を足す（HTML Purifierの公式に
        // 案内されている、独自のタグを追加する書き方）。
        if ($definition = $config->maybeGetRawHTMLDefinition()) {
            $definition->addElement('figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common');
            $definition->addElement('figcaption', 'Inline', 'Flow', 'Common');
        }

        return self::$purifier = new HTMLPurifier($config);
    }
}
