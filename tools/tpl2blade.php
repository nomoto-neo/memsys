<?php

/**
 * 旧フレームワークのSmartyのテンプレート（.tpl）を、ネオビットフレームワークのBlade（.blade.php）に
 * 書き換えるツール。Laravelにも Composerにも依存しない、この1ファイルだけで動く。
 *
 *   php tools/tpl2blade.php AAA.tpl            AAA.tplと同じ場所にAAA.blade.phpを書き出す
 *   php tools/tpl2blade.php dir/               ディレクトリの中の.tplを全部（下の階層も）
 *   php tools/tpl2blade.php --force AAA.tpl    AAA.blade.phpがもうあっても上書きする
 *   php tools/tpl2blade.php --stdout AAA.tpl   ファイルに書かず、画面に出す
 *
 * ■ 考え方
 * 機械的に決まるところは全部書き換え、決まらないところには
 * {{-- TODO(tpl2blade): … --}} の印を付けて、元の書き方を印の中に残す。
 * 書き換えの後に、印を付けた行の一覧を出す。元の.tplは書き換えない。
 *
 * ■ 書き換えの決まり
 * - 区切りは <!--{ … }-->。変数は$outの中身が $名前 で展開されている前提
 * - 一番上の変数 $name は $input['name']。foreachの行 $d.id は $d->id
 * - 名前が _ary で終わる変数は区分表。foreachならcode_table('名前')、
 *   $xxx_ary[$v] ならcode_label('名前', $v)
 * - |checked:$k・|selected:$k は @checked(hit(…))・@selected(hit(…))
 * - html_optionsはcode_options()、html_valuesはcode_labels()
 * - <!--{$err.xxx}--> は、エラー欄の<div class="invalid-feedback" …>
 * - HTMLのコメント <!-- … --> は、Bladeのコメント {{-- … --}}
 * - method="post"の<form>には、@csrfを足す
 * サイトごとに変わる読み替えは、下の「設定」の定数を書き換える。
 */

// ---- 設定 ----

// Smartyの区切り
const DELIM_OPEN = '<!--{';
const DELIM_CLOSE = '}-->';

// 区分表の変数の、名前の終わり
const CODE_TABLE_SUFFIX = '_ary';

// 区分表の名前の読み替え。旧の名前（_aryを除いたもの） => code_table()に渡す名前。
// 無いものは、旧の名前のまま渡す
const CODE_TABLE_NAMES = [
    'pref' => 'prefectures',
    'prefecture' => 'prefectures',
];

// $input['…']にせず、そのままの名前で使う変数。新しいフレームワークでも同じ名前で渡すもの
const VIEW_VARS = ['readonly', 'disabled', 'errors', 'loop'];

// エラーの文を持つ変数。<!--{$err.name}--> をエラー欄に書き換える
const ERROR_VAR = 'err';

// アップロード欄の変数。_ajax_upload_blockへの差し替えを人に任せる
const UPLOAD_VARS = ['pics'];

// ページ送りのテンプレートの名前（拡張子なし）。includeを、Laravelのページ送りに書き換える
const PAGER_INCLUDES = ['pager'];

// ページ送りに書き換えるときの、一覧の変数の名前。一覧ごとに違うので、印を付けて直してもらう
const PAGER_VARIABLE = '$rows';

// レイアウトに当たるテンプレートの名前。@extends('layouts.…')に置き換えるよう、印を付ける
const LAYOUT_INCLUDES = ['head', 'body_header', 'header', 'footer'];

// 素の文の中で、Bladeの命令と読まれてしまう @名前。前に@を足して、文字のまま出す
const BLADE_DIRECTIVES = 'if|elseif|else|endif|unless|endunless|isset|endisset|empty|endempty|auth|endauth|guest|endguest'
    .'|foreach|endforeach|forelse|endforelse|for|endfor|while|endwhile|break|continue|switch|case|default|endswitch'
    .'|php|endphp|include|includeIf|includeWhen|each|extends|section|endsection|yield|show|stop|parent|push|endpush'
    .'|stack|prepend|endprepend|once|endonce|csrf|method|json|error|enderror|class|style|checked|selected|disabled'
    .'|readonly|required|vite|lang|env|endenv|production|endproduction|dd|dump|verbatim|endverbatim|use|props|can|endcan';

// ---- 入口 ----

exit(main($argv));

function main(array $argv): int
{
    $force = false;
    $stdout = false;
    $paths = [];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--force') {
            $force = true;
        } elseif ($arg === '--stdout') {
            $stdout = true;
        } else {
            $paths[] = $arg;
        }
    }

    if ($paths === []) {
        fwrite(STDERR, "使い方: php tpl2blade.php [--force] [--stdout] AAA.tpl [BBB.tpl …|ディレクトリ]\n");

        return 1;
    }

    // 渡されたファイルと、ディレクトリの中の.tplを集める
    $files = [];
    foreach ($paths as $path) {
        if (is_dir($path)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (strtolower($file->getExtension()) === 'tpl') {
                    $files[] = $file->getPathname();
                }
            }
        } elseif (is_file($path)) {
            $files[] = $path;
        } else {
            fwrite(STDERR, "見つかりません: {$path}\n");

            return 1;
        }
    }
    sort($files);

    $failed = false;

    foreach ($files as $file) {
        $converter = new Tpl2Blade();
        $blade = $converter->convert((string) file_get_contents($file));
        $target = preg_replace('/\.tpl$/i', '', $file).'.blade.php';

        if ($stdout) {
            echo $blade;
        } elseif (is_file($target) && ! $force) {
            fwrite(STDERR, "もうあるので書きません（上書きは --force）: {$target}\n");
            $failed = true;

            continue;
        } else {
            file_put_contents($target, $blade);
        }

        // 結果の報告は、--stdoutのときに出力と混ざらないよう、標準エラーに出す
        fwrite(STDERR, $converter->report($file, $stdout ? '（画面に出力）' : $target));
    }

    return $failed ? 1 : 0;
}

