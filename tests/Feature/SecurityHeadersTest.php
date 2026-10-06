<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ブラウザに守り方を伝えるヘッダー（App\Http\Middleware\SecurityHeaders）。
 * 全部の応答に付く。HTTPSだけで開かせる指定は、HTTPSで届いたときだけ付く。
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_headers_are_added_to_a_page(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_headers_are_added_to_the_not_found_page(): void
    {
        $response = $this->get('/_test/no-such-page');

        $response->assertNotFound();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_hsts_is_not_sent_over_http(): void
    {
        $response = $this->get('http://localhost/login');

        $response->assertOk();
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_sent_over_https(): void
    {
        $response = $this->get('https://localhost/login');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
