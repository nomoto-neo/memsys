<?php

namespace Database\Factories;

use App\Enums\NoticeMail;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * ダミー会員（t_members）を1件分作るための「型紙」。
 *
 * ■ factory()とは何か
 * `Member::factory()->count(30)->create()`のように呼ぶと、この
 * definition()が30回呼ばれ、返ってきた配列がそのままEloquentの
 * create()（＝INSERT）に渡される。1件分の「作り方」だけをここに
 * 書いておけば、何件作るか・作った後にどう調整するかは呼び出し側
 * （Seederやtinker、テストコード）が決められる、という役割分担。
 *
 * ■ Fakerのロケール
 * `fake()`ヘルパーはconfig('app.faker_locale')の設定に従うが、ここでは
 * 環境の設定に左右されないよう、\Faker\Factory::create('ja_JP')で明示的に
 * 日本語ロケールのFakerを作っている（ほかのfactoryがあっても、この
 * factoryは環境の設定の影響を受けない）。
 *
 * 使っているja_JPロケール固有のメソッドは、$faker->name（氏名）・
 * kanaName（カナ）・phoneNumber（電話番号）。出力の例は次のとおり。
 *
 * ```
 * php artisan tinker
 * >>> $f = \Faker\Factory::create('ja_JP');
 * >>> $f->name;         // 例: "山田 太郎"
 * >>> $f->kanaName;     // 例: "ヤマダ タロウ"
 * >>> $f->phoneNumber;  // 例: "090-1234-5678"
 * ```
 *
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    /**
     * パスワードのハッシュ化（bcrypt）は毎回同じ処理で重いので、
     * 30件作るたびに計算し直さず、静的プロパティに1回だけ結果を
     * キャッシュしておく（Laravel公式のUserFactoryと同じ手筋）。
     */
    protected static ?string $hashedPassword = null;

    public function definition(): array
    {
        $faker = \Faker\Factory::create('ja_JP');

        return [
            'name' => $faker->name,
            'kana' => $faker->kanaName,
            'email' => $faker->unique()->safeEmail(),
            'password' => static::$hashedPassword ??= Hash::make('password'),
            'phone' => $faker->phoneNumber,
            // 18歳〜80歳になる範囲の生年月日をランダムに作る。
            'birthdate' => $faker->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            // ★要確認：都道府県コードは「1=北海道…47=沖縄」という、
            // 都道府県コード（JIS X 0401）の並び順を前提にした
            // 1〜47のランダムな整数にしている。実際の管理画面の
            // 選択肢（config等）の並びと数値が一致しているか、
            // 一度確認してください。ズレていると、登録画面で
            // 見たときに違う都道府県が選択された状態に見えてしまう。
            'prefecture' => $faker->numberBetween(1, 47),
            'notice_mail' => NoticeMail::Receive->value,
            'staff_id' => null,
        ];
    }
}
