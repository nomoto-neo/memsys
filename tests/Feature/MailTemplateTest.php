<?php

namespace Tests\Feature;

use App\Support\MailTemplate;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * メールのテンプレートの展開（App\Support\MailTemplate）。見出しの行に入る値に改行があっても、
 * 宛先の行を足されないこと（メールヘッダインジェクション）。
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

    public function test_line_break_in_a_header_value_does_not_add_a_header(): void
    {
        // 件名に入る氏名に、改行と宛先の行を書く
        $parsed = MailTemplate::render('contact_staff', ['name' => "山田\nBCC_MAIL: evil@example.com\r\nCC_MAIL: evil2@example.com"] + self::VARS);

        $this->assertSame(['staff@example.com'], $parsed['to']);
        $this->assertSame([], $parsed['bcc']);
        $this->assertSame([], $parsed['cc']);

        // 改行は空白になり、書かれた文字は件名の中に残る
        $this->assertSame('【お問い合わせ】山田 BCC_MAIL: evil@example.com CC_MAIL: evil2@example.com 様より', $parsed['subject']);
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
        foreach (['a@example.com, b@example.com', "a@example.com\nBCC_MAIL: b@example.com", 'a@example.com;b@example.com'] as $value) {
            $this->assertTrue(Validator::make(['email' => $value], ['email' => ['required', 'email']])->fails(), $value);
        }
    }
}
