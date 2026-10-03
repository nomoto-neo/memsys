<?php

namespace App\Support;

use Illuminate\Support\Facades\Blade;

/**
 * メールのテンプレートを1つ読み込み、変数を展開して、見出しと本文に分ける。
 * テンプレートはresources/mail-templates/に置く。
 *
 * ■ 展開
 * テンプレートはBladeで展開するので、@ifや@foreachで、条件や繰り返しを書ける。
 * Bladeは@phpで何でも書けてしまうので、テンプレートを直すのはプログラマーだけ、という前提で使う。
 *
 * ■ ファイルの構成
 * - {テンプレート名}.blade.php：見出しの行（FROM_MAIL:など）と本文。展開した後の文字列を、
 *   MailTemplateParserで見出しと本文に分ける
 * - {テンプレート名}_html.blade.php：HTML版の本文。同じ場所に置くと、HTMLとテキストの両方を
 *   付けたメールになる。こちらのファイルには見出しの行は書かずに本文だけを書く。
 * view()機能は使わずに中身を読んで展開するので、resources/viewsでなくてよいが、bladeの文法で
 * 書くので拡張子を.blade.phpにしている。
 *
 * ■ エスケープの書き分け
 * - テキスト版は、値を{!! !!}で埋め込む。{{ }}だと、&が&amp;になるなど文字が化ける
 * - HTML版は、訪問者が入力した値を{{ }}で埋め込む。そのまま入れると、入力に書かれた
 *   タグが、受け取ったスタッフのメールソフトで動いてしまう
 * 改行を<br>にする変換はしない。HTML版で、その項目を囲むタグに
 * style="white-space: pre-wrap;"を付ければ、改行どおりに表示できる。
 *
 * テンプレートに、渡していない変数を書くと、警告がログに残り、そこは空になる。
 */
final class MailTemplate
{
    private const DIRECTORY = 'mail-templates';

    /**
     * テンプレートを読み込んで展開し、見出しと本文に分けた結果を返す。
     * MailTemplateParser::parse()の結果に、HTML版の本文の'html'を足したもの。
     * HTML版が無ければ'html'はnull。
     */
    public static function render(string $name, array $vars): array
    {
        // テキスト版を展開し、見出しと本文に分ける
        $raw = self::readFile(self::mainPath($name));
        $expanded = Blade::render($raw, $vars);

        $parsed = MailTemplateParser::parse($expanded);

        // HTML版があれば、展開して足す
        $parsed['html'] = self::renderHtmlCompanion($name, $vars);

        return $parsed;
    }

    // HTML版のテンプレートがあれば、展開した中身を返す。無ければnullで、テキストだけのメールになる
    private static function renderHtmlCompanion(string $name, array $vars): ?string
    {
        $path = self::htmlPath($name);

        if (! is_readable($path)) {
            return null;
        }

        return Blade::render(self::readFile($path), $vars);
    }

    // テキスト版のテンプレートの場所
    private static function mainPath(string $name): string
    {
        return resource_path(self::DIRECTORY.'/'.$name.'.blade.php');
    }

    // HTML版のテンプレートの場所
    private static function htmlPath(string $name): string
    {
        return resource_path(self::DIRECTORY.'/'.$name.'_html.blade.php');
    }

    // テンプレートの中身。読めなければ例外
    private static function readFile(string $path): string
    {
        if (! is_readable($path)) {
            throw new MailTemplateException("メールテンプレートが読み込めません: {$path}");
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new MailTemplateException("メールテンプレートが開けません: {$path}");
        }

        return $raw;
    }
}
