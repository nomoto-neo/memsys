<?php

namespace Tests\Feature;

use App\Enums\StaffAcl;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Neobit\Core\Http\Middleware\AjaxFormResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * 画面を読み直さずに送るフォーム（ajax_submit）への返事の読み替え。コントローラーはリダイレクトを
 * 返したまま、Ajaxのときだけ、エラーの文言か移動先のURLのJSONになる。
 * 例として、スタッフ本人による2段階認証の登録解除のフォームを使う。
 */
class AjaxFormResponseTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    /** 2段階認証を登録済みのスタッフ */
    private function staff(): Staff
    {
        $this->secret = (new Google2FA())->generateSecretKey();

        $staff = Staff::create([
            'name' => '管理 花子',
            'login_id' => 'hanako',
            'email' => 'hanako@example.com',
            'password' => Hash::make('correct-password'),
            'acl' => StaffAcl::Manager,
        ]);
        $staff->registerTwoFactor($this->secret);

        return $staff;
    }

    /** ajax_submitのフォームからの送信に、JavaScriptが付けるヘッダー */
    private function ajaxHeaders(): array
    {
        return [
            AjaxFormResponse::HEADER => '1',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    public function test_error_redirect_becomes_json_with_the_messages(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'admin')
            ->delete('/app/admin/2fa', ['code' => '000000'], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertExactJson(['errors' => ['code' => '確認コードが正しくありません。']]);

        // 画面を読み直さないので、エラーは次に開く画面へ持ち越さない
        $this->assertFalse(session()->has('errors'));
        $this->assertTrue($staff->refresh()->hasTwoFactorConfirmed());
    }

    public function test_validation_error_is_also_returned_as_json(): void
    {
        // 入力が空のときは、validate()が止める。項目の名前は、同じ形で届く
        $this->actingAs($this->staff(), 'admin')
            ->delete('/app/admin/2fa', [], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_success_redirect_becomes_json_with_the_destination(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'admin')
            ->delete('/app/admin/2fa', ['code' => (new Google2FA())->getCurrentOtp($this->secret)], $this->ajaxHeaders())
            ->assertOk()
            ->assertExactJson(['redirect' => route('admin.staff.show', $staff)]);

        $this->assertFalse($staff->refresh()->hasTwoFactorConfirmed());

        // 完了のメッセージは、移動先の画面で出る
        $this->actingAs($staff, 'admin')->get(route('admin.staff.show', $staff))
            ->assertOk()->assertSee('2段階認証の登録を解除しました。');
    }

    public function test_ordinary_submit_still_gets_a_redirect(): void
    {
        $staff = $this->staff();

        // ヘッダーが無ければ、今までどおりリダイレクトとセッションのエラーになる
        $this->actingAs($staff, 'admin')
            ->delete('/app/admin/2fa', ['code' => '000000'])
            ->assertRedirect(route('admin.staff.show', $staff))
            ->assertSessionHasErrors('code', errorBag: 'selfResetTwoFactor');
    }
}
