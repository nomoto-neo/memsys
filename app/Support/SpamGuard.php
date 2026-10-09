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
 * 訪問者向けのフォームのスパム対策。人に手間をかけさせない、次の3つを組み合わせる。
 *
 * 1. ハニーポット：人には見えない入力欄を置く。人は何も書かないがスパムを送るプログラムは
 *    見つけた欄を埋めがちなので、入力があれば機械とみなす
 * 2. 送信までの時間：入力画面を表示した時刻を暗号化してhiddenに持たせる。表示から送信までが
 *    短すぎれば機械とみなす。暗号化しているので書き換えられない
 * 3. Cloudflare Turnstile：画像を選ばせずに人か機械かを判定する。入力画面の枠がブラウザの
 *    裏で判定して結果を送ってくるので、それが本物かをサーバーからCloudflareに問い合わせる
 *
 * ■ 使い方
 * - 入力画面の<form>の中に@include('_spam_guard')を置く
 * - 入力画面から送信を受け取るところで、check()を呼んで結果で分ける。Turnstileの結果は
 *   1回しか使えないので確認画面から先では呼ばず、確認画面を通った人にだけ進ませる仕組みで守る
 *
 *     $spam = SpamGuard::check($request, minSeconds: self::SPAM_GUARD_MIN_SECONDS);
 *
 * ■ 判定の結果
 * - Bot     ハニーポットに入力があるか速すぎるときは、送れたように見せて何も保存しない
 * - Failed  Turnstileに通らなかったか、hiddenの値が無いか壊れているときは、入力画面に戻して
 *           もう一度試してもらう。Turnstileの結果が期限切れか使用済みのときもここになる
 * - Passed  それ以外。Cloudflareに問い合わせできなかったときと、鍵の設定が無いか間違っている
 *           ときも、送信を止めないためにPassedにしてログに残す
 *
 * ■ 鍵
 * .envのTURNSTILE_SITE_KEYとTURNSTILE_SECRET_KEY。Cloudflareのダッシュボードでドメインごとに
 * 発行する。手元の開発ではCloudflareのテスト用の鍵を使う。必ず通る鍵は、サイトキーが
 * 1x00000000000000000000AA、シークレットキーが1x0000000000000000000000000000000AA。
 */
final class SpamGuard
{
    /** ハニーポットの欄の名前。機械が埋めたくなるよう、ありそうな名前にしている。 */
    public const HONEYPOT_FIELD = 'homepage_url';

    /** 入力画面を表示した時刻を、暗号化して持たせるhiddenの名前。 */
    public const STARTED_FIELD = 'form_started_token';

    /** Turnstileの枠が送ってくるトークンの名前。Cloudflareが決めているもの。 */
    public const TURNSTILE_FIELD = 'cf-turnstile-response';

    /** Turnstileの判定を問い合わせる先。 */
    private const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** Cloudflareへの問い合わせを待つ秒数。これを超えたら障害とみなして通す。 */
    private const TURNSTILE_TIMEOUT_SECONDS = 5;

    /** Cloudflareの側の問題を表すエラーコード。これが返ってきたときも障害とみなして通す。 */
    private const TURNSTILE_UNAVAILABLE_ERRORS = ['internal-error'];

    /** こちらの鍵の設定の問題を表すエラーコード。送信は止めず、設定を直すようログに残す。 */
    private const TURNSTILE_CONFIG_ERRORS = ['missing-input-secret', 'invalid-input-secret'];

    /** 入力画面を表示した時刻を暗号化した値。_spam_guardがhiddenに入れる。 */
    public static function startedToken(): string
    {
        return Crypt::encryptString((string) time());
    }

    /**
     * 送信がスパムかどうかを判定する。$minSecondsは表示から送信までにかかるはずの
     * いちばん短い秒数で、これより速ければ機械とみなす。
     */
    public static function check(Request $request, int $minSeconds): SpamCheckResult
    {
        // ハニーポットに入力がある
        if (filled($request->input(self::HONEYPOT_FIELD))) {
            Log::info('SpamGuard: ハニーポットに入力がありました。', ['ip' => $request->ip(), 'path' => $request->path()]);

            return SpamCheckResult::Bot;
        }

        // 表示した時刻が無いか壊れている。古い画面のまま送ったときや、書き換えられたときなど
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

    /** Turnstileのトークンが本物かを、Cloudflareのsiteverifyに問い合わせる。 */
    private static function verifyTurnstile(Request $request): SpamCheckResult
    {
        $secret = config('services.turnstile.secret_key');

        if (blank($secret)) {
            Log::error('SpamGuard: Turnstileのシークレットキーが設定されていないので、判定せずに通しました。');

            return SpamCheckResult::Passed;
        }

        $token = (string) $request->input(self::TURNSTILE_FIELD);

        // 枠の判定が終わる前に送られた。枠を出すCloudflareのスクリプトを読めない環境でもこうなる
        if ($token === '') {
            Log::info('SpamGuard: Turnstileのトークンがありません。', ['ip' => $request->ip(), 'path' => $request->path()]);

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
