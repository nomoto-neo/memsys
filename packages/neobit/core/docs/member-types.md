# 個人会員と企業会員（LoginAccount）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Auth/LoginAccount.php`・`IsLoginAccount.php`・`TwoFactorLogin.php`・`TeamInvitationManager.php`・`TeamInvitationOwner.php`・`HasTeamInvitations.php`・`LoginIdMemory.php`、`config/site_login.php`、設計の詳細は「個人会員と企業会員を共存させる設計」（`docs/member-types-spec.md`）　**実例**：個人会員は `AuthSessionController` ほか、企業会員は `app/Http/Controllers/Company/` の下と `Admin\CompanyController`・`Admin\CompanyUserController`

訪問者側でログインする人は、個人会員と、企業の担当者の2種類です。ログインの仕組み（[login.md](login.md)）は同じ共通部品を使い、画面・URL・コントローラーは種類ごとに分けて書きます。企業会員を使わないサイトは、企業会員のファイルを消します。

## 名前の決まり

ログインするモデルは、共通の型 `LoginAccount` を実装します。ガード・ルート・メールのテンプレート・Cookie の名前は、モデルの「種類の名前」から決まりで作り、設定には書きません。共通部品は、渡されたモデルに名前を聞くだけで、個人か企業かの場合分けを持ちません。

| もの | 決まり | 個人会員（`Member`） | 企業の担当者（`CompanyUser`） |
|---|---|---|---|
| 種類の名前 | モデルの短い名前。`AppServiceProvider` の `enforceMorphMap()` に書いた名前。操作ログなどが DB に持つ名前と同じ | `member` | `company_user` |
| ガード | 種類の名前。画面の名前に合わせるときは、定数 `LOGIN_GUARD` | `web` | `company` |
| ルートの名前 | 頭に「種類の名前.」。画面の名前に合わせるときは、定数 `LOGIN_ROUTE_PREFIX` | `login`・`mypage` | `company.login`・`company.mypage` |
| メールのテンプレート | 頭に「種類の名前_」。無ければ、頭を付けない名前、フレームワークの既定の順に探す（[mail.md](mail.md)） | `member_verification_code` | `company_user_verification_code` |
| 信頼済み端末の Cookie | 「種類の名前_trusted_device」 | `member_trusted_device` | `company_user_trusted_device` |

ガードとルートは、画面の側の名前なので、種類の名前とは別に決められます。個人会員は、URL とルートの名前を短いままにするために、ガードを `web`、ルートの頭を無しにしています。企業の担当者は、企業会員の画面の URL（`/company/…`）に合わせて、ガードを `company`、ルートの頭を `company.` にしています。モデルの定数 `LOGIN_GUARD`・`LOGIN_ROUTE_PREFIX` で、決まりと違う名前にしています。

## モデルに書くもの

```php
class CompanyUser extends Authenticatable implements LoginAccount, PasskeyUser
{
    use HasPasskeys;        // パスキー（two-factor.md）
    use HasTwoFactor;       // 2段階認証・バックアップコード・信頼済み端末（two-factor.md）
    use IsLoginAccount;     // 名前の決まり

    // 種類の名前（company_user）は、モデルの短い名前から決まるので、書かない。
    // ガードとルートの頭は、企業会員の画面の名前（/company/…）に合わせている
    public const LOGIN_GUARD = 'company';
    public const LOGIN_ROUTE_PREFIX = 'company.';

    public const LOGIN_ID_TYPE = LoginIdType::Either;            // ログインに使う値。書かなければメールアドレス
    public const TWO_FACTOR_METHOD = TwoFactorMethod::Email;     // 2段階目の方式
    public const BACKUP_CODES = true;                            // バックアップコードを使うか

    /** 画面やメールに出す名前 */
    public function displayName(): string { ... }

    /** お知らせや確認コードを送るメールアドレス。未登録なら null */
    public function notificationEmail(): ?string { ... }

    /** ログインに使う値。メールアドレスでログインする会員は、書かなくてよい */
    public function loginId(): string { ... }
}
```

## コントローラーに書くもの

共通部品には、会員のモデルを渡します。

```php
// ログインのコントローラー（login.mdの「ログインの流れ」）
use TwoFactorLogin;
private const LOGIN_ACCOUNT = CompanyUser::class;               // ガード・ルート・2段階目の方式は、ここから決まる
private const TWO_FACTOR_VIEW = 'company.auth.login-verify';    // 2段階目の画面

// パスワードが合った後は、トレイトに任せる（2段階目が要るか、信頼済みの端末か）
return $this->continueAfterPassword($request, $user, $request->boolean('remember'));
```

