<?php

namespace App\Providers;

use App\Models\Inquiry;
use App\Models\Member;
use App\Models\News;
use App\Models\Passkey;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // laravel/passkeysの設定。
        // - パッケージのルート（/passkeys/login など）は登録させない。パッケージの
        //   ルートは1つのガード・1種類のユーザーを前提にしているため、会員と
        //   スタッフの両方で使うこのサイトでは、ルートとコントローラーを自前で用意し
        //   （App\Support\PasskeyLogin・PasskeyManagement）、パッケージからは
        //   登録・照合の処理（Actionクラス）だけを使う。
        //   パッケージのルートの登録は、パッケージのServiceProviderのboot()で行われ、
        //   それはこのクラスのboot()より先に動くので、ここ（register()）で止める。
        // - パッケージの処理が使うモデルを、passkeysテーブルを会員・スタッフ共通で
        //   使うApp\Models\Passkeyに差し替える。
        Passkeys::ignoreRoutes();
        Passkeys::usePasskeyModel(Passkey::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ポリモーフィックリレーション（trusted_devices・two_factor_backup_codes・passkeysの
        // authenticatable_type列）に記録する名前。何も指定しないとクラス名
        // （"App\Models\Member"）がそのままDBに入り、クラスの名前空間や名前を
        // 変えたときにDBの値も書き換える必要が出てしまう。短い名前に固定しておく。
        //
        // enforceMorphMap()は、ここに載っていないモデルをポリモーフィック
        // リレーションで使おうとすると例外にする。DBにクラス名が紛れ込むことが
        // 無いよう、モデルを追加したときはここにも足す。
        //
        // 非公開のアップロードファイル（モデルのPRIVATE_FILE_FIELDS）のURLにも、
        // 持ち主の種類としてこの名前を使う（App\Support\UploadFilePath参照）。
        // 非公開のフィールドを持つモデルも、ここに載せる。
        Relation::enforceMorphMap([
            'member' => Member::class,
            'staff' => Staff::class,
            'inquiry' => Inquiry::class,
            'news' => News::class,
        ]);
    }
}
