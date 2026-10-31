<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Mail\TemplatedMail;
use Neobit\Core\Models\OperationLog;
use Neobit\Core\Security\FileWatcher;
use Tests\TestCase;

/**
 * 管理画面の「改ざん監視」（Neobit\Core\Http\Controllers\FileWatchController）と、承認の取り込み（app:watch-files --approve）。
 * 一時のディレクトリにプロジェクトの形を作り、ctimeはテストが決める（Tests\Feature\FileWatchTestと同じ）。
 */
class FileWatchApprovalTest extends TestCase
{
    use RefreshDatabase;

    /** 一時のプロジェクトの場所 */
    private string $base;

    /** パス => テストが決めたctime */
    private array $ctimes = [];

    /** 基準を作った時刻 */
    private int $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = str_replace('\\', '/', sys_get_temp_dir()).'/file-watch-approval-test-'.uniqid();
        config(['neobit.file_watch.release_file' => '.version']);

        $this->start = Carbon::parse('2026-10-30 10:00:00')->getTimestamp();
        Carbon::setTestNow(Carbon::createFromTimestamp($this->start));

        $this->write('app/Models/News.php', '<?php // ニュース');
        $this->write('public/index.php', '<?php // 入口');
        $this->write('.version', '2026-10-30');
        File::ensureDirectoryExists($this->base.'/storage/app/public');

        $watcher = $this->watcher();
        $watcher->init();
        $this->app->instance(FileWatcher::class, $watcher);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function watcher(): FileWatcher
    {
        return new FileWatcher(
            $this->base,
            $this->base.'/public',
            $this->base.'/storage/app/public',
            fn (string $path) => $this->ctimes[str_replace('\\', '/', $path)] ?? $this->start - 86400,
        );
    }

    /** ファイルを書き、そのctimeを決める。時刻は基準を作った時刻からの分 */
    private function write(string $path, string $content, ?int $minutes = null): void
    {
        File::ensureDirectoryExists(dirname($this->base.'/'.$path));
        file_put_contents($this->base.'/'.$path, $content);
        $this->ctimes[$this->base.'/'.$path] = $minutes === null ? ($this->start ?? time()) - 86400 : $this->start + $minutes * 60;
    }

    /** 基準を作った時刻から$minutes分にする */
    private function at(int $minutes): void
    {
        Carbon::setTestNow(Carbon::createFromTimestamp($this->start + $minutes * 60));
    }

    private function staff(StaffAcl $acl = StaffAcl::Manager): Staff
    {
        return Staff::create([
            'name' => '野本',
            'login_id' => 'nomoto'.$acl->value,
            'email' => 'nomoto'.$acl->value.'@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => $acl,
        ]);
    }

    public function test_screen_shows_the_current_state_and_approval_is_taken_in_by_the_cron(): void
    {
        Mail::fake();
        config(['neobit.file_watch.to' => 'dev@example.com']);
        $manager = $this->staff();

        // 鍵の無い書き換え（改ざんの疑い）と、来たばかりの追加（判断待ち）
        $this->write('public/index.php', '<?php // 新しい入口', 2);
        $this->write('app/Models/Page.php', '<?php // 固定ページ', 25);
        $this->at(27);

        // 開いたときに、その場でチェックし直して出す。承認では、画面に出した一覧のしるしを送る
        $shown = $this->watcher()->inspect();
        $signature = FileWatcher::signature([...$shown['alerts'], ...$shown['pending']]);
        $this->actingAs($manager, 'admin')->get(route('admin.file-watch.index'))
            ->assertOk()
            ->assertSee('2026-10-30 10:27:00 現在のチェック状況です。')
            ->assertSee('改ざんの疑い（1件）')
            ->assertSee('判断待ち（1件）')
            ->assertSee('name="signature" value="'.$signature.'"', false)
            ->assertSee('承認では取り込めません。');

        // 承認すると、目印を置いて操作ログに残す。まだ基準には取り込まない
        $this->actingAs($manager, 'admin')->post(route('admin.file-watch.approve'), ['signature' => $signature])
            ->assertRedirect(route('admin.file-watch.index'))
            ->assertSessionMissing('status');
        $this->assertSame('野本', $this->watcher()->pendingApproval()['by']);
        $log = OperationLog::where('action', OperationLogAction::FileWatchApproval->value)->sole();
        $this->assertSame(['変わったファイル（1件）', 'public/index.php', '増えたファイル（1件）', 'app/Models/Page.php'], $log->detailLines());

        // 取り込む前に増えた変化は、承認の一覧に無いので取り込まない
        $this->write('app/Models/Shell.php', '<?php // 怪しいもの', 27);

        // 毎分のcronが拾って取り込み、承認のメールを送る
        $this->at(28);
        $this->artisan('app:watch-files --approve')->expectsOutputToContain('2件を基準に取り込みました。')->assertSuccessful();
        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo('dev@example.com'));
        $this->assertNull($this->watcher()->pendingApproval());

