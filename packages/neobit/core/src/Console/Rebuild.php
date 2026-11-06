<?php

namespace Neobit\Core\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Neobit\Core\Operations\AssetRebuilder;

/**
 * 画面（Blade）とJS・CSSの作り直しのコマンド。php artisan neobit:rebuildで動く。判断と作り直しは
 * Neobit\Core\Operations\AssetRebuilderにある。
 *
 * Laravelのスケジューラー（apacheのcron）には入れず、コードを更新する利用者（devapp）のcrontabから毎分動かす。
 * コンパイル済みの画面とJS・CSSの出し先を、Webの利用者に書かせないため。
 * 元のファイルが変わっていなければ、何もせずに終わる。
 */
#[Signature('neobit:rebuild')]
#[Description('画面とJS・CSSの元のファイルが変わっていれば、作り直す')]
class Rebuild extends Command
{
    public function handle(): int
    {
        $rebuilder = new AssetRebuilder(base_path(), public_path());

        // 前の回が動いている間は、何もせずに終わる（ビルドが1分を超えたとき）。次の分に見直す
        if (! $rebuilder->lock()) {
            $this->info('前の回が動いているので、何もせずに終わります。');

            return self::SUCCESS;
        }

        try {
            foreach ($rebuilder->run() as $message) {
                $this->line($message);
            }
        } finally {
            $rebuilder->unlock();
        }

        return self::SUCCESS;
    }
}
