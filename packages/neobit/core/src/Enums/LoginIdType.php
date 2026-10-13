<?php

namespace Neobit\Core\Enums;

/**
 * 会員がログインに使う値の種類。モデルの定数 LOGIN_ID_TYPE に書いて、案件ごとに選ぶ。
 * ログイン画面で入力された値から、どの列で会員を探すかが決まる
 * （Neobit\Core\Auth\IsLoginAccount::loginIdCondition()）。
 *
 * メールアドレスもログインIDも、重ならない値にしておく。個人会員は個人会員の全体で、
 * 企業の担当者は同じ企業の中で重ならない。どちらの列で探しても、1人に決まる。
 */
enum LoginIdType
{
    /** メールアドレス。emailの列で探す */
    case Email;

    /** ログインID。login_idの列で探す */
    case LoginId;

    /**
     * ログインIDでも、メールアドレスでもよい。入力に「@」があればemail、無ければlogin_idの列で探す。
     * ログインIDには、「@」を使わせないこと。使わせると、どちらの値かを見分けられない
     */
    case Either;
}
