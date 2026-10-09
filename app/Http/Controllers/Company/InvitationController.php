<?php

namespace App\Http\Controllers\Company;

use App\Enums\OperationLogAction;
use App\Http\Controllers\Controller;
use App\Models\CompanyInvitation;
use App\Models\CompanyUser;
use App\Support\CompanyInvitationManager;
use App\Support\OperationRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 招待された人の、担当者としての登録。招待のメールのリンク（/company/invitation/{token}）から開く。
 *
 * 本人が、担当者ID・氏名・パスワードを決める。メールアドレスは、招待の宛先のものになる。
 * リンクは宛先のメールアドレスにしか届かないので、確認コードは求めず、登録が済んだら
 * そのままログインさせる。招待は1回使ったら消す。
 * 招待の発行と照合は、App\Support\CompanyInvitationManagerが行う。
 */
class InvitationController extends Controller
{
    /**
     * 登録フォームの検証ルール。担当者IDは、その企業の中で重ならないこと。
     * password_confirmationは、confirmedルールでpasswordと照合するので、ここには書かない。
     */
    private function rules(CompanyInvitation $invitation): array
    {
        return [
            'login_id' => [
                'required', 'string', 'max:50', 'regex:'.CompanyUser::LOGIN_ID_PATTERN,
                Rule::unique(CompanyUser::class, 'login_id')->where('company_id', $invitation->company_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /** 登録フォームの表示（GET /company/invitation/{token}） */
    public function show(string $token): View|Response
    {
        $invitation = $this->usableInvitation($token);

        if ($invitation === null) {
            return $this->invalid();
        }

        return view('company.auth.invitation', [
            'token' => $token,
            'invitation' => $invitation,
            // パスワードは再表示しない
            'input' => old(),
            // password_confirmationはrules()に無いので、必須マークだけ足す
            'required' => required_fields($this->rules($invitation), ['password_confirmation']),
        ]);
    }

    /** 担当者の登録（POST /company/invitation/{token}） */
    public function store(Request $request, string $token): RedirectResponse|Response
    {
        $invitation = $this->usableInvitation($token);

        if ($invitation === null) {
            return $this->invalid();
        }

        $validated = $request->validate($this->rules($invitation));

        // 担当者を作り、招待を消す。操作ログは、作った担当者を操作した人にして残す。
        // 検証の後で同じ担当者IDが使われたときは、一意制約の違反になるので、入力し直してもらう
        try {
            $user = DB::transaction(function () use ($invitation, $validated) {
                $user = CompanyUser::create([
                    'company_id' => $invitation->company_id,
                    'login_id' => $validated['login_id'],
                    'name' => $validated['name'],
                    'email' => $invitation->email,
                    'password' => Hash::make($validated['password']),
                ]);

                $invitation->delete();

                OperationRecorder::record(OperationLogAction::Create, $user, operator: $user);

                return $user;
            });
        } catch (UniqueConstraintViolationException $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['login_id' => 'この担当者IDは、すでに使われています。']);
        }

        // そのままログインさせる（登録の直後に、もう一度ログインし直させない）
        Auth::guard(CompanyUser::memberGuard())->login($user);
        $request->session()->regenerate();

        return redirect()->route(CompanyUser::memberRoute('mypage'))->with('status', '担当者の登録が完了しました。');
    }

    /**
     * リンクの値から、使える招待を探す。期限内で、企業が承認済みのものだけ。
     * 止めた企業や申請中の企業には、担当者を足させない
     */
    private function usableInvitation(string $token): ?CompanyInvitation
    {
        $invitation = CompanyInvitationManager::find($token);

        return $invitation?->company?->isApproved() ? $invitation : null;
    }

    /** 招待が使えないときの画面。期限切れ・取り消し・登録済みのどれかは伝えない */
    private function invalid(): Response
    {
        return response()->view('company.auth.invitation-invalid', status: 404);
    }
}
