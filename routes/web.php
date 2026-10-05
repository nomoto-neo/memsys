<?php

use App\Http\Controllers\Admin\AuthSessionController as AdminSessionController;
use App\Http\Controllers\Admin\MemberController as AdminMemberController;
use App\Http\Controllers\Admin\NewsController as AdminNewsController;
use App\Http\Controllers\Admin\OperationLogController as AdminOperationLogController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Admin\CodeController as AdminCodeController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\BulkMailController as AdminBulkMailController;
use App\Http\Controllers\Admin\BulkMailTemplateController as AdminBulkMailTemplateController;
use App\Http\Controllers\Admin\TwoFactorChallengeController;
use App\Http\Controllers\AuthPasswordController;
use App\Http\Controllers\AuthRegisteredMemberController;
use App\Http\Controllers\AuthSessionController;
use App\Http\Controllers\Company\AuthSessionController as CompanySessionController;
use App\Http\Controllers\Company\MypageController as CompanyMypageController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Contact2Controller;
use App\Http\Controllers\MypageController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\TopController;
use App\Http\Controllers\UploadedFileController;
use Illuminate\Support\Facades\Route;

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
// 確認画面へ進むたびにCloudflare Turnstileへ問い合わせる（App\Support\SpamGuard）ので、回数を制限する
Route::post('/contact/confirm', [ContactController::class, 'confirmStore'])
    ->middleware('throttle:20,1,contact-confirm')
    ->name('contact.confirm');
Route::post('/contact/back', [ContactController::class, 'back'])->name('contact.back');
Route::post('/contact/store', [ContactController::class, 'store'])
    ->middleware('throttle:5,1,contact-store')
    ->name('contact.store');
Route::get('/contact/thanks', [ContactController::class, 'thanks'])->name('contact.thanks');

// ログインした人だけが見られるアップロードファイルと、アップロード直後の一時ファイル
// （App\Http\Controllers\UploadedFileController）。会員・スタッフのどちらのガードでも使い、
// 訪問者のお問い合わせの一時ファイルにも使うので、authのグループには入れない。
Route::get('/uploads/tmp/{filename}', [UploadedFileController::class, 'tmp'])->name('uploads.tmp');
Route::get('/uploads/{type}/{id}/{field}/{filename}', [UploadedFileController::class, 'show'])
    ->whereNumber('id')
    ->where('field', '[a-z0-9_]+')
    ->name('uploads.show');

// MailTemplate方式・AjaxFileUpload方式を使わない、普通のLaravelの書き方によるお問い合わせフォーム。
// 比較用・技術習得のため残置（提携先向けのデモには含めない）
Route::get('/contact2', [Contact2Controller::class, 'create'])->name('contact2.create');
Route::post('/contact2', [Contact2Controller::class, 'store'])->name('contact2.store');

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

    // パスキーでのログイン（App\Support\PasskeyLogin）。使わない場合はこの2つを消す
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

