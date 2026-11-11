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
 * 元のファイルが変わっていなければ、何もせずに終わる。--forceを付けると、変わっていなくても両方を作り直す。
 *
 * 手で流したときに何が行われたか分かるよう、npm run buildの表示をそのまま出し、結果をview:cacheと同じINFO・ERRORの形で出す。
 * JS・CSSがうまく作れたときは、npm run buildの表示で分かるので、結果の行は出さない（ログには書く）。
 * cronでは表示を捨てる（> /dev/null）ので、表示が増えてもメールは届かない。
 */
#[Signature('neobit:rebuild {--force : 元のファイルが変わっていなくても、画面とJS・CSSの両方を作り直す}')]
#[Description('画面とJS・CSSの元のファイルが変わっていれば、作り直す')]
class Rebuild extends Command
{
    public function handle(): int
    {
        $rebuilder = new AssetRebuilder(base_path(), public_path());

        // 前の回が動いている間は、何もせずに終わる（ビルドが1分を超えたとき）。次の分に見直す
        if (! $rebuilder->lock()) {
            $this->components->info('前の回が動いているので、何もせずに終わります。');

            return self::SUCCESS;
        }

        try {
            // npm run buildの表示は、流れているそのままに出す
            $done = $rebuilder->run(
                force: (bool) $this->option('force'),
                buildOutput: fn (string $output) => $this->output->write($output),
            );
        } finally {
            $rebuilder->unlock();
        }

        if ($done === []) {
            $this->components->info('画面も JS・CSS も、元のファイルが変わっていないので、作り直していません。');

            return self::SUCCESS;
        }

        foreach ($done as $result) {
            // うまく作れたJS・CSSは、npm run buildの表示で分かるので出さない
            if ($result['kind'] === 'assets' && $result['ok']) {
                continue;
            }

            if ($result['ok']) {
                $this->components->info($result['message']);
            } else {
                $this->components->error($result['message']);
            }
        }

        return self::SUCCESS;
    }
}
