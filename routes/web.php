<?php

use App\Http\Controllers\AuthPasswordController;
use App\Http\Controllers\AuthRegisteredMemberController;
use App\Http\Controllers\AuthSessionController;
use App\Http\Controllers\Contact2Controller;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\MailUnsubscribeController;
use App\Http\Controllers\MypageController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\TopController;
use Illuminate\Support\Facades\Route;

/**
 * 訪問者と個人会員の画面のルート。企業会員はroutes/company.php、管理画面はroutes/admin.php、
 * 固定ページはroutes/pages.phpにある。読み込みと、ファイルごとの頭のURL・名前・ミドルウェアは、
 * bootstrap/app.phpが受け持つ。
 */

// 静的なページを表示する場合
//Route::view('/', 'welcome');

// TOPページ（最新のお知らせを出す）
Route::get('/', [TopController::class, 'index'])->name('top');

// ニュース：訪問者向け
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::post('/news', [NewsController::class, 'storeSearchCondition'])->name('news.search');
Route::get('/news/{news}', [NewsController::class, 'show'])->name('news.show');

// お問い合わせ
Route::post('/contact/ajax-upload', [ContactController::class, 'uploadAjaxFile'])
    ->middleware('throttle:20,1,contact-upload')
    ->name('contact.ajaxUpload');
Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
// 確認画面へ進むたびにCloudflare Turnstileへ問い合わせる（Neobit\Core\Security\SpamGuard）ので、回数を制限する
Route::post('/contact/confirm', [ContactController::class, 'confirm'])
    ->middleware('throttle:20,1,contact-confirm')
    ->name('contact.confirm');
Route::post('/contact/back', [ContactController::class, 'back'])->name('contact.confirm.back');
Route::post('/contact/store', [ContactController::class, 'store'])
    ->middleware('throttle:5,1,contact-store')
    ->name('contact.store');
Route::get('/contact/thanks', [ContactController::class, 'thanks'])->name('contact.thanks');

// お知らせメールの配信停止（App\Services\MailUnsubscribe）。メールの中のURLから開くので、ログインは要らない。
// 停止の操作は全部を操作ログに残すので、回数を制限する
Route::get('/mail/unsubscribe', [MailUnsubscribeController::class, 'show'])->name('mail.unsubscribe');
Route::post('/mail/unsubscribe', [MailUnsubscribeController::class, 'store'])
    ->middleware('throttle:10,1,mail-unsubscribe')
    ->name('mail.unsubscribe.store');

// メルマガだけの申し込み（App\Services\NewsletterSubscription）。会員登録と同じ確認コードを送るので、
// 送信と再送の回数を制限する。コード照合の回数の制限は、コントローラーの側で行う
Route::get('/newsletter', [NewsletterController::class, 'create'])->name('newsletter.create');
Route::post('/newsletter/send', [NewsletterController::class, 'send'])
    ->middleware('throttle:5,1,newsletter-send')
    ->name('newsletter.send');
Route::get('/newsletter/verify', [NewsletterController::class, 'verifyForm'])->name('newsletter.verify');
Route::post('/newsletter/verify', [NewsletterController::class, 'verify'])->name('newsletter.verify.confirm');
Route::post('/newsletter/verify/resend', [NewsletterController::class, 'resend'])
    ->middleware('throttle:3,1,newsletter-verify-resend')
    ->name('newsletter.verify.resend');
Route::post('/newsletter/verify/back', [NewsletterController::class, 'backFromVerify'])->name('newsletter.verify.back');
Route::get('/newsletter/done', [NewsletterController::class, 'done'])->name('newsletter.done');

// MailTemplate方式・AjaxFileUpload方式を使わない、普通のLaravelの書き方によるお問い合わせフォーム。
// 比較用・技術習得のため残置（提携先向けのデモには含めない）
Route::get('/contact2', [Contact2Controller::class, 'create'])->name('contact2.create');
// 送るたびにスタッフへメールが届くので、回数を制限する。スパムの確認（Neobit\Core\Security\SpamGuard）は
// 付けていないので、公開するサイトでは、この2つのルートを消す
Route::post('/contact2', [Contact2Controller::class, 'store'])
    ->middleware('throttle:5,1,contact2-store')
    ->name('contact2.store');

