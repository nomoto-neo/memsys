<?php

namespace Tests\Feature\Auth;

use App\Enums\StaffAcl;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Auth\BackupCodeGenerator;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\OperationLog;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * 2段階認証の登録を解除するコマンド（php artisan app:reset-two-factor）。
 * 画面から解除できる人がいないときの、サーバーからの逃げ道。
 */
class ResetTwoFactorCommandTest extends TestCase
{
    use RefreshDatabase;

    private function registeredStaff(): Staff
    {
        $staff = Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('correct-password'),
            'acl' => StaffAcl::Manager,
        ]);
        $staff->registerTwoFactor((new Google2FA())->generateSecretKey());
        (new BackupCodeGenerator())->generateFor($staff);

        return $staff;
    }

    public function test_command_resets_the_two_factor_of_the_staff(): void
    {
        $staff = $this->registeredStaff();

        $this->artisan('app:reset-two-factor', ['type' => 'staff', 'login' => 'hanako'])
            ->expectsConfirmation('この人の2段階認証の登録を解除します。よろしいですか？', 'yes')
            ->assertSuccessful();

        // 認証アプリの登録とバックアップコードが消え、操作ログに残る
        $this->assertFalse($staff->refresh()->hasTwoFactorConfirmed());
        $this->assertSame(0, $staff->backupCodes()->count());
        $this->assertSame(1, OperationLog::where('action', OperationLogAction::TwoFactorReset)
            ->where('target_id', $staff->id)->whereNull('operator_id')->count());
    }

    public function test_command_does_nothing_when_not_confirmed(): void
    {
        $staff = $this->registeredStaff();

        $this->artisan('app:reset-two-factor', ['type' => 'staff', 'login' => 'hanako'])
            ->expectsConfirmation('この人の2段階認証の登録を解除します。よろしいですか？', 'no')
            ->assertSuccessful();

        $this->assertTrue($staff->refresh()->hasTwoFactorConfirmed());
    }

    public function test_command_fails_for_an_unknown_type_or_person(): void
    {
        $staff = $this->registeredStaff();

        $this->artisan('app:reset-two-factor', ['type' => 'nobody', 'login' => 'hanako'])->assertFailed();
        $this->artisan('app:reset-two-factor', ['type' => 'staff', 'login' => 'taro'])->assertFailed();

        $this->assertTrue($staff->refresh()->hasTwoFactorConfirmed());
    }
}
