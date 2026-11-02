<?php

use Neobit\Core\Security\Gatekeeper;

/**
 * 入口の見張り。IPブロックとアクセス制限を、Laravelを起動する前に判断する（Neobit\Core\Security\Gatekeeper）。
 * 止めているIPアドレスの要求を、Laravelを起動せずに返して軽くするためと、Laravelを通らないPHP（WordPressなど）にも
 * 同じ守りを掛けるため。判断に使う状態は、Laravelが書き出したJSON（storage/app/gatekeeper/state.json）を読む。
 *
 * ■ 掛け方
 * Laravelの入口のpublic/index.phpの頭でrequireする。Laravelを通らないPHPには、PHPのauto_prepend_fileで掛ける。
 * 状態のファイルの場所は、requireの前に定数NEOBIT_GATEKEEPER_STATEで渡す。渡さなければ、このファイルから3つ上の
 * ディレクトリをプロジェクトのルートとみなす（vendor/neobit/core/gatekeeper.php の形）。
 * 両方で掛かっても、2回目は何もしない。
 *
 * ■ 状態を読めないとき
 * 何もせずに通す。後ろのLaravelのミドルウェアが、同じ判断をするため。
 */
if (defined('NEOBIT_GATEKEEPER_DONE')) {
    return;
}
define('NEOBIT_GATEKEEPER_DONE', true);

// LaravelもComposerも読み込まないので、判断のクラスだけを読む。送り忘れなどで無ければ、何もせずに通す
if (! class_exists(Gatekeeper::class, false)) {
    if (! is_file(__DIR__.'/src/Security/Gatekeeper.php')) {
        return;
    }
    require __DIR__.'/src/Security/Gatekeeper.php';
}

Gatekeeper::guard(defined('NEOBIT_GATEKEEPER_STATE') ? NEOBIT_GATEKEEPER_STATE : dirname(__DIR__, 3).'/storage/app/gatekeeper/state.json');
