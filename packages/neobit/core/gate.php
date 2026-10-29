<?php

use Neobit\Core\Security\Gate;

/**
 * 入口の見張り。IPブロックとアクセス制限を、Laravelを起動する前に判断する（Neobit\Core\Security\Gate）。
 * 止めているIPアドレスの要求を、Laravelを起動せずに返して軽くするためと、Laravelを通らないPHP（WordPressなど）にも
 * 同じ守りを掛けるため。判断に使う状態は、Laravelが書き出したJSON（storage/app/gate/state.json）を読む。
 *
 * ■ 掛け方
 * Laravelの入口のpublic/index.phpの頭でrequireする。Laravelを通らないPHPには、PHPのauto_prepend_fileで掛ける。
 * 状態のファイルの場所は、requireの前に定数NEOBIT_GATE_STATEで渡す。渡さなければ、このファイルから3つ上の
 * ディレクトリをプロジェクトのルートとみなす（vendor/neobit/core/gate.php の形）。
 * 両方で掛かっても、2回目は何もしない。
 *
 * ■ 状態を読めないとき
 * 何もせずに通す。後ろのLaravelのミドルウェアが、同じ判断をするため。
 */
if (defined('NEOBIT_GATE_DONE')) {
    return;
}
define('NEOBIT_GATE_DONE', true);

// LaravelもComposerも読み込まないので、判断のクラスだけを読む。送り忘れなどで無ければ、何もせずに通す
if (! class_exists(Gate::class, false)) {
    if (! is_file(__DIR__.'/src/Security/Gate.php')) {
        return;
    }
    require __DIR__.'/src/Security/Gate.php';
}

Gate::guard(defined('NEOBIT_GATE_STATE') ? NEOBIT_GATE_STATE : dirname(__DIR__, 3).'/storage/app/gate/state.json');
