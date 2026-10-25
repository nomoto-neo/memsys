<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\BasicAuthUser;
use Neobit\Core\Models\OperationLog;
use Tests\TestCase;

/**
 * ベーシック認証（Neobit\Core\Security\BasicAuth・Neobit\Core\Http\Controllers\BasicAuthController）。
 * 管理画面から、範囲（訪問者画面・管理画面）ごとに、目印のファイルを作る・消すことと、ユーザーから.htpasswdを書き直すこと。
 * 認証そのものはApacheが行う。
 */
class BasicAuthTest extends TestCase
{
    use RefreshDatabase;

    /** テストのあいだのstorage。手元のstorageに目印のファイルを残さないため */
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'basic-auth-test-'.uniqid();
        File::ensureDirectoryExists($this->storage);
        $this->app->useStoragePath($this->storage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    private function manager(StaffAcl $acl = StaffAcl::Manager): Staff
    {
        return Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('staff-password'),
            'acl' => $acl,
        ]);
    }

    /** storage/app/basic-auth の中のファイルのパス */
    private function file(string $name): string
    {
        return $this->storage.'/app/basic-auth/'.$name;
    }

    /** .htpasswdの行。ユーザー名 => ハッシュ */
    private function htpasswd(string $area): array
    {
        $lines = array_filter(explode("\n", (string) file_get_contents($this->file($area.'.htpasswd'))));

        return collect($lines)->mapWithKeys(fn (string $line) => [explode(':', $line, 2)[0] => explode(':', $line, 2)[1]])->all();
    }

    public function test_each_area_has_its_own_users_and_switch(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager, 'admin')->get(route('admin.basic-auth.index'))->assertOk()->assertSee('訪問者画面')->assertSee('管理画面');

        // ユーザーがいなければ掛けられない。エラーは、その範囲の入れ物に入る
        $this->actingAs($manager, 'admin')
            ->patch(route('admin.basic-auth.update', 'site'), ['on' => '1'])
            ->assertSessionHasErrorsIn('site', ['on' => 'ユーザーがいないので、ベーシック認証を掛けられません。先にユーザーを追加してください。']);
        $this->assertFileDoesNotExist($this->file('site.on'));

        // 範囲ごとにユーザーを追加すると、その範囲の.htpasswdにbcryptのハッシュで書く
        $this->actingAs($manager, 'admin')
            ->post(route('admin.basic-auth.store', 'site'), ['area' => 'site', 'username' => 'guest', 'password' => 'guest-pass'])
            ->assertRedirect(route('admin.basic-auth.index'));
        $this->actingAs($manager, 'admin')
            ->post(route('admin.basic-auth.store', 'admin'), ['area' => 'admin', 'username' => 'staff', 'password' => 'staff-pass'])
            ->assertRedirect(route('admin.basic-auth.index'));

        $site = $this->htpasswd('site');
        $admin = $this->htpasswd('admin');
        $this->assertSame(['guest'], array_keys($site));
        $this->assertSame(['staff'], array_keys($admin));
        $this->assertStringStartsWith('$2y$', $site['guest']);
        $this->assertTrue(password_verify('guest-pass', $site['guest']));
        $this->assertTrue(password_verify('staff-pass', $admin['staff']));

        // 訪問者画面にだけ掛ける
        $this->actingAs($manager, 'admin')->patch(route('admin.basic-auth.update', 'site'), ['on' => '1'])->assertSessionHas('status');
        $this->assertFileExists($this->file('site.on'));
        $this->assertFileDoesNotExist($this->file('admin.on'));

        // 掛けている範囲の最後のユーザーは削除できない。掛けていない範囲は削除できる
        $guest = BasicAuthUser::where('area', 'site')->firstOrFail();
        $this->actingAs($manager, 'admin')
            ->delete(route('admin.basic-auth.destroy', $guest))
            ->assertSessionHas('error', 'ベーシック認証を掛けている間は、最後のユーザーを削除できません。');
        $staff = BasicAuthUser::where('area', 'admin')->firstOrFail();
        $this->actingAs($manager, 'admin')->delete(route('admin.basic-auth.destroy', $staff))->assertSessionHas('status');
        $this->assertSame([], $this->htpasswd('admin'));

        // 外すと、目印のファイルを消し、最後のユーザーも削除できる
        $this->actingAs($manager, 'admin')->patch(route('admin.basic-auth.update', 'site'), ['on' => '0']);
        $this->assertFileDoesNotExist($this->file('site.on'));
        $this->actingAs($manager, 'admin')->delete(route('admin.basic-auth.destroy', $guest))->assertSessionHas('status');
        $this->assertSame([], $this->htpasswd('site'));

        // 操作ログ。範囲とユーザー名を残し、パスワードは残さない
        $logs = OperationLog::orderBy('id')->get()->filter(fn (OperationLog $log) => str_starts_with($log->action->value, 'basic_auth'))->values();
        $this->assertSame(
            ['basic_auth_user_add', 'basic_auth_user_add', 'basic_auth_change', 'basic_auth_user_delete', 'basic_auth_change', 'basic_auth_user_delete'],
            $logs->map(fn (OperationLog $log) => $log->action->value)->all(),
        );
        $this->assertSame(OperationLogAction::BasicAuthUserAdd, $logs->first()->action);
        $this->assertStringContainsString('訪問者画面：guest', json_encode($logs->first()->detail, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('guest-pass', OperationLog::query()->get()->toJson());
    }

    public function test_wrong_users_are_rejected(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager, 'admin')->post(route('admin.basic-auth.store', 'site'), ['username' => 'staff', 'password' => 'secret-pass']);

        $this->actingAs($manager, 'admin')
            ->post(route('admin.basic-auth.store', 'site'), ['username' => 'sta:ff', 'password' => 'secret-pass'])
            ->assertSessionHasErrorsIn('site', ['username' => 'ユーザー名は、半角の英数字と - _ . で入力してください。']);
        $this->actingAs($manager, 'admin')
            ->post(route('admin.basic-auth.store', 'site'), ['username' => 'staff', 'password' => 'secret-pass'])
            ->assertSessionHasErrorsIn('site', ['username' => 'このユーザー名は、もう登録されています。']);
        $this->actingAs($manager, 'admin')
            ->post(route('admin.basic-auth.store', 'site'), ['username' => 'other', 'password' => 'short'])
            ->assertSessionHasErrorsIn('site', 'password');

        // 範囲が違えば同じユーザー名でもよい。大文字と小文字は別のユーザー
        $this->actingAs($manager, 'admin')
            ->post(route('admin.basic-auth.store', 'admin'), ['username' => 'staff', 'password' => 'secret-pass'])
            ->assertSessionHasNoErrors();
        $this->actingAs($manager, 'admin')
            ->post(route('admin.basic-auth.store', 'site'), ['username' => 'Staff', 'password' => 'secret-pass'])
            ->assertSessionHasNoErrors();
        $this->assertSame(3, BasicAuthUser::count());

        // 知らない範囲は、ルートに当たらず断る
        $this->actingAs($manager, 'admin')->patch('/admin/basic-auth/other', ['on' => '1'])->assertClientError();
        $this->assertFileDoesNotExist($this->file('other.on'));
    }

    public function test_staff_who_is_not_a_manager_is_sent_away(): void
    {
        $staff = $this->manager(StaffAcl::Staff);

        $this->actingAs($staff, 'admin')->get(route('admin.basic-auth.index'))->assertRedirect(route('admin.staff.edit', $staff));
    }
}
