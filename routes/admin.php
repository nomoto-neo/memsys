<?php

use App\Http\Controllers\Admin\AuthSessionController as AdminSessionController;
use App\Http\Controllers\Admin\BulkMailController as AdminBulkMailController;
use App\Http\Controllers\Admin\BulkMailTemplateController as AdminBulkMailTemplateController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\CompanyUserController as AdminCompanyUserController;
use App\Http\Controllers\Admin\MemberController as AdminMemberController;
use App\Http\Controllers\Admin\NewsController as AdminNewsController;
use App\Http\Controllers\Admin\NewsletterSubscriberController as AdminNewsletterSubscriberController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use Illuminate\Support\Facades\Route;
use Neobit\Core\Routing\FrameworkRoutes;

/**
 * 管理画面のルート。会員用の"web"ガードとは別の"admin"ガードで保護する。
 * URLの頭（設定のneobit.route.admin_path）と名前の頭のadmin.、接続元のIPアドレスの制限（admin.ip）は、
 * bootstrap/app.phpが付ける。
 */
Route::middleware('guest:admin')->group(function () {
    // 未ログイン状態はログイン画面（ログイン試行のthrottle制御はコントローラー側で行う）
    Route::get('/login', [AdminSessionController::class, 'create'])->name('login');
    Route::post('/login', [AdminSessionController::class, 'store'])->name('login.store');

    // パスキーでのログイン（Neobit\Core\Auth\PasskeyLogin）。パスキーで通ったときは2段階目も求めない
    Route::get('/login/passkey/options', [AdminSessionController::class, 'passkeyLoginOptions'])
        ->middleware('throttle:20,1,admin-passkey-options')
        ->name('login.passkey.options');
    Route::post('/login/passkey', [AdminSessionController::class, 'passkeyLogin'])
        ->middleware('throttle:10,1,admin-passkey-login')
        ->name('login.passkey');
});

// ログインの2段階目（入口はNeobit\Core\Auth\TwoFactorLoginトレイト。方式はStaff::TWO_FACTOR_METHOD）。
// パスワード通過後で2段階目の前という中間の状態なので、ミドルウェアでは守らず、セッションで判定する。
// 照合の試行制限はコントローラーの側で行う。再送は、メールの方式のときだけ使う
Route::get('/login/verify', [AdminSessionController::class, 'showVerification'])->name('login.verify');
Route::post('/login/verify', [AdminSessionController::class, 'verifyCode'])->name('login.verify.confirm');
Route::post('/login/verify/resend', [AdminSessionController::class, 'resendCode'])
    ->middleware('throttle:3,1,admin-login-verify-resend')
    ->name('login.verify.resend');

