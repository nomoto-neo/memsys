# ログイン認証

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**実例**：会員は `AuthSessionController`・`PasswordResetController`・`AuthPasswordController`・`AuthRegisteredMemberController`・`MypageController`、管理は `Admin\AuthSessionController`・`Admin\StaffController`。パスワードが合った後の流れは、どのログインも `TwoFactorLogin` トレイト

認証の画面は遷移が独特なので、FormFlow には載せず、それぞれのコントローラーで書いています。新しく作るより、実例を写して直す方が安全です。

## ガードと画面の区分（サンプルサイトの例）

| | 個人会員 | 企業会員 | 管理画面 |
|---|---|---|---|
| ガード | `web`（`Member`、`t_members`） | `company`（`CompanyUser`、`t_company_users`） | `admin`（`Staff`、`t_staffs`） |
| ログインに使う値 | メールアドレス | 企業ID と、担当者ID またはメールアドレス | ログインID |
| ログインが要る画面 | `Route::middleware(['auth:web', 'auth.session'])` | `Route::middleware(['auth:company', 'auth.session', 'company.approved'])` | `Route::middleware(['auth:admin', 'auth.session'])` |
| ログイン前だけの画面 | `guest`（`guest:web` と同じ） | `guest:company` | `guest:admin` |
| 2段階目の方式 | メールの確認コード | 同じ | 認証アプリ（TOTP） |
| バックアップコード | 希望する人が発行 | 同じ | 同じ |
| 2段階目の省略 | 「この端末を信頼する」 | 同じ | 同じ |

企業会員の `company.approved` は、運営が承認済みの企業の担当者だけを通します。申請中・停止の企業の担当者は、ログイン画面へ戻します（[member-types.md](member-types.md)）。

2段階目の方式は、モデルの定数で選びます（下の「2段階認証」）。表は、見本のサイトでの値です。省略の仕組み（`TrustedDeviceManager::for()`）は、どのログインでも同じです。

未ログインのときの行き先・ログイン済みでゲスト専用画面に来たときの行き先は、`bootstrap/app.php` でルート名（`admin.*`・`company.*` かどうか）から振り分けています。

個人会員と企業会員は、同じ共通部品を、会員のモデルを渡して使い分けます。企業会員だけの仕組み（企業と担当者、承認、招待、移したデータの初回のログイン）と、会員の種類の足し方・外し方は、[member-types.md](member-types.md)にまとめてあります。

## ログインの流れ（会員・管理とも同じ形）

1. ID・パスワードを `Auth::guard(...)->validate()` で確かめる（まだログインはしない）。ここまではコントローラーに書き、合ったら `continueAfterPassword()` を呼ぶ。入力された値で、どの列から人を探すかは、モデルの定数 `LOGIN_ID_TYPE` で決まる（`loginIdCondition()`。[member-types.md](member-types.md)）。
2. 2段階目の方式が「なし」なら、そのままログインを確定する。
3. 信頼済み端末なら、そのままログインを確定する。
4. そうでなければ「パスワード確認済み・2段階目が未完了」をセッションに置き、2段階目の画面へ。2段階目の画面は `guest` にも `auth` にも入れず、トレイトがセッションで守る。
5. 2段階目が通ったら、ログインを確定する。
6. ログインが必要な画面から来た場合はその画面へ、そうでなければ既定の画面（マイページ・管理画面TOP）へ移す。移動先は `LoginRedirect::for(Member::class, route('mypage'))` で決める（2・3・5とパスキーで通ったとき）。2つ目の引数が、既定の画面。

2から5は `TwoFactorLogin` トレイトが受け持ちます。ログインの確定（`Auth::login()` と `session()->regenerate()`）は、どの道を通ってもトレイトの中の1か所で行います。

未ログインでログインの要る画面を開くと、ログインの後に、その画面へ戻します。Laravel 標準の `redirect()->intended()` は、戻り先を1つしか記憶できず、複数の種類のログイン画面を同じブラウザで使うと、正しい戻り先を判別できません。そこで `LoginRedirect` が、戻り先をログインの種類ごとに別々に記憶します。

開こうとした画面がどの種類の画面かは、そのルートの `auth` のミドルウェアから読みます。ログインの必要なルートには、`auth:web`・`auth:admin` のように、ガードの名前を省かずに書きます。

