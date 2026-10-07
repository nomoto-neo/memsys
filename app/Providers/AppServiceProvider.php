<?php

namespace App\Providers;

use App\Enums\OperationLogAction;
use App\Models\BulkMail;
use App\Models\BulkMailTemplate;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Inquiry;
use App\Models\Member;
use App\Models\News;
use App\Models\Passkey;
use App\Models\Staff;
use App\Support\AdminRequestLimit;
use App\Support\LegacyPasswordUserProvider;
use App\Support\OperationRecorder;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Passkeys;

/**
 * アプリ全体の設定を、起動のときに行う。
 */
class AppServiceProvider extends ServiceProvider
{
    // サービスの登録（ほかのサービスプロバイダのboot()より前に動く）
    public function register(): void
    {
        // パスキーのパッケージ（laravel/passkeys）の設定。パッケージのルートは、1つのガードだけを
        // 前提にしているので使わず、会員とスタッフの両方に対応したルートを自前で用意する。
        // パッケージのルートはパッケージのboot()で登録されるので、それより前のここで止める
        Passkeys::ignoreRoutes();

        // パッケージが使うモデルを、会員・スタッフ共通のpasskeysテーブルのモデルに差し替える
        Passkeys::usePasskeyModel(Passkey::class);
    }

    // 起動のときの設定
    public function boot(): void
    {
        // サイトの各所で使う、モデルの短縮名を登録する。ここで決めた名前を、DBの「誰の行か」の列
        // （authenticatable_type）・操作ログの種類・非公開のアップロードファイルのURL・
        // セッションやログに残す種類の名前に使う。クラス名のままだと、名前空間を変えたときに
        // DBの値も直すことになるため。載っていないモデルを使うと例外で止まるので、
        // モデルを足したら必ずここにも足す。
        Relation::enforceMorphMap([
            'member' => Member::class,
            'staff' => Staff::class,
            'inquiry' => Inquiry::class,
            'news' => News::class,
            'bulk_mail' => BulkMail::class,
            'bulk_mail_template' => BulkMailTemplate::class,
            'category' => Category::class,
            // 企業会員。企業と、ログインする担当者
            'company' => Company::class,
            'company_user' => CompanyUser::class,
        ]);

        // ログインとログアウトを操作ログに残す。会員とスタッフの、パスワード・2段階目・パスキー・
        // 「ログイン状態を保持する」のどの入り方でも、Laravelがこのイベントを出す
        Event::listen(fn (Login $event) => OperationRecorder::record(OperationLogAction::Login, operator: $event->user));
        Event::listen(function (Logout $event) {
            // ログインしていない状態でログアウトが呼ばれたときは、誰のものでもないので書かない
            if ($event->user !== null) {
                OperationRecorder::record(OperationLogAction::Logout, operator: $event->user);
            }
        });

        // 会員のパスワードの照合。古い方式のパスワードを、ログインのときに今の方式へ置き換える。
        // config/auth.phpの会員のプロバイダーが、この名前で使う
        Auth::provider('eloquent-legacy', fn ($app, array $config) => new LegacyPasswordUserProvider($app['hash'], $config['model']));

        // 信頼するプロキシ（.envのTRUSTED_PROXIES）から届いたX-Forwarded-Forを、訪問者の
        // IPアドレスとして使う。ログインの試行制限・回数の制限・操作ログなど、IPアドレスを
        // 使うところの全部に効く。このヘッダーは送る側が自由に書けるので、信頼するプロキシの
        // ほかから届いたものは使わない。書いていなければ、直接つないできた相手のIPアドレスを使う
        // 設定のキャッシュが古くてこの項目がまだ無いとき（デプロイの直後、config:cacheをやり直す前）も、
        // 書いていないものとして扱う
        if (! empty(config('app.trusted_proxies'))) {
            TrustProxies::at(config('app.trusted_proxies'));
        }

        // ログイン後の管理画面の全体に掛ける、スタッフごとの回数の制限。
        // routes/web.phpの管理画面のグループが、throttle:admin-screenで使う
        RateLimiter::for('admin-screen', AdminRequestLimit::limit(...));

        // 一斉メールを送る速さの制限。App\Jobs\SendBulkMailのRateLimitedが使う
        RateLimiter::for('bulk-mail', fn () => Limit::perMinute(config('mail.bulk_per_minute')));
    }
}