/**
 * 1つのテンプレートを書き換える。convert()が全体、あとはその中の部品。
 * 式（変数・演算子・修飾子）の書き換えは、tokenize()で細かく分けてからconvertTokens()で組み立てる。
 */
final class Tpl2Blade
{
    // テンプレートの中で作られた変数の名前。foreach・for・assignで作ったもの。$input['…']にしない
    private array $locals = [];

    // 式を書き換えている間に出た、人に任せること。タグを書き終えたところで印にする
    private array $pending = [];

    // 書き換えたタグの数と、印を付けた数
    private int $tagCount = 0;

    private int $todoCount = 0;

    // 今のタグのすぐ前が、半角の英数字か。Bladeが命令と読めるよう、空白を空けるかを決める
    private bool $afterWord = false;

    // 命令の前後に空白を足した数
    private int $spaced = 0;

    // assignで値を入れる変数の名前。値を入れない場合に備えて、冒頭で空のまま用意する
    private array $assigned = [];

    // foreachの入れ子。foreachelseが出たら、対応する@foreachを@forelseに直すのに使う
    private array $foreachStack = [];

    // 出力のかけら。最後につなげる
    private array $out = [];

    // ---- 全体 ----

    public function convert(string $source): string
    {
        $pos = 0;
        $length = strlen($source);

        while ($pos < $length) {
            $next = strpos($source, '<!--', $pos);

            // もうタグもコメントも無ければ、残りは素の文
            if ($next === false) {
                $this->out[] = $this->escapeText(substr($source, $pos));
                break;
            }

            $this->out[] = $this->escapeText(substr($source, $pos, $next - $pos));

            if (substr($source, $next, strlen(DELIM_OPEN)) === DELIM_OPEN) {
                // Smartyのタグ
                $end = $this->findTagEnd($source, $next);
                $inner = substr($source, $next + strlen(DELIM_OPEN), $end - $next - strlen(DELIM_OPEN));
                $pos = $end + strlen(DELIM_CLOSE);

                // Bladeは、英数字にくっついた@ifなどを命令と読まない。前後が英数字なら空白を空ける
                $this->afterWord = $next > 0 && $this->isWordChar($source[$next - 1]);
                $blade = $this->convertTag($inner);

                if (preg_match('/@\w+$/', $blade) && $pos < $length && $this->isWordChar($source[$pos])) {
                    $blade .= ' ';
                    $this->spaced++;
                }

                $this->out[] = $blade;
            } else {
                // HTMLのコメント
                $end = $this->findHtmlCommentEnd($source, $next);
                $this->out[] = $this->convertHtmlComment(substr($source, $next, $end - $next));
                $pos = $end;
            }
        }

        return $this->declareAssigned($source).$this->addCsrf(implode('', $this->out));
    }

    /**
     * assignで値を入れる変数を、冒頭で空のまま用意する1行。Smartyは値の無い変数を空として読むが、
     * Bladeはエラーにするので、@ifの中でだけ値を入れる変数が、入れなかったときに止まらないようにする。
     * コントローラーが同じ名前で渡した値は、消さずにそのまま使う（??=）。
     */
    private function declareAssigned(string $source): string
    {
        if ($this->assigned === []) {
            return '';
        }

        $newline = str_contains($source, "\r\n") ? "\r\n" : "\n";
        $names = array_map(fn (string $name) => "\${$name} ??= null;", array_keys($this->assigned));

        return '{{-- この画面の中で値を入れる変数。値を入れない場合に備えて、空のまま用意しておく --}}'.$newline
            .'@php '.implode(' ', $names).' @endphp'.$newline;
    }

    // 書き換えの結果の報告。印を付けた行の一覧も出す
    public function report(string $source, string $target): string
    {
        $lines = ["{$source} → {$target}", "  タグ {$this->tagCount}個を書き換え、印（TODO）は{$this->todoCount}個"];

        if ($this->spaced > 0) {
            $lines[] = "  英数字にくっついた命令の前後に、空白を{$this->spaced}か所足しました（class=\"a@if\" → class=\"a @if\"）";
        }

        $number = 0;
        foreach (preg_split('/\r\n|\n/', implode('', $this->out)) as $line) {
            $number++;
            if (preg_match_all('/TODO\(tpl2blade\): (.*?)(?: \| 元:| --}})/u', $line, $matches)) {
                foreach ($matches[1] as $message) {
                    $lines[] = sprintf('  %5d行目: %s', $number, $message);
                }
            }
        }

        return implode("\n", $lines)."\n";
    }

    // Bladeが命令の名前の続きと読む文字（半角の英数字と_）か
    private function isWordChar(string $char): bool
    {
        return (bool) preg_match('/^\w$/', $char);
    }

    // Smartyのタグの終わり（}-->）の位置。値の中に入れ子のタグがあるので、対応を数える
    private function findTagEnd(string $source, int $start): int
    {
        // コメント <!--{* … *}--> は、中に何が書いてあっても *}--> まで
        if (substr($source, $start + strlen(DELIM_OPEN), 1) === '*') {
            $end = strpos($source, '*'.DELIM_CLOSE, $start);

            return $end === false ? strlen($source) - strlen(DELIM_CLOSE) : $end + 1;
        }

        $depth = 0;
        $pos = $start;

        while (true) {
            $open = strpos($source, DELIM_OPEN, $pos + 1);
            $close = strpos($source, DELIM_CLOSE, $pos + 1);

            if ($close === false) {
                return strlen($source) - strlen(DELIM_CLOSE);
            }

            if ($open !== false && $open < $close) {
                $depth++;
                $pos = $open;

                continue;
            }

            if ($depth === 0) {
                return $close;
            }

            $depth--;
            $pos = $close;
        }
    }

