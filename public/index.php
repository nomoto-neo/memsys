<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Neobit\Core\Http\PublicFiles;

define('LARAVEL_START', microtime(true));

// IPブロックとアクセス制限を、Laravelを起動する前に判断する（Neobit\Core\Security\Gate）。
// 止めるときは、ここで応答を返して終わる。見張りのファイルが無ければ、後ろのLaravelが判断する
define('NEOBIT_GATE_STATE', __DIR__.'/../storage/app/gate/state.json');
if (is_file($gate = __DIR__.'/../vendor/neobit/core/gate.php')) {
    require $gate;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

// 公開ディレクトリの古い.phpに当たったときは、グローバルの場所で動かす（Neobit\Core\Http\PublicFiles）
$script = PublicFiles::handle($app, Request::capture());
if ($script !== null) {
    require $script;
}
