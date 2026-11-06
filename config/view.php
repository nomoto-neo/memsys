<?php

/**
 * コンパイル済みの画面の置き場所。PHPとしてそのまま動くので、Webの利用者が読むだけのbootstrap/cacheの下に置く。
 * storageの下はWebの利用者が書けるので、置くと侵入されたときに書き換えられ、cronの利用者にも動かされる。
 * デプロイの手順のview:cacheで全部を作る（利用ガイドの vendor/neobit/core/docs/deploy.md）。送った画面は、devappのcronのneobit:rebuildも作り直す。
 */
return [
    'compiled' => base_path('bootstrap/cache/views'),

    // 元の画面のファイルが新しいときに、作り直そうとするか。Webの利用者はコンパイル済みの画面を書けないので、
    // サーバーではfalseにし、neobit:rebuildが作り直すまでは前の画面を出す。手元はtrueで、直したらすぐ出す
    'check_cache_timestamps' => (bool) env('VIEW_CHECK_CACHE_TIMESTAMPS', true),
];
