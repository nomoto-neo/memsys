<?php

namespace App\Providers;

use App\Models\BulkMail;
use App\Models\Inquiry;
use App\Models\Member;
use App\Models\News;
use App\Models\Passkey;
use App\Models\Staff;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
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
        // ポリモーフィックリレーションでDBに記録する、モデルの短い名前。指定しないとクラス名が
        // そのままDBに入り、名前空間を変えたときにDBも直すことになるため。載っていないモデルを
        // 使うと例外になるので、モデルを足したらここにも足す。非公開のアップロードファイルの
        // URLにもこの名前を使うので、非公開のフィールドを持つモデルもここに載せる
        Relation::enforceMorphMap([
            'member' => Member::class,
            'staff' => Staff::class,
            'inquiry' => Inquiry::class,
            'news' => News::class,
            'bulk_mail' => BulkMail::class,
        ]);

        // 信頼するプロキシ（.envのTRUSTED_PROXIES）から届いたX-Forwarded-Forを、訪問者の
        // IPアドレスとして使う。ログインの試行制限・回数の制限・操作ログなど、IPアドレスを
        // 使うところの全部に効く。このヘッダーは送る側が自由に書けるので、信頼するプロキシの
        // ほかから届いたものは使わない。書いていなければ、直接つないできた相手のIPアドレスを使う
        // 設定のキャッシュが古くてこの項目がまだ無いとき（デプロイの直後、config:cacheをやり直す前）も、
        // 書いていないものとして扱う
        if (! empty(config('app.trusted_proxies'))) {
            TrustProxies::at(config('app.trusted_proxies'));
        }

        // 一斉メールを送る速さの制限。App\Jobs\SendBulkMailのRateLimitedが使う
        RateLimiter::for('bulk-mail', fn () => Limit::perMinute(config('mail.bulk_per_minute')));
    }
}