## 2段階認証（TwoFactorLogin・TwoFactorChallenge）

**ファイル**：`packages/neobit/core/src/Enums/TwoFactorMethod.php`、`packages/neobit/core/src/Auth/LoginAccount.php`・`HasTwoFactor.php`・`TwoFactorLogin.php`・`TwoFactorChallenge.php`・`TwoFactorManagement.php`・`TwoFactorAuthenticator.php`・`BackupCodeGenerator.php`・`TrustedDeviceManager.php`、`packages/neobit/core/resources/views/_two_factor.blade.php`・`_two_factor_manage.blade.php`・`_two_factor_backup_codes.blade.php`・`_two_factor_reset.blade.php`、設計の詳細は「2段階認証の設計」（`docs/two-factor-spec.md`）　**実例**：ログインは `AuthSessionController`・`Company\AuthSessionController`・`Admin\AuthSessionController`、本人による管理は `MypageController`・`Company\MypageController`・`Admin\StaffController`、運営による解除は `Admin\MemberController`・`Admin\CompanyUserController`・`Admin\StaffController`

ログインの2段階目の方式は、ログインするモデルの定数で選びます。部品は、相手が会員かスタッフかを知りません。

| 方式（`TwoFactorMethod`） | 2段階目 |
|---|---|
| `Email` | メールで送る確認コード（6桁、10分有効） |
| `Totp` | 認証アプリのコード。未登録の人は、パスワードの後の画面で QR コードを読み取って登録する |
| `None` | 求めない。パスワードが合ったら、そのままログインを確定する |

```php
// モデル。ログインする人の共通の型（LoginAccount）を実装する。会員もスタッフも同じ
class Staff extends Authenticatable implements LoginAccount, PasskeyUser
{
    use HasTwoFactor;

    /** ログインの2段階目の方式 */
    public const TWO_FACTOR_METHOD = TwoFactorMethod::Totp;

    /** バックアップコードを使うか。使うなら、希望する人が自分の画面で発行する */
    public const BACKUP_CODES = true;
}

// ログインのコントローラー
use TwoFactorLogin;
private const LOGIN_ACCOUNT = Staff::class;                    // ガード・ルート・2段階目の方式は、ここから決まる
private const TWO_FACTOR_VIEW = 'admin.auth.login-verify';     // 2段階目の画面
private function loginRedirectUrl(): string                    // ログインが確定した後の移動先
{
    return LoginRedirect::for(self::LOGIN_ACCOUNT, route('admin.dashboard'));
}

// パスワードが合った後は、トレイトに任せる
return $this->continueAfterPassword($request, $staff, $request->boolean('remember'));
```

| アクション | ルート名（管理は `admin.`、企業会員は `company.` が付く） |
|---|---|
| `showVerification()`（GET） | `login.verify` |
| `verifyCode()`（POST） | `login.verify.confirm` |
| `resendCode()`（POST。メールの方式だけ。`throttle` を付ける） | `login.verify.resend` |

- **定数に既定の値はありません**。`TWO_FACTOR_METHOD` と `BACKUP_CODES` を書いていないモデルでログインすると、エラーになります。書き忘れで「なし」になるのを防ぐためです。
- **2段階目の画面のビューは、`@include('neobit::_two_factor')` するだけにします**。中身は方式と状態（コードの入力か、QR コードの登録か）で出し分けます。
- **認証アプリの登録は、共通のテーブル `two_factor_secrets` に持ちます**。バックアップコード（`two_factor_backup_codes`）・信頼済み端末（`trusted_devices`）と同じ扱いで、業務のテーブルには列を足しません。読み書きは、モデルの `hasTwoFactorConfirmed()`・`twoFactorConfirmedAt()`・`registerTwoFactor($secret)`・`resetTwoFactor()` で行います。
- **本人をもう一度確かめるときは `TwoFactorChallenge` を使います**。`send()` で準備（メールの方式なら確認コードを送る）、`verify()` で照合、`throttle()` で試行制限です。方式での場合分けは、部品の中にあります。
- **メールの方式のモデルは、メールアドレスを必須にします**。スタッフのメールアドレスは任意なので、スタッフを `Email` にするときは、検証のルールを必須に変えます。
- **稼働中のサイトで方式を変えたら、その種類の信頼済み端末の記録を消します**（`trusted_devices` の、`authenticatable_type` がその種類の行）。
- **スタッフ向けの確認コードのメールは `staff_verification_code` です**。スタッフを `Email` にしたときに使います。スタッフの登録・編集の画面では、方式が `Email` の間、メールアドレスが必須になります。

