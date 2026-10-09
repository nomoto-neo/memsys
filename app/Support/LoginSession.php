<?php

namespace App\Support;

use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ログアウト。そのガードのログインだけを終わらせ、同じブラウザのほかのガードのログインは残す。
 *
 * 個人会員・企業の担当者・スタッフは、ガードは別でも、同じブラウザでは1つのセッションを使う。
 * セッションを丸ごと捨てると、会員としてログアウトしただけで、管理画面のログインも切れてしまう。
 * 運営のスタッフが、会員の側の画面を確かめながら管理画面で操作する、といった使い方のために、
 * ほかのガードのログインは残す。
 *
 * ■ やり方
 * ほかのガードのログインの値を控えてから、セッションを丸ごと捨て、控えた値だけを戻す。
 * ログアウトした人が残したもの（検索条件、確認コードの仮置きなど）を、何があるかを数え上げずに
 * 全部消すためである。そのため、残したほかのガードの側でも、検索条件などは消える。
 * セッションのidとCSRFトークンは、作り直す。
 */
final class LoginSession
{
    /** そのガードからログアウトする。$guardはconfig/auth.phpのガードの名前 */
    public static function logout(Request $request, string $guard): void
    {
        // ほかのガードのログインの値を控える
        $kept = [];
        foreach (array_keys(config('auth.guards')) as $name) {
            $other = Auth::guard($name);

            if ($name === $guard || ! $other instanceof SessionGuard) {
                continue;
            }

            // ログイン中の人のidと、auth.session（パスワードが変わったらログアウトさせる仕組み）が
            // 控えているパスワードのハッシュ値
            foreach ([$other->getName(), 'password_hash_'.$name] as $key) {
                if ($request->session()->has($key)) {
                    $kept[$key] = $request->session()->get($key);
                }
            }
        }

        Auth::guard($guard)->logout();

        // セッションを捨てて、ほかのガードのログインだけを戻す
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put($kept);
    }
}
