<?php

namespace Tests\Feature\Auth;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\Support\ReadsSentMail;
use Tests\TestCase;

/**
 * 「このデバイスを記憶する」のCookie。パスの指定を誤っていた頃に保存された古いCookieが
 * ブラウザに残っていても、新しく記憶した端末が使われること。
 */
class TrustedDeviceCookieTest extends TestCase
{
    use ReadsSentMail;
    use RefreshDatabase;

    public function test_remembering_a_device_removes_the_old_cookie_of_the_wrong_path(): void
    {
        Member::factory()->create(['email' => 'taro@example.com', 'password' => Hash::make('correct-password')]);

        $this->post('/login', ['email' => 'taro@example.com', 'password' => 'correct-password']);
        $response = $this->post('/login/verify', ['code' => $this->lastVerificationCode(), 'remember_device' => '1']);

        // 同じ名前のCookieを2つ返す。パスごとに別のCookieとして扱われる
        $cookies = collect($response->headers->getCookies())
            ->filter(fn (Cookie $cookie) => $cookie->getName() === 'member_trusted_device')
            ->keyBy(fn (Cookie $cookie) => $cookie->getPath());

        // 新しい値は、どのURLにも送られるパス「/」で、30日
        $this->assertTrue($cookies->has('/'));
        $this->assertGreaterThan(now()->addDays(29)->timestamp, $cookies['/']->getExpiresTime());

        // 古いCookieが残っていたパス（/login）には、期限切れを返して消させる
        $this->assertTrue($cookies->has('/login'));
        $this->assertLessThan(now()->timestamp, $cookies['/login']->getExpiresTime());
    }
}
