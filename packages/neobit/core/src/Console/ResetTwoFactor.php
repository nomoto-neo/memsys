<?php

namespace Neobit\Core\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Neobit\Core\Auth\LoginAccount;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Operations\OperationRecorder;

/**
 * 2段階認証の登録を解除するコマンド。
 * php artisan app:reset-two-factor staff yamada で動く。
 *
 * 2段階目を通れなくなった人を、画面から解除できる人がいないときに使う。スタッフが1人だけのサイトで、
 * その人が認証アプリの入った端末を無くしたときなど。ふだんは、管理画面の詳細から解除する。
 *
 * ■ 使い方
 * - 種類は、モデルの種類の名前（LoginAccount::loginType()）。例：staff・member・company_user
 * - ログインに使う値は、その人の loginId() の値。認証アプリやパスキーの表示名と同じもの
 * - 対象の人を出して、確かめてから解除する。--forceを付けると確かめずに解除する
 *
 * ■ 解除されるもの
 * 管理画面からの解除と同じ（Neobit\Core\Auth\HasTwoFactor::resetTwoFactor()）。認証アプリの登録・
 * バックアップコード・信頼済みの端末・パスキー。認証アプリの方式の人は、次のログインで
 * QRコードから登録し直す。操作ログにも残る。
 */
#[Signature('app:reset-two-factor {type : 種類の名前。例：staff・member・company_user} {login : ログインに使う値} {--force : 確かめずに解除する}')]
#[Description('2段階認証の登録を解除する')]
class ResetTwoFactor extends Command
{
    public function handle(): int
    {
        $type = (string) $this->argument('type');
        $login = (string) $this->argument('login');

        $model = $this->loginModels()[$type] ?? null;

        if ($model === null) {
            $this->error("種類「{$type}」はありません。使える種類：".implode('・', array_keys($this->loginModels())));

            return self::FAILURE;
        }

        // ログインに使う値は、種類によって複数の列から作るので、1人ずつ loginId() と比べて探す。
        // 削除済みの人は対象にしない
        $accounts = $model::query()->cursor()
            ->filter(fn (LoginAccount $account) => $account->loginId() === $login)
            ->values();

        if ($accounts->isEmpty()) {
            $this->error("ログインに使う値が「{$login}」の人は、見つかりません。");

            return self::FAILURE;
        }

        // 同じ値の人が複数いるときは、どの人か決められないので、一覧を出して止める
        if ($accounts->count() > 1) {
            $this->table(['id', '名前'], $accounts->map(fn (LoginAccount $account) => [$account->getKey(), $account->displayName()])->all());
            $this->error("ログインに使う値が「{$login}」の人が、複数います。");

            return self::FAILURE;
        }

        $account = $accounts->first();
        $this->table(['id', '名前', 'ログインに使う値'], [[$account->getKey(), $account->displayName(), $account->loginId()]]);

        // 戻せない操作なので、確かめる。--no-interactionで動かすときは、--forceが要る
        if (! $this->option('force') && ! $this->confirm('この人の2段階認証の登録を解除します。よろしいですか？')) {
            $this->info('取りやめました。');

            return self::SUCCESS;
        }

        // 解除と操作ログは、どちらかだけが残らないようにまとめて行う。操作した人は記録に残らない
        DB::transaction(function () use ($account) {
            $account->resetTwoFactor();

            OperationRecorder::record(OperationLogAction::TwoFactorReset, $account, detail: ['実行' => 'コマンド']);
        });

        $this->info('2段階認証の登録を解除しました。');

        return self::SUCCESS;
    }

    /**
     * ログインするモデルを、種類の名前から引けるようにしたもの。config/auth.phpのprovidersに
     * あるモデルのうち、LoginAccountを実装しているもの。
     *
     * @return array<string, class-string<LoginAccount>>
     */
    private function loginModels(): array
    {
        $models = [];

        foreach (config('auth.providers') as $provider) {
            $model = $provider['model'] ?? null;

            if (is_string($model) && is_subclass_of($model, LoginAccount::class)) {
                $models[$model::loginType()] = $model;
            }
        }

        return $models;
    }
}