Route::middleware('guest')->group(function () {
    // 会員登録
    Route::get('/regist', [AuthRegisteredMemberController::class, 'create'])->name('regist.create');
    Route::post('/regist/confirm', [AuthRegisteredMemberController::class, 'confirm'])->name('regist.confirm');
    Route::post('/regist/back', [AuthRegisteredMemberController::class, 'back'])->name('regist.back');
    // 確認画面から先：確認コードをメールで送り、コードの入力が済んだら会員を作る
    // （コード照合のthrottle制御はコントローラー側で行う）
    Route::post('/regist/send', [AuthRegisteredMemberController::class, 'send'])
        ->middleware('throttle:3,1,regist-send')
        ->name('regist.send');
    Route::get('/regist/verify', [AuthRegisteredMemberController::class, 'verifyForm'])->name('regist.verify');
    Route::post('/regist/verify', [AuthRegisteredMemberController::class, 'verify'])->name('regist.verify.confirm');
    Route::post('/regist/verify/resend', [AuthRegisteredMemberController::class, 'resend'])
        ->middleware('throttle:3,1,regist-verify-resend')
        ->name('regist.verify.resend');
    Route::post('/regist/verify/back', [AuthRegisteredMemberController::class, 'backFromVerify'])->name('regist.verify.back');

    // 会員ログイン（ログイン試行のthrottle制御はコントローラー側で行う）
    Route::get('/login', [AuthSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthSessionController::class, 'store'])->name('login.store');

    // パスキーでのログイン（Neobit\Core\Auth\PasskeyLogin）。使わない場合はこの2つを消す
    // （ログイン画面のボタンも消える）
    Route::get('/login/passkey/options', [AuthSessionController::class, 'passkeyLoginOptions'])
        ->middleware('throttle:20,1,member-passkey-options')
        ->name('login.passkey.options');
    Route::post('/login/passkey', [AuthSessionController::class, 'passkeyLogin'])
        ->middleware('throttle:10,1,member-passkey-login')
        ->name('login.passkey');

    // パスワード忘れ、未登録のメールアドレスだったとしても送信完了メッセージはわざと同じにする
    Route::get('/password/forgot', [PasswordResetController::class, 'create'])->name('password.forgot');
    Route::post('/password/forgot', [PasswordResetController::class, 'sendCode'])
        ->middleware('throttle:5,1,password-forgot')
        ->name('password.forgot.send');
    Route::get('/password/reset', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/password/reset', [PasswordResetController::class, 'update'])
        ->middleware('throttle:5,1,password-reset')
        ->name('password.reset.update');
});

// ログインの2段階目（入口はNeobit\Core\Auth\TwoFactorLoginトレイト。方式はMember::TWO_FACTOR_METHOD）。
// パスワード通過後で2段階目の前という中間の状態なので、ミドルウェアでは守らず、セッションで判定する。
// 照合の試行制限はコントローラーの側で行う。再送は、メールの方式のときだけ使う
Route::get('/login/verify', [AuthSessionController::class, 'showVerification'])->name('login.verify');
Route::post('/login/verify', [AuthSessionController::class, 'verifyCode'])->name('login.verify.confirm');
Route::post('/login/verify/resend', [AuthSessionController::class, 'resendCode'])
    ->middleware('throttle:3,1,login-verify-resend')
    ->name('login.verify.resend');

// ログイン中の会員だけが使う画面。
// auth.session（Laravel標準のAuthenticateSession）は、ログインしたときのパスワードの
// ハッシュ値をセッションに控えておき、リクエストのたびに今のパスワードと比べる。
// パスワードが変わっていれば、そのセッションをログアウトさせる。「ログイン状態を
// 保持する」のCookieも、Cookieに入っているパスワードのハッシュ値で同じように比べる。
// これにより、パスワードを変えると、ほかの端末のログインと保持用のCookieが無効になる
// （変えた本人の端末は、変えたリクエストの最後に新しい値を控え直すので、そのまま使える）。
// ガードの名前（auth:web）を省かずに書く。ログインの後に元の画面へ戻す仕組み（Neobit\Core\Auth\LoginRedirect）が、
// どのガードの画面かを、ここから読むため。
Route::middleware(['auth:web', 'auth.session'])->group(function () {
    // マイページ
    Route::get('/mypage', [MypageController::class, 'index'])->name('mypage');
    // 会員情報の変更
    Route::get('/mypage/edit', [MypageController::class, 'edit'])->name('mypage.edit');
    Route::patch('/mypage/update', [MypageController::class, 'update'])->name('mypage.update');
    // メールアドレスが変わるときの、確認コードの入力（Neobit\Core\Auth\EmailChange）。
    // コード照合のthrottle制御はコントローラー側で行う
    Route::get('/mypage/email/verify', [MypageController::class, 'emailChangeForm'])->name('mypage.email');
    Route::post('/mypage/email/verify', [MypageController::class, 'emailChangeConfirm'])->name('mypage.email.confirm');
    Route::post('/mypage/email/verify/resend', [MypageController::class, 'emailChangeResend'])
        ->middleware('throttle:3,1,mypage-email-resend')
        ->name('mypage.email.resend');
    Route::post('/mypage/email/verify/back', [MypageController::class, 'emailChangeBack'])->name('mypage.email.back');
    // 顔写真のアップロード先（Neobit\Core\Upload\AjaxFileUpload）
    Route::post('/mypage/ajax-upload', [MypageController::class, 'uploadAjaxFile'])
        ->middleware('throttle:20,1,mypage-upload')
        ->name('mypage.ajaxUpload');
    // 履歴書のPDF
    Route::get('/mypage/resume', [MypageController::class, 'resume'])->name('mypage.resume');
    Route::get('/mypage/resume-on-sheet', [MypageController::class, 'resumeOnSheet'])->name('mypage.resumeOnSheet');
    // 退会（会員データを物理削除する）
    Route::get('/mypage/withdraw', [MypageController::class, 'withdraw'])->name('mypage.withdraw');
    Route::delete('/mypage/withdraw', [MypageController::class, 'destroy'])->name('mypage.destroy');

    // パスワード変更
    Route::get('/mypage/password', [AuthPasswordController::class, 'edit'])->name('password.edit');
    Route::patch('/mypage/password/update', [AuthPasswordController::class, 'update'])->name('password.update');
    Route::post('/mypage/password/resend', [AuthPasswordController::class, 'resend'])
        ->middleware('throttle:3,1,password-resend')
        ->name('password.resend');

    // パスキーの一覧・登録・削除（Neobit\Core\Auth\PasskeyManagement）。使わない場合はこの6つを消す
    // （マイページの入口も消える）
    // 本人による、2段階認証の管理（Neobit\Core\Auth\TwoFactorManagement）。バックアップコードの発行と、
    // 認証アプリの登録の解除。入口は、どちらかができるときだけ出る。.code（確認コードの送信）は、
    // メールの方式のときだけ使う
    Route::get('/mypage/two-factor', [MypageController::class, 'twoFactorIndex'])->name('mypage.twoFactor');
    Route::get('/mypage/two-factor/backup-codes', [MypageController::class, 'showNewBackupCodes'])->name('twoFactor.backupCodes');
    Route::post('/mypage/two-factor/backup-codes/code', [MypageController::class, 'sendBackupCodesCode'])
        ->middleware('throttle:3,1,member-backup-codes-code-send')
        ->name('twoFactor.backupCodes.code');
    Route::post('/mypage/two-factor/backup-codes/regenerate', [MypageController::class, 'regenerateBackupCodes'])
        ->name('twoFactor.regenerateBackupCodes');
    Route::delete('/mypage/two-factor', [MypageController::class, 'selfReset'])->name('twoFactor.selfReset');

    Route::get('/mypage/passkeys', [MypageController::class, 'passkeyIndex'])->name('mypage.passkeys');
    Route::post('/mypage/passkeys/code', [MypageController::class, 'passkeySendCode'])
        ->middleware('throttle:3,1,member-passkey-code-send')
        ->name('mypage.passkeys.code');
    Route::post('/mypage/passkeys/confirm', [MypageController::class, 'passkeyConfirm'])->name('mypage.passkeys.confirm');
    Route::get('/mypage/passkeys/options', [MypageController::class, 'passkeyRegistrationOptions'])->name('mypage.passkeys.options');
    Route::post('/mypage/passkeys', [MypageController::class, 'passkeyStore'])->name('mypage.passkeys.store');
    Route::delete('/mypage/passkeys/{passkey}', [MypageController::class, 'passkeyDestroy'])->name('mypage.passkeys.destroy');

    // 会員ログアウト
    Route::post('/logout', [AuthSessionController::class, 'destroy'])->name('logout');
});