### バックアップコード

バックアップコードは、2段階目を通れないときに、その代わりに1回ずつ使える緊急用のコードです。認証アプリの入った端末が手元に無いときと、確認コードのメールが届かないときに使います。

- **使うかどうかは、モデルの定数 `BACKUP_CODES` で選びます**。2段階目を通る手段が1つ増えるので、種類ごとに選べるようにしています。方式が `None` のモデルでは、値に関係なく使いません。
- **自動では発行しません**。希望する人が、ログイン後の自分の画面（下の「本人による管理」）で発行します。
- **発行の前に、その人の方式で本人を確かめます**。`Totp` は今の認証アプリのコード、`Email` はメールで送る確認コードです。
- **入力欄は、発行してある人にだけ出ます**。ログインの2段階目と、解除の確認の画面です。
- **`false` にしたモデルでは、2段階目を通れなくなった人は運営が解除します**。画面から解除できる人がいないときは、コマンドを使います（下の「サーバーからの解除」）。

### 本人による管理（TwoFactorManagement）

ログイン中の本人が、バックアップコードを発行し、認証アプリの登録を解除する画面です。入口は、方式が `Totp` のときと、バックアップコードを使うときに出ます。

```php
// 本人の情報を扱うコントローラー
use TwoFactorManagement;
private const TWO_FACTOR_GUARD = 'web';                                         // ログイン中の本人を取るガード
private const TWO_FACTOR_MANAGE_VIEW = 'mypage.two-factor';                     // 管理の画面。_two_factor_manage を @include する
private const TWO_FACTOR_BACKUP_CODES_VIEW = 'mypage.two-factor-backup-codes';  // バックアップコードを1回だけ見せる画面
private function twoFactorManageUrl(LoginAccount $owner): string                // 操作の後の戻り先
{
    return route('mypage.twoFactor');
}
private function twoFactorHomeUrl(): string                                     // バックアップコードを見た後の移動先
{
    return route('mypage');
}
```

| アクション | ルート名（管理は `admin.`、企業会員は `company.` が付く） |
|---|---|
| `twoFactorIndex()`（GET。会員だけ。スタッフは自分の詳細の画面に出している） | `mypage.twoFactor` |
| `showNewBackupCodes()`（GET） | `twoFactor.backupCodes` |
| `sendBackupCodesCode()`（POST。メールの方式だけ。`throttle` を付ける） | `twoFactor.backupCodes.code` |
| `regenerateBackupCodes()`（POST） | `twoFactor.regenerateBackupCodes` |
| `selfReset()`（DELETE） | `twoFactor.selfReset` |

- **発行と解除の前に、本人をもう一度確かめます**。ログイン中の画面を他人に使われたときに、被害を広げないためです。
- **解除は、バックアップコードでもできます**。端末を無くした人が、発行してあったバックアップコードでログインした後に、別のバックアップコードで自分で解除できるようにするためです。解除は操作ログに残ります。
- **解除すると、バックアップコード・信頼済み端末・パスキーも消えます**（`$account->resetTwoFactor()`）。パスキーでのログインは2段階目を求めないので、残すと登録し直さないまま使えてしまうためです。
- **端末を無くし、バックアップコードも無い人は、運営が解除します**。管理画面の詳細に `@include('neobit::_two_factor_reset', ['twoFactorAccount' => $member, 'twoFactorResetUrl' => route(...)])` を置き、コントローラーで `$member->resetTwoFactor()` を呼びます。ボタンは、方式が `Totp` で登録が済んでいるときだけ出ます。

### サーバーからの解除（neobit:reset-two-factor）

画面から解除できる人がいないときに、サーバーで実行します。スタッフが1人だけのサイトで、その人が端末を無くしたときなどです。

```
php artisan neobit:reset-two-factor staff yamada
```

