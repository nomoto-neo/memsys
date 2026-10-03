<?php

namespace App\Support;

use App\Enums\SpamCheckResult;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 訪問者向けフォームのスパム対策。人に手間をかけさせない、次の3つを組み合わせる。
 *
 * 1. ハニーポット：人には見えない入力欄（HONEYPOT_FIELD）を置く。人は何も書かないが、
 *    スパムを送るプログラムは見つけた欄を埋めがちなので、入力があれば機械とみなす。
 * 2. 送信までの時間：入力画面を表示した時刻を暗号化してhidden（STARTED_FIELD）に持たせ、
 *    表示から送信までが短すぎれば機械とみなす。暗号化しているので、書き換えられない。
 * 3. Cloudflare Turnstile：画像を選ばせない、人か機械かの判定。入力画面に置いた枠が
 *    ブラウザの裏側で判定し、結果のトークン（TURNSTILE_FIELD）を送ってくるので、
 *    サーバーからCloudflareに本物かを問い合わせる（siteverify）。
 *
 * ■ 使い方
 * - 入力画面の<form>の中に @include('_spam_guard') を置く（3つの欄と、Turnstileの枠・スクリプト）
 * - 入力画面から送信を受け取るところ（確認画面を表示する処理など）で check() を呼び、
 *   結果（App\Enums\SpamCheckResult）で分ける。確認画面から先は、確認画面を通った人にしか
 *   進めない仕組み（お問い合わせのconfirm_tokenなど）で守り、ここでは呼ばない
 *   （Turnstileのトークンは1回しか使えないため）。
 *
 *     $spam = SpamGuard::check($request, minSeconds: self::SPAM_GUARD_MIN_SECONDS);
 *
 * ■ 判定の結果
 * - Bot     ハニーポットに入力がある、または速すぎる。送れたように見せて、何も保存しない
 * - Failed  Turnstileに通らなかった、期限切れ（5分）・使用済み、またはhiddenの値が無い・
 *           壊れている（古い画面のまま送ったなど）。入力画面に戻してもう一度試してもらう
 * - Passed  それ以外。Cloudflareに問い合わせできなかった（障害・時間切れ）ときと、
 *           鍵が設定されていない・間違っているときも、送信を止めないためにPassedにして
 *           ログに残す
 *
 * ■ 鍵（config/services.phpのturnstile。.envのTURNSTILE_SITE_KEY・TURNSTILE_SECRET_KEY）
 * Cloudflareのダッシュボードで、サイトのドメインごとに発行する。手元の開発では、Cloudflareが
 * 公開しているテスト用の鍵（必ず通る：サイトキー 1x00000000000000000000AA、
 * シークレットキー 1x0000000000000000000000000000000AA）を使う。
 */
final class SpamGuard
{
    // ハニーポットの欄の名前。機械が埋めたくなるよう、ありそうな名前にしている。
    public const HONEYPOT_FIELD = 'homepage_url';

    // 入力画面を表示した時刻（暗号化したもの）を持たせるhiddenの名前。
    public const STARTED_FIELD = 'form_started_token';

    // Turnstileの枠が送ってくるトークンの名前（Cloudflareが決めているもの）。
    public const TURNSTILE_FIELD = 'cf-turnstile-response';

    // Turnstileの判定を問い合わせる先。
    private const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    // Cloudflareへの問い合わせを待つ秒数。これを超えたら障害とみなして通す。
    private const TURNSTILE_TIMEOUT_SECONDS = 5;

    // Cloudflareの側の問題を表すエラーコード。これが返ってきたときも、障害とみなして通す。
    private const TURNSTILE_UNAVAILABLE_ERRORS = ['internal-error'];

    // こちらの鍵の設定の問題を表すエラーコード。送信は止めず、設定を直すようログに残す。
    private const TURNSTILE_CONFIG_ERRORS = ['missing-input-secret', 'invalid-input-secret'];

    /**
     * 入力画面を表示した時刻を暗号化した値（_spam_guardがhiddenに入れる）。
     */
    public static function startedToken(): string
    {
        return Crypt::encryptString((string) time());
    }

    /**
     * 送信がスパムかどうかを判定する。$minSecondsは、表示から送信までにかかるはずの
     * いちばん短い秒数（これより速ければ機械とみなす）。
     */
    public static function check(Request $request, int $minSeconds): SpamCheckResult
    {
        // ハニーポットに入力がある
        if (filled($request->input(self::HONEYPOT_FIELD))) {
            Log::info('SpamGuard: ハニーポットに入力がありました。', ['ip' => $request->ip(), 'path' => $request->path()]);

            return SpamCheckResult::Bot;
        }

        // 表示した時刻が無い・壊れている（古い画面のまま送った、書き換えられたなど）
        try {
            $startedAt = (int) Crypt::decryptString((string) $request->input(self::STARTED_FIELD));
        } catch (DecryptException) {
            Log::info('SpamGuard: 表示した時刻の値が無いか、壊れています。', ['ip' => $request->ip(), 'path' => $request->path()]);

            return SpamCheckResult::Failed;
        }

        // 表示から送信までが速すぎる
        $elapsed = time() - $startedAt;
        if ($elapsed < $minSeconds) {
            Log::info('SpamGuard: 表示から送信までが速すぎます。', ['ip' => $request->ip(), 'path' => $request->path(), 'seconds' => $elapsed]);

            return SpamCheckResult::Bot;
        }

        return self::verifyTurnstile($request);
    }

    /**
     * Turnstileのトークンが本物かを、Cloudflareに問い合わせる（siteverify）。
     */
    private static function verifyTurnstile(Request $request): SpamCheckResult
    {
        $secret = config('services.turnstile.secret_key');

        if (blank($secret)) {
            Log::error('SpamGuard: Turnstileのシークレットキーが設定されていないので、判定せずに通しました。');

            return SpamCheckResult::Passed;
        }

        $token = (string) $request->input(self::TURNSTILE_FIELD);

        if ($token === '') {
            return SpamCheckResult::Failed;
        }

        try {
            $response = Http::asForm()
                ->timeout(self::TURNSTILE_TIMEOUT_SECONDS)
                ->post(self::TURNSTILE_VERIFY_URL, [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('SpamGuard: Turnstileに問い合わせできなかったので、判定せずに通しました。', ['message' => $e->getMessage()]);

            return SpamCheckResult::Passed;
        }

        if (! $response->successful()) {
            Log::warning('SpamGuard: Turnstileが応答しなかったので、判定せずに通しました。', ['status' => $response->status()]);

            return SpamCheckResult::Passed;
        }

        if ($response->json('success') === true) {
            return SpamCheckResult::Passed;
        }

        $errors = (array) $response->json('error-codes', []);

        if (array_intersect($errors, self::TURNSTILE_UNAVAILABLE_ERRORS)) {
            Log::warning('SpamGuard: Turnstileの側で問題が起きたので、判定せずに通しました。', ['errors' => $errors]);

            return SpamCheckResult::Passed;
        }

        if (array_intersect($errors, self::TURNSTILE_CONFIG_ERRORS)) {
            Log::error('SpamGuard: Turnstileのシークレットキーが正しくないので、判定せずに通しました。', ['errors' => $errors]);

            return SpamCheckResult::Passed;
        }

        Log::info('SpamGuard: Turnstileの判定に通りませんでした。', ['ip' => $request->ip(), 'path' => $request->path(), 'errors' => $errors]);

        return SpamCheckResult::Failed;
    }
}
