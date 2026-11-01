<?php

/**
 * フレームワークの画面（CSV取り込み、2段階認証など）が継承するレイアウトの既定の値。
 * アプリのconfig/neobit.phpのlayoutsの節で、アプリのレイアウトに差し替える。
 * レイアウトは、セクション title・content（必須）・content-width と、スタック head-extra・scripts を持つ。
 */
return [

    // 管理画面の画面が継承するレイアウト
    'admin' => 'neobit::layouts.plain',

    // 訪問者・会員の画面が継承するレイアウト
    'app' => 'neobit::layouts.plain',

];
