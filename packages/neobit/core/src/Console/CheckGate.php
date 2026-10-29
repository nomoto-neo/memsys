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
 * WordPressを置く案件（設定のneobit.wordpressがある）では、WordPressの見張りが掛かっているかも出し、
 * 掛かっていなければ失敗で終わる。
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

        // WordPressの見張り。掛かっていなければ、WordPressが守られていないので失敗にする
        $wordpressError = $this->checkWordPress();
        if ($wordpressError !== null) {
            $this->error('WordPress の保護機能：'.$wordpressError);

            return self::FAILURE;
        }

        // 写しが古いと、見張りが解除したIPアドレスを止め続けるので、知らせる
        if (! GateState::isCurrent()) {
            $this->error('状態のファイルが、今の状態と食い違っています。php artisan app:gate --export で書き出し直してください。');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * WordPressの見張りが掛かっているかを確かめ、掛かっていれば出す。掛かっていなければ、その理由を返す。
     * WordPressを置かない案件では、何も出さずにnullを返す。
     */
    private function checkWordPress(): ?string
    {
        $config = config('neobit.wordpress');
        if (! is_array($config)) {
            return null;
        }

        $gate = (string) ($config['gate'] ?? '');
        if ($gate === GateState::WORDPRESS_PREPEND) {
            // auto_prepend_fileは、コマンドからは分からないので、Webの要求で見た控えを出す
            $seen = GateState::prependSeen();
            if ($seen === null) {
                // 送った直後は、まだWebの要求が無い。デプロイの手順を失敗に見せないよう、警告だけにする
                $this->warn('WordPress の保護機能：prepend ですが、まだ Web の要求で確かめていません。Laravel のページ（管理画面など）を1回開いてから、もう一度実行してください。');

                return null;
            }
            if (! $seen['attached']) {
                return 'prepend なのに、Web の要求で auto_prepend_file に gate.php が設定されていません（'.date('Y-m-d H:i', $seen['at']).' に確かめた）。';
            }
            $this->line('WordPress の保護機能：prepend（'.date('Y-m-d H:i', $seen['at']).' までの Web の要求で、掛かっていることを確かめた）');

            return null;
        }

        if ($gate === GateState::WORDPRESS_WP_CONFIG) {
            // wp-config.phpで、見張りをrequireしているか。コメントにした行は数えない
            $name = (string) ($config['wp_config'] ?? 'wp-config.php');
            $lines = @file(base_path($name));
            if ($lines === false) {
                return "{$name} がありません。";
            }
            $required = array_filter($lines, fn (string $line) => ! preg_match('#^\s*(//|\#|\*|/\*)#', $line)
                && preg_match('/\brequire(_once)?\b[^;]*gate\.php/', $line) === 1);
            if ($required === []) {
                return "{$name} に gate.php の require がありません。";
            }
            $this->line("WordPress の保護機能：wp-config（{$name} で読んでいる）");

            return null;
        }

        if ($gate === '') {
            return 'config/neobit.php に wordpress の節があるのに、.env の WORDPRESS_GATE がありません。';
        }

        return "WORDPRESS_GATE の値（{$gate}）が正しくありません。prepend か wp-config を書いてください。";
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
