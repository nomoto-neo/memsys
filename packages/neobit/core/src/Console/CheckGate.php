<?php

namespace Neobit\Core\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Neobit\Core\Security\GateState;

/**
 * 入口の見張りの状態を確かめるコマンド。php artisan app:gate で動く。
 *
 * オプションが無ければ、書き出した状態（storage/app/gate/state.json）を出す。写しが無いときと、今の状態と
 * 食い違っているときは、知らせて失敗で終わる。--export は、状態と見張りが返す画面を書き出し直してから、状態を出す。
 * サーバーで.envを変えてconfig:cacheをやり直した後に流す。
 */
#[Signature('app:gate {--export : 状態のファイルを書き出し直す}')]
#[Description('入口の見張りの状態を出す（--export で書き出し直す）')]
class CheckGate extends Command
{
    public function handle(): int
    {
        // 書き出し直す。見張りが返す画面も作り直す
        if ($this->option('export')) {
            $error = GateState::export(withPages: true);
            if ($error !== null) {
                $this->error("状態のファイルを書き出せませんでした。{$error}");

                return self::FAILURE;
            }
            $this->line('状態のファイルを書き出しました。');
        }

        // 状態のファイルを、プロジェクトのルートからのパスで見せる
        $file = ltrim(str_replace('\\', '/', Str::after(GateState::stateFile(), base_path())), '/');
        $state = GateState::read();
        if ($state === null) {
            $this->error("状態のファイルがありません：{$file}");
            $this->line('php artisan app:gate --export で書き出してください。');

            return self::FAILURE;
        }

        // 止めている一覧のうち、期限の過ぎていないもの
        $now = time();
        $blocks = array_filter((array) $state['blocks'], fn (array $block) => $block[1] === null || $block[1] > $now);

        $this->line("状態のファイル：{$file}（".date('Y-m-d H:i:s', (int) ($state['exported_at'] ?? 0)).' に書き出し）');
        $this->line('止めている IP：'.count($blocks).'件');
        $this->line('サイトの制限：'.$this->restriction((array) $state['site']));
        $this->line('管理画面の制限：'.$this->restriction((array) $state['admin']));

        // 写しが古いと、見張りが解除したIPアドレスを止め続けるので、知らせる
        if (! GateState::isCurrent()) {
            $this->error('状態のファイルが、今の状態と食い違っています。php artisan app:gate --export で書き出し直してください。');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** 制限の範囲の状態を、1行の文にする */
    private function restriction(array $area): string
    {
        if (empty($area['restricted'])) {
            return 'しない';
        }

        return 'する（許す IP：'.count((array) ($area['ips'] ?? [])).'件）';
    }
}