1つ目は種類の名前（`staff`・`member`・`company_user`）、2つ目はログインに使う値です。企業の担当者は `企業ID/ログインID` と書きます。対象の人を出して、確かめてから解除します。消えるものは、画面からの解除と同じです。

### テストで方式を差し替える

方式はモデルの定数なので、実行中には変えられません。テストでは、トレイト `Tests\Support\FakesTwoFactorMethod` を使って差し替えます。テストが終わると、元に戻ります。

```php
use FakesTwoFactorMethod;

$this->useTwoFactorMethod(Staff::class, TwoFactorMethod::Email);
$this->useBackupCodes(Staff::class, false);    // バックアップコードを使うかも、同じように差し替える
```

## ログインする人の共通の型（LoginAccount）

ログインするモデル（`Member`・`CompanyUser`・`Staff`）は、共通の型 `Neobit\Core\Auth\LoginAccount` を実装し、トレイト `IsLoginAccount` を付けます。認証の共通部品は、相手を `Member` や `Staff` の名指しではなく、この型で受け取ります。ガード・ルート・メールのテンプレート・信頼済み端末の Cookie の名前は、モデルの「種類の名前」（モデルの短い名前。`member`・`company_user`・`staff`）から、決まりのとおりに作ります。名前の決まりと、モデル・コントローラーに書くものは、[member-types.md](member-types.md)にあります。

## 古い方式のパスワード（LegacyPasswordUserProvider）

**ファイル**：`packages/neobit/core/src/Auth/LegacyPasswordUserProvider.php`（冒頭のコメント）・`packages/neobit/core/src/Auth/LegacyPassword.php`・`packages/neobit/core/src/Auth/Legacy/`・`config/login.php`

既存のシステムから会員を移したサイトだけで使います。既存のシステムのパスワードは、MD5 や SHA-1 のような古い方式のハッシュ値で持っていることが多く、元のパスワードには戻せません。古いハッシュ値のまま移し、本人が次にログインしたときに、今の方式（bcrypt）に置き換えます。会員にパスワードを決め直してもらう必要はありません。

**移すとき**

古いハッシュ値を、今の方式でもう一度ハッシュ値にして、`legacy_password` の列に入れます。`password` の列は空にします。古い方式のハッシュ値は短い時間で破られるので、新しいシステムの DB には、そのままでは置きません。

```php
$member->forceFill([
    'password' => null,
    'legacy_password' => LegacyPasswordUserProvider::wrap($oldHash),   // $oldHash は既存のシステムのハッシュ値
])->save();
```

**設定**（`config/neobit.php` の `auth` の節。ログインする人の種類の名前ごとに書く）

```php
'auth' => [
    'legacy_passwords' => [
        'member' => [
            Neobit\Core\Auth\Legacy\Md5Password::class,
            Neobit\Core\Auth\Legacy\Sha1Password::class,
        ],
    ],
],
```

- 書いた順に試します。途中で方式を変えたシステムのデータも、並べておけば通ったものを採ります。
- ソルトの無い MD5・SHA-1・SHA-256 は、`Neobit\Core\Auth\Legacy` に用意してあります。
- ソルトを付ける、何度も繰り返す、といったそのサイトだけの方式は、`LegacyPassword` を実装したクラスを書いて、設定に足します。メソッドは「入力されたパスワードと会員を受け取って、古い方式のハッシュ値を返す」の1つです。会員を受け取るので、会員ごとのソルトの列も読めます。
- **新しく始めるサイトは、書かずに既定の空のままにします**。古い方式は、何も働きません。

**決まりごと**

- 照合は、Laravel がパスワードを確かめる所（`config/auth.php` の会員のプロバイダー。`driver` が `eloquent-legacy`）に入れてあります。ログインのコントローラーには、何も書きません。
- 通ったら、入力されたパスワードを今の方式で `password` に保存し、`legacy_password` を消します。次からは、標準の照合です。
- 今の方式のパスワードを持っている会員は、`legacy_password` に値があっても、今のパスワードだけで照合します。
- パスワードを変えるか再設定すると、`legacy_password` は消えます（`PasswordChange`）。
- `legacy_password` が残っている会員は、まだ一度もログインしていない会員です。件数は `Member::whereNotNull('legacy_password')->count()` で数えられます。
- `LegacyPasswordUserProvider::wrap()` は、1件ごとに時間がかかります（今の方式が、わざと時間をかける作りのため）。件数が多いときは、データの取り込みとは別に進めます。
- 包んでおくことは、既に漏れているパスワードには効きません。新しいシステムの DB が漏れたときに、古いハッシュ値から短い時間でパスワードを割り出されることを防ぐ、という備えです。

