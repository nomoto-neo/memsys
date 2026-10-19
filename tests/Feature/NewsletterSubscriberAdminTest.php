<?php

namespace Tests\Feature;

use App\Enums\AppOperationLogAction;
use App\Enums\NewsletterSource;
use App\Enums\NoticeMail;
use App\Enums\StaffAcl;
use App\Models\NewsletterSubscriber;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Models\OperationLog;
use Tests\TestCase;

/**
 * 管理画面のメルマガ希望者（Admin\NewsletterSubscriberController）。一覧・検索と、手での停止。管理者だけが使える。
 */
class NewsletterSubscriberAdminTest extends TestCase
{
    use RefreshDatabase;

    private function staff(StaffAcl $acl): Staff
    {
        return Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => $acl,
        ]);
    }

    private function subscriber(string $email, NoticeMail $noticeMail): NewsletterSubscriber
    {
        return NewsletterSubscriber::create([
            'name' => 'ニック',
            'email' => $email,
            'notice_mail' => $noticeMail->value,
            'source' => NewsletterSource::Form->value,
            'subscribed_at' => now(),
        ]);
    }

    public function test_list_and_search(): void
    {
        $this->subscriber('guest@example.com', NoticeMail::Receive);
        $this->subscriber('stopped@example.com', NoticeMail::Stop);
        $manager = $this->staff(StaffAcl::Manager);

        $this->actingAs($manager, 'admin')->get(route('admin.newsletter-subscribers.index'))
            ->assertOk()
            ->assertSee('guest@example.com')
            ->assertSee('stopped@example.com')
            ->assertSee('メルマガ希望者');

        // 状態で絞る。検索条件を保存した後の転送先（ページ番号付きの一覧）で効く
        $this->actingAs($manager, 'admin')->followingRedirects()
            ->post(route('admin.newsletter-subscribers.search'), ['notice_mail' => (string) NoticeMail::Stop->value])
            ->assertDontSee('guest@example.com')
            ->assertSee('stopped@example.com');
    }

    public function test_manager_can_stop_a_subscriber(): void
    {
        $subscriber = $this->subscriber('guest@example.com', NoticeMail::Receive);

        $this->actingAs($this->staff(StaffAcl::Manager), 'admin')
            ->patch(route('admin.newsletter-subscribers.stop', $subscriber))
            ->assertRedirect()
            ->assertSessionHas('status', 'guest@example.com への配信を停止しました。');

        $subscriber->refresh();
        $this->assertFalse($subscriber->receivesNoticeMail());
        $this->assertSame(NewsletterSource::Admin->value, $subscriber->source);
        $this->assertTrue(OperationLog::where('action', AppOperationLogAction::NewsletterStop)->exists());
    }

    public function test_staff_who_is_not_a_manager_cannot_use_the_screen(): void
    {
        $subscriber = $this->subscriber('guest@example.com', NoticeMail::Receive);
        $staff = $this->staff(StaffAcl::Staff);

        // 管理者だけのルート（EnsureStaffIsManager）は、自分の情報の編集の画面へ回す
        $this->actingAs($staff, 'admin')->get(route('admin.newsletter-subscribers.index'))
            ->assertRedirect(route('admin.staff.edit', $staff));
        $this->actingAs($staff, 'admin')->patch(route('admin.newsletter-subscribers.stop', $subscriber))
            ->assertRedirect(route('admin.staff.edit', $staff));
        $this->assertTrue($subscriber->fresh()->receivesNoticeMail());
    }
}
