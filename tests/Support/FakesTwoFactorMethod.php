<?php

namespace Tests\Support;

use App\Enums\TwoFactorMethod;
use App\Models\CompanyUser;
use App\Models\Member;
use App\Models\Staff;

/**
 * テストの間だけ、ログインの2段階目の方式と、バックアップコードを使うかを差し替える。どちらもモデルの定数で決まり、
 * 実行中には変えられないので、モデルの側に用意した差し替えの口を使う（App\Support\HasTwoFactor）。
 *
 * 差し替えはモデルのクラスに残るので、テストが終わったら必ず元に戻す。戻し忘れると、
 * 後に続く別のテストが、差し替えた方式のまま動いてしまう。
 */
trait FakesTwoFactorMethod
{
    /** 差し替えの口を持つモデル */
    private const TWO_FACTOR_MODELS = [Member::class, CompanyUser::class, Staff::class];

    /** そのモデルの方式を、このテストの間だけ差し替える */
    protected function useTwoFactorMethod(string $model, TwoFactorMethod $method): void
    {
        $model::fakeTwoFactorMethod($method);
    }

    /** そのモデルがバックアップコードを使うかを、このテストの間だけ差し替える */
    protected function useBackupCodes(string $model, bool $use): void
    {
        $model::fakeBackupCodes($use);
    }

    /** テストの後始末。PHPUnitが、テストごとに呼ぶ */
    protected function tearDown(): void
    {
        foreach (self::TWO_FACTOR_MODELS as $model) {
            $model::fakeTwoFactorMethod(null);
            $model::fakeBackupCodes(null);
        }

        parent::tearDown();
    }
}
