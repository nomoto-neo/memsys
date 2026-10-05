<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 企業会員のマイページ。ログイン中の担当者が、自分の企業の情報と、自分の情報を見る。
 */
class MypageController extends Controller
{
    // マイページの表示（GET /company/mypage）。
    // ログイン中の担当者は、このコントローラーではいつも企業会員のガードから取る。
    public function index(): View
    {
        $user = Auth::guard(CompanyUser::memberGuard())->user();

        return view('company.mypage.index', [
            'user' => $user,
            'company' => $user->company,
        ]);
    }
}
