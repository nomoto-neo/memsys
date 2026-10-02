<?php

namespace App\Support;

/**
 * 変数展開が終わったあとのメール本文テキストを、以前のフレームワークと同じ
 * 規約で「宛先などの制御情報」と「本文」に分解するクラス。
 *
 * 以前のフレームワーク（Smarty）でのメールテンプレートは、次の形の
 * プレーンテキストファイルだった。
 *
 *   FROM_MAIL: info@example.com
 *   FROM_NAME: 株式会社サンプル
 *   TO_MAIL: staff1@example.com, staff2@example.com
 *   SUBJECT: お問い合わせを受け付けました
 *
 *   {$name} 様
 *
 *   お問い合わせありがとうございます。
 *
 * このクラスは「Smartyの変数展開」の部分は担当しない（それは
 * App\Support\MailTemplateが行う）。展開が終わったプレーンテキストを
 * 受け取り、次の規約で分解するだけの、状態を持たない純粋な処理。
 *
 * - ファイルの先頭から、空行が現れるまでの各行を「見出し行」として読む。
 *   見出し行は "KEY: 値" の形（大文字小文字は区別する）。
 * - 認識する見出しは FROM_MAIL・FROM_NAME・TO_MAIL・CC_MAIL・BCC_MAIL・
 *   SUBJECT・REPLY_TO の7つ。TO_MAIL・CC_MAIL・BCC_MAIL・REPLY_TOは
 *   カンマ区切りで複数書ける（前後の空白は無視する）。
 * - 見出しとして認識できない行（"KEY: 値"の形になっていない行）が
 *   空行より前に現れた場合は、その時点で見出し行の読み取りをやめ、
 *   その行以降を本文として扱う（見出しの書き忘れで本文の1行目を
 *   誤って読み捨てないようにするための安全策）。
 * - 最初の空行の次の行から末尾までが本文。空行自体は本文に含まない。
 * - 空行が1つも無いファイルは、全体を見出しとして読もうとした結果、
 *   本文が空になる（テンプレートの書き方の誤りとして気づきやすいよう、
 *   あえて「本文が全部見出しに化ける」動きのままにしている）。
 */
final class MailTemplateParser
{
    /**
     * @return array{
     *     from_mail: ?string,
     *     from_name: ?string,
     *     to: string[],
     *     cc: string[],
     *     bcc: string[],
     *     reply_to: string[],
     *     subject: ?string,
     *     body: string,
     * }
     */
    public static function parse(string $renderedText): array
    {
        // "\r\n"・"\r"のどちらで改行されていても同じ結果になるよう、
        // 最初に"\n"だけに統一する。
        $normalized = str_replace(["\r\n", "\r"], "\n", $renderedText);
        $lines = explode("\n", $normalized);

        $headers = [];
        $bodyStartLine = count($lines);

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                // 最初の空行が見出しと本文の境目。
                $bodyStartLine = $index + 1;
                break;
            }

            if (! preg_match('/^([A-Z_]+):\s?(.*)$/', $line, $matches)) {
                // "KEY: 値"の形になっていない行に行き当たったら、
                // そこから先はもう見出しではなく本文として扱う。
                $bodyStartLine = $index;
                break;
            }

            [, $key, $value] = $matches;
            $headers[$key] = trim($value);
        }

        $body = implode("\n", array_slice($lines, $bodyStartLine));

        return [
            'from_mail' => $headers['FROM_MAIL'] ?? null,
            'from_name' => $headers['FROM_NAME'] ?? null,
            'to' => self::splitAddresses($headers['TO_MAIL'] ?? ''),
            'cc' => self::splitAddresses($headers['CC_MAIL'] ?? ''),
            'bcc' => self::splitAddresses($headers['BCC_MAIL'] ?? ''),
            'reply_to' => self::splitAddresses($headers['REPLY_TO'] ?? ''),
            'subject' => $headers['SUBJECT'] ?? null,
            'body' => $body,
        ];
    }

    /**
     * "a@example.com, b@example.com" のようなカンマ区切りの文字列を、
     * 前後の空白を落とした上で配列にする。空文字列は空配列になる
     * （array_filterで、カンマの連続や末尾のカンマによる空要素も落とす）。
     */
    private static function splitAddresses(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
    }
}