| したいこと | 書き方 |
|---|---|
| ログイン中の人を取る | `Auth::guard(CompanyUser::loginGuard())->user()` |
| その種類のルートへ移す | `route(CompanyUser::loginRoute('mypage'))` |
| 確認コード | `new LoginVerificationCode(CompanyUser::class)` |
| 信頼済み端末 | `TrustedDeviceManager::for($user)` |
| ログインの後の移動先 | `LoginRedirect::for(CompanyUser::class, route('company.mypage'))` |
| パスワードを変えた後始末 | `PasswordChange::resetAndNotify($user)` |
| 情報が変わったお知らせ | `LoginProfileNotice::send($user, $changedFields)` |
| メールアドレスの変更の確認 | トレイトの `holdForEmailChange($request, $user)`（`EmailChange`） |
| ログアウト | トレイトの `logoutAccount($request)` |

## ルート

```php
Route::prefix('company')->name('company.')->group(function () {
    Route::middleware('guest:company')->group(function () {
        /* 登録・ログイン・パスワードの再設定 */

        // 招待された人の登録。ログイン中の人が開いてもマイページへ移さないよう、guest:companyを外す
        Route::middleware('throttle:20,1,company-invitation')->withoutMiddleware('guest:company')->group(function () { /* ... */ });
    });

    // 2段階目と、初回のログインでの登録。1段階目の後の中間の状態なので、ミドルウェアでは守らない
    Route::get('/login/verify', ...);

    Route::middleware(['auth:company', 'auth.session', 'company.approved'])->group(function () { /* マイページ */ });
});
```

- `company.approved`（`EnsureCompanyIsApproved`）は、企業が承認済みかを、画面を開くたびに確かめます。運営が企業を止めると、ログイン中の担当者も次の操作でログアウトになります。
- ログインしていないときの移動先は、`bootstrap/app.php` で、ルートの名前の頭（`company.`）から決めています。

## 企業会員の画面

| 画面 | コントローラー | 中身 |
|---|---|---|
| 登録 | `Company\RegistrationController` | 企業と最初の担当者を「申請中」で作る。確認コードでメールアドレスを確かめ、運営へメールで知らせる |
| ログイン | `Company\AuthSessionController` | 企業ID・担当者ID・パスワード。担当者ID の欄には、メールアドレスを入れてもよい（`CompanyUser::LOGIN_ID_TYPE`）。「企業ID と担当者ID を記憶する」（`LoginIdMemory`） |
| 初回のログインでの登録 | `Company\FirstLoginSetupController` | 既存のシステムから移した企業だけ。企業の情報を1つ照合して、氏名・メールアドレス・担当者ID・パスワードを決める |
| マイページ・企業の情報の変更 | `Company\MypageController` | どの担当者も変えられる。パスキーの管理も持つ |
| 自分の情報の変更 | `Company\ProfileController` | 氏名とメールアドレス。メールアドレスが変わるときは、新しいアドレスへの確認コードの入力を挟む（`EmailChange`） |
| パスワードの変更・再設定 | `Company\AuthPasswordController`・`Company\PasswordResetController` | 再設定は、企業ID とメールアドレスの2つが合う人に確認コードを送る。担当者ID を忘れた人も、再設定できる |
| 担当者の管理 | `Company\UserController` | 一覧・招待・ほかの担当者の編集と削除 |
| 招待された人の登録 | `Company\InvitationController` | 招待のメールのリンクから、担当者ID・氏名・パスワードを決める |
| 管理画面：企業会員 | `Admin\CompanyController` | 一覧・登録・詳細・編集・CSV、承認・却下・停止・再開・削除、招待のメールの送信 |
| 管理画面：担当者 | `Admin\CompanyUserController` | 確認・編集・削除 |

## 企業会員の決まりごと

