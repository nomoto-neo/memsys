<?php

namespace Neobit\Core\Mail;

/**
 * 展開が終わったメールのテンプレートを、宛先などの見出しと本文に分ける。
 * 変数の展開はMailTemplateが行い、ここは分けるだけ。
 *
 *   FROM: 株式会社サンプル <info@example.com>
 *   TO: staff1@example.com, staff2@example.com
 *   SUBJECT: お問い合わせを受け付けました
 *   LIST_UNSUBSCRIBE: <https://example.com/unsubscribe>
 *
 *   山田 様
 *
 *   お問い合わせありがとうございます。
 *
 * - 先頭から空行までの各行を「KEY: 値」の見出しとして読む。KEYは大文字と小文字を区別する
 * - 最初の空行の次から最後までが本文
 * - 空行より前に「KEY: 値」の形でない行があれば、そこからを本文にする。見出しを書き忘れたときに
 *   本文の1行目を読み捨てないため
 * - 空行が無ければ全部を見出しとして読むので、本文が空になる。書き方の誤りに気付きやすいよう
 *   そのままにしている
 *
 * ■ 決まった見出し
 * - FROM      送信元。「名前 <アドレス>」か、アドレスだけ。名前の符号化は、送るときにメールの部品が行う
 * - TO・CC・BCC・REPLY_TO  宛先と返信先。アドレスだけを、カンマで区切って複数書ける。
 *   名前は書けない。名前の中のカンマと、区切りのカンマを見分けられないため
 * - SUBJECT   件名
 *
 * ■ そのほかの見出し
 * 決まった見出しのほかは、メールのヘッダーとして足す。名前は、「_」を「-」に変え、語の頭だけを
 * 大文字にする。LIST_UNSUBSCRIBEはList-Unsubscribeになる。値が空の行は足さない。
 * 次の名前は、書き間違いやメールの形を壊すものなので、例外にして送信を止める。
 * - 決まった見出しと紛らわしい名前。FROM_MAIL・TO_MAILなど
 * - メールの部品が自分で付けるヘッダー。CONTENT_TYPE・DATEなど
 */
final class MailTemplateParser
{
    /** アドレスをカンマで区切って書く見出し。見出しの名前 => 結果のキー */
    private const ADDRESS_KEYS = [
        'TO' => 'to',
        'CC' => 'cc',
        'BCC' => 'bcc',
        'REPLY_TO' => 'reply_to',
    ];

    /** メールのヘッダーとして足せない見出し。書いてあれば例外にする */
    private const FORBIDDEN_KEYS = [
        // 決まった見出しと紛らわしい名前
        'FROM_MAIL',
        'FROM_NAME',
        'TO_MAIL',
        'CC_MAIL',
        'BCC_MAIL',
        'REPLY_TO_MAIL',
        'TITLE',
        // メールの部品が自分で付けるヘッダー
        'CONTENT_TYPE',
        'CONTENT_TRANSFER_ENCODING',
        'MIME_VERSION',
        'DATE',
        'MESSAGE_ID',
        'SENDER',
        'RETURN_PATH',
        'RECEIVED',
    ];

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
     *     headers: array<string, string>,
     *     body: string,
     * }
     */
    public static function parse(string $renderedText): array
    {
        // どの改行でも同じ結果になるよう、"\n"にそろえる
        $normalized = str_replace(["\r\n", "\r"], "\n", $renderedText);
        $lines = explode("\n", $normalized);

        $values = [];
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
            $values[$key] = trim($value);
        }

        [$fromMail, $fromName] = self::splitFrom($values['FROM'] ?? '');

        $parsed = [
            'from_mail' => $fromMail,
            'from_name' => $fromName,
            'subject' => $values['SUBJECT'] ?? null,
            'headers' => [],
            'body' => implode("\n", array_slice($lines, $bodyStartLine)),
        ];

        foreach (self::ADDRESS_KEYS as $key => $name) {
            $parsed[$name] = self::splitAddresses($values[$key] ?? '');
        }

        // 決まった見出しのほかは、メールのヘッダーとして足す
        foreach (array_diff_key($values, self::ADDRESS_KEYS, ['FROM' => true, 'SUBJECT' => true]) as $key => $value) {
            if (in_array($key, self::FORBIDDEN_KEYS, true)) {
                throw new MailTemplateException("メールテンプレートの見出しに、使えない名前があります: {$key}");
            }

            if ($value !== '') {
                $parsed['headers'][self::headerName($key)] = $value;
            }
        }

        return $parsed;
    }

    /**
     * 送信元の値を、[アドレス, 名前]に分ける。「名前 <アドレス>」か、アドレスだけ。
     * 書いていなければ[null, null]。名前やアドレスが空なら、そこだけnull。
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function splitFrom(string $value): array
    {
        if ($value === '') {
            return [null, null];
        }

        // アドレスだけ
        if (! str_contains($value, '<') && ! str_contains($value, '>')) {
            return [$value, null];
        }

        // 「名前 <アドレス>」。名前を"で囲んでいれば外す
        if (! preg_match('/^([^<>]*)<([^<>]*)>$/', $value, $matches)) {
            throw new MailTemplateException("メールテンプレートの送信元（FROM）を読めません: {$value}");
        }

        $address = trim($matches[2]);
        $name = trim(trim($matches[1]), '"');

        return [$address !== '' ? $address : null, $name !== '' ? $name : null];
    }

    /** 見出しの名前を、メールのヘッダーの名前にする。LIST_UNSUBSCRIBEならList-Unsubscribe */
    private static function headerName(string $key): string
    {
        return implode('-', array_map(fn (string $word) => ucfirst(strtolower($word)), explode('_', $key)));
    }

    /** カンマで区切ったアドレスを前後の空白を除いて配列にする。空の要素は除く */
    private static function splitAddresses(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
    }
}
