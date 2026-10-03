<?php

namespace App\Support;

/**
 * 展開が終わったメールのテンプレートを、宛先などの見出しと本文に分ける。
 * 変数の展開はMailTemplateが行い、ここは分けるだけ。
 *
 *   FROM_MAIL: info@example.com
 *   FROM_NAME: 株式会社サンプル
 *   TO_MAIL: staff1@example.com, staff2@example.com
 *   SUBJECT: お問い合わせを受け付けました
 *
 *   山田 様
 *
 *   お問い合わせありがとうございます。
 *
 * - 先頭から空行までの各行を「KEY: 値」の見出しとして読む。KEYは大文字と小文字を区別する
 * - 見出しはFROM_MAIL・FROM_NAME・TO_MAIL・CC_MAIL・BCC_MAIL・SUBJECT・REPLY_TOの7つ。
 *   宛先の4つはカンマで区切って複数書ける
 * - 空行より前に「KEY: 値」の形でない行があれば、そこからを本文にする。見出しを書き忘れたときに
 *   本文の1行目を読み捨てないため
 * - 最初の空行の次から最後までが本文
 * - 空行が無ければ全部を見出しとして読むので、本文が空になる。書き方の誤りに気付きやすいよう
 *   そのままにしている
 */
final class MailTemplateParser
{
    /**
     * 見出しと本文に分けた結果を返す。
     *
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
        // どの改行でも同じ結果になるよう、"\n"にそろえる
        $normalized = str_replace(["\r\n", "\r"], "\n", $renderedText);
        $lines = explode("\n", $normalized);

        $headers = [];
        $bodyStartLine = count($lines);

        foreach ($lines as $index => $line) {
            // 最初の空行が見出しと本文の境目
            if (trim($line) === '') {
                $bodyStartLine = $index + 1;
                break;
            }

            // 「KEY: 値」の形でない行からは、本文として扱う
            if (! preg_match('/^([A-Z_]+):\s?(.*)$/', $line, $matches)) {
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

    // カンマで区切ったアドレスを前後の空白を除いて配列にする。空の要素は除く
    private static function splitAddresses(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
    }
}
