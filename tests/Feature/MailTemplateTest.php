<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Neobit\Core\Mail\MailTemplate;
use Neobit\Core\Mail\MailTemplateException;
use Neobit\Core\Mail\MailTemplateParser;
use Tests\TestCase;

/**
 * メールのテンプレートの展開（Neobit\Core\Mail\MailTemplate）と、見出しの読み取り
 * （Neobit\Core\Mail\MailTemplateParser）。見出しの行に入る値に改行があっても、宛先の行を
 * 足されないこと（メールヘッダインジェクション）。
 */
class MailTemplateTest extends TestCase
{
    private const VARS = [
        'from_mail' => 'from@example.com',
        'from_name' => 'サイト',
        'staff_mail' => 'staff@example.com',
        'email' => 'visitor@example.com',
        'name' => '山田 太郎',
        'furigana' => 'ヤマダ タロウ',
        'phone' => '',
        'zip' => '',
        'prefecture' => '',
        'city' => '',
        'address_other' => '',
        'body' => "1行目\n2行目",
    ];

    // ---- 見出しの行に入る値 ----

    public function test_line_break_in_a_header_value_does_not_add_a_header(): void
    {
        // 件名に入る氏名に、改行と宛先の行を書く
        $parsed = MailTemplate::render('contact_staff', ['name' => "山田\nBCC: evil@example.com\r\nCC: evil2@example.com"] + self::VARS);

        $this->assertSame(['staff@example.com'], $parsed['to']);
        $this->assertSame([], $parsed['bcc']);
        $this->assertSame([], $parsed['cc']);
        $this->assertSame([], $parsed['headers']);

        // 改行は空白になり、書かれた文字は件名の中に残る
        $this->assertSame('【お問い合わせ】山田 BCC: evil@example.com CC: evil2@example.com 様より', $parsed['subject']);
    }

    public function test_blank_line_in_a_header_value_does_not_end_the_headers(): void
    {
        // 空行を書いて、その後ろを本文として読ませようとする
        $parsed = MailTemplate::render('contact_staff', ['name' => "山田\n\n偽の本文"] + self::VARS);

        $this->assertSame(['visitor@example.com'], $parsed['reply_to']);
        $this->assertStringNotContainsString('偽の本文', explode('山田', $parsed['body'])[0]);
    }

    public function test_line_breaks_in_the_body_are_kept(): void
    {
        $parsed = MailTemplate::render('contact_staff', self::VARS);

        $this->assertStringContainsString("1行目\n2行目", $parsed['body']);
        $this->assertSame('【お問い合わせ】山田 太郎 様より', $parsed['subject']);
    }

    public function test_email_rule_rejects_what_could_add_a_recipient(): void
    {
        // 宛先の行はカンマで複数書けるので、宛先に入れる値は、検証でメールアドレスの形に限っている
        foreach (['a@example.com, b@example.com', "a@example.com\nBCC: b@example.com", 'a@example.com;b@example.com'] as $value) {
            $this->assertTrue(Validator::make(['email' => $value], ['email' => ['required', 'email']])->fails(), $value);
        }
    }

    // ---- 送信元 ----

    public function test_from_is_split_into_name_and_address(): void
    {
        $parsed = MailTemplate::render('contact_staff', self::VARS);

        $this->assertSame('from@example.com', $parsed['from_mail']);
        $this->assertSame('サイト', $parsed['from_name']);
    }

    public function test_from_accepts_an_address_only_and_a_quoted_name(): void
    {
        $this->assertSame(['from@example.com', null], $this->fromOf('FROM: from@example.com'));
        $this->assertSame(['from@example.com', null], $this->fromOf('FROM: <from@example.com>'));
        $this->assertSame(['from@example.com', '株式会社 サンプル'], $this->fromOf('FROM: "株式会社 サンプル" <from@example.com>'));

        // 送信元を書いていないときと、アドレスが空のときは、呼ぶ側が設定の値を使う
        $this->assertSame([null, null], $this->fromOf('SUBJECT: 件名'));
        $this->assertSame([null, 'サイト'], $this->fromOf('FROM: サイト <>'));
    }

    public function test_from_that_cannot_be_read_stops_the_mail(): void
    {
        $this->expectException(MailTemplateException::class);

        MailTemplateParser::parse("FROM: A<B> <from@example.com>\n\n本文");
    }

    // ---- そのほかの見出し ----

    public function test_other_header_lines_become_mail_headers(): void
    {
        $parsed = MailTemplateParser::parse(implode("\n", [
            'FROM: from@example.com',
            'TO: a@example.com, b@example.com',
            'SUBJECT: 件名',
            'LIST_UNSUBSCRIBE: <https://example.com/u?email=a%40example.com>',
            'LIST_UNSUBSCRIBE_POST: List-Unsubscribe=One-Click',
            'X_PRIORITY:',
            '',
            '本文',
        ]));

        $this->assertSame(['a@example.com', 'b@example.com'], $parsed['to']);
        $this->assertSame('件名', $parsed['subject']);
        $this->assertSame('本文', $parsed['body']);

        // 名前は「_」を「-」に変えて、語の頭だけを大文字にする。値が空の行は足さない
        $this->assertSame([
            'List-Unsubscribe' => '<https://example.com/u?email=a%40example.com>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ], $parsed['headers']);
    }

    public function test_names_that_look_like_the_fixed_ones_stop_the_mail(): void
    {
        foreach (['TO_MAIL: a@example.com', 'FROM_NAME: サイト', 'CONTENT_TYPE: text/html'] as $line) {
            try {
                MailTemplateParser::parse("FROM: from@example.com\n{$line}\n\n本文");
                $this->fail("例外にならない: {$line}");
            } catch (MailTemplateException $e) {
                $this->assertStringContainsString(explode(':', $line)[0], $e->getMessage());
            }
        }
    }

    /** 見出しの行から読んだ、[送信元のアドレス, 送信元の名前] */
    private function fromOf(string $headerLine): array
    {
        $parsed = MailTemplateParser::parse($headerLine."\n\n本文");

        return [$parsed['from_mail'], $parsed['from_name']];
    }
}