- **企業と担当者に分かれます**。企業（`Company`）は情報を持つだけで、ログインするのは担当者（`CompanyUser`）です。担当者に権限の区別は無く、どの担当者も同じことができます。誰が行ったかは、操作ログ（[operation-log.md](operation-log.md)）で追います。
- **企業ID**（`t_companies.code`）は、新しく登録した企業では id と同じ番号が自動で入ります。始まりの番号は `neobit:set-next-id`（[table.md](table.md)）で決めます。既存のシステムから移した企業には、今までのログインID を入れます。
- **担当者ID**（`t_company_users.login_id`）は、企業の中でだけ重ならなければよい値です。使える文字は `CompanyUser::LOGIN_ID_PATTERN` です。**メールアドレスも、同じ企業の担当者どうしで重ならないようにします**。確認コードとパスワードの再設定のメールが、本人だけに届くようにするためです。画面からの登録・変更では検証のルール（`CompanyUser::uniqueEmailInCompany()`）で確かめ、DB にも同じ内容の制約があります。別の企業の担当者と同じアドレスは、使えます。既存のシステムのデータに、同じ企業で同じアドレスの担当者がいるときは、持ち込む前に直します。1人を残して、ほかの人の分を空にしておけば、その人たちは初回のログインで自分のアドレスを登録します。
- **状態**（`CompanyStatus`）は、申請中・承認済み・停止の3つです。ログインできるのは、承認済みの企業の担当者だけです。企業の側が登録した企業は申請中、運営が管理画面で登録した企業は承認済みで作られます。
- **企業の側に、退会の画面はありません**。退会の連絡を受けたら、運営が管理画面で停止してから削除します。削除できるのは、停止の企業だけです。承認済みの企業を、押し間違いで消さないようにするためです。削除すると、企業・担当者・招待の行と、担当者の信頼済み端末・パスキーが消えます。
- **担当者は、招待のメールで足します**（`TeamInvitationManager`）。招待する側が担当者ID やパスワードを決めて渡すことはしません。パスワードを本人のほかに知っている人を作らないためです。運営が企業を登録するときも、最初の担当者へ招待を送ります。
- **招待のリンク**は7日間有効で、1回使うと消えます。DB には、リンクに入れた値のハッシュ値だけを持ちます（`team_invitations`）。期限の切れた招待は、後片付け（[scheduler.md](scheduler.md)）が消します。
- **誰が招待したかを、招待の行に残します**（`inviter_type`・`inviter_id`）。担当者のことも、管理画面のスタッフのこともあります。招待中の一覧に「招待した人」として出します。スタッフが送った招待は、メールの文面と企業の側の画面では「運営」と出し、スタッフの氏名は出しません。管理画面では、スタッフの氏名も出します。
- **送り直せるのは、招待した本人だけです**。送り直しは、同じ番号の招待で、メールを送り直す操作です。ほかの担当者やスタッフが同じ宛先を招きたいときは、新しく招待します。前の招待は自動で消えて、新しい番号の招待になり、新しく送った人が、招待した人になります。**取り消しは、本人でなくてもできます**。宛先を間違えた招待を、送った人がいないときにも止められるようにするためです。
- **操作ログに、招待の番号を残します**。招待の送信・取り消しと、招待からの登録の記録の補足に、`招待ID:12` の形で残します。宛先のメールアドレスは残しません。操作ログの補足の検索（[operation-log.md](operation-log.md)）で番号を入れると、誰が送った招待から誰が登録したかを、たどれます。
- **招待のリンクを開いたとき、ほかの担当者のログインが残っていれば、終わらせます**。同じパソコンを複数の人で使っていて、前の人がログアウトしていなかった場合です。何も知らせずに、企業会員のログインだけを終わらせて、登録の画面を出します。管理画面や個人会員のログインは残します。終わらせるのは、使える招待のリンクのときだけです。期限切れや、でたらめな値のリンクでは、終わらせません（`Company\InvitationController`）。
- **招待の部品は、企業に限りません**。`TeamInvitationManager` は、招待する集団を共通の型（`TeamInvitationOwner`）で受け取ります。集団のモデルに、インターフェースとトレイトを付け、`invitedAccountClass()` を書きます。招待のメールのテンプレート（`種類の名前_invitation`）と、登録の画面のルート（`種類の名前.invitation.show`）は、そのモデルの種類の名前から決まります。メールには、サイトの名前（`site_name`）と、招待した人の氏名（`inviter_name`）を渡します。招待した人は、招待を作ったときに、その画面でログインしていた人です。管理画面のスタッフが招待したときは、氏名を出さず、空で渡します。送り直しを本人だけにする確かめは、呼ぶ側のコントローラーが `$invitation->isSentBy()` で行います。リンクから登録する画面は、項目が集団ごとに違うので、プロジェクトのコントローラーに書きます（実例：`Company\InvitationController`）。

    ```php
    class Company extends Model implements TeamInvitationOwner
    {
        use HasTeamInvitations;                    // invitations() の関連

        public static function invitedAccountClass(): string
        {
            return CompanyUser::class;                 // 招待から登録される人のモデル
        }
    }

    TeamInvitationManager::invite($company, $email);   // 招待を作って、メールを送る
    ```
- **お知らせのメール**は、次のときに送ります。運営が管理画面から氏名やメールアドレスを変えたときは、送りません。