    // HTMLのコメントの終わり（-->の次）の位置。中にSmartyのタグがあれば、その}-->は飛ばす
    private function findHtmlCommentEnd(string $source, int $start): int
    {
        $pos = $start + 4;

        while (true) {
            $open = strpos($source, DELIM_OPEN, $pos);
            $close = strpos($source, '-->', $pos);

            if ($close === false) {
                return strlen($source);
            }

            if ($open !== false && $open < $close) {
                $pos = $this->findTagEnd($source, $open) + strlen(DELIM_CLOSE);

                continue;
            }

            return $close + 3;
        }
    }

    // HTMLのコメントを、Bladeのコメントにする。訪問者にソースで見えないようにするため。
    // 中に書かれた古いタグは、コメントの中なのでそのまま残す
    private function convertHtmlComment(string $comment): string
    {
        // <!--[if IE]> のような、ブラウザが読むコメントは変えない
        if (preg_match('/^<!--\s*\[|<!\[endif\]/', $comment)) {
            return $comment;
        }

        $inner = trim(substr($comment, 4, -3), "- \t");

        return '{{-- '.$this->commentSafe($inner).' --}}';
    }

    // Bladeのコメントの中に入れても、コメントが途中で終わらない文字にする
    private function commentSafe(string $text): string
    {
        return str_replace('--}}', '--}_}', $text);
    }

