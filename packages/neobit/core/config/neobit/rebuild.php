<?php

/**
 * 画面とJS・CSSの作り直し（neobit:rebuild。Neobit\Core\Operations\AssetRebuilder）の既定の値。
 * アプリのconfig/neobit.phpのrebuildの節に書いたキーが、これに優先する。
 * コードを更新する利用者（devapp）のcrontabから毎分動かす。使い方は利用ガイドの vendor/neobit/core/docs/guide/setup.md の「画面とJS・CSSの作り直し」。
 */
return [

    // npmのコマンド。cronの中でnpmの場所が見つからないサーバーは、.envのREBUILD_NPMに絶対のパスを書く
    'npm' => env('REBUILD_NPM', 'npm'),

];
