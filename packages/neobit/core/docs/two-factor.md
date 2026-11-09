# 2段階認証とパスキー

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**実例**：ログインは `AuthSessionController`、本人による管理は `MypageController`・`Admin\StaffController`、運営による解除は `Admin\MemberController`。企業会員と管理画面にも、同じ形のコントローラーがあります。設計の詳細は「2段階認証の設計」（`docs/two-factor-spec.md`）と「パスキーの設計」（`docs/passkeys-spec.md`）

## 2段階目の方式

ログインの2段階目の方式は、ログインするモデルの定数で選びます。部品は、相手が会員かスタッフかを知りません。

| 方式（`TwoFactorMethod`） | 2段階目 |
|---|---|
| `Email` | メールで送る確認コード（6桁、10分有効） |
| `Totp` | 認証アプリのコード。未登録の人は、パスワードの後の画面で QR コードを読み取って登録する |
| `None` | 求めない。パスワードが合ったら、そのままログインを確定する |

見本のサイトでは、個人会員と企業会員が `Email`、スタッフが `Totp` です。バックアップコードと「この端末を信頼する」（2段階目の省略）は、どれも使います。省略の仕組み（`TrustedDeviceManager::for()`）は、どのログインでも同じです。

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
```

- **定数に既定の値はありません**。`TWO_FACTOR_METHOD` と `BACKUP_CODES` を書いていないモデルでログインすると、エラーになります。書き忘れで「なし」になるのを防ぐためです。
- **本人をもう一度確かめるときは `TwoFactorChallenge` を使います**。`send()` で準備（メールの方式なら確認コードを送る）、`verify()` で照合、`throttle()` で試行制限です。方式での場合分けは、部品の中にあります。
- **メールの方式のモデルは、メールアドレスを必須にします**。スタッフのメールアドレスは任意なので、スタッフを `Email` にするときは、検証のルールを必須に変えます。スタッフの登録・編集の画面では、方式が `Email` の間、メールアドレスが必須になります。スタッフ向けの確認コードのメールは `staff_verification_code` です。
- **稼働中のサイトで方式を変えたら、その種類の信頼済み端末の記録を消します**（`trusted_devices` の、`authenticatable_type` がその種類の行）。

## ログインのコントローラーに書くもの

ログインのコントローラー（[login.md](login.md) の「ログインの流れ」）に、2段階目の画面のビューを足します。

```php
private const TWO_FACTOR_VIEW = 'admin.auth.login-verify';     // 2段階目の画面
```

| アクション | ルート名（管理は `admin.`、企業会員は `company.` が付く） |
|---|---|
| `showVerification()`（GET） | `login.verify` |
| `verifyCode()`（POST） | `login.verify.confirm` |
| `resendCode()`（POST。メールの方式だけ。`throttle` を付ける） | `login.verify.resend` |

- **2段階目の画面のビューは、`@include('neobit::_two_factor')` するだけにします**。中身は方式と状態（コードの入力か、QR コードの登録か）で出し分けます。

## バックアップコード

バックアップコードは、2段階目を通れないときに、その代わりに1回ずつ使える緊急用のコードです。認証アプリの入った端末が手元に無いときと、確認コードのメールが届かないときに使います。

- **使うかどうかは、モデルの定数 `BACKUP_CODES` で選びます**。2段階目を通る手段が1つ増えるので、種類ごとに選べるようにしています。方式が `None` のモデルでは、値に関係なく使いません。
- **自動では発行しません**。希望する人が、ログイン後の自分の画面（下の「本人による管理」）で発行します。
- **発行の前に、その人の方式で本人を確かめます**。`Totp` は今の認証アプリのコード、`Email` はメールで送る確認コードです。
- **入力欄は、発行してある人にだけ出ます**。ログインの2段階目と、解除の確認の画面です。
- **`false` にしたモデルでは、2段階目を通れなくなった人は運営が解除します**（下の「運営による解除」）。

## 本人による管理（TwoFactorManagement）

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

## 運営による解除

端末を無くし、バックアップコードも無い人は、運営が解除します。管理画面の詳細に `@include('neobit::_two_factor_reset', ['twoFactorAccount' => $member, 'twoFactorResetUrl' => route(...)])` を置き、コントローラーで `$member->resetTwoFactor()` を呼びます。ボタンは、方式が `Totp` で登録が済んでいるときだけ出ます。

画面から解除できる人がいないときは、サーバーで `neobit:reset-two-factor` を実行します。スタッフが1人だけのサイトで、その人が端末を無くしたときなどです。

```
php artisan neobit:reset-two-factor staff yamada
```

1つ目は種類の名前（`staff`・`member`・`company_user`）、2つ目はログインに使う値です。企業の担当者は `企業ID/ログインID` と書きます。対象の人を出して、確かめてから解除します。消えるものは、画面からの解除と同じです。

## パスキー（PasskeyLogin・PasskeyManagement）

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
- 退会と2段階認証の登録解除では、`$record->passkeys()->delete()` でパスキーも消します。パスワードを変えたときは `PasswordChange` が消します（[login.md](login.md)）。

## 認証まわりのテーブル

信頼済み端末（`trusted_devices`）・認証アプリの登録（`two_factor_secrets`）・バックアップコード（`two_factor_backup_codes`）・パスキー（`passkeys`）は、会員・スタッフで共通の1つのテーブルにし、`authenticatable_type`（`'member'`・`'company_user'`・`'staff'`）と `authenticatable_id` で区別します。業務のテーブルには列を足しません。

- 認証アプリの登録の読み書きは、モデルの `hasTwoFactorConfirmed()`・`twoFactorConfirmedAt()`・`registerTwoFactor($secret)`・`resetTwoFactor()` で行います。
- 新しい認証対象のモデルを足したら、`AppServiceProvider` の `Relation::enforceMorphMap()` にも足します。

## テストで方式を差し替える

方式はモデルの定数なので、実行中には変えられません。テストでは、トレイト `Tests\Support\FakesTwoFactorMethod` を使って差し替えます。テストが終わると、元に戻ります。

```php
use FakesTwoFactorMethod;

$this->useTwoFactorMethod(Staff::class, TwoFactorMethod::Email);
$this->useBackupCodes(Staff::class, false);    // バックアップコードを使うかも、同じように差し替える
```
