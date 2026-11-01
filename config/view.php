<?php

/**
 * コンパイル済みの画面の置き場所。PHPとしてそのまま動くので、Webの利用者が読むだけのbootstrap/cacheの下に置く。
 * storageの下はWebの利用者が書けるので、置くと侵入されたときに書き換えられ、cronの利用者にも動かされる。
 * デプロイの手順のview:cacheで全部を作る（利用ガイドの0章）。
 */
return [
    'compiled' => base_path('bootstrap/cache/views'),
];
