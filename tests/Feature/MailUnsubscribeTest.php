<?php

namespace Tests\Feature;

use App\Enums\BulkMailStatus;
use App\Enums\NoticeMail;
use App\Enums\OperationLogAction;
use App\Enums\StaffAcl;
use App\Jobs\SendBulkMail;
use App\Models\BulkMail;
use App\Models\Member;
use App\Models\OperationLog;
use App\Models\Staff;
use App\Support\MailUnsubscribe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * お知らせメール（一斉メール）の配信停止。会員は「受け取る・受け取らない」を持ち、
 * メールの中のURLからも、ログインせずに停止できる（App\Support\MailUnsubscribe）。
 * 「受け取らない」の会員のアドレスが宛先のCSVにあれば、一斉メールは送れない。
 */
class MailUnsubscribeTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    private function member(array $override = []): Member
    {
        return Member::factory()->create($override + ['email' => 'member@example.com']);
    }

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

    /** 配信停止の操作ログ。古い順 */
    private function unsubscribeLogs(): array
    {
        return OperationLog::where('action', OperationLogAction::MailUnsubscribe)->orderBy('id')->get()->all();
    }

    // ---- 配信停止のURL ----

    public function test_opening_the_url_changes_nothing(): void
    {
        $member = $this->member();

        $this->get(MailUnsubscribe::url($member->email))
            ->assertOk()
            ->assertSee('配信を停止する');

        $this->assertTrue($member->fresh()->receivesNoticeMail());
        $this->assertSame([], $this->unsubscribeLogs());
    }

    public function test_button_stops_the_mail_and_is_logged(): void
    {
        $member = $this->member();

        $this->post(MailUnsubscribe::url($member->email))
            ->assertOk()
            ->assertSee('お知らせメールの配信を停止しました。');

        $this->assertFalse($member->fresh()->receivesNoticeMail());

        // 対象は会員の種類とidで残し、メールアドレスは残さない
        [$log] = $this->unsubscribeLogs();
        $this->assertSame($member->getMorphClass(), $log->target_type);
        $this->assertSame($member->id, $log->target_id);
        $this->assertSame(['notice_mail'], $log->changed_fields);
        $this->assertNull($log->detail);
    }

    public function test_address_without_member_shows_the_same_page_and_is_logged(): void
    {
        $this->post(MailUnsubscribe::url('guest@example.com'))
            ->assertOk()
            ->assertSee('お知らせメールの配信を停止しました。');

        // 会員がいないときは、どのアドレスへの操作かを補足に残す
        [$log] = $this->unsubscribeLogs();
        $this->assertNull($log->target_id);
        $this->assertSame('guest@example.com', $log->detail['email']);
    }

    public function test_rewritten_address_does_not_stop_but_is_logged(): void
    {
        $member = $this->member();
        $this->member(['email' => 'other@example.com']);

        // ほかの人のURLの、メールアドレスだけを書き換える
        $url = str_replace(urlencode('other@example.com'), urlencode($member->email), MailUnsubscribe::url('other@example.com'));

        $this->post($url)
            ->assertOk()
            ->assertSee('お知らせメールの配信を停止しました。');

        $this->assertTrue($member->fresh()->receivesNoticeMail());

        [$log] = $this->unsubscribeLogs();
        $this->assertNull($log->target_id);
        $this->assertSame($member->email, $log->detail['email']);
    }

    public function test_stopping_twice_is_logged_twice(): void
    {
        $member = $this->member();

        $this->post(MailUnsubscribe::url($member->email))->assertOk();
        $this->post(MailUnsubscribe::url($member->email))->assertOk();

        $this->assertFalse($member->fresh()->receivesNoticeMail());
        $this->assertCount(2, $this->unsubscribeLogs());
    }

    // ---- 一斉メール ----

    public function test_bulk_mail_carries_the_url_and_the_header(): void
    {
        $member = $this->member();
        $bulkMail = BulkMail::create([
            'subject' => 'お知らせ',
            'body' => '{{$name}} 様',
            'csv_filename' => 'recipients.csv',
            'recipient_count' => 1,
            'status' => BulkMailStatus::Sending,
        ]);

        (new SendBulkMail($bulkMail->id, $member->name, $member->email))->handle();

        // 本文の末尾とヘッダーに、同じURLが入る
        $mail = $this->lastMail();
        $url = MailUnsubscribe::url($member->email);
        $this->assertStringContainsString($url, (string) $mail->getTextBody());
        $this->assertSame("<{$url}>", $mail->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
        $this->assertSame('List-Unsubscribe=One-Click', $mail->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());

        // メールソフトの「登録解除」のボタンと同じ、URLへのPOSTで停止できる
        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->assertFalse($member->fresh()->receivesNoticeMail());
    }

    public function test_bulk_mail_is_not_sent_to_a_member_who_stopped(): void
    {
        $this->member(['notice_mail' => NoticeMail::Stop->value]);
        $csv = UploadedFile::fake()->createWithContent('recipients.csv', "停止 太郎,Member@example.com\n会員 以外,guest@example.com\n");

        // 確認画面で、その行がエラーになり、送信のボタンが出ない
        $this->actingAs($this->manager(), 'admin')
            ->post(route('admin.bulk-mails.confirm'), [
                'subject' => 'お知らせ',
                'body' => '本文',
                'csv_file' => $csv,
            ])
            ->assertOk()
            ->assertSee('お知らせメールを受け取らない会員です。')
            ->assertDontSee('件に送信する');
    }

    // ---- 会員の項目 ----

    public function test_forms_show_the_setting(): void
    {
        $member = $this->member(['notice_mail' => NoticeMail::Stop->value]);

        // 会員登録は、「受け取る」を選んだ状態で出す
        $this->get(route('regist.create'))
            ->assertOk()
            ->assertSee('id="notice_mail_1" type="radio" name="notice_mail" value="1"', false);

        $this->actingAs($member, 'web')->get(route('mypage'))->assertOk()->assertSee('受け取らない');
        $this->actingAs($member, 'web')->get(route('mypage.edit'))->assertOk()->assertSee('お知らせメール');

        $staff = $this->manager();
        $this->actingAs($staff, 'admin')->get(route('admin.members.index'))->assertOk()->assertSee('お知らせメール（指定なし）');
        $this->actingAs($staff, 'admin')->get(route('admin.members.edit', $member))->assertOk()->assertSee('お知らせメール');
        $this->actingAs($staff, 'admin')->get(route('admin.members.show', $member))->assertOk()->assertSee('お知らせメール');
    }

    public function test_member_can_change_the_setting_on_mypage(): void
    {
        $member = $this->member();

        $this->actingAs($member, 'web')
            ->patch('/mypage/update', [
                'name' => $member->name,
                'kana' => $member->kana,
                'email' => $member->email,
                'phone' => $member->phone,
                'birthdate' => $member->birthdate?->format('Y-m-d'),
                'prefecture' => $member->prefecture,
                'notice_mail' => NoticeMail::Stop->value,
            ])
            ->assertRedirect(route('mypage'));

        $this->assertFalse($member->fresh()->receivesNoticeMail());
    }

    public function test_member_list_can_be_narrowed_to_receivers(): void
    {
        $this->member(['name' => '受信 花子']);
        $this->member(['name' => '停止 太郎', 'email' => 'stop@example.com', 'notice_mail' => NoticeMail::Stop->value]);

        $this->actingAs($this->manager(), 'admin')
            ->followingRedirects()
            ->post(route('admin.members.search'), ['notice_mail' => NoticeMail::Receive->value])
            ->assertOk()
            ->assertSee('受信 花子')
            ->assertDontSee('停止 太郎');
    }
}