// メールによる二段階認証（認証試行のthrottle制御はコントローラー側で行う）
// パスワード通過後で二段階認証前という中間状態なのでmiddleware判断はせず独自にSESSIONで判定
// （入口はApp\Support\MemberLoginトレイト）
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
// ガードの名前（auth:web）を省かずに書く。ログインの後に元の画面へ戻す仕組み（App\Support\LoginRedirect）が、
// どのガードの画面かを、ここから読むため。
Route::middleware(['auth:web', 'auth.session'])->group(function () {
    // マイページ
    Route::get('/mypage', [MypageController::class, 'index'])->name('mypage');
    // 会員情報の変更
    Route::get('/mypage/edit', [MypageController::class, 'edit'])->name('mypage.edit');
    Route::patch('/mypage/update', [MypageController::class, 'update'])->name('mypage.update');
    // 顔写真のアップロード先（App\Support\AjaxFileUpload）
    Route::post('/mypage/ajax-upload', [MypageController::class, 'uploadAjaxFile'])
        ->middleware('throttle:20,1,mypage-upload')
        ->name('mypage.ajaxUpload');
    // 履歴書のPDF
    Route::get('/mypage/resume', [MypageController::class, 'resume'])->name('mypage.resume');
    // 退会（会員データを物理削除する）
    Route::get('/mypage/withdraw', [MypageController::class, 'withdraw'])->name('mypage.withdraw');
    Route::delete('/mypage/withdraw', [MypageController::class, 'destroy'])->name('mypage.destroy');

    // パスワード変更
    Route::get('/mypage/password', [AuthPasswordController::class, 'edit'])->name('password.edit');
    Route::patch('/mypage/password/update', [AuthPasswordController::class, 'update'])->name('password.update');
    Route::post('/mypage/password/resend', [AuthPasswordController::class, 'resend'])
        ->middleware('throttle:3,1,password-resend')
        ->name('password.resend');

    // パスキーの一覧・登録・削除（App\Support\PasskeyManagement）。使わない場合はこの6つを消す
    // （マイページの入口も消える）
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

// 企業会員：個人会員の"web"ガードとは別の"company"ガードで保護する。
// ログインするのは、企業に属する担当者（App\Models\CompanyUser）。URLは/companyの下にまとめ、
// ルートの名前の頭にcompany.を付ける（共通部品が、この名前の決まりからルートを作る）。
Route::prefix('company')->name('company.')->group(function () {
    Route::middleware('guest:company')->group(function () {
        // ログイン（企業ID・担当者ID・パスワード。試行のthrottle制御はコントローラー側で行う）
        Route::get('/login', [CompanySessionController::class, 'create'])->name('login');
        Route::post('/login', [CompanySessionController::class, 'store'])->name('login.store');

        // パスキーでのログイン（App\Support\PasskeyLogin）。使わない場合はこの2つを消す
        Route::get('/login/passkey/options', [CompanySessionController::class, 'passkeyLoginOptions'])
            ->middleware('throttle:20,1,company-passkey-options')
            ->name('login.passkey.options');
        Route::post('/login/passkey', [CompanySessionController::class, 'passkeyLogin'])
            ->middleware('throttle:10,1,company-passkey-login')
            ->name('login.passkey');
    });

    // メールによる2段階目（入口はApp\Support\MemberLoginトレイト）。
    // パスワード通過後で2段階目の前という中間の状態なので、ミドルウェアでは守らず、セッションで判定する
    Route::get('/login/verify', [CompanySessionController::class, 'showVerification'])->name('login.verify');
    Route::post('/login/verify', [CompanySessionController::class, 'verifyCode'])->name('login.verify.confirm');
    Route::post('/login/verify/resend', [CompanySessionController::class, 'resendCode'])
        ->middleware('throttle:3,1,company-login-verify-resend')
        ->name('login.verify.resend');

    // ログイン中の担当者だけが使う画面。company.approvedは、承認済みの企業かを毎回確かめる
    // （App\Http\Middleware\EnsureCompanyIsApproved）。auth.sessionは個人会員の側と同じ
    Route::middleware(['auth:company', 'auth.session', 'company.approved'])->group(function () {
        // マイページ
        Route::get('/mypage', [CompanyMypageController::class, 'index'])->name('mypage');

        // ログアウト
        Route::post('/logout', [CompanySessionController::class, 'destroy'])->name('logout');
    });
});

// 管理画面：会員用の"web"ガードとは別の"admin"ガードで保護する。
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        // 未ログイン状態はログイン画面（ログイン試行のthrottle制御はコントローラー側で行う）
        Route::get('/login', [AdminSessionController::class, 'create'])->name('login');
        Route::post('/login', [AdminSessionController::class, 'store'])->name('login.store');

        // パスキーでのログイン（App\Support\PasskeyLogin）。パスキーで通ったときは2段階目も求めない
        Route::get('/login/passkey/options', [AdminSessionController::class, 'passkeyLoginOptions'])
            ->middleware('throttle:20,1,admin-passkey-options')
            ->name('login.passkey.options');
        Route::post('/login/passkey', [AdminSessionController::class, 'passkeyLogin'])
            ->middleware('throttle:10,1,admin-passkey-login')
            ->name('login.passkey');
    });

    // 管理ログインの2段階目（TOTP）未ログインとログイン済の中間状態
    // （認証試行のthrottle制御はコントローラー側で行う）
    Route::get('/2fa', [TwoFactorChallengeController::class, 'show'])->name('twoFactor.show');
    Route::post('/2fa/verify', [TwoFactorChallengeController::class, 'verify'])->name('twoFactor.verify');

    // ログイン中のスタッフだけが使う画面。auth.sessionは会員側と同じ
    // （パスワードが変わったら、ほかの端末のログインと保持用のCookieを無効にする）。
    // throttle:admin-screenは、スタッフごとの回数の制限（App\Support\AdminRequestLimit）。
    // 一覧や詳細を、プログラムで続けて開いてデータを取り出す動きを止める。
    Route::middleware(['auth:admin', 'auth.session', 'throttle:admin-screen'])->group(function () {
        // 管理画面TOP
        Route::view('/', 'admin.dashboard')->name('dashboard');
        // ログアウト
        Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');

        // 2段階認証の初回登録直後にバックアップコードを一度だけ見せる画面。
        Route::get('/2fa/backup-codes', [TwoFactorChallengeController::class, 'showNewBackupCodes'])->name('twoFactor.backupCodes');

        // 本人による、バックアップコードの再発行・2段階認証の登録解除。
        Route::post('/2fa/backup-codes/regenerate', [TwoFactorChallengeController::class, 'regenerateBackupCodes'])
            ->name('twoFactor.regenerateBackupCodes');
        Route::delete('/2fa', [TwoFactorChallengeController::class, 'selfReset'])->name('twoFactor.selfReset');

        // 本人によるパスキーの一覧・登録・削除（App\Support\PasskeyManagement）。
        // 本人確認はTOTPで行うので、会員側の.code（確認コードの送信）は無い。
        Route::get('/passkeys', [AdminStaffController::class, 'passkeyIndex'])->name('passkeys');
        Route::post('/passkeys/confirm', [AdminStaffController::class, 'passkeyConfirm'])->name('passkeys.confirm');
        Route::get('/passkeys/options', [AdminStaffController::class, 'passkeyRegistrationOptions'])->name('passkeys.options');
        Route::post('/passkeys', [AdminStaffController::class, 'passkeyStore'])->name('passkeys.store');
        Route::delete('/passkeys/{passkey}', [AdminStaffController::class, 'passkeyDestroy'])->name('passkeys.destroy');

        // 会員管理
        Route::get('/members', [AdminMemberController::class, 'index'])->name('members.index');
        Route::post('/members', [AdminMemberController::class, 'storeSearchCondition'])->name('members.search');
        // CSVダウンロード（/members/{member}より前に書く。後ろだと"csv"が会員のidとして扱われる）
        Route::get('/members/csv', [AdminMemberController::class, 'csv'])->name('members.csv');
        // CSV取り込み（入口はApp\Support\CsvImportトレイト。これも/members/{member}より前に書く）
        Route::get('/members/csv-import', [AdminMemberController::class, 'csvImport'])->name('members.csv-import');
        Route::post('/members/csv-import/confirm', [AdminMemberController::class, 'csvImportConfirm'])->name('members.csv-import.confirm');
        Route::post('/members/csv-import/execute', [AdminMemberController::class, 'csvImportExecute'])->name('members.csv-import.execute');
        // 顔写真のアップロード先（App\Support\AjaxFileUpload。これも/members/{member}より前に書く）
        Route::post('/members/ajax-upload', [AdminMemberController::class, 'uploadAjaxFile'])->name('members.ajaxUpload');
        Route::get('/members/{member}', [AdminMemberController::class, 'show'])->name('members.show');
        Route::get('/members/{member}/edit', [AdminMemberController::class, 'edit'])->name('members.edit');
        Route::patch('/members/{member}/confirm', [AdminMemberController::class, 'confirmUpdate'])->name('members.confirm.edit');
        Route::post('/members/{member}/back', [AdminMemberController::class, 'backToEdit'])->name('members.confirm.edit.back');
        Route::patch('/members/{member}/update', [AdminMemberController::class, 'update'])->name('members.update');
        // 履歴書のPDF
        Route::get('/members/{member}/resume', [AdminMemberController::class, 'resume'])->name('members.resume');
        // この会員の操作ログ。操作ログの一覧と同じく、管理者だけが開ける
        Route::get('/members/{member}/operation-logs', [AdminMemberController::class, 'operationLogs'])
            ->middleware('acl.manager')
            ->name('members.operation-logs');

        // スタッフ管理
        Route::middleware('acl.manager')->group(function () {
            // 「管理者」にしか触らせたくない機能
            Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff.index');
            Route::post('/staff', [AdminStaffController::class, 'storeSearchCondition'])->name('staff.search');
            Route::get('/staff/create', [AdminStaffController::class, 'create'])->name('staff.create');

            Route::post('/staff/confirm', [AdminStaffController::class, 'confirmStore'])->name('staff.confirm.create');
            Route::post('/staff/back', [AdminStaffController::class, 'backToCreate'])->name('staff.confirm.create.back');
            Route::post('/staff/store', [AdminStaffController::class, 'store'])->name('staff.store');

            // 削除（自分自身は削除できない。判断はApp\Policies\StaffPolicy）
            Route::delete('/staff/{staff}/delete', [AdminStaffController::class, 'destroy'])
                ->middleware('can:delete,staff')
                ->name('staff.destroy');

            // 削除の取り消し（削除済みのスタッフだけ）。削除済みのスタッフを{staff}で
            // 受け取れるよう、ルートモデルバインディングにwithTrashed()を付けている。
            Route::patch('/staff/{staff}/restore', [AdminStaffController::class, 'restore'])
                ->withTrashed()
                ->middleware('can:restore,staff')
                ->name('staff.restore');

            // 管理者による2段階認証（TOTP）の登録解除（自分自身は対象外）
            Route::delete('/staff/{staff}/two-factor', [AdminStaffController::class, 'resetTwoFactor'])
                ->middleware('can:resetTwoFactor,staff')
                ->name('staff.twoFactor.reset');
        });

        // 本人か管理者だけが触れる機能（判断はApp\Policies\StaffPolicy）
        // 詳細画面は、削除済みのスタッフも表示する（削除を取り消すボタンを出すため）。
        Route::get('/staff/{staff}', [AdminStaffController::class, 'show'])
            ->withTrashed()
            ->middleware('can:view,staff')
            ->name('staff.show');
        Route::middleware('can:update,staff')->group(function () {
            Route::get('/staff/{staff}/edit', [AdminStaffController::class, 'edit'])->name('staff.edit');
            Route::patch('/staff/{staff}/confirm', [AdminStaffController::class, 'confirmUpdate'])->name('staff.confirm.edit');
            Route::post('/staff/{staff}/back', [AdminStaffController::class, 'backToEdit'])->name('staff.confirm.edit.back');
            Route::patch('/staff/{staff}/update', [AdminStaffController::class, 'update'])->name('staff.update');
        });

        // 項目見出し管理
        Route::middleware('acl.manager')->group(function () {
            // 項目見出し一覧（DBで管理するコード表 t_codes の編集）
            Route::get('/codes', [AdminCodeController::class, 'index'])->name('codes.index');
            Route::patch('/codes', [AdminCodeController::class, 'update'])->name('codes.update');

            // 一斉メールの文面の管理
            Route::get('/bulk-mail-templates', [AdminBulkMailTemplateController::class, 'index'])->name('bulk-mail-templates.index');
            Route::get('/bulk-mail-templates/create', [AdminBulkMailTemplateController::class, 'create'])->name('bulk-mail-templates.create');
            Route::post('/bulk-mail-templates', [AdminBulkMailTemplateController::class, 'store'])->name('bulk-mail-templates.store');
            Route::get('/bulk-mail-templates/{template}/edit', [AdminBulkMailTemplateController::class, 'edit'])->name('bulk-mail-templates.edit');
            Route::patch('/bulk-mail-templates/{template}', [AdminBulkMailTemplateController::class, 'update'])->name('bulk-mail-templates.update');
            Route::delete('/bulk-mail-templates/{template}', [AdminBulkMailTemplateController::class, 'destroy'])->name('bulk-mail-templates.destroy');

            // 一斉メールの送信。添付ファイルのAjaxアップロード先も含む
            Route::post('/bulk-mails/ajax-upload', [AdminBulkMailController::class, 'uploadAjaxFile'])->name('bulk-mails.ajaxUpload');
            Route::get('/bulk-mails', [AdminBulkMailController::class, 'index'])->name('bulk-mails.index');
            Route::get('/bulk-mails/create', [AdminBulkMailController::class, 'create'])->name('bulk-mails.create');
            Route::post('/bulk-mails/confirm', [AdminBulkMailController::class, 'confirm'])->name('bulk-mails.confirm');
            Route::post('/bulk-mails/back', [AdminBulkMailController::class, 'back'])->name('bulk-mails.back');
            Route::post('/bulk-mails', [AdminBulkMailController::class, 'store'])->name('bulk-mails.store');
            Route::get('/bulk-mails/{bulkMail}', [AdminBulkMailController::class, 'show'])->name('bulk-mails.show');

            // 操作ログの一覧・検索（App\Support\OperationRecorderが書いた記録を見るだけ）
            Route::get('/operation-logs', [AdminOperationLogController::class, 'index'])->name('operation-logs.index');
            Route::post('/operation-logs', [AdminOperationLogController::class, 'storeSearchCondition'])->name('operation-logs.search');
            Route::get('/operation-logs/csv', [AdminOperationLogController::class, 'csv'])->name('operation-logs.csv');
            // 1日ごとの棒を押したとき。今の検索条件のまま、その1日に絞る
            Route::post('/operation-logs/day', [AdminOperationLogController::class, 'searchDay'])->name('operation-logs.day');
        });

        // ニュースカテゴリー管理
        Route::patch('/categories/reorder', [AdminCategoryController::class, 'reorder'])->name('categories.reorder');
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
        Route::patch('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

        // ニュース管理
        // 一覧用画像・添付ファイルのAjaxアップロード先。
        Route::post('/news/ajax-upload', [AdminNewsController::class, 'uploadAjaxFile'])->name('news.ajaxUpload');
        Route::get('/news', [AdminNewsController::class, 'index'])->name('news.index');
        Route::post('/news', [AdminNewsController::class, 'storeSearchCondition'])->name('news.search');
        Route::get('/news/create', [AdminNewsController::class, 'create'])->name('news.create');
        Route::post('/news/confirm', [AdminNewsController::class, 'confirmStore'])->name('news.confirm.create');
        Route::post('/news/back', [AdminNewsController::class, 'backToCreate'])->name('news.confirm.create.back');
        Route::post('/news/store', [AdminNewsController::class, 'store'])->name('news.store');
        // CSVダウンロード（/news/{news}より前に書く）
        Route::get('/news/csv', [AdminNewsController::class, 'csv'])->name('news.csv');
        // CSV取り込み（入口はApp\Support\CsvImportトレイト。これも/news/{news}より前に書く）
        Route::get('/news/csv-import', [AdminNewsController::class, 'csvImport'])->name('news.csv-import');
        Route::post('/news/csv-import/confirm', [AdminNewsController::class, 'csvImportConfirm'])->name('news.csv-import.confirm');
        Route::post('/news/csv-import/execute', [AdminNewsController::class, 'csvImportExecute'])->name('news.csv-import.execute');
        Route::get('/news/{news}', [AdminNewsController::class, 'show'])->name('news.show');
        Route::get('/news/{news}/edit', [AdminNewsController::class, 'edit'])->name('news.edit');
        Route::patch('/news/{news}/confirm', [AdminNewsController::class, 'confirmUpdate'])->name('news.confirm.edit');
        Route::post('/news/{news}/back', [AdminNewsController::class, 'backToEdit'])->name('news.confirm.edit.back');
        Route::patch('/news/{news}/update', [AdminNewsController::class, 'update'])->name('news.update');
        Route::delete('/news/{news}/delete', [AdminNewsController::class, 'destroy'])->name('news.destroy');
    });
});
