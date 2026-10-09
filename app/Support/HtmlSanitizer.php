<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * エディタで入力されたHTMLをサニタイズする。許可したタグと属性のほかは取り除く。
 *
 * ■ なぜ必要か
 * エディタが防いでいるのはエディタの画面から入力できるものだけで、直接送信すれば
 * <script>などを含む好きなHTMLを送れてしまう。本文はエスケープせずに表示するので、
 * サーバーで取り除かないとスクリプトが公開ページや管理画面で動いてしまう。管理画面で
 * 動くと、開いた管理者の権限で操作されてしまう。ブラウザの側の制限は対策にならないので、
 * 必ずサーバーでこのクラスを通す。
 *
 * ■ 使っているライブラリ
 * ezyang/htmlpurifierのHTML Purifier。Laravel用のラッパーもあるが、中身は同じで手順が
 * 増えるだけなので、許可リストと理由をこのファイル1か所にまとめている。
 *
 * ■ 許可の考え方
 * エディタのボタンで作れるものだけに絞るのではなく、文章の見た目を整えるタグと属性は広く
 * 許可し、スクリプトを動かせるもの・ページを乗っ取れるもの・フォームは確実に取り除く。
 * エディタにはHTMLを直接書く機能もあるので、許可を絞りすぎると正しい書式が黙って
 * 消えてしまうため。
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
 *   制限で、data:で始まる埋め込み画像も取り除かれる
 * - 表: table, caption, colgroup, col, thead, tbody, tfoot, tr,
 *   th・td（colspan・rowspan）
 * - HTML5のfigure・figcaption。SunEditorが画像や表を包むのに使う
 * - 動画の埋め込み: iframeは、summernoteの「動画」ボタンが出力する
 *   YouTube・Vimeoの埋め込み用URLのものだけ
 * - すべてのタグに付けられる属性: class, style, title。クラス名は制限せず、
 *   styleは下のALLOWED_CSSにあるプロパティだけ
 *
 * 取り除かれる主なもの:
 * - script・style・link・meta・object・embed・form・input・button・
 *   上記のほかのiframeなど、許可リストに無いタグ
 * - on〜で始まるイベント属性、id、data-〜属性など、許可リストに無い属性
 * - styleの中のALLOWED_CSSに無いプロパティ。positionで画面全体を覆ったり、
 *   background-imageで外部のURLを読み込ませたりするのを防ぐ
 *
 * 許可リストを変えたら、DEFINITION_REVを1つ上げること。
 */
final class HtmlSanitizer
{
    /**
     * HTML Purifierが知らないfigure・figcaptionを足した、タグの定義の名前と版数。
     * この許可リストはキャッシュされるため、古い定義が使われ続けないように許可リストを
     * 変えたらDEFINITION_REVを1つ上げる。
     */
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

    /** "*."で始まるものは、すべてのタグに付けられる属性。 */
    private const ALLOWED_ATTRIBUTES = [
        '*.class', '*.style', '*.title',
        'a.href', 'a.target',
        'img.src', 'img.alt', 'img.width', 'img.height',
        'font.color', 'font.face', 'font.size',
        'th.colspan', 'th.rowspan', 'td.colspan', 'td.rowspan',
        'col.span', 'colgroup.span',
        'iframe.src', 'iframe.width', 'iframe.height', 'iframe.frameborder',
    ];

    /**
     * style属性で使ってよいCSSのプロパティ。文字・段落・表・画像の見た目に関わるものだけで、
     * positionやbackground-imageなどは入れない。HTML Purifierが知らないプロパティを書くと
     * 例外になるので、増やすときは対応しているかを確かめる
     */
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

    /**
     * 埋め込みを許可するiframeのsrc。プライバシー強化モードを含むYouTubeと、
     * Vimeoの埋め込み用URLだけ。
     */
    private const SAFE_IFRAME_REGEXP = '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%';

    private static ?HTMLPurifier $purifier = null;

    /** サニタイズしたHTMLを返す。nullならnull、空文字なら空文字をそのまま返す。 */
    public static function clean(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return self::purifier()->purify($html);
    }

    /** HTML Purifierの設定の組み立ては重いので、1回のリクエストの中では1度だけ作って使い回す */
    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        // エディタが出すことのあるu・s・strike・fontのような古い書式のタグも許可するため、Transitionalにする
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.AllowedElements', self::ALLOWED_ELEMENTS);
        $config->set('HTML.AllowedAttributes', self::ALLOWED_ATTRIBUTES);
        $config->set('CSS.AllowedProperties', self::ALLOWED_CSS);
        $config->set('URI.AllowedSchemes', self::ALLOWED_SCHEMES);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', self::SAFE_IFRAME_REGEXP);
        // altの無い<img>に補うaltを空にする。既定ではサーバー上のランダムなファイル名が入るため
        $config->set('Attr.DefaultImageAlt', '');

        // 定義のキャッシュは使わない。既定ではvendorの下に書こうとして、書けないと警告になる。
        // 保存と表示のときに呼ぶ程度なので毎回組み立てても負荷は問題にならない
        $config->set('Cache.DefinitionImpl', null);

        $config->set('HTML.DefinitionID', self::DEFINITION_ID);
        $config->set('HTML.DefinitionRev', self::DEFINITION_REV);

        // HTML Purifierが知らないHTML5のfigure・figcaptionを定義に足す
        if ($definition = $config->maybeGetRawHTMLDefinition()) {
            $definition->addElement('figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common');
            $definition->addElement('figcaption', 'Inline', 'Flow', 'Common');
        }

        return self::$purifier = new HTMLPurifier($config);
    }
}