// ログイン中のスタッフだけが使う画面。auth.sessionは会員側と同じ
// （パスワードが変わったら、ほかの端末のログインと保持用のCookieを無効にする）。
// throttle:admin-screenは、スタッフごとの回数の制限（Neobit\Core\Security\AdminRequestLimit）。
// 一覧や詳細を、プログラムで続けて開いてデータを取り出す動きを止める。
Route::middleware(['auth:admin', 'auth.session', 'throttle:admin-screen'])->group(function () {
    // 管理画面TOP
    Route::view('/', 'admin.dashboard')->name('dashboard');
    // ログアウト
    Route::post('/logout', [AdminSessionController::class, 'destroy'])->name('logout');

    // 発行した直後のバックアップコードを一度だけ見せる画面。
    Route::get('/2fa/backup-codes', [AdminStaffController::class, 'showNewBackupCodes'])->name('twoFactor.backupCodes');

    // 本人による、バックアップコードの発行・2段階認証の登録解除。.code（確認コードの送信）は、
    // メールの方式のときだけ使う
    Route::post('/2fa/backup-codes/code', [AdminStaffController::class, 'sendBackupCodesCode'])
        ->middleware('throttle:3,1,admin-backup-codes-code-send')
        ->name('twoFactor.backupCodes.code');
    Route::post('/2fa/backup-codes/regenerate', [AdminStaffController::class, 'regenerateBackupCodes'])
        ->name('twoFactor.regenerateBackupCodes');
    Route::delete('/2fa', [AdminStaffController::class, 'selfReset'])->name('twoFactor.selfReset');

    // 本人によるパスキーの一覧・登録・削除（Neobit\Core\Auth\PasskeyManagement）。
    // 登録の前の本人確認は、ログインの2段階目の方式で行う。.code（確認コードの送信）は、
    // メールの方式のときだけ使う。
    Route::get('/passkeys', [AdminStaffController::class, 'passkeyIndex'])->name('passkeys');
    Route::post('/passkeys/code', [AdminStaffController::class, 'passkeySendCode'])
        ->middleware('throttle:3,1,admin-passkey-code-send')
        ->name('passkeys.code');
    Route::post('/passkeys/confirm', [AdminStaffController::class, 'passkeyConfirm'])->name('passkeys.confirm');
    Route::get('/passkeys/options', [AdminStaffController::class, 'passkeyRegistrationOptions'])->name('passkeys.options');
    Route::post('/passkeys', [AdminStaffController::class, 'passkeyStore'])->name('passkeys.store');
    Route::delete('/passkeys/{passkey}', [AdminStaffController::class, 'passkeyDestroy'])->name('passkeys.destroy');

    // 会員管理
    Route::get('/members', [AdminMemberController::class, 'index'])->name('members.index');
    Route::post('/members', [AdminMemberController::class, 'storeSearchCondition'])->name('members.search');
    // CSVダウンロード（/members/{member}より前に書く。後ろだと"csv"が会員のidとして扱われる）
    Route::get('/members/csv', [AdminMemberController::class, 'csv'])->name('members.csv');
    // CSV取り込み（入口はNeobit\Core\Csv\CsvImportトレイト。これも/members/{member}より前に書く）
    Route::get('/members/csv-import', [AdminMemberController::class, 'csvImport'])->name('members.csv-import');
    Route::post('/members/csv-import/confirm', [AdminMemberController::class, 'csvImportConfirm'])->name('members.csv-import.confirm');
    Route::post('/members/csv-import/execute', [AdminMemberController::class, 'csvImportExecute'])->name('members.csv-import.execute');
    // 顔写真のアップロード先（Neobit\Core\Upload\AjaxFileUpload。これも/members/{member}より前に書く）
    Route::post('/members/ajax-upload', [AdminMemberController::class, 'uploadAjaxFile'])->name('members.ajaxUpload');
    Route::get('/members/{member}', [AdminMemberController::class, 'show'])->name('members.show');
    Route::get('/members/{member}/edit', [AdminMemberController::class, 'edit'])->name('members.edit');
    Route::patch('/members/{member}/confirm', [AdminMemberController::class, 'confirmForUpdate'])->name('members.confirm.update');
    Route::post('/members/{member}/back', [AdminMemberController::class, 'backToEdit'])->name('members.confirm.update.back');
    Route::patch('/members/{member}/update', [AdminMemberController::class, 'update'])->name('members.update');
    // 履歴書のPDF
    Route::get('/members/{member}/resume', [AdminMemberController::class, 'resume'])->name('members.resume');
    // 定型紙（resources/pdf-sheets/）に文字を載せた履歴書のPDF
    Route::get('/members/{member}/resume-on-sheet', [AdminMemberController::class, 'resumeOnSheet'])->name('members.resumeOnSheet');
    // 2段階認証（認証アプリ）の登録の解除。端末を無くした会員の代わりに行う
    Route::delete('/members/{member}/two-factor', [AdminMemberController::class, 'resetTwoFactor'])->name('members.twoFactor.reset');
    // この会員の操作ログ。操作ログの一覧と同じく、管理者だけが開ける
    Route::get('/members/{member}/operation-logs', [AdminMemberController::class, 'operationLogs'])
        ->middleware('acl.manager')
        ->name('members.operation-logs');

    // 企業会員管理。企業は、企業の側が自分で登録するほか、運営がここで登録することもできる
    Route::get('/companies', [AdminCompanyController::class, 'index'])->name('companies.index');
    Route::post('/companies', [AdminCompanyController::class, 'storeSearchCondition'])->name('companies.search');
    // 登録（これも/companies/{company}より前に書く）。最初の担当者へ、招待のメールを送る
    Route::get('/companies/create', [AdminCompanyController::class, 'create'])->name('companies.create');
    Route::post('/companies/confirm', [AdminCompanyController::class, 'confirmForStore'])->name('companies.confirm.store');
    Route::post('/companies/back', [AdminCompanyController::class, 'backToCreate'])->name('companies.confirm.store.back');
    Route::post('/companies/store', [AdminCompanyController::class, 'store'])->name('companies.store');
    // CSVダウンロード（/companies/{company}より前に書く。後ろだと"csv"が企業のidとして扱われる）
    Route::get('/companies/csv', [AdminCompanyController::class, 'csv'])->name('companies.csv');
    Route::get('/companies/{company}', [AdminCompanyController::class, 'show'])->name('companies.show');
    Route::get('/companies/{company}/edit', [AdminCompanyController::class, 'edit'])->name('companies.edit');
    Route::patch('/companies/{company}/confirm', [AdminCompanyController::class, 'confirmForUpdate'])->name('companies.confirm.update');
    Route::post('/companies/{company}/back', [AdminCompanyController::class, 'backToEdit'])->name('companies.confirm.update.back');
    Route::patch('/companies/{company}/update', [AdminCompanyController::class, 'update'])->name('companies.update');
    // 申請の承認・却下と、利用の停止・再開。却下は、企業と担当者の行を消す
    Route::patch('/companies/{company}/approve', [AdminCompanyController::class, 'approve'])->name('companies.approve');
    Route::delete('/companies/{company}/reject', [AdminCompanyController::class, 'reject'])->name('companies.reject');
    Route::patch('/companies/{company}/suspend', [AdminCompanyController::class, 'suspend'])->name('companies.suspend');
    Route::patch('/companies/{company}/resume', [AdminCompanyController::class, 'resume'])->name('companies.resume');
    // 削除。停止の企業だけ。企業の側に退会の画面は無いので、退会の連絡を受けたときに使う
    Route::delete('/companies/{company}/delete', [AdminCompanyController::class, 'destroy'])->name('companies.destroy');
    // 担当者の招待のメールの送信・送り直し・取り消し（Neobit\Core\Team\TeamInvitationManager）。
    // scopeBindings()で、{invitation}をその{company}の招待に限る
    Route::post('/companies/{company}/invitations', [AdminCompanyController::class, 'storeInvitation'])->name('companies.invitations.store');
    Route::prefix('/companies/{company}/invitations/{invitation}')->name('companies.invitations.')->scopeBindings()->group(function () {
        Route::post('/resend', [AdminCompanyController::class, 'resendInvitation'])->name('resend');
        Route::delete('/', [AdminCompanyController::class, 'cancelInvitation'])->name('cancel');
    });
    // 企業の担当者の確認・編集・削除。入口は、企業の詳細画面の担当者の一覧。
    // scopeBindings()で、{user}をその{company}の担当者に限る（ほかの企業の担当者のidでは404になる）
    Route::prefix('/companies/{company}/users/{user}')->name('companies.users.')->scopeBindings()->group(function () {
        Route::get('/', [AdminCompanyUserController::class, 'show'])->name('show');
        Route::get('/edit', [AdminCompanyUserController::class, 'edit'])->name('edit');
        Route::patch('/confirm', [AdminCompanyUserController::class, 'confirmForUpdate'])->name('confirm.update');
        Route::post('/back', [AdminCompanyUserController::class, 'backToEdit'])->name('confirm.update.back');
        Route::patch('/update', [AdminCompanyUserController::class, 'update'])->name('update');
        Route::delete('/delete', [AdminCompanyUserController::class, 'destroy'])->name('destroy');
        // 2段階認証（認証アプリ）の登録の解除。端末を無くした担当者の代わりに行う
        Route::delete('/two-factor', [AdminCompanyUserController::class, 'resetTwoFactor'])->name('twoFactor.reset');
    });

    // スタッフ管理
    Route::middleware('acl.manager')->group(function () {
        // 「管理者」にしか触らせたくない機能
        Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [AdminStaffController::class, 'storeSearchCondition'])->name('staff.search');
        Route::get('/staff/create', [AdminStaffController::class, 'create'])->name('staff.create');

        Route::post('/staff/confirm', [AdminStaffController::class, 'confirmForStore'])->name('staff.confirm.store');
        Route::post('/staff/back', [AdminStaffController::class, 'backToCreate'])->name('staff.confirm.store.back');
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
        Route::patch('/staff/{staff}/confirm', [AdminStaffController::class, 'confirmForUpdate'])->name('staff.confirm.update');
        Route::post('/staff/{staff}/back', [AdminStaffController::class, 'backToEdit'])->name('staff.confirm.update.back');
        Route::patch('/staff/{staff}/update', [AdminStaffController::class, 'update'])->name('staff.update');
    });

    // 管理者だけが使う画面
    Route::middleware('acl.manager')->group(function () {
        // フレームワークの画面（Neobit\Core\Routing\FrameworkRoutes）。使わない画面は、その行を消す
        FrameworkRoutes::codes('/codes');
        FrameworkRoutes::ipBlocks('/ip-blocks');
        FrameworkRoutes::accessRestriction('/access-restriction');
        FrameworkRoutes::basicAuth('/basic-auth');
        FrameworkRoutes::fileWatch('/file-watch');
        FrameworkRoutes::seoSettings('/seo-settings');
        FrameworkRoutes::redirects('/redirects');
        FrameworkRoutes::operationLogs('/operation-logs');

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
        // メルマガ希望者（お知らせメールを受け取る会員と、会員ではない人）の宛先のCSV。/bulk-mails/{bulkMail}より前に書く
        Route::get('/bulk-mails/subscribers-csv', [AdminBulkMailController::class, 'subscribersCsv'])->name('bulk-mails.subscribersCsv');
        Route::get('/bulk-mails/{bulkMail}', [AdminBulkMailController::class, 'show'])->name('bulk-mails.show');
        Route::get('/bulk-mails/{bulkMail}/failed-csv', [AdminBulkMailController::class, 'failedCsv'])->name('bulk-mails.failedCsv');

        // メルマガ希望者（会員ではない人）の一覧・検索と、手での停止（App\Services\NewsletterSubscription）
        Route::get('/newsletter-subscribers', [AdminNewsletterSubscriberController::class, 'index'])->name('newsletter-subscribers.index');
        Route::post('/newsletter-subscribers', [AdminNewsletterSubscriberController::class, 'storeSearchCondition'])->name('newsletter-subscribers.search');
        Route::patch('/newsletter-subscribers/{subscriber}/stop', [AdminNewsletterSubscriberController::class, 'stop'])->name('newsletter-subscribers.stop');
    });

    // ニュースカテゴリー管理
    Route::patch('/categories/reorder', [AdminCategoryController::class, 'reorder'])->name('categories.reorder');
    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
    Route::patch('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    // 固定ページ管理。本文のエディタの画像のAjaxアップロード先も、ここに置く
    Route::post('/pages/ajax-upload', [AdminPageController::class, 'uploadAjaxFile'])->name('pages.ajaxUpload');
    Route::get('/pages', [AdminPageController::class, 'index'])->name('pages.index');
    Route::post('/pages', [AdminPageController::class, 'storeSearchCondition'])->name('pages.search');
    Route::get('/pages/create', [AdminPageController::class, 'create'])->name('pages.create');
    Route::post('/pages/confirm', [AdminPageController::class, 'confirmForStore'])->name('pages.confirm.store');
    Route::post('/pages/back', [AdminPageController::class, 'backToCreate'])->name('pages.confirm.store.back');
    Route::post('/pages/store', [AdminPageController::class, 'store'])->name('pages.store');
    Route::get('/pages/{page}', [AdminPageController::class, 'show'])->name('pages.show');
    Route::get('/pages/{page}/edit', [AdminPageController::class, 'edit'])->name('pages.edit');
    Route::patch('/pages/{page}/confirm', [AdminPageController::class, 'confirmForUpdate'])->name('pages.confirm.update');
    Route::post('/pages/{page}/back', [AdminPageController::class, 'backToEdit'])->name('pages.confirm.update.back');
    Route::patch('/pages/{page}/update', [AdminPageController::class, 'update'])->name('pages.update');
    Route::delete('/pages/{page}/delete', [AdminPageController::class, 'destroy'])->name('pages.destroy');

    // ニュース管理
    // 一覧用画像・添付ファイルのAjaxアップロード先。
    Route::post('/news/ajax-upload', [AdminNewsController::class, 'uploadAjaxFile'])->name('news.ajaxUpload');
    Route::get('/news', [AdminNewsController::class, 'index'])->name('news.index');
    Route::post('/news', [AdminNewsController::class, 'storeSearchCondition'])->name('news.search');
    Route::get('/news/create', [AdminNewsController::class, 'create'])->name('news.create');
    Route::post('/news/confirm', [AdminNewsController::class, 'confirmForStore'])->name('news.confirm.store');
    Route::post('/news/back', [AdminNewsController::class, 'backToCreate'])->name('news.confirm.store.back');
    Route::post('/news/store', [AdminNewsController::class, 'store'])->name('news.store');
    // CSVダウンロード（/news/{news}より前に書く）
    Route::get('/news/csv', [AdminNewsController::class, 'csv'])->name('news.csv');
    // CSV取り込み（入口はNeobit\Core\Csv\CsvImportトレイト。これも/news/{news}より前に書く）
    Route::get('/news/csv-import', [AdminNewsController::class, 'csvImport'])->name('news.csv-import');
    Route::post('/news/csv-import/confirm', [AdminNewsController::class, 'csvImportConfirm'])->name('news.csv-import.confirm');
    Route::post('/news/csv-import/execute', [AdminNewsController::class, 'csvImportExecute'])->name('news.csv-import.execute');
    Route::get('/news/{news}', [AdminNewsController::class, 'show'])->name('news.show');
    Route::get('/news/{news}/edit', [AdminNewsController::class, 'edit'])->name('news.edit');
    Route::patch('/news/{news}/confirm', [AdminNewsController::class, 'confirmForUpdate'])->name('news.confirm.update');
    Route::post('/news/{news}/back', [AdminNewsController::class, 'backToEdit'])->name('news.confirm.update.back');
    Route::patch('/news/{news}/update', [AdminNewsController::class, 'update'])->name('news.update');
    Route::delete('/news/{news}/delete', [AdminNewsController::class, 'destroy'])->name('news.destroy');
});