| メール | テンプレート | 送るとき |
|---|---|---|
| 確認コード | `company_user_verification_code` | 登録・ログイン・パスワードの変更と再設定・パスキーの登録・初回のログインでの登録・自分のメールアドレスの変更 |
| 申請の通知（運営宛） | `company_registration_staff` | 企業の側が登録したとき。宛先は `.env` の `COMPANY_REGISTRATION_STAFF_EMAIL` |
| 承認・却下 | `company_approved`・`company_rejected` | 運営が承認・却下したとき。停止と再開では送らない |
| 招待 | `company_user_invitation` | 担当者か運営が招待したとき、送り直したとき |
| パスワードの変更 | `company_user_password_changed` | 本人か運営がパスワードを変えたとき |
| 担当者の情報の変更 | `company_user_profile_changed` | 本人か、同じ企業のほかの担当者が、氏名やメールアドレスを変えたとき |

## 既存のシステムから会員を移すサイト

既存のシステムからデータを移すサイトだけで働く機能が、2つあります。新しく始めるサイトでは、対象の会員がいないので、何も起きません。初回のログインでの登録は、コントローラーとルートを消しても構いません。

| 機能 | 設定 | 働く条件 |
|---|---|---|
| 古い方式のパスワードの置き換え（[legacy-password.md](legacy-password.md)） | `config/neobit.php` の `auth.legacy_passwords` | `legacy_password` に値がある会員 |
| 初回のログインでの登録 | `config/site_login.php` の `identity_check_column` | メールアドレスが空の担当者 |

- 移した企業は、企業1件にログインID とパスワードが1組であることが多いので、企業ごとに「最初の担当者」を1人だけ作ります。担当者ID は全部の企業で同じ初期値にし、氏名とメールアドレスは空にします。
- 最初の担当者がログインすると、2段階目の代わりに、初回のログインでの登録へ回ります。企業のデータにある値を1つ（`identity_check_column`。見本のサイトでは電話番号）照合してから、入力されたメールアドレスに確認コードを送ります。既存の ID とパスワードは漏れている前提で扱い、それだけを知っている他人に登録させないためです。
- 照合する列を変えたら、登録の画面（`company/auth/login-setup.blade.php`）の入力欄の見出しも合わせて直します。
- 照合する列の値が空の企業は、本人では登録できません。スタッフが、管理画面の担当者の編集からメールアドレスを入れます。
- 手元で試すための見本（企業ID `legacy`）は、`CompanySeeder` にあります。

## 企業会員を使わないサイト

個人会員だけのサイトでは、次を消します。パスキーを使わないサイトで、該当のルートを消すのと同じ扱いです。

| 場所 | 消すもの |
|---|---|
| `app/Http/Controllers/` | `Company/` の下の全部、`Admin/CompanyController.php`・`Admin/CompanyUserController.php` |
| `app/Models/`・`app/Enums/` | `Company`・`CompanyUser`、`CompanyStatus` |
| `app/Http/Middleware/` | `EnsureCompanyIsApproved` |
| `resources/` | `views/company/`・`views/layouts/company.blade.php`・`views/admin/companies/`・`views/admin/company_users/`、`mail-templates/company_*.blade.php`、管理画面の左のメニュー（`admin/_menu.blade.php`）の「企業会員」 |
| `routes/company.php`・`routes/admin.php` | 企業会員の画面と、管理画面の企業会員管理のルート |
| `config/` | `auth.php` の `company` のガードとプロバイダー、`site_login.php` の `company_user` |
| `bootstrap/app.php` | 移動先の `company.*` の行、`company.approved` の短い名前 |
| `database/` | 企業（`t_companies`）と担当者（`t_company_users`）のテーブルのマイグレーション、`CompanySeeder` と、`DatabaseSeeder` の呼び出し。招待のテーブル（`team_invitations`）は、共通のテーブルなので残す |
| `tests/Feature/Company/` | 全部 |

`packages/neobit/core/` の共通部品は、消しません。`LoginIdMemory`（ログイン画面の入力の記憶）や、招待の部品（`TeamInvitationManager` ほか）は、今は企業会員だけが使っていますが、企業に限らない部品です。

共通部品の中にも、企業会員の名前を書いた行があります。残しておいても動きますが、消すなら次の所です。

- `AppServiceProvider` の `enforceMorphMap()` の `company`・`company_user`
- `AppOperationLogModel` の `Company`・`CompanyUser`
- `OperationLogAction` の `InvitationSend`・`InvitationCancel`

## 個人会員をログインID でログインさせるサイト

