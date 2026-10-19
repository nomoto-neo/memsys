<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Http\Request;
use Neobit\Core\Auth\LoginVerificationCode;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * 確認コードのメールに差し込む使い道の名前（LoginVerificationCode::issueForAddress()のpurposeLabel）。
 * 案件の側で決めた使い道は名前を渡し、渡さなければ使い道の定数から決めた名前になる。
 */
class VerificationCodeLabelTest extends TestCase
{
    use ReadsSentMail;

    /** セッションを持ったリクエスト */
    private function request(): Request
    {
        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    public function test_label_passed_by_the_caller_is_used(): void
    {
        $sent = (new LoginVerificationCode(Member::class))
            ->issueForAddress($this->request(), 'taro@example.com', '山田 太郎', 'newsletter', purposeLabel: 'メルマガの登録');

        $this->assertTrue($sent);
        $this->assertStringContainsString('メルマガの登録のお手続き', (string) $this->lastMail()->getSubject());
        $this->assertStringContainsString('メルマガの登録のお手続きのための確認コード', (string) $this->lastMail()->getTextBody());
    }

    public function test_label_comes_from_the_purpose_without_it(): void
    {
        (new LoginVerificationCode(Member::class))
            ->issueForAddress($this->request(), 'taro@example.com', '山田 太郎', LoginVerificationCode::PURPOSE_REGISTER);

        $this->assertStringContainsString('会員登録のお手続き', (string) $this->lastMail()->getSubject());
    }

    public function test_code_issued_for_a_custom_purpose_is_verified_only_for_it(): void
    {
        $request = $this->request();
        $codes = new LoginVerificationCode(Member::class);
        $codes->issueForAddress($request, 'taro@example.com', '山田 太郎', 'newsletter', purposeLabel: 'メルマガの登録');

        preg_match('/\d{6}/', (string) $this->lastMail()->getTextBody(), $m);

        // 使い道ごとにセッションのキーが分かれるので、ほかの使い道では通らない
        $this->assertNull($codes->verifyForAddress($request, LoginVerificationCode::PURPOSE_REGISTER, $m[0]));
        $this->assertSame('taro@example.com', $codes->verifyForAddress($request, 'newsletter', $m[0]));
    }
}
