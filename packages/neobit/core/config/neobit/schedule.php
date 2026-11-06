<?php

/**
 * サーバーのcronの既定の値。アプリのconfig/neobit.phpのscheduleの節に書いたキーが、これに優先する。
 */
return [

    // サーバーでcron（毎分のphp artisan schedule:run）が動くならtrue。動かないサーバーだけ、.envにCRON_ENABLED=falseと書く。
    // 動かないサーバーでは、cronの代わりに、サイトへのアクセスを契機にroutes/console.phpの予定を動かす（Neobit\Core\Operations\RequestScheduler）。
    // 予定の時刻を過ぎて最初のアクセスで、応答を返した後に動く。キューのワーカーも予定の1つなので、一斉メールなどもアクセスを契機に送られる。
    // 改ざん監視（neobit:file-watch）はスケジューラーの外のcronで動かすので、falseのサーバーでは動かない
    'cron_enabled' => env('CRON_ENABLED', true),

];
