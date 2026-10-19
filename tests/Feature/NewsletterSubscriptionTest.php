<?php

namespace Tests\Feature;

use App\Enums\AppOperationLogAction;
use App\Enums\NewsletterSource;
use App\Enums\NoticeMail;
use App\Enums\StaffAcl;
use App\Models\Member;
use App\Models\NewsletterSubscriber;
use App\Models\Staff;
use App\Services\MailUnsubscribe;
use App\Services\NewsletterSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Neobit\Core\Models\OperationLog;
use Neobit\Core\Security\SpamGuard;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * メルマガだけの申し込み（NewsletterController）と、会員の登録・退会・配信停止・一斉メールとのつながり
 * （App\Services\NewsletterSubscription）。
 */
class NewsletterSubscriptionTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Turnstileの鍵を外す。テストからCloudflareに問い合わせないため（鍵が無ければ、Turnstileは通す）
        config(['services.turnstile.secret_key' => null]);
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

    /** 申し込みの入力を送る。スパム対策の「表示から送信までの時間」は、10秒前に表示したことにする */
    private function send(string $name, string $email, int $secondsAgo = 10): TestResponse
    {
        return $this->post(route('newsletter.send'), [
            'name' => $name,
            'email' => $email,
            SpamGuard::STARTED_FIELD => Crypt::encryptString((string) (time() - $secondsAgo)),
        ]);
    }

    /** 最後に送った確認コード */
    private function lastCode(): string
    {
        preg_match('/\d{6}/', (string) $this->lastMail()->getTextBody(), $m);

        return $m[0];
    }

    /** 入力からコードの入力まで通して申し込む */
    private function subscribe(string $name, string $email): TestResponse
    {
        $this->send($name, $email)->assertRedirect(route('newsletter.verify'));

        return $this->post(route('newsletter.verify.confirm'), ['code' => $this->lastCode()]);
    }

    // ---- 申し込み ----

    public function test_code_mail_is_sent_with_the_newsletter_subject(): void
    {
        $this->get(route('newsletter.create'))->assertOk()->assertSee('メルマガの登録');

        $this->send('ニック', 'guest@example.com')->assertRedirect(route('newsletter.verify'));

        $this->assertStringContainsString('メルマガの登録のお手続き', (string) $this->lastMail()->getSubject());
        $this->get(route('newsletter.verify'))->assertOk()->assertSee('guest@example.com');
    }

    public function test_subscriber_is_saved_after_the_code(): void
    {
        $this->subscribe('ニック', 'guest@example.com')->assertRedirect(route('newsletter.done'));

        $subscriber = NewsletterSubscriber::sole();
        $this->assertSame('ニック', $subscriber->name);
        $this->assertSame(NoticeMail::Receive->value, $subscriber->notice_mail);
        $this->assertSame(NewsletterSource::Form->value, $subscriber->source);
        $this->assertNotNull($subscriber->subscribed_at);
        $this->assertSame('127.0.0.1', $subscriber->ip);
        $this->assertTrue(OperationLog::where('action', AppOperationLogAction::NewsletterSubscribe)->exists());

        $this->get(route('newsletter.done'))->assertOk()->assertSee('メルマガの登録が完了しました');
    }

    public function test_wrong_code_saves_nothing(): void
    {
        $this->send('ニック', 'guest@example.com');

        $this->post(route('newsletter.verify.confirm'), ['code' => '000000'])
            ->assertRedirect(route('newsletter.verify'))
            ->assertSessionHasErrors('code');

        $this->assertSame(0, NewsletterSubscriber::count());
    }

    public function test_member_address_turns_on_the_member_setting(): void
    {
        $member = Member::factory()->create(['email' => 'member@example.com', 'notice_mail' => NoticeMail::Stop->value]);

        // 完了の画面は、会員ではないときと同じ
        $this->subscribe('ニック', 'member@example.com')->assertRedirect(route('newsletter.done'));

        // 会員の側で持ち、メルマガの行は作らない
        $this->assertTrue($member->fresh()->receivesNoticeMail());
        $this->assertSame(0, NewsletterSubscriber::count());
    }

    public function test_stopped_address_can_subscribe_again(): void
    {
        NewsletterSubscription::stop('guest@example.com', NewsletterSource::UnsubscribeLink, null);

        $this->subscribe('ニック', 'guest@example.com');

        $subscriber = NewsletterSubscriber::sole();
        $this->assertTrue($subscriber->receivesNoticeMail());
        $this->assertSame(NewsletterSource::Form->value, $subscriber->source);
        $this->assertNull($subscriber->unsubscribed_at);
    }

    public function test_bot_is_shown_the_done_page_without_saving(): void
    {
        // 表示から送信までが速すぎる
        $this->send('ニック', 'guest@example.com', secondsAgo: 0)->assertRedirect(route('newsletter.done'));

        $this->assertSame(0, $this->sentMails()->count());
        $this->assertSame(0, NewsletterSubscriber::count());
    }

    // ---- 配信停止・会員登録・退会 ----

    public function test_unsubscribe_link_of_a_non_member_stops_the_address(): void
    {
        $this->subscribe('ニック', 'guest@example.com');

        $this->post(MailUnsubscribe::url('guest@example.com'))->assertOk();

        $subscriber = NewsletterSubscriber::sole();
        $this->assertFalse($subscriber->receivesNoticeMail());
        $this->assertSame(NewsletterSource::UnsubscribeLink->value, $subscriber->source);
        $this->assertNotNull($subscriber->unsubscribed_at);
    }

    public function test_stopping_twice_keeps_the_first_record(): void
    {
        $first = NewsletterSubscription::stop('guest@example.com', NewsletterSource::UnsubscribeLink, '10.0.0.1');
        $this->travel(1)->days();
        NewsletterSubscription::stop('guest@example.com', NewsletterSource::Admin, '10.0.0.2');

        $subscriber = NewsletterSubscriber::sole();
        $this->assertSame(NewsletterSource::UnsubscribeLink->value, $subscriber->source);
        $this->assertSame('10.0.0.1', $subscriber->ip);
        $this->assertTrue($subscriber->unsubscribed_at->equalTo($first->unsubscribed_at));
    }

    public function test_member_registration_removes_the_row(): void
    {
        $this->subscribe('ニック', 'guest@example.com');
        $member = Member::factory()->create(['email' => 'guest@example.com']);

        NewsletterSubscription::forgetForMember($member);

        $this->assertSame(0, NewsletterSubscriber::count());
    }

    public function test_withdrawal_of_a_receiving_member_leaves_a_stop_row(): void
    {
        $member = Member::factory()->create(['email' => 'member@example.com', 'notice_mail' => NoticeMail::Receive->value]);

        $this->actingAs($member, 'web')->delete(route('mypage.destroy'))->assertRedirect('/');

        $subscriber = NewsletterSubscriber::sole();
        $this->assertSame('member@example.com', $subscriber->email);
        $this->assertFalse($subscriber->receivesNoticeMail());
        $this->assertSame(NewsletterSource::Withdrawal->value, $subscriber->source);
    }

    public function test_withdrawal_of_a_member_who_did_not_receive_leaves_nothing(): void
    {
        $member = Member::factory()->create(['email' => 'member@example.com', 'notice_mail' => NoticeMail::Stop->value]);

        $this->actingAs($member, 'web')->delete(route('mypage.destroy'));

        $this->assertSame(0, NewsletterSubscriber::count());
    }

    // ---- 一斉メール ----

    public function test_stopped_non_member_is_an_error_in_the_recipient_csv(): void
    {
        NewsletterSubscription::stop('guest@example.com', NewsletterSource::UnsubscribeLink, null);
        $csv = UploadedFile::fake()->createWithContent('recipients.csv', "ゲスト,Guest@example.com\n会員 以外,other@example.com\n");

        $this->actingAs($this->manager(), 'admin')
            ->post(route('admin.bulk-mails.confirm'), [
                'subject' => 'お知らせ',
                'body' => '本文',
                'csv_file' => $csv,
            ])
            ->assertOk()
            ->assertSee('お知らせメールの配信を停止しているアドレスです。')
            ->assertDontSee('件に送信する');
    }

    public function test_subscribers_csv_has_receiving_members_and_non_members(): void
    {
        Member::factory()->create(['name' => '会員 太郎', 'email' => 'taro@example.com', 'notice_mail' => NoticeMail::Receive->value]);
        Member::factory()->create(['name' => '会員 次郎', 'email' => 'jiro@example.com', 'notice_mail' => NoticeMail::Stop->value]);
        $this->subscribe('ニック', 'guest@example.com');
        NewsletterSubscription::stop('stopped@example.com', NewsletterSource::UnsubscribeLink, null);

        $manager = $this->manager();

        $this->actingAs($manager, 'admin')->get(route('admin.bulk-mails.create'))
            ->assertSee(route('admin.bulk-mails.subscribersCsv'));

        $csv = $this->actingAs($manager, 'admin')->get(route('admin.bulk-mails.subscribersCsv'));

        // 宛先のCSVと同じ形。会員が先、会員ではない人が後。「受け取らない」の人は入らない
        $this->assertSame("\xEF\xBB\xBF\"会員 太郎\",taro@example.com\r\nニック,guest@example.com\r\n", $csv->streamedContent());
    }
}