## ログアウト（LoginSession）

ログアウトは `LoginSession::logout($request, ガードの名前)` で行います。ログインのコントローラーは、`TwoFactorLogin` トレイトの `logoutAccount()` を呼びます。

- **そのガードのログインだけを終わらせます**。同じブラウザのほかのガードのログイン（会員と管理画面）は残ります。運営のスタッフが、会員の側の画面を確かめながら管理画面で操作する、といった使い方のためです。
- **ログアウトした人がセッションに残したものは、全部消えます**（検索条件、確認コードの仮置きなど）。残したほかのガードの側でも、検索条件などは消えます。
- `$request->session()->invalidate()` を直接呼ぶと、ほかのガードのログインも切れます。ログアウトの処理には使いません。

## 試行制限（LoginThrottle）

```php
/** コントローラーの定数。値はほかのコントローラーと重ならない名前にする */
private const THROTTLE_SCOPE = 'member-login';

$throttle = new LoginThrottle(self::THROTTLE_SCOPE, $request->ip(), $credentials['email']);
if ($throttle->isBlocked()) {
    throw ValidationException::withMessages(['email' => $throttle->blockedMessage('ログイン')]);
}
// 失敗したら $throttle->hit()、成功したら $throttle->clear()
```

失敗した回数だけを、IP 単位とアカウント単位の2本で数えます。使われている scope は、コントローラーを `THROTTLE_SCOPE` で検索すると一覧できます。メール送信を伴うルート（確認コードの再送など）には、ルートの `throttle` を付けます。

## 確認コード（LoginVerificationCode）

ログインする人の種類は、作るときにモデルで渡します（`new LoginVerificationCode(Member::class)`）。会員にも、スタッフにも使います。メールのテンプレートとセッションのキーは、種類の名前から決まります。

用途（`PURPOSE_LOGIN`・`PURPOSE_PASSWORD_RESET`・`PURPOSE_MYPAGE_PASSWORD`・`PURPOSE_REGISTER`・`PURPOSE_PASSKEY`・`PURPOSE_BACKUP_CODES`・`PURPOSE_FIRST_LOGIN`・`PURPOSE_EMAIL_CHANGE`）ごとにセッションで管理します。`issue()` で発行とメール送信、`verify()` で照合します。会員登録のようにまだ会員がいない場合と、初回のログインでの登録やメールアドレスの変更のようにメールアドレスがまだ保存されていない場合は、`issueForAddress()`・`verifyForAddress()` です。

案件の側で決めた使い道（メルマガの登録など）にも使えます。使い道の文字列は案件の側で決め、メールの件名と本文に差し込む名前を `purposeLabel:` で渡します。使い道ごとにセッションのキーが分かれるので、ほかの画面のコードとは混ざりません。

```php
(new LoginVerificationCode(Member::class))->issueForAddress($request, $email, $name, 'newsletter', purposeLabel: 'メルマガの登録');
// 件名は「【サイト名】メルマガの登録のお手続き」。照合は verifyForAddress($request, 'newsletter', $code)
```

## メールアドレスの変更の確認（EmailChange）

**ファイル**：`packages/neobit/core/src/Auth/EmailChange.php`　**実例**：`MypageController`・`Company\ProfileController`、画面は `mypage/email-verify.blade.php`・`company/mypage/profile-email-verify.blade.php`

本人が自分の情報を保存するときに、メールアドレスが変わる場合だけ、確認コードの入力を挟みます。入力内容をセッションに仮置きして、新しいアドレスに確認コードを送り、コードが入力できた時点で、仮置きした内容をまとめて保存します。打ち間違えたアドレスに変えてログインできなくなることと、ログイン中の画面を使った他人が自分のアドレスに書き換えることを防ぎます。メールアドレスが変わらない保存は、今までどおりすぐ保存します。

