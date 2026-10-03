<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理者だけが使える画面を守るミドルウェア。スタッフの一覧や登録などに使う。
 *
 * auth:adminの後ろに置くので、ログインは済んでいる前提で動く。管理者でなければ、
 * 403で止めるのではなく、自分の編集画面へ案内する。本人か管理者かで変わる画面は、
 * StaffPolicyで判断する。
 */
class EnsureStaffIsManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = Auth::guard('admin')->user();

        // 管理者でなければ、自分の編集フォームへ回す
        if (! $staff->isManager()) {
            return redirect()->route('admin.staff.edit', $staff);
        }

        return $next($request);
    }
}