        // 取り込んだものは知らせず、承認の後に増えたものは判断待ちに残る
        $this->at(29);
        $status = $this->watcher()->inspect();
        $this->assertSame([], $status['alerts']);
        $this->assertSame(['app/Models/Shell.php'], array_column($status['pending'], 'label'));

        // 最近取り込んだ更新に、承認した人の名前で出る
        $this->assertSame('野本', $this->watcher()->recentlyAccepted()[0]['by']);
        $this->actingAs($manager, 'admin')->get(route('admin.file-watch.index'))
            ->assertSee('2026-10-30 10:28 に野本さんが承認した更新（2件）');
    }

    public function test_many_items_are_cut_in_the_modal_and_the_log(): void
    {
        // 51件の追加。確かめのモーダルと操作ログは50件で切り、「ほか1件」にする
        $manager = $this->staff();
        for ($i = 1; $i <= 51; $i++) {
            $this->write(sprintf('public/wp-content/plugins/sample/file%02d.php', $i), '<?php', 2);
        }
        $this->at(20);
        $signature = FileWatcher::signature($this->watcher()->inspect()['alerts']);

        $this->actingAs($manager, 'admin')->get(route('admin.file-watch.index'))
            ->assertSee('次の51件を、通常の更新として基準に取り込みます。')
            ->assertSee('増えたファイル（51件）')
            ->assertSee('ほか1件');

        // 操作ログは、種類の見出しと50件と「ほか1件」。見出しの件数は切る前の件数
        $this->actingAs($manager, 'admin')->post(route('admin.file-watch.approve'), ['signature' => $signature]);
        $log = OperationLog::where('action', OperationLogAction::FileWatchApproval->value)->sole();
        $this->assertCount(52, $log->detailLines());
        $this->assertSame('増えたファイル（51件）', $log->detailLines()[0]);
        $this->assertSame('ほか1件', $log->detailLines()[51]);

        // 目印には、全部の一覧を書く
        $this->assertCount(51, $this->watcher()->pendingApproval()['items']);
    }

    public function test_approve_without_a_request_does_nothing(): void
    {
        // 目印が無ければ、何もせずすぐ終わる（毎分動くため）
        $this->artisan('app:watch-files --approve')->doesntExpectOutputToContain('取り込みました')->assertSuccessful();
    }

    public function test_list_changed_after_opening_is_not_approved(): void
    {
        $manager = $this->staff();
        $this->write('public/index.php', '<?php // 新しい入口', 2);
        $this->at(20);
        $signature = FileWatcher::signature($this->watcher()->inspect()['alerts']);

        // 画面を開いた後に変化が増えたら、一覧が違うので承認しない。開き直してもらう
        $this->write('app/Models/Shell.php', '<?php // 怪しいもの', 21);
        $this->at(22);
        $this->actingAs($manager, 'admin')->post(route('admin.file-watch.approve'), ['signature' => $signature])
            ->assertSessionHas('status', '画面を開いた後に、変更内容の一覧が変わりました。開き直した一覧を確かめてから、承認してください。');
        $this->assertNull($this->watcher()->pendingApproval());

        // 画面を開いた後に元へ戻っていれば、承認するものは無い
        File::delete($this->base.'/app/Models/Shell.php');
        $this->write('public/index.php', '<?php // 入口', 23);
        $this->at(24);
        $this->actingAs($manager, 'admin')->post(route('admin.file-watch.approve'), ['signature' => $signature])
            ->assertSessionHas('status', '承認する変更内容がありません。');
        $this->assertNull($this->watcher()->pendingApproval());
    }

    public function test_the_regular_check_also_takes_in_an_approval(): void
    {
        // 毎分の行が無いサーバーでも、5分ごとの回が承認を先に取り込む
        Mail::fake();
        config(['neobit.file_watch.to' => 'dev@example.com']);
        $this->write('public/index.php', '<?php // 新しい入口', 2);
        $this->at(20);
        $watcher = $this->watcher();
        $watcher->requestApproval($watcher->inspect()['alerts'], '野本');

        $this->artisan('app:watch-files')->expectsOutputToContain('改ざんの疑い：0件')->assertSuccessful();
        $this->assertNull($this->watcher()->pendingApproval());
        Mail::assertSent(TemplatedMail::class);
    }

    public function test_screen_tells_when_not_watching_or_without_a_baseline(): void
    {
        $manager = $this->staff();

        config(['neobit.file_watch.release_file' => '']);
        $this->actingAs($manager, 'admin')->get(route('admin.file-watch.index'))
            ->assertSee('改ざん監視は使っていません');

        config(['neobit.file_watch.release_file' => '.version']);
        File::delete($this->base.'/bootstrap/cache/file-watch.json');
        $this->actingAs($manager, 'admin')->get(route('admin.file-watch.index'))
            ->assertSee('改ざん監視の基準がまだありません。');
    }

    public function test_only_managers_can_open_the_screen(): void
    {
        // 管理者でないスタッフは、自分の情報の画面へ移す（管理者だけのグループの決まり）
        $staff = $this->staff(StaffAcl::Staff);
        $this->actingAs($staff, 'admin')->get(route('admin.file-watch.index'))->assertRedirect(route('admin.staff.edit', $staff));
    }
}