ログインに使う値は、モデルの定数 `LOGIN_ID_TYPE`（`Neobit\Core\Enums\LoginIdType`）で選びます。見本のサイトでは、個人会員はメールアドレス（`Email`）、企業の担当者は、担当者ID でもメールアドレスでも入れる形（`Either`）です。ログインのコントローラーは、`Member::loginIdCondition($入力された値)` で、探す条件（`[列の名前 => 値]`）をモデルに聞きます。定数を変えれば、探す列が切り替わります。個人会員をログインID でログインさせるには、定数のほかに、ログインID の列と、入力させる画面が要ります。直す所は、次のとおりです。

| 直す所 | 中身 | 写す元 |
|---|---|---|
| `t_members` | `login_id` の列を足し、重ならない制約を付ける。メールアドレスの重ならない制約は、そのまま残す | `t_company_users` |
| `Member` | 定数 `LOGIN_ID_TYPE` を、`LoginIdType::LoginId`（ログインID だけ）か `LoginIdType::Either`（ログインID でもメールアドレスでも）にする。`loginId()` を書いて `login_id` を返す | `CompanyUser` |
| ログイン | 入力欄の名前と検証を、ログインID に合わせる。会員を探す列は、`Member::loginIdCondition()` が定数から決めるので、書き換えない | `Company\AuthSessionController::store()` |
| パスワードの再設定 | メールアドレスだけで本人が決まるので、今の画面のままでよい。ログインID も入力させる形にするなら、写す元を見る | `Company\PasswordResetController` |
| 会員登録 | ログインID を入力させ、重なりを確かめる。メールアドレスの重なりも、今までどおり確かめる | `Company\RegistrationController` |
| マイページ・管理画面の会員 | ログインID を変えさせるかを決める。メールアドレスの重なりの検証は、そのまま残す | `Company\ProfileController` |
| 初回のログインでの登録 | メールアドレスが空の会員を移すサイトだけ。会員のデータにある値を1つ（生年月日や電話番号）照合する | `Company\FirstLoginSetupController` |

- ログインID は、個人会員の中で重ならないようにします。**メールアドレスも、今までどおり重ならないようにします**。確認コードとパスワードの再設定のメールが、本人だけに届くようにするためです。既存のシステムのデータに、同じアドレスの会員がいるとき（家族で1つのアドレスを使っている、など）は、持ち込む前に直します。1人を残して、ほかの人の分を空にしておけば、その人たちは初回のログインで自分のアドレスを登録します。
- 「メールアドレスでログインする人」と「ログインID でログインする人」が混ざるデータは、ログインID の列にそろえます。メールアドレスでログインしていた人には、メールアドレスをログインID として入れ、入力欄は「ログインID またはメールアドレス」の1つにします。
- **`LoginIdType::Either` は、入力に `@` があればメールアドレス、無ければログインID として探します**。2つの列を「または」でつないで探すことは、しません。ある人のログインID と、別の人のメールアドレスが同じ文字列だったときに、どちらの人かを決められないためです。そのため、ログインID には `@` を使わせません（企業の担当者ID は `CompanyUser::LOGIN_ID_PATTERN`）。

## 会員の種類をもう1つ足すとき

代理店のような3つ目が要るようになったら、企業会員のモデル・コントローラー・画面・ルートを写して作ります。共通部品は、種類の名前をモデルから聞いて動くので、次の所に名前を足すだけです。

1. モデルに `LoginAccount`・`IsLoginAccount` を書く。種類の名前は、3で `enforceMorphMap()` に書く、モデルの短い名前になる。ガードやルートの頭を、種類の名前と違う名前にするときは、定数 `LOGIN_GUARD`・`LOGIN_ROUTE_PREFIX` を書く。
2. `config/auth.php` に、種類の名前のガードとプロバイダーを足す。古い方式のパスワードを使うなら、`driver` を `eloquent-legacy` にする。
3. `AppServiceProvider` の `enforceMorphMap()` と、`AppOperationLogModel` に足す。操作ログに氏名を出すなら、モデルに `OperationLogNamedModel`（`operationLogNames()`）を付ける。
4. `bootstrap/app.php` の、ログインしていないときの移動先に足す。
5. その種類だけの文面にしたいメールは、テンプレートを、種類の名前を頭に付けて用意する（`種類の名前_verification_code`・`_password_changed`・`_profile_changed`・`_invitation`）。用意しなければ、頭を付けない名前のテンプレートか、フレームワークの既定が使われる（[mail.md](mail.md)）。
6. 古い方式のパスワードを使うなら、`config/neobit.php` の `auth.legacy_passwords` に、種類の名前のキーを足す。種類ごとのサイトの設定が要るなら、`config/site_login.php` に種類の名前のキーを足す。