```php
// 本人の情報を FormFlow で保存しているコントローラー
use EmailChange;
private const EMAIL_CHANGE_GUARD = 'web';                               // ログイン中の本人を取るガード
private const EMAIL_CHANGE_ROUTE = 'mypage.email';                      // 確認コードの入力画面のルート名
private const EMAIL_CHANGE_VIEW = 'mypage.email-verify';                // 確認コードの入力画面のビュー
private const EMAIL_CHANGE_EDIT_ROUTE = 'mypage.edit';                  // 入力画面のルート名
private const EMAIL_CHANGE_DONE_ROUTE = 'mypage';                       // 保存の後の移動先のルート名
private const EMAIL_CHANGE_DONE_MESSAGE = 'プロフィールを更新しました。';  // 保存の後に出すメッセージ
private const EMAIL_CHANGE_THROTTLE_SCOPE = 'member-email-change-code'; // 確認コードの試行制限

public function update(Request $request): RedirectResponse
{
    $member = Auth::user();

    // メールアドレスが変わるときは、保存せずに確認コードの入力画面へ進む
    $toVerify = $this->holdForEmailChange($request, $member);

    if ($toVerify !== null) {
        return $toVerify;
    }

    $this->saveData($member, $request);
    // ...
}
```

| アクション | ルート名 |
|---|---|
| `emailChangeForm()`（GET）・`emailChangeConfirm()`（POST）・`emailChangeResend()`（POST）・`emailChangeBack()`（POST） | `EMAIL_CHANGE_ROUTE` と、その後ろに `.confirm`・`.resend`・`.back`。`.resend` には `throttle` を付ける |

- 保存は `FormFlow` の `saveValidated()` を通ります。操作ログと `afterSave()`（お知らせのメール）は、確認を挟まない保存と同じです。お知らせは、変わる前と後の両方のアドレスに届きます（[operation-log.md](operation-log.md)）。
- 仮置きした内容は、保存の前にもう一度 `rules()` で検証します。コードの入力を待つ間に、同じアドレスが別の会員に使われたときは、入力画面へ戻します。
- 確認コードを送れる回数は、1人につき1時間に5回までです（`EmailChange` の定数）。新しいアドレスは自由に入力できるので、他人のアドレスに大量に送りつけられるのを防ぎます。
- 確認コードの入力画面の「入力内容を修正する」は、仮置きを消して、入力内容を持って入力画面へ戻します。
- 対象は、本人が自分で変える画面だけです。管理画面からスタッフが変えるときと、企業会員でほかの担当者が変えるとき（`Company\UserController`）は、変える人が新しいアドレスのメールを受け取れないので、確認を挟みません。

## パスワードを変えるとき

- `auth.session` が、パスワードを変えたときにほかの端末のログインと「ログイン状態を保持する」の Cookie を無効にします。
- パスワードの更新は、必ず「ログイン中のユーザーのインスタンス」に対して行います（別のインスタンスを更新すると本人までログアウトされます）。別のインスタンスを保存した場合は `Auth::guard(...)->setUser($record)` で差し替えます（実例：`StaffController::afterSave()`）。
- パスワードを変えたら、保存の直後に `PasswordChange::resetAndNotify($record, changedBy: 管理画面から変えたスタッフ)` を呼びます（トランザクションの中でよい）。信頼済み端末とパスキーをすべて無効にし、確定後に登録されているメールアドレスへお知らせを送ります（テンプレートは `member_password_changed`・`company_user_password_changed`・`staff_password_changed`）。スタッフの2段階認証（TOTP）は消しません。戻り値は削除したパスキーの件数で、画面のメッセージに使えます。お問い合わせの画面のように、サイトごとに違う案内の URL は、テンプレートの中に `{!! route('contact.create') !!}` のように書きます。

## 認証まわりのテーブル

信頼済み端末（`trusted_devices`）・認証アプリの登録（`two_factor_secrets`）・バックアップコード（`two_factor_backup_codes`）・パスキー（`passkeys`）のようなフレームワーク内部のテーブルは、会員・スタッフで共通の1つにし、`authenticatable_type`（`'member'`・`'company_user'`・`'staff'`）と `authenticatable_id` で区別します。新しい認証対象のモデルを足したら、`AppServiceProvider` の `Relation::enforceMorphMap()` にも足します。

