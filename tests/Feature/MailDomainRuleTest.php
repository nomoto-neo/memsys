<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Staff;
use App\Rules\MailDomainRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * メールアドレスのドメインがDNSにあるかの検証（App\Rules\MailDomainRule）と、一斉メールの宛先のCSVでの使い方。
 * 無いドメインには、決して引けない「.invalid」のドメイン（RFC 2606）を使う。ネットワークが無くても同じ結果になるため。
 */
class MailDomainRuleTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): Staff
    {
        return Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => StaffAcl::Manager,
        ]);
    }

    /** 一斉メールの確認画面へ、宛先のCSVを送る */
    private function confirm(string $csv)
    {
        return $this->actingAs($this->manager(), 'admin')
            ->post(route('admin.bulk-mails.confirm'), [
                'subject' => 'お知らせ',
                'body' => '本文',
                'csv_file' => UploadedFile::fake()->createWithContent('recipients.csv', $csv),
            ])
            ->assertOk();
    }

    public function test_missing_domain_fails_with_the_domain_in_the_message(): void
    {
        $validator = Validator::make(['email' => 'taro@Exmaple.invalid'], ['email' => [new MailDomainRule()]]);

        $this->assertTrue($validator->fails());
        $this->assertSame('メールアドレスのドメイン（exmaple.invalid）が見つかりません。打ち間違いがないか確かめてください。', $validator->errors()->first('email'));
    }

    public function test_recipient_csv_reports_missing_domains(): void
    {
        config(['mail.bulk_check_domain' => true]);

        $this->confirm("誤り 太郎,taro@exmaple.invalid\n")
            ->assertSee('メールアドレスのドメイン（exmaple.invalid）が見つかりません。')
            ->assertDontSee('件に送信する');
    }

    public function test_format_errors_are_shown_without_looking_up_the_domain(): void
    {
        config(['mail.bulk_check_domain' => true]);

        // 形の誤りがあれば、ドメインの文は出さない
        $this->confirm("誤り 次郎,jiro@@exmaple.invalid\n")
            ->assertDontSee('が見つかりません');
    }

    public function test_domains_are_not_checked_when_the_setting_is_off(): void
    {
        config(['mail.bulk_check_domain' => false]);

        $this->confirm("誤り 太郎,taro@exmaple.invalid\n")
            ->assertDontSee('が見つかりません')
            ->assertSee('件に送信する');
    }
}
