<?php

/**
 * 入力フォームの既定の値。アプリのconfig/neobit.phpのformの節に書いたキーが、これに優先する。
 */
return [

    // 必須項目の項目名の横に付けるマークのHTML。管理画面（admin）と訪問者向けの画面（public）で別々に決められる。
    // 呼び出し側はrequired_mark()を使い、どちらを使うかは今の画面から自動で決まる。
    // required-markクラスは消さない。resources/js/global.jsが、このクラスの付いたマークを目印にして、入力欄にrequired属性を付ける。
    // ビューではエスケープせずに出力するので、HTMLとして正しい文字列だけを書く（利用者の入力値などは入れない）。
    // 配列なので、アプリが書くと丸ごと置き換わる
    'required_mark' => [
        'admin' => '<span class="text-danger required-mark">※必須</span>',
        'public' => '<span class="text-danger required-mark">※必須</span>',
    ],

];
