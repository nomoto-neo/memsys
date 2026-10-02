<?php

namespace App\Support;

use Illuminate\Support\Facades\Blade;

/**
 * メールテンプレート（resources/mail-templates/配下のBladeファイル）を1件
 * 読み込み、変数を展開してMailTemplateParserに渡すまでを行うクラス。
 * 以前のフレームワークで言う「Smartyでfetch()して、結果のテキストを
 * 分解する」の一連の流れに当たる。
 *
 * ■ 展開エンジン
 *
 * テンプレートはBladeで展開する（Blade::render()）。本文の中で条件によって
 * ブロックを出し分けたり、配列を繰り返したりできるよう、@if・@foreach・
 * @includeなどの制御構文が使えるものにしている。以前のフレームワーク
 * （Smarty）の{if}・{foreach}に当たる。
 *
 * Bladeは最終的にPHPへコンパイルされて実行されるので、テンプレートに@phpを
 * 書けば任意の処理ができる。テンプレートを編集するのはプログラマーだけ、
 * という前提で使うこと（プログラマー以外が編集する運用にするなら、展開の
 * 方法を見直す）。
 *
 * ■ ファイルの構成・拡張子
 *
 * - {テンプレート名}.blade.php … 見出し行（FROM_MAIL:等）＋本文。
 *   ファイル全体をBlade::render()に通した後の文字列を、そのまま
 *   MailTemplateParser::parse()に渡して見出しと本文に分解する
 *   （見出し行の解析はBladeとは関係なく、以前のフレームワークと同じ規約）。
 * - {テンプレート名}_html.blade.php … HTML版（マルチパート送信用）。
 *   同じディレクトリ・同じベース名＋"_html"サフィックスで置くと、
 *   自動的に読み込んでHTML版の本文として使う（無ければプレーン
 *   テキストのみで送信される。App\Mail\TemplatedMail::content()参照）。
 *   こちらには見出し行を書かない。宛先・件名などの制御情報は
 *   {テンプレート名}.blade.php側だけが持つ（2つのファイルに書けてしまうと、
 *   内容が食い違ったときにどちらが正か分からなくなるため）。
 *
 *   拡張子を.blade.phpにしているのは、エディタにBladeファイルとして
 *   認識させ、シンタックスハイライトや構文チェックが効くようにするため。
 *   このクラスはLaravelのview()（ビュー名の解決）は使わず、ファイルの中身を
 *   file_get_contents()で読んでBlade::render()に文字列として渡すので、
 *   resources/views配下に置く必要は無く、resources/mail-templates/に置く。
 *
 * ■ エスケープの書き分け
 *
 * Bladeの書き方のとおり、{{ $var }}はエスケープし、{!! $var !!}は
 * そのまま出力する。
 *
 * - {テンプレート名}.blade.php（プレーンテキスト）側は、値を
 *   {!! !!}で埋め込む。{{ }}にすると"&"が"&amp;"に化けるなど、
 *   プレーンテキストなのに文字化けする。
 * - {テンプレート名}_html.blade.php（HTML）側は、訪問者の入力値を
 *   埋め込む箇所は{{ }}にする。問い合わせフォームの各項目は訪問者の
 *   入力値なので、無変換のままHTMLへ差し込むと"<script>...</script>"
 *   のような値がそのままタグとして解釈され、受信したスタッフの
 *   メールソフト上でHTMLインジェクションが成立してしまう。
 *
 *   改行を<br>に変えたい項目（お問い合わせ内容等）があっても、自動
 *   変換はしない。HTML版のテンプレートで、その項目を囲むタグに
 *   style="white-space: pre-wrap;"を指定すれば、改行入りの値でも
 *   CSSだけで見た目どおりに表示できる。
 *
 * ■ 変数名の書き間違い
 *
 * $varsに無い変数をテンプレートに書くと、"Undefined variable"の警告が
 * ログに残る（処理は止まらず、その箇所は空になる）。ログで書き間違いに
 * 気づける。
 */
final class MailTemplate
{
    private const DIRECTORY = 'mail-templates';

    /**
     * $nameのテンプレートファイル（{$name}.blade.php）を読み込み、
     * $varsで変数展開したうえでMailTemplateParser::parse()の結果
     * （from_mail・from_name・to・cc・bcc・reply_to・subject・body）に、
     * 'html'（HTML版の本文。{$name}_html.blade.phpが無ければnull）を
     * 加えて返す。
     */
    public static function render(string $name, array $vars): array
    {
        $raw = self::readFile(self::mainPath($name));
        $expanded = Blade::render($raw, $vars);

        $parsed = MailTemplateParser::parse($expanded);
        $parsed['html'] = self::renderHtmlCompanion($name, $vars);

        return $parsed;
    }

    /**
     * {$name}_html.blade.phpが同じディレクトリにあれば、変数展開した
     * 内容を返す。無ければnull（＝App\Mail\TemplatedMailはプレーン
     * テキストのみで送信する）。
     */
    private static function renderHtmlCompanion(string $name, array $vars): ?string
    {
        $path = self::htmlPath($name);

        if (! is_readable($path)) {
            return null;
        }

        return Blade::render(self::readFile($path), $vars);
    }

    private static function mainPath(string $name): string
    {
        return resource_path(self::DIRECTORY.'/'.$name.'.blade.php');
    }

    private static function htmlPath(string $name): string
    {
        return resource_path(self::DIRECTORY.'/'.$name.'_html.blade.php');
    }

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
