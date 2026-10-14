<?php

namespace Neobit\Core\Mail;

use Illuminate\Support\Facades\Blade;

/**
 * メールのテンプレートを1つ読み込み、変数を展開して、見出しと本文に分ける。
 *
 * ■ 置き場所と、探す順
 * テンプレートは、アプリのresources/mail-templates/に置く。フレームワークが送るメールの
 * 既定のテンプレートは、フレームワークのresources/mail-templates/にある。
 * 名前は、1つか、配列で受け取る。配列は探す名前の一覧で、先に書いた名前を優先する。
 * 1. アプリの置き場所を、一覧の順に探す
 * 2. 無ければ、フレームワークの置き場所を、一覧の順に探す
 * アプリに同じ名前のファイルを置けば、フレームワークの既定の代わりにそれが使われる。
 * ログインする人へのメールは、「種類の名前を付けた名前」「付けない名前」の順の配列で渡される。
 * アプリは、その種類だけの文面をmember_verification_codeに、全部の種類に共通の文面を
 * verification_codeに置ける。
 * 既定のテンプレートは、php artisan vendor:publish --tag=neobit-mail-templates でアプリへ写せる。
 *
 * ■ 展開
 * テンプレートはBladeで展開するので、@ifや@foreachで、条件や繰り返しを書ける。
 * Bladeは@phpで何でも書けてしまうので、テンプレートを直すのはプログラマーだけ、という前提で使う。
 *
 * ■ ファイルの構成
 * - {テンプレート名}.blade.php：見出しの行（FROM:など）と本文。展開した後の文字列を、
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
 * ■ 見出しの行に入る値
 * 見出しの行（SUBJECT: など）に入る値からは、改行を除いて空白に置き換える。訪問者の入力に
 * 改行を書かれて、宛先の行を足されるのを防ぐため。本文に入る値は、改行もそのまま出す。
 * 宛先の行（TO: など）はカンマで複数書けるので、宛先に入れる値は、呼ぶ側で
 * メールアドレスとして検証しておく。
 *
 * テンプレートに、渡していない変数を書くと、警告がログに残り、そこは空になる。
 */
final class MailTemplate
{
    /** テンプレートを置くディレクトリの名前。アプリとフレームワークの、resources/の下 */
    private const DIRECTORY = 'mail-templates';

    /** テンプレートのファイルの拡張子 */
    private const EXTENSION = '.blade.php';

    /**
     * テンプレートを読み込んで展開し、見出しと本文に分けた結果を返す。
     * MailTemplateParser::parse()の結果に、HTML版の本文の'html'を足したもの。
     * HTML版が無ければ'html'はnull。
     *
     * @param  string|string[]  $names  テンプレートの名前。配列なら、先に書いた名前を優先して探す
     */
    public static function render(string|array $names, array $vars): array
    {
        $mainPath = self::locate((array) $names);

        // テキスト版を、見出しの行と本文に分けてから展開する。見出しの行には、改行を除いた値を入れる
        $raw = self::readFile($mainPath);
        [$headerRaw, $bodyRaw] = self::splitHeaderLines($raw);

        $expanded = Blade::render($headerRaw, self::withoutLineBreaks($vars)).Blade::render($bodyRaw, $vars);

        $parsed = MailTemplateParser::parse($expanded);

        // HTML版があれば、展開して足す。テキスト版と同じ場所の、同じ名前のものを使う
        $parsed['html'] = self::renderHtmlCompanion($mainPath, $vars);

        return $parsed;
    }

    /**
     * 展開する前のテンプレートを、先頭の見出しの行（KEY: 値）と、その後ろに分ける。
     * 見出しの行の決め方は、MailTemplateParserと同じ。先頭から、空行か、見出しの形でない行の
     * 手前までが見出し。分けた2つをつなげると、元のテンプレートに戻る。
     *
     * @return array{0: string, 1: string}
     */
    private static function splitHeaderLines(string $raw): array
    {
        $offset = 0;

        foreach (preg_split('/(?<=\n)/', $raw) as $line) {
            if (! preg_match('/^[A-Z_]+:/', $line)) {
                break;
            }

            $offset += strlen($line);
        }

        return [substr($raw, 0, $offset), substr($raw, $offset)];
    }

    /**
     * 文字の値から、改行を除いた変数の一覧を返す。見出しの行に入れる値に使う。
     * 値に改行があると、その後ろが次の見出しの行として読まれる。お問い合わせの氏名のような
     * 訪問者の入力に「改行＋BCC: …」を書かれると、好きな宛先へメールを送らせることが
     * できてしまう（メールヘッダインジェクション）。検証のルールに頼らず、ここで必ず除く。
     */
    private static function withoutLineBreaks(array $vars): array
    {
        return array_map(
            fn (mixed $value) => is_string($value) ? str_replace(["\r\n", "\r", "\n"], ' ', $value) : $value,
            $vars,
        );
    }

    /** HTML版のテンプレートがあれば、展開した中身を返す。無ければnullで、テキストだけのメールになる */
    private static function renderHtmlCompanion(string $mainPath, array $vars): ?string
    {
        $path = substr($mainPath, 0, -strlen(self::EXTENSION)).'_html'.self::EXTENSION;

        if (! is_readable($path)) {
            return null;
        }

        return Blade::render(self::readFile($path), $vars);
    }

    /**
     * テキスト版のテンプレートの場所。アプリの置き場所を名前の順に探し、無ければ
     * フレームワークの置き場所を名前の順に探す。どこにも無ければ例外。
     *
     * @param  string[]  $names
     */
    private static function locate(array $names): string
    {
        $directories = [
            resource_path(self::DIRECTORY),
            __DIR__.'/../../resources/'.self::DIRECTORY,
        ];

        foreach ($directories as $directory) {
            foreach ($names as $name) {
                $path = $directory.'/'.$name.self::EXTENSION;

                if (is_readable($path)) {
                    return $path;
                }
            }
        }

        throw new MailTemplateException('メールテンプレートが読み込めません: '.implode('・', $names));
    }

    /** テンプレートの中身。読めなければ例外 */
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
