<?php

namespace App\Support;

use App\Enums\OperationLogAction;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ログイン後の管理画面の全体に掛ける、回数の制限。スタッフごとに、1分に送れる回数を決める。
 * 一覧や詳細を、プログラムで続けて開いてデータを取り出す動きを止めるため。
 * AppServiceProviderがadmin-screenという名前で登録し、routes/web.phpの管理画面のグループが
 * throttle:admin-screenで使う。
 *
 * ■ 回数
 * .envのADMIN_REQUESTS_PER_MINUTE（config('app.admin_requests_per_minute')）。
 * 人が普通に使って届かない数にする。画面の表示のほか、アップロードや、送信中の画面の
 * 読み直しも1回に数える。
 *
 * ■ 超えたとき
 * その回は処理せずに、少し待つよう案内する画面を返す。あわせて、操作ログに「回数の制限」を残し、
 * warningのログを書く。ERROR_NOTIFY_LEVELがwarningなら、エラーの通知のメールが届く。
 * 続けて取り出そうとすると、超えた回数だけ記録が増えてしまうので、操作ログとログは、
 * スタッフごとにNOTIFY_INTERVAL_SECONDSに1回までにする。
 */
final class AdminRequestLimit
{
    // 設定が無いときの、1分に送れる回数。設定のキャッシュが古くて項目がまだ無いときにも使う
    private const DEFAULT_PER_MINUTE = 120;

    // 超えたことを、操作ログとログに残す間隔（秒）。この間に何度超えても、残すのは1回
    private const NOTIFY_INTERVAL_SECONDS = 60;

    // この回の、回数の制限。操作しているスタッフのidで数えるので、同じ事務所から複数の人が
    // 使っても、互いに影響しない。
    public static function limit(Request $request): Limit
    {
        // 設定が無いか0のときは、既定の回数にする。0のまま使うと、全部の操作が止まる
        $perMinute = (int) config('app.admin_requests_per_minute') ?: self::DEFAULT_PER_MINUTE;
        $staffId = $request->user()?->getKey();

        return Limit::perMinute($perMinute)
            ->by('admin-screen:'.$staffId)
            ->response(fn (Request $request) => self::exceeded($request, $perMinute, $staffId));
    }

    // 回数を超えたときの記録と、返す画面
    private static function exceeded(Request $request, int $perMinute, mixed $staffId): Response
    {
        // 記録を残すのは、スタッフごとに間隔の中で1回だけ。add()は、まだ無いときだけ置けてtrueを返す
        if (Cache::add('admin-request-limit-notified:'.$staffId, true, self::NOTIFY_INTERVAL_SECONDS)) {
            // 問い合わせの部分には入力値が入ることがあるので、URLはパスまでにする
            $path = $request->method().' /'.ltrim($request->path(), '/');

            OperationRecorder::record(OperationLogAction::Throttled, detail: ['path' => $path]);

            // 文言にスタッフのidを入れているのは、エラーの通知が文言で同じ内容かを見分けるため。
            // 入れないと、別のスタッフの分が同じ内容として間引かれる
            Log::warning("AdminRequestLimit: 管理画面の操作が上限を超えました（スタッフID:{$staffId}）。", [
                'per_minute' => $perMinute,
                'ip' => $request->ip(),
                'path' => $path,
            ]);
        }

        $message = '操作の回数が上限を超えました。1分ほど待ってから、もう一度お試しください。';

        // アップロードのように、JavaScriptからの送信にはJSONで返す
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return response()->view('admin.too_many_requests', ['message' => $message], Response::HTTP_TOO_MANY_REQUESTS);
    }
}