## パスキー（PasskeyLogin・PasskeyManagement）

**ファイル**：`packages/neobit/core/src/Auth/PasskeyLogin.php`・`PasskeyManagement.php`・`PasskeyCeremony.php`・`HasPasskeys.php`・`UserAgentLabel.php`、`packages/neobit/core/src/Models/Passkey.php`、`packages/neobit/core/resources/views/_passkeys.blade.php`、`resources/js/passkeys.js`、設計の詳細は「パスキーの設計」（`docs/passkeys-spec.md`）　**実例**：会員は `AuthSessionController`・`MypageController`、管理は `Admin\AuthSessionController`・`Admin\StaffController`

ログイン画面の「パスキーでログイン」で通ったときは、パスワードも2段階目も求めずにログインさせます。登録の前の本人確認は、その人のログインの2段階目の方式（メールの確認コードか、認証アプリのコード）で行います。2段階目が「なし」の人は、パスワードの再入力で確かめます。トレイトが `TwoFactorChallenge` に任せるので、方式での場合分けは要りません。

パッケージ（composer の `laravel/passkeys`、npm の `@laravel/passkeys`）・`passkeys` テーブル・モデルの `implements PasskeyUser` と `use HasPasskeys` は、標準構成として常に入れておきます。使うかどうかは、次のコントローラーの `use` とルートで切り替えます。画面の入口（ログイン画面のボタン、マイページ・スタッフ詳細のリンク）は `Route::has()` で出し分けているので、ルートを消せば消えます。

```php
// ログインのコントローラー
use PasskeyLogin;
private const PASSKEY_GUARD = 'web';                  // ログインさせるガード
private function passkeyRedirectUrl(): string         // ログイン後の移動先
{
    return LoginRedirect::for(Member::class, route('mypage'));
}

// 本人のパスキーを扱うコントローラー
use PasskeyManagement;
private const PASSKEY_GUARD = 'web';                           // ログイン中の本人を取るガード
private const PASSKEY_ROUTE = 'mypage.passkeys';               // 一覧画面のルート名
private const PASSKEY_VIEW = 'mypage.passkeys';                // 一覧画面のビュー
```

| トレイト | アクション | ルート名 |
|---|---|---|
| `PasskeyLogin` | `passkeyLoginOptions()`（GET）・`passkeyLogin()`（POST） | `login.passkey.options`・`login.passkey`（管理は `admin.` を付ける。ゲスト用のグループに入れ、`throttle` を付ける） |
| `PasskeyManagement` | `passkeyIndex()`（GET）・`passkeySendCode()`（POST。メールの方式だけ。`throttle` を付ける）・`passkeyConfirm()`（POST）・`passkeyRegistrationOptions()`（GET）・`passkeyStore()`（POST）・`passkeyDestroy()`（DELETE） | `PASSKEY_ROUTE` と、その後ろに `.code`・`.confirm`・`.options`・`.store`・`.destroy` |

- 一覧画面のビューは、`@push('head-extra')` で CSRF の `<meta>` と `vendor/neobit/core/resources/js/passkeys.js` を読み込み、`@include('neobit::_passkeys')` するだけにします。
- 本人確認の失敗は、ログインの2段階目と同じカウンターで数えます（`TwoFactorChallenge::throttle()`）。
- 認証アプリの方式の人は、認証アプリを登録してある間だけパスキーを持てます。未登録の間は、登録に進めません。
- パスキーは `APP_URL` のドメインに結び付きます。`APP_URL` をブラウザで開いているアドレスと完全に同じにし、HTTPS（開発時の `localhost` は例外）で使います。
- **登録の案内**：パスキーを持っていない人に、パスワードでログインした直後の画面の上で、登録を1回だけ勧めます（`PasskeySuggestion`・`_passkey_suggest.blade.php`）。パスキーの一覧画面のルートがあれば出て、無ければ出ません。見せたかどうかは、DB ではなくブラウザの Cookie で覚えます。パスワードを変えたときと、認証アプリを登録し直したときは、もう1回出ます。
- 退会と2段階認証の登録解除では、`$record->passkeys()->delete()` でパスキーも消します。パスワードを変えたときは `PasswordChange` が消します。
