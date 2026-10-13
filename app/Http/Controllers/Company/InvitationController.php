<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Neobit\Core\Auth\LoginSession;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\TeamInvitation;
use Neobit\Core\Operations\OperationRecorder;
use Neobit\Core\Team\TeamInvitationManager;

/**
 * 招待された人の、担当者としての登録。招待のメールのリンク（/company/invitation/{token}）から開く。
 *
 * 本人が、担当者ID・氏名・パスワードを決める。メールアドレスは、招待の宛先のものになる。
 * 同じパソコンに、ほかの担当者のログインが残っていたときは、何も知らせずに、そのログインを
 * 終わらせてから登録の画面を出す。招待された本人が、新しい担当者として登録できるようにするため。
 * 終わらせるのは、使える招待のリンクのときだけ。期限切れや、でたらめな値のリンクでは終わらせない。
 * リンクは宛先のメールアドレスにしか届かないので、確認コードは求めず、登録が済んだら
 * そのままログインさせる。招待は1回使ったら消す。
 * 招待の発行と照合は、Neobit\Core\Team\TeamInvitationManagerが行う。
 */
class InvitationController extends Controller
{
    /**
     * 登録フォームの検証ルール。担当者IDは、その企業の中で重ならないこと。
     * password_confirmationは、confirmedルールでpasswordと照合するので、ここには書かない。
     */
    private function rules(TeamInvitation $invitation): array
    {
        return [
            'login_id' => [
                'required', 'string', 'max:50', 'regex:'.CompanyUser::LOGIN_ID_PATTERN,
                Rule::unique(CompanyUser::class, 'login_id')->where('company_id', $invitation->team_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /** 登録フォームの表示（GET /company/invitation/{token}） */
    public function show(Request $request, string $token): View|Response
    {
        $invitation = $this->usableInvitation($token);

        if ($invitation === null) {
            return $this->invalid();
        }

        $this->logoutIfLoggedIn($request);

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

        $this->logoutIfLoggedIn($request);

        $validated = $request->validate($this->rules($invitation));

        // 担当者を作り、招待を消す。操作ログは、作った担当者を操作した人にして残す。
        // 検証の後で同じ担当者IDが使われたときは、一意制約の違反になるので、入力し直してもらう
        try {
            $user = DB::transaction(function () use ($invitation, $validated) {
                $user = CompanyUser::create([
                    'company_id' => $invitation->team_id,
                    'login_id' => $validated['login_id'],
                    'name' => $validated['name'],
                    'email' => $invitation->email,
                    'password' => Hash::make($validated['password']),
                ]);

                $invitation->delete();

                // 補足に招待の番号を残す。誰が送った招待から登録したかを、操作ログでたどれるようにする
                OperationRecorder::record(OperationLogAction::Create, $user, detail: TeamInvitationManager::logDetail($invitation), operator: $user);

                return $user;
            });
        } catch (UniqueConstraintViolationException $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['login_id' => 'この担当者IDは、すでに使われています。']);
        }

        // そのままログインさせる（登録の直後に、もう一度ログインし直させない）
        Auth::guard(CompanyUser::loginGuard())->login($user);
        $request->session()->regenerate();

        return redirect()->route(CompanyUser::loginRoute('mypage'))->with('status', '担当者の登録が完了しました。');
    }

    /**
     * リンクの値から、使える招待を探す。期限内で、企業への招待で、企業が承認済みのものだけ。
     * 止めた企業や申請中の企業には、担当者を足させない。
     * 招待を送った後に、その企業に同じメールアドレスの担当者ができていれば、使えない。
     * 同じ企業の中で、メールアドレスを重ねないため
     */
    private function usableInvitation(string $token): ?TeamInvitation
    {
        $invitation = TeamInvitationManager::find($token);
        $company = $invitation?->team;

        if (! $company instanceof Company || ! $company->isApproved()) {
            return null;
        }

        return $company->users()->where('email', $invitation->email)->exists() ? null : $invitation;
    }

    /**
     * 企業会員としてログイン中なら、そのログインを終わらせる。同じブラウザの、管理画面や
     * 個人会員のログインは残す（Neobit\Core\Auth\LoginSession）。
     */
    private function logoutIfLoggedIn(Request $request): void
    {
        if (Auth::guard(CompanyUser::loginGuard())->check()) {
            LoginSession::logout($request, CompanyUser::loginGuard());
        }
    }

    /** 招待が使えないときの画面。期限切れ・取り消し・登録済みのどれかは伝えない */
    private function invalid(): Response
    {
        return response()->view('company.auth.invitation-invalid', status: 404);
    }
}
