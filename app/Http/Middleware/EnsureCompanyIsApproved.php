<?php

namespace App\Http\Middleware;

use App\Models\CompanyUser;
use App\Support\LoginSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 企業会員の画面を、承認済みの企業の担当者だけに使わせるミドルウェア。
 *
 * auth:companyの後ろに置くので、ログインは済んでいる前提で動く。企業の状態は、ログインのときにも
 * 確かめるが、ログインした後で運営が企業を止めることがある。画面を開くたびに確かめ、
 * 承認済みでなければ、その場でログアウトさせてログイン画面へ回す。
 */
class EnsureCompanyIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard(CompanyUser::memberGuard())->user();

        if (! $user->company->isApproved()) {
            // 企業会員のログインだけを終わらせる（App\Support\LoginSession）
            LoginSession::logout($request, CompanyUser::memberGuard());

            return redirect()->route(CompanyUser::memberRoute('login'))
                ->with('error', 'この企業会員は、現在ご利用いただけません。');
        }

        return $next($request);
    }
}