    // 素の文の中の、Bladeが命令と読んでしまう書き方を、文字のまま出るようにする
    private function escapeText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/(?<!@)(\{\{|\{!!)/', '@$1', $text);

        return preg_replace('/(?<![\w@])@(?=(?:'.BLADE_DIRECTIVES.')\b)/', '@@', $text);
    }

    // method="post"の<form>の開始タグの後ろに、@csrfを足す。Laravelでは、無いと送信が419で止まる
    private function addCsrf(string $blade): string
    {
        return preg_replace_callback(
            '/^([ \t]*)(<form\b(?:\{\{.*?\}\}|[^>])*>)/mi',
            function (array $m) {
                if (! preg_match('/\bmethod\s*=\s*["\']?post\b/i', $m[2])) {
                    return $m[0];
                }

                return $m[1].$m[2]."\n".$m[1].'  @csrf';
            },
            $blade,
        );
    }

    // ---- タグ ----

    // 1つのタグ（区切りの内側）を、Bladeに書き換える
    private function convertTag(string $inner): string
    {
        $this->tagCount++;
        $this->pending = [];
        $body = trim($inner);

        // コメント
        if (str_starts_with($body, '*')) {
            return '{{-- '.$this->commentSafe(trim($body, "* \t\r\n")).' --}}';
        }

        $blade = $this->convertTagBody($body, $inner);

        // 英数字のすぐ後ろの命令は、空白を空ける
        if ($this->afterWord && str_starts_with($blade, '@')) {
            $blade = ' '.$blade;
            $this->spaced++;
        }

        // 式の書き換えで出た、人に任せることを印にする
        foreach ($this->pending as $message) {
            $blade .= $this->todo($message, $inner);
        }

        return $blade;
    }

    private function convertTagBody(string $body, string $inner): string
    {
        // 閉じるタグ
        if (preg_match('/^\/(\w+)$/', $body, $m)) {
            return $this->convertCloseTag($m[1], $inner);
        }

        // 変数や式を出すタグ。$x = 1 の形なら代入
        if (preg_match('/^[\$\(\'"\d]/', $body)) {
            if (preg_match('/^\$(\w+)\s*=(?!=)\s*(.+)$/s', $body, $m)) {
                $this->locals[$m[1]] = true;
                $this->assigned[$m[1]] = true;

                return '@php $'.$m[1].' = '.$this->expr($m[2]).'; @endphp';
            }

            return $this->convertOutput($body, $inner);
        }

        if (! preg_match('/^(\w+)\b\s*(.*)$/s', $body, $m)) {
            return $this->todo('書き換えられないタグです', $inner);
        }

        [, $name, $rest] = $m;

        switch ($name) {
            case 'if':
                return '@if ('.$this->expr($rest).')';
            case 'elseif':
                return '@elseif ('.$this->expr($rest).')';
            case 'else':
                return '@else';
            case 'foreach':
                return $this->convertForeach($rest, $inner);
            case 'foreachelse':
                return $this->convertForeachElse($inner);
            case 'for':
                return $this->convertFor($rest, $inner);
            case 'assign':
                return $this->convertAssign($rest, $inner);
            case 'include':
                return $this->convertInclude($rest, $inner);
            case 'html_options':
                return $this->convertHtmlOptions($rest, $inner);
            case 'html_values':
                return $this->convertHtmlValues($rest, $inner);
            case 'html_radios':
            case 'html_checkboxes':
                return $this->convertHtmlChoices($name, $rest, $inner);
            case 'literal':
                return '';
            case 'ldelim':
                return '{';
            case 'rdelim':
                return '}';
            case 'break':
                return '@break';
            case 'continue':
                return '@continue';
            default:
                return $this->todo("{$name} は書き換えの決まりがありません", $inner);
        }
    }

    private function convertCloseTag(string $name, string $inner): string
    {
        switch ($name) {
            case 'if':
                return '@endif';
            case 'for':
                return '@endfor';
            case 'literal':
                return '';
            case 'foreach':
                $index = array_pop($this->foreachStack);

                // foreachelseがあったループは、@forelseに直してあるので、閉じ方も変える
                return ($index['forelse'] ?? false) ? '@endforelse' : '@endforeach';
            default:
                return $this->todo("/{$name} は書き換えの決まりがありません", $inner);
        }
    }

    // 変数や式を出すタグ
    private function convertOutput(string $body, string $inner): string
    {
        // エラーの文：<!--{$err.name}--> は、新しいフレームワークのエラー欄にする
        if (preg_match('/^\$'.ERROR_VAR.'\.(\w+)$/', $body, $m)) {
            return '<div class="invalid-feedback" data-item="'.$m[1].'">{{ $errors->first(\''.$m[1].'\') }}</div>';
        }

        // アップロード欄：書き方が違うので、人に任せる
        if (preg_match('/^\$('.implode('|', UPLOAD_VARS).')\.(\w+)/', $body, $m)) {
            return $this->todo("アップロード欄です。@include('_ajax_upload_block', ['field' => '{$m[2]}', …]) に差し替えます", $inner);
        }

        $piece = $this->exprPiece($body);

        return match ($piece['kind']) {
            // @checked()は、前の属性とくっつかないよう、空白を1つ空けて出す
            'directive' => ' '.$piece['code'],
            'html' => '{!! '.$piece['code'].' !!}',
            default => '{{ '.$piece['code'].' }}',
        };
    }

    // foreach。Smarty3の「foreach $a as $k=>$v」と、Smarty2の「foreach from=$a item=v key=k」
    private function convertForeach(string $rest, string $inner): string
    {
        $key = null;

        if (preg_match('/^(.+?)\s+as\s+\$(\w+)(?:\s*=>\s*\$(\w+))?\s*$/s', $rest, $m)) {
            $from = $m[1];
            $item = $m[3] ?? $m[2];
            $key = isset($m[3]) ? $m[2] : null;
        } else {
            $attrs = $this->attributes($rest);

            if (! isset($attrs['from'], $attrs['item'])) {
                return $this->todo('foreachの書き方が読み取れません', $inner);
            }

            $from = $attrs['from'];
            $item = trim($attrs['item'], '\'"$');
            $key = isset($attrs['key']) ? trim($attrs['key'], '\'"$') : null;
        }

        // 回す元は、変数を作る前に書き換える。同じ名前の変数を作るループに備えて
        $source = $this->expr($from);

        $this->locals[$item] = true;
        if ($key !== null) {
            $this->locals[$key] = true;
        }

        $this->out[] = '';
        $this->foreachStack[] = ['index' => array_key_last($this->out), 'forelse' => false];

        $as = $key !== null ? "\${$key} => \${$item}" : "\${$item}";

        // 後から@forelseに直せるよう、命令の名前だけを別のかけらにして覚えておく
        $this->out[array_key_last($this->out)] = '@foreach';

        if ($this->afterWord) {
            $this->out[] = ' ';
            $this->spaced++;
            [$last, $before] = [array_key_last($this->out), array_key_last($this->out) - 1];
            [$this->out[$before], $this->out[$last]] = [' ', '@foreach'];
            $this->foreachStack[array_key_last($this->foreachStack)]['index'] = $last;
        }

        return " ({$source} as {$as})";
    }

    // foreachelse。対応する@foreachを@forelseに直して、@emptyを出す
    private function convertForeachElse(string $inner): string
    {
        if ($this->foreachStack === []) {
            return $this->todo('foreachelseに対応するforeachがありません', $inner);
        }

        $last = array_key_last($this->foreachStack);
        $this->foreachStack[$last]['forelse'] = true;
        $this->out[$this->foreachStack[$last]['index']] = '@forelse';

        return '@empty';
    }

    // for $i=0 to 5 step 1
    private function convertFor(string $rest, string $inner): string
    {
        if (! preg_match('/^\$(\w+)\s*=\s*(.+?)\s+to\s+(.+?)(?:\s+step\s+(.+))?$/s', $rest, $m)) {
            return $this->todo('forの書き方が読み取れません', $inner);
        }

        $this->locals[$m[1]] = true;
        $var = '$'.$m[1];
        $step = isset($m[4]) ? "{$var} += ".$this->expr($m[4]) : "{$var}++";

        return "@for ({$var} = ".$this->expr($m[2])."; {$var} <= ".$this->expr($m[3])."; {$step})";
    }

    // assign var=name value=…
    private function convertAssign(string $rest, string $inner): string
    {
        $attrs = $this->attributes($rest);

        if (! isset($attrs['var'], $attrs['value'])) {
            return $this->todo('assignの書き方が読み取れません', $inner);
        }

        $name = trim($attrs['var'], '\'"$');
        $value = $this->expr($attrs['value']);
        $this->locals[$name] = true;
        $this->assigned[$name] = true;

        return "@php \${$name} = {$value}; @endphp";
    }

    // include file="…" 名前=値 …
    private function convertInclude(string $rest, string $inner): string
    {
        $attrs = $this->attributes($rest);

        if (! isset($attrs['file']) || ! preg_match('/^([\'"])(.*)\1$/s', $attrs['file'], $m)) {
            return $this->todo('includeのファイル名が読み取れません', $inner);
        }

        $path = preg_replace('/\.tpl$/i', '', $m[2]);
        $basename = basename($path);

        // ページ送りは、Laravelのページ送りにする。一覧の変数の名前は、人が直す
        if (in_array($basename, PAGER_INCLUDES, true)) {
            return '{{ '.PAGER_VARIABLE."->links('pagination::bootstrap-5') }}"
                .$this->todo(PAGER_VARIABLE.' を、この一覧の変数の名前（コントローラーが渡すページ送り付きの一覧）に直します', $inner);
        }

        // ../ を除いて、/ を . にしたものを、ビューの名前にする
        $view = str_replace('/', '.', trim(preg_replace('#(\.\./|\./)#', '', $path), '/'));

        $params = [];
        foreach ($attrs as $name => $value) {
            if ($name !== 'file') {
                $params[] = "'{$name}' => ".$this->expr($value);
            }
        }

        $blade = "@include('{$view}'".($params !== [] ? ', ['.implode(', ', $params).']' : '').')';

        if (in_array($basename, LAYOUT_INCLUDES, true)) {
            $blade .= $this->todo("レイアウトの部品です。@extends('layouts.…') と @section('content') に置き換えます", $inner);
        }

        return $blade;
    }

    // html_options options=$xxx_ary selected=$v → code_options('xxx', …)
    private function convertHtmlOptions(string $rest, string $inner): string
    {
        $attrs = $this->attributes($rest);
        $codeName = $this->codeNameOf($attrs['options'] ?? '');

        // name=が付くと<select>ごと出す部品になるので、機械的には書き換えない
        if ($codeName === null || isset($attrs['name'])) {
            return $this->todo('html_optionsを書き換えられません。区分表（…_ary）のoptions=だけに対応しています', $inner);
        }

        $selected = isset($attrs['selected']) ? ', '.$this->expr($attrs['selected']) : '';

        return "{{ code_options('{$codeName}'{$selected}) }}";
    }

    // html_values options=$xxx_ary selected=$v separator="/" → code_labels('xxx', …, '/')
    private function convertHtmlValues(string $rest, string $inner): string
    {
        $attrs = $this->attributes($rest);
        $codeName = $this->codeNameOf($attrs['options'] ?? '');

        if ($codeName === null || ! isset($attrs['selected'])) {
            return $this->todo('html_valuesを書き換えられません', $inner);
        }

        $separator = isset($attrs['separator']) ? ', '.$this->expr($attrs['separator']) : '';

        return "{{ code_labels('{$codeName}', ".$this->expr($attrs['selected'])."{$separator}) }}";
    }

    // html_radios・html_checkboxes。新しいフレームワークに部品は無いので、@foreachに広げる。
    // 1個ずつを囲むタグとクラスはデザインで変わるので、印を付けて直してもらう
    private function convertHtmlChoices(string $tag, string $rest, string $inner): string
    {
        $attrs = $this->attributes($rest);
        $codeName = $this->codeNameOf($attrs['options'] ?? '');

        if ($codeName === null || ! isset($attrs['name'])) {
            return $this->todo("{$tag}を書き換えられません", $inner);
        }

        $field = trim($attrs['name'], '\'"');
        $type = $tag === 'html_radios' ? 'radio' : 'checkbox';
        $name = $tag === 'html_radios' ? $field : $field.'[]';
        $selected = $this->expr($attrs['selected'] ?? $attrs['checked'] ?? '$'.$field);

        return "@foreach (code_table('{$codeName}') as \$k => \$name)\n"
            ."    <label><input type=\"{$type}\" name=\"{$name}\" value=\"{{ \$k }}\" @checked(hit({$selected}, \$k))>{{ \$name }}</label>\n"
            .'@endforeach'
            .$this->todo('囲むタグとクラスを、デザインに合わせて直します', $inner);
    }

    // 「$xxx_ary」だけが書かれていれば、code_table()に渡す名前を返す。違えばnull
    private function codeNameOf(string $value): ?string
    {
        if (! preg_match('/^\$(\w+)$/', trim($value), $m) || isset($this->locals[$m[1]])) {
            return null;
        }

        return $this->codeName($m[1]);
    }

    // 変数の名前が区分表（…_ary）なら、code_table()に渡す名前を返す。違えばnull
    private function codeName(string $variable): ?string
    {
        if (! str_ends_with($variable, CODE_TABLE_SUFFIX)) {
            return null;
        }

        $base = substr($variable, 0, -strlen(CODE_TABLE_SUFFIX));

        return CODE_TABLE_NAMES[$base] ?? $base;
    }

    // タグの「名前=値」の並びを、名前 => 値（書かれたままの文字）にする
    private function attributes(string $rest): array
    {
        $tokens = $this->tokenize($rest);
        $attrs = [];
        $name = null;
        $start = 0;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            // 「名前 =」が出てきたら、そこまでが前の値
            if ($tokens[$i]['type'] === 'ident' && ($tokens[$i + 1]['text'] ?? null) === '=') {
                if ($name !== null) {
                    $attrs[$name] = $this->sourceOf($rest, $tokens, $start, $i);
                }
                $name = $tokens[$i]['text'];
                $start = $i + 2;
                $i++;
            }
        }

        if ($name !== null) {
            $attrs[$name] = $this->sourceOf($rest, $tokens, $start, $count);
        }

        return $attrs;
    }

    // トークンの$from番目から$toの手前までの、元の文字
    private function sourceOf(string $source, array $tokens, int $from, int $to): string
    {
        if ($from >= $to) {
            return '';
        }

        $begin = $tokens[$from]['pos'];
        $end = $to < count($tokens) ? $tokens[$to]['pos'] : strlen($source);

        return trim(substr($source, $begin, $end - $begin));
    }

    // 人に任せるところに付ける印。元の書き方を残す
    private function todo(string $message, string $inner): string
    {
        $this->todoCount++;
        $original = preg_replace('/\s+/', ' ', trim($inner));

        return '{{-- TODO(tpl2blade): '.$message.' | 元: '.$this->commentSafe(DELIM_OPEN.$original.DELIM_CLOSE).' --}}';
    }

    // ---- 式 ----

    // Smartyの式を、PHPの式にする
    private function expr(string $source): string
    {
        return $this->exprPiece($source)['code'];
    }

    // Smartyの式を、PHPの式と、その種類（expr・directive・html）にする
    private function exprPiece(string $source): array
    {
        $tokens = $this->tokenize($source);
        $pos = 0;
        $pieces = $this->convertTokens($tokens, $pos, null);

        // 修飾子で終わる1つの値なら、その種類をそのまま返す
        if (count($pieces) === 1) {
            return $pieces[0];
        }

        return ['code' => $this->join($pieces), 'kind' => 'expr', 'operand' => true, 'wrap' => true];
    }

    // 式のかけらをつなげる。? : のような、ほかと並べると優先順位が変わるものは括弧で囲む
    private function join(array $pieces): string
    {
        $code = '';

        foreach ($pieces as $i => $piece) {
            $text = ($piece['wrap'] ?? false) && count($pieces) > 1 ? '('.$piece['code'].')' : $piece['code'];
            $previous = $pieces[$i - 1]['code'] ?? null;

            // 関数の呼び出しの括弧と、カンマの前は空けない
            $glue = ($code === '' || $text === ',' || ($piece['call'] ?? false)) ? '' : ' ';

            $code .= $glue.$text;
        }

        return $code;
    }

    /**
     * トークンの並びを、PHPの式のかけらの並びにする。$stopの文字が出たら、そこで止まる。
     * 修飾子（|name:arg）は、直前の値に掛ける。
     */
    private function convertTokens(array $tokens, int &$pos, ?string $stop): array
    {
        $pieces = [];
        $count = count($tokens);

        while ($pos < $count) {
            $token = $tokens[$pos];

            if ($stop !== null && $token['type'] === 'op' && $token['text'] === $stop) {
                break;
            }

            $pos++;

            switch ($token['type']) {
                case 'var':
                    $pieces[] = $this->operand($this->variable($token));
                    break;

                case 'string':
                    $pieces[] = $this->operand($this->string($token['text']));
                    break;

                case 'number':
                    $pieces[] = $this->operand($token['text']);
                    break;

                case 'tag':
                    // 値の中に書かれた、入れ子のタグ
                    $pieces[] = $this->operand($this->expr($token['text']));
                    break;

                case 'ident':
                    $pieces[] = $this->word($token['text']);
                    break;

                case 'pipe':
                    $pieces = $this->applyModifier($pieces, ltrim($token['text'], '@'), $tokens, $pos);
                    break;

                default:
                    if ($token['text'] === '(') {
                        $inner = $this->convertTokens($tokens, $pos, ')');
                        $pos++;
                        $previous = end($pieces);

                        // 直前が関数の名前なら、その呼び出しの括弧
                        $isCall = $previous !== false && ($previous['function'] ?? false);
                        $pieces[] = ['code' => '('.$this->join($inner).')', 'kind' => 'expr', 'operand' => true, 'call' => $isCall];
                    } else {
                        $pieces[] = ['code' => $token['text'], 'kind' => 'expr', 'operand' => false];
                    }
            }
        }

        return $this->mergeCalls($pieces);
    }

    // 「関数の名前」と「その括弧」を、1つの値にまとめる。後ろの修飾子が、呼び出し全体に掛かるように
    private function mergeCalls(array $pieces): array
    {
        $merged = [];

        foreach ($pieces as $piece) {
            if (($piece['call'] ?? false) && $merged !== []) {
                $last = array_pop($merged);
                $merged[] = $this->operand($last['code'].$piece['code']);

                continue;
            }

            $merged[] = $piece;
        }

        return $merged;
    }

    private function operand(string $code, string $kind = 'expr', bool $wrap = false): array
    {
        return ['code' => $code, 'kind' => $kind, 'operand' => true, 'wrap' => $wrap];
    }

    // 英字の演算子と、true・falseなどの語
    private function word(string $word): array
    {
        $operators = [
            'eq' => '==', 'ne' => '!=', 'neq' => '!=', 'gt' => '>', 'lt' => '<',
            'ge' => '>=', 'gte' => '>=', 'le' => '<=', 'lte' => '<=',
            'and' => '&&', 'or' => '||', 'not' => '!', 'mod' => '%',
        ];

        if (isset($operators[$word])) {
            return ['code' => $operators[$word], 'kind' => 'expr', 'operand' => false];
        }

        if (in_array(strtolower($word), ['true', 'false', 'null'], true)) {
            return $this->operand(strtolower($word));
        }

        // それ以外は、関数の名前として扱う
        return ['code' => $word, 'kind' => 'expr', 'operand' => true, 'function' => true];
    }

    // 変数を、PHPの書き方にする
    private function variable(array $token): string
    {
        $name = $token['name'];
        $accessors = $token['accessors'];

        // $x@first のような、ループの情報
        foreach ($accessors as $accessor) {
            if ($accessor['type'] === 'loop') {
                $map = ['first' => 'first', 'last' => 'last', 'index' => 'index', 'iteration' => 'iteration', 'total' => 'count'];

                if (isset($map[$accessor['name']])) {
                    return '$loop->'.$map[$accessor['name']];
                }

                $this->pending[] = "\${$name}@{$accessor['name']} は書き換えの決まりがありません";

                return '$loop';
            }
        }

        // $smarty.const.NAME・$smarty.now など
        if ($name === 'smarty') {
            $path = array_map(fn ($a) => $a['name'] ?? '', $accessors);

            if (($path[0] ?? '') === 'const' && isset($path[1])) {
                return $path[1];
            }

            if (($path[0] ?? '') === 'now') {
                return 'time()';
            }

            $this->pending[] = '$smarty.'.implode('.', $path).' は書き換えの決まりがありません';

            return 'null';
        }

        $isLocal = isset($this->locals[$name]) || in_array($name, VIEW_VARS, true);

        // エラーの文
        if (! $isLocal && $name === ERROR_VAR && ($accessors[0]['type'] ?? '') === 'prop') {
            return "\$errors->first('{$accessors[0]['name']}')";
        }

        // 区分表
        $codeName = $isLocal ? null : $this->codeName($name);

        if ($codeName !== null) {
            if ($accessors === []) {
                return "code_table('{$codeName}')";
            }

            // $xxx_ary[$v] は、その値の名称
            $first = array_shift($accessors);
            $key = $first['type'] === 'index' ? $this->expr($first['expr']) : "'{$first['name']}'";

            return "code_label('{$codeName}', {$key})".$this->accessors($accessors, false);
        }

        if ($isLocal) {
            // テンプレートの中で作った変数。$d.id は、一覧の行（モデル）の列
            return '$'.$name.$this->accessors($accessors, true);
        }

        // 一番上の変数は、$inputの中
        return "\$input['{$name}']".$this->accessors($accessors, false);
    }

    // .name・[式] の並びを、PHPの書き方にする。$asObjectなら、.name を ->name にする
    private function accessors(array $accessors, bool $asObject): string
    {
        $code = '';

        foreach ($accessors as $accessor) {
            $code .= match ($accessor['type']) {
                'prop' => $asObject ? '->'.$accessor['name'] : "['{$accessor['name']}']",
                'arrow' => '->'.$accessor['name'],
                'index' => '['.$this->expr($accessor['expr']).']',
                default => '',
            };
        }

        return $code;
    }

    // 文字列。中に入れ子のタグがあれば、文字と式をつなげた形にする
    private function string(string $literal): string
    {
        $quote = $literal[0];
        $body = substr($literal, 1, -1);

        if (! str_contains($body, DELIM_OPEN)) {
            // "…"の中の $変数 は、Smartyでは展開される。PHPの変数とは名前が違うので、人に任せる
            if ($quote === '"' && preg_match('/(?<!\\\\)\$\w/', $body)) {
                $this->pending[] = '"…" の中に変数があります。文字と式をつなげる形に直します';
            }

            return $this->quote($quote === '"' ? stripcslashes($body) : str_replace(["\\'", '\\\\'], ["'", '\\'], $body));
        }

        $parts = [];
        $pos = 0;

        while (($open = strpos($body, DELIM_OPEN, $pos)) !== false) {
            if ($open > $pos) {
                $parts[] = $this->quote(substr($body, $pos, $open - $pos));
            }

            $end = $this->findTagEnd($body, $open);
            $parts[] = $this->expr(substr($body, $open + strlen(DELIM_OPEN), $end - $open - strlen(DELIM_OPEN)));
            $pos = $end + strlen(DELIM_CLOSE);
        }

        if ($pos < strlen($body)) {
            $parts[] = $this->quote(substr($body, $pos));
        }

        return implode('.', $parts);
    }

    // PHPの'…'の文字列にする
    private function quote(string $text): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $text)."'";
    }

    // ---- 修飾子 ----

    // 直前の値に、修飾子を1つ掛ける
    private function applyModifier(array $pieces, string $name, array $tokens, int &$pos): array
    {
        // :引数 を読む。引数は1つの値
        $args = [];
        while (($tokens[$pos]['type'] ?? null) === 'colon') {
            $pos++;
            $args[] = $this->argument($tokens, $pos);
        }

        $target = array_pop($pieces);

        if ($target === null || ! $target['operand']) {
            $this->pending[] = "|{$name} を掛ける値がありません";

            return $target === null ? $pieces : [...$pieces, $target];
        }

        $value = ($target['wrap'] ?? false) ? '('.$target['code'].')' : $target['code'];
        $pieces[] = $this->modifier($name, $value, $args, $target);

        return $pieces;
    }

    // 修飾子の引数を1つ読む
    private function argument(array $tokens, int &$pos): string
    {
        $token = $tokens[$pos] ?? null;

        if ($token === null) {
            return "''";
        }

        $pos++;

        if ($token['type'] === 'op' && $token['text'] === '(') {
            $inner = $this->convertTokens($tokens, $pos, ')');
            $pos++;

            return '('.$this->join($inner).')';
        }

        if ($token['type'] === 'op' && $token['text'] === '-' && isset($tokens[$pos])) {
            return '-'.$this->argument($tokens, $pos);
        }

        return match ($token['type']) {
            'var' => $this->variable($token),
            'string' => $this->string($token['text']),
            'tag' => $this->expr($token['text']),
            'ident' => $this->word($token['text'])['code'],
            default => $token['text'],
        };
    }

    // 修飾子ごとの書き換え
    private function modifier(string $name, string $value, array $args, array $target): array
    {
        $rest = $args !== [] ? ', '.implode(', ', $args) : '';

        switch ($name) {
            // 値が当たっていたら checked・selected を出す、旧フレームワークの修飾子
            case 'checked':
            case 'selected':
                $condition = isset($args[0]) ? "hit({$value}, {$args[0]})" : $value;

                return $this->operand("@{$name}({$condition})", 'directive');

            case 'ifelse':
                return $this->operand("{$value} ? ".($args[0] ?? "''").' : '.($args[1] ?? "''"), 'expr', true);

            case 'default':
                return $this->operand("{$value} ?: ".($args[0] ?? "''"), $target['kind'], true);

            case 'number_format':
                return $this->operand("number_format((float) {$value}{$rest})");

            case 'count':
                return $this->operand("count((array) {$value})");

            case 'string_format':
                return $this->operand('sprintf('.($args[0] ?? "'%s'").", {$value})");

            case 'date_format':
                return $this->dateFormat($value, $args[0] ?? "'Y-m-d'");

            case 'escape':
                return $this->operand($value);

            case 'nl2br':
                return $this->operand("nl2br(e({$value}))", 'html');

            case 'cat':
                return $this->operand("{$value}.".($args[0] ?? "''"), 'expr', true);

            case 'replace':
                return $this->operand('str_replace('.($args[0] ?? "''").', '.($args[1] ?? "''").", {$value})");

            case 'truncate':
                return $this->operand("\\Illuminate\\Support\\Str::limit({$value}".($args !== [] ? ', '.$args[0] : '').')');

            case 'upper':
                return $this->operand("mb_strtoupper({$value})");

            case 'lower':
                return $this->operand("mb_strtolower({$value})");

            case 'strip_tags':
            case 'trim':
            case 'urlencode':
            case 'intval':
            case 'abs':
            case 'round':
            case 'implode':
                return $this->operand("{$name}({$value}{$rest})");

            default:
                $this->pending[] = "|{$name} は書き換えの決まりがありません";

                return $target;
        }
    }

    /**
     * 日付の書式。一覧の行（モデル）の列は日付の型なので ?->format() を使い、
     * 入力値（文字）は date() と strtotime() を使う。どちらも、値が無ければ空になる。
     * 書式が %Y/%m/%d の形（strftime）なら、date() の書き方に直す。
     */
    private function dateFormat(string $value, string $format): array
    {
        if (str_contains($format, '%')) {
            $format = strtr($format, [
                '%Y' => 'Y', '%y' => 'y', '%m' => 'm', '%d' => 'd', '%e' => 'j', '%H' => 'H',
                '%M' => 'i', '%S' => 's', '%I' => 'h', '%p' => 'A', '%a' => 'D', '%A' => 'l',
            ]);
        }

        if (str_contains($value, '->')) {
            return $this->operand("{$value}?->format({$format})");
        }

        return $this->operand("{$value} ? date({$format}, strtotime({$value})) : ''", 'expr', true);
    }

    // ---- 字句 ----

    /**
     * 式を、変数・文字列・数・語・演算子に分ける。変数は、後ろに続く .name・[式]・@first まで
     * 1つにまとめる。値の中に書かれた入れ子のタグも、1つにまとめる。
     */
    private function tokenize(string $source): array
    {
        $tokens = [];
        $pos = 0;
        $length = strlen($source);

        while ($pos < $length) {
            $char = $source[$pos];

            if (ctype_space($char)) {
                $pos++;

                continue;
            }

            $start = $pos;

            // 入れ子のタグ
            if (substr($source, $pos, strlen(DELIM_OPEN)) === DELIM_OPEN) {
                $end = $this->findTagEnd($source, $pos);
                $tokens[] = ['type' => 'tag', 'text' => substr($source, $pos + strlen(DELIM_OPEN), $end - $pos - strlen(DELIM_OPEN)), 'pos' => $start];
                $pos = $end + strlen(DELIM_CLOSE);

                continue;
            }

            // 文字列。中の入れ子のタグは、その中の引用符で終わらないよう、丸ごと飛ばす
            if ($char === '"' || $char === "'") {
                $pos++;
                while ($pos < $length && $source[$pos] !== $char) {
                    if (substr($source, $pos, strlen(DELIM_OPEN)) === DELIM_OPEN) {
                        $pos = $this->findTagEnd($source, $pos) + strlen(DELIM_CLOSE);

                        continue;
                    }
                    $pos += $source[$pos] === '\\' ? 2 : 1;
                }
                $pos++;
                $tokens[] = ['type' => 'string', 'text' => substr($source, $start, $pos - $start), 'pos' => $start];

                continue;
            }

            // 変数
            if ($char === '$' && preg_match('/\G\$(\w+)/', $source, $m, 0, $pos)) {
                $pos += strlen($m[0]);
                $tokens[] = ['type' => 'var', 'name' => $m[1], 'accessors' => $this->readAccessors($source, $pos), 'pos' => $start];

                continue;
            }

            if (preg_match('/\G\d+(?:\.\d+)?/', $source, $m, 0, $pos)) {
                $pos += strlen($m[0]);
                $tokens[] = ['type' => 'number', 'text' => $m[0], 'pos' => $start];

                continue;
            }

            if (preg_match('/\G@?[A-Za-z_]\w*/', $source, $m, 0, $pos)) {
                $pos += strlen($m[0]);
                $tokens[] = ['type' => 'ident', 'text' => $m[0], 'pos' => $start];

                continue;
            }

            // 演算子。長いものから見る
            foreach (['===', '!==', '==', '!=', '>=', '<=', '&&', '||', '=>', '->', '::'] as $operator) {
                if (substr($source, $pos, strlen($operator)) === $operator) {
                    $pos += strlen($operator);
                    $tokens[] = ['type' => 'op', 'text' => $operator, 'pos' => $start];

                    continue 2;
                }
            }

            $pos++;
            $tokens[] = [
                'type' => match ($char) {
                    '|' => 'pipe',
                    ':' => 'colon',
                    default => 'op',
                },
                'text' => $char,
                'pos' => $start,
            ];

            // 修飾子の名前は、|のすぐ後ろ
            if ($char === '|' && preg_match('/\G\s*(@?\w+)/', $source, $m, 0, $pos)) {
                $tokens[array_key_last($tokens)]['text'] = $m[1];
                $pos += strlen($m[0]);
            }
        }

        return $tokens;
    }

    // 変数の後ろに続く .name・.$var・[式]・->name・@first を読む
    private function readAccessors(string $source, int &$pos): array
    {
        $accessors = [];
        $length = strlen($source);

        while ($pos < $length) {
            if (preg_match('/\G\.(\w+)/', $source, $m, 0, $pos)) {
                $accessors[] = ['type' => 'prop', 'name' => $m[1]];
                $pos += strlen($m[0]);
            } elseif (preg_match('/\G\.(\$\w+)/', $source, $m, 0, $pos)) {
                $accessors[] = ['type' => 'index', 'expr' => $m[1]];
                $pos += strlen($m[0]);
            } elseif (preg_match('/\G->(\w+)/', $source, $m, 0, $pos)) {
                $accessors[] = ['type' => 'arrow', 'name' => $m[1]];
                $pos += strlen($m[0]);
            } elseif (preg_match('/\G@(\w+)/', $source, $m, 0, $pos)) {
                $accessors[] = ['type' => 'loop', 'name' => $m[1]];
                $pos += strlen($m[0]);
            } elseif ($source[$pos] === '[') {
                // 対応する ] までが添え字の式
                $depth = 0;
                $end = $pos;
                while ($end < $length) {
                    $depth += $source[$end] === '[' ? 1 : ($source[$end] === ']' ? -1 : 0);
                    if ($depth === 0) {
                        break;
                    }
                    $end++;
                }
                $accessors[] = ['type' => 'index', 'expr' => substr($source, $pos + 1, $end - $pos - 1)];
                $pos = $end + 1;
            } else {
                break;
            }
        }

        return $accessors;
    }
}
