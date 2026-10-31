<?php

use App\Http\Controllers\Company\AuthPasswordController as CompanyPasswordController;
use App\Http\Controllers\Company\AuthSessionController as CompanySessionController;
use App\Http\Controllers\Company\FirstLoginSetupController as CompanyFirstLoginSetupController;
use App\Http\Controllers\Company\InvitationController as CompanyInvitationController;
use App\Http\Controllers\Company\MypageController as CompanyMypageController;
use App\Http\Controllers\Company\PasswordResetController as CompanyPasswordResetController;
use App\Http\Controllers\Company\ProfileController as CompanyProfileController;
use App\Http\Controllers\Company\RegistrationController as CompanyRegistrationController;
use App\Http\Controllers\Company\UserController as CompanyUserController;
use Illuminate\Support\Facades\Route;

/**
 * 企業会員の画面のルート。個人会員の"web"ガードとは別の"company"ガードで保護する。
 * ログインするのは、企業に属する担当者（App\Models\CompanyUser）。URLの頭の/companyと名前の頭のcompany.は、
 * bootstrap/app.phpが付ける（共通部品が、この名前の決まりからルートを作る）。
 */
Route::middleware('guest:company')->group(function () {
    // 企業会員の登録。企業と最初の担当者を「申請中」で作る。運営が承認するまでは、ログインできない
    Route::get('/regist', [CompanyRegistrationController::class, 'create'])->name('regist.create');
    Route::post('/regist/confirm', [CompanyRegistrationController::class, 'confirm'])->name('regist.confirm');
    Route::post('/regist/back', [CompanyRegistrationController::class, 'back'])->name('regist.back');
    // 確認画面から先：確認コードをメールで送り、コードの入力が済んだら企業と担当者を作る
    // （コード照合のthrottle制御はコントローラー側で行う）
    Route::post('/regist/send', [CompanyRegistrationController::class, 'send'])
        ->middleware('throttle:3,1,company-regist-send')
        ->name('regist.send');
    Route::get('/regist/verify', [CompanyRegistrationController::class, 'verifyForm'])->name('regist.verify');
    Route::post('/regist/verify', [CompanyRegistrationController::class, 'verify'])->name('regist.verify.confirm');
    Route::post('/regist/verify/resend', [CompanyRegistrationController::class, 'resend'])
        ->middleware('throttle:3,1,company-regist-verify-resend')
        ->name('regist.verify.resend');
    Route::post('/regist/verify/back', [CompanyRegistrationController::class, 'backFromVerify'])->name('regist.verify.back');
    // 申請の完了（承認待ちの案内）
    Route::get('/regist/thanks', [CompanyRegistrationController::class, 'thanks'])->name('regist.thanks');

    // 招待された人の、担当者としての登録。招待のメールのリンクから開く
    // （Neobit\Core\Team\TeamInvitationManager）。リンクの値を総当たりで探られないよう、回数を制限する。
    // ログイン中の人が開いてもマイページへ移さないよう、guest:companyは外す。同じパソコンに
    // 前の人のログインが残っていたときは、コントローラーがそのログインを終わらせる
    Route::middleware('throttle:20,1,company-invitation')->withoutMiddleware('guest:company')->group(function () {
        Route::get('/invitation/{token}', [CompanyInvitationController::class, 'show'])->name('invitation.show');
        Route::post('/invitation/{token}', [CompanyInvitationController::class, 'store'])->name('invitation.store');
    });

    // ログイン（企業ID・担当者ID・パスワード。試行のthrottle制御はコントローラー側で行う）
    Route::get('/login', [CompanySessionController::class, 'create'])->name('login');
    Route::post('/login', [CompanySessionController::class, 'store'])->name('login.store');

    // パスキーでのログイン（Neobit\Core\Auth\PasskeyLogin）。使わない場合はこの2つを消す
    Route::get('/login/passkey/options', [CompanySessionController::class, 'passkeyLoginOptions'])
        ->middleware('throttle:20,1,company-passkey-options')
        ->name('login.passkey.options');
    Route::post('/login/passkey', [CompanySessionController::class, 'passkeyLogin'])
        ->middleware('throttle:10,1,company-passkey-login')
        ->name('login.passkey');

    // パスワード忘れ。企業ID・担当者ID・メールアドレスが合わなくても、送信完了メッセージはわざと同じにする
    Route::get('/password/forgot', [CompanyPasswordResetController::class, 'create'])->name('password.forgot');
    Route::post('/password/forgot', [CompanyPasswordResetController::class, 'sendCode'])
        ->middleware('throttle:5,1,company-password-forgot')
        ->name('password.forgot.send');
    Route::get('/password/reset', [CompanyPasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/password/reset', [CompanyPasswordResetController::class, 'update'])
        ->middleware('throttle:5,1,company-password-reset')
        ->name('password.reset.update');
});

// ログインの2段階目（入口はNeobit\Core\Auth\TwoFactorLoginトレイト。方式はCompanyUser::TWO_FACTOR_METHOD）。
// パスワード通過後で2段階目の前という中間の状態なので、ミドルウェアでは守らず、セッションで判定する
Route::get('/login/verify', [CompanySessionController::class, 'showVerification'])->name('login.verify');
Route::post('/login/verify', [CompanySessionController::class, 'verifyCode'])->name('login.verify.confirm');
Route::post('/login/verify/resend', [CompanySessionController::class, 'resendCode'])
    ->middleware('throttle:3,1,company-login-verify-resend')
    ->name('login.verify.resend');

// 既存のシステムから移した企業の、初回のログインでの登録（FirstLoginSetupController）。
// メールアドレスが空の担当者を、2段階目の代わりにここへ回す。これも1段階目の後の中間の状態なので、
// セッションで判定する。照合と確認コードのthrottle制御は、コントローラー側で行う。
// 新しく始めるサイトでは使われないので、この6つを消してよい
Route::get('/login/setup', [CompanyFirstLoginSetupController::class, 'create'])->name('login.setup');
Route::post('/login/setup', [CompanyFirstLoginSetupController::class, 'send'])
    ->middleware('throttle:10,1,company-login-setup')
    ->name('login.setup.send');
Route::get('/login/setup/verify', [CompanyFirstLoginSetupController::class, 'verifyForm'])->name('login.setup.verify');
Route::post('/login/setup/verify', [CompanyFirstLoginSetupController::class, 'verify'])->name('login.setup.verify.confirm');
Route::post('/login/setup/verify/resend', [CompanyFirstLoginSetupController::class, 'resend'])
    ->middleware('throttle:3,1,company-login-setup-resend')
    ->name('login.setup.verify.resend');
Route::post('/login/setup/verify/back', [CompanyFirstLoginSetupController::class, 'backFromVerify'])->name('login.setup.verify.back');

// ログイン中の担当者だけが使う画面。company.approvedは、承認済みの企業かを毎回確かめる
// （App\Http\Middleware\EnsureCompanyIsApproved）。auth.sessionは個人会員の側と同じ
Route::middleware(['auth:company', 'auth.session', 'company.approved'])->group(function () {
    // マイページ
    Route::get('/mypage', [CompanyMypageController::class, 'index'])->name('mypage');
    // 企業の情報の変更。どの担当者も変えられる
    Route::get('/mypage/edit', [CompanyMypageController::class, 'edit'])->name('mypage.edit');
    Route::patch('/mypage/update', [CompanyMypageController::class, 'update'])->name('mypage.update');
    // 自分の情報（氏名・メールアドレス）の変更
    Route::get('/mypage/profile', [CompanyProfileController::class, 'edit'])->name('mypage.profile');
    Route::patch('/mypage/profile', [CompanyProfileController::class, 'update'])->name('mypage.profile.update');
    // メールアドレスが変わるときの、確認コードの入力（Neobit\Core\Auth\EmailChange）。
    // コード照合のthrottle制御はコントローラー側で行う
    Route::get('/mypage/profile/email/verify', [CompanyProfileController::class, 'emailChangeForm'])->name('mypage.profile.email');
    Route::post('/mypage/profile/email/verify', [CompanyProfileController::class, 'emailChangeConfirm'])->name('mypage.profile.email.confirm');
    Route::post('/mypage/profile/email/verify/resend', [CompanyProfileController::class, 'emailChangeResend'])
        ->middleware('throttle:3,1,company-email-resend')
        ->name('mypage.profile.email.resend');
    Route::post('/mypage/profile/email/verify/back', [CompanyProfileController::class, 'emailChangeBack'])->name('mypage.profile.email.back');

    // 担当者の管理。同じ企業の担当者の一覧・招待・編集・削除。どの担当者も行える。
    // 招待と送り直しはメールを送るので、回数を制限する
    Route::get('/mypage/users', [CompanyUserController::class, 'index'])->name('users.index');
    Route::get('/mypage/users/invite', [CompanyUserController::class, 'inviteForm'])->name('users.invite');
    Route::post('/mypage/users/invite', [CompanyUserController::class, 'invite'])
        ->middleware('throttle:10,1,company-invite')
        ->name('users.invite.send');
    Route::post('/mypage/users/invitations/{invitation}/resend', [CompanyUserController::class, 'resendInvitation'])
        ->middleware('throttle:10,1,company-invite')
        ->name('users.invitations.resend');
    Route::delete('/mypage/users/invitations/{invitation}', [CompanyUserController::class, 'cancelInvitation'])->name('users.invitations.cancel');
    Route::get('/mypage/users/{user}/edit', [CompanyUserController::class, 'edit'])->name('users.edit');
    Route::patch('/mypage/users/{user}', [CompanyUserController::class, 'update'])->name('users.update');
    Route::delete('/mypage/users/{user}', [CompanyUserController::class, 'destroy'])->name('users.destroy');

    // パスワード変更
    Route::get('/mypage/password', [CompanyPasswordController::class, 'edit'])->name('password.edit');
    Route::patch('/mypage/password/update', [CompanyPasswordController::class, 'update'])->name('password.update');
    Route::post('/mypage/password/resend', [CompanyPasswordController::class, 'resend'])
        ->middleware('throttle:3,1,company-password-resend')
        ->name('password.resend');

    // パスキーの一覧・登録・削除（Neobit\Core\Auth\PasskeyManagement）。使わない場合はこの6つを消す
    // （マイページの入口も消える）
    // 本人による、2段階認証の管理（Neobit\Core\Auth\TwoFactorManagement）。バックアップコードの発行と、
    // 認証アプリの登録の解除。入口は、どちらかができるときだけ出る。.code（確認コードの送信）は、
    // メールの方式のときだけ使う
    Route::get('/mypage/two-factor', [CompanyMypageController::class, 'twoFactorIndex'])->name('mypage.twoFactor');
    Route::get('/mypage/two-factor/backup-codes', [CompanyMypageController::class, 'showNewBackupCodes'])->name('twoFactor.backupCodes');
    Route::post('/mypage/two-factor/backup-codes/code', [CompanyMypageController::class, 'sendBackupCodesCode'])
        ->middleware('throttle:3,1,company-backup-codes-code-send')
        ->name('twoFactor.backupCodes.code');
    Route::post('/mypage/two-factor/backup-codes/regenerate', [CompanyMypageController::class, 'regenerateBackupCodes'])
        ->name('twoFactor.regenerateBackupCodes');
    Route::delete('/mypage/two-factor', [CompanyMypageController::class, 'selfReset'])->name('twoFactor.selfReset');

    Route::get('/mypage/passkeys', [CompanyMypageController::class, 'passkeyIndex'])->name('mypage.passkeys');
    Route::post('/mypage/passkeys/code', [CompanyMypageController::class, 'passkeySendCode'])
        ->middleware('throttle:3,1,company-passkey-code-send')
        ->name('mypage.passkeys.code');
    Route::post('/mypage/passkeys/confirm', [CompanyMypageController::class, 'passkeyConfirm'])->name('mypage.passkeys.confirm');
    Route::get('/mypage/passkeys/options', [CompanyMypageController::class, 'passkeyRegistrationOptions'])->name('mypage.passkeys.options');
    Route::post('/mypage/passkeys', [CompanyMypageController::class, 'passkeyStore'])->name('mypage.passkeys.store');
    Route::delete('/mypage/passkeys/{passkey}', [CompanyMypageController::class, 'passkeyDestroy'])->name('mypage.passkeys.destroy');

    // ログアウト
    Route::post('/logout', [CompanySessionController::class, 'destroy'])->name('logout');
});
