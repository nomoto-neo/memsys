<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * スタッフ一覧・登録・削除など「管理者(acl=1)専用」の操作を守るミドルウェア。
 *
 * auth:adminミドルウェアより後ろに置く前提（ログイン自体はそちらが
 * 保証してくれているので、ここではAuth::guard('admin')->user()が
 * 必ず取れるものとして扱っている）。
 *
 * 管理者でなければ、一覧などの管理者専用画面には進ませず、
 * 自分自身の編集フォームへリダイレクトする（403で弾くのではなく、
 * 「あなたが見るべき画面はこちらです」という形にしている）。
 * 一方、show()/edit()/update()のような「本人か管理者か」で判定が
 * 変わる画面は、このミドルウェアではなくStaffController側で
 * 個別にチェックしている（ルート単位で機械的に判定できる話ではないため）。
 */
class EnsureStaffIsManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = Auth::guard('admin')->user();

        if (! $staff->isManager()) {
            return redirect()->route('admin.staff.edit', $staff);
        }

        return $next($request);
    }
}
