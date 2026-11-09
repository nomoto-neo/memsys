# ログイン認証

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**実例**：個人会員の `AuthSessionController`（ログイン）・`PasswordResetController`（パスワードの再設定）・`MypageController`（本人の情報）。企業会員は `Company\`、管理画面は `Admin\` の下に、同じ形のコントローラーがあります。

認証の画面は遷移が独特なので、FormFlow には載せず、それぞれのコントローラーで書いています。新しく作るより、実例を写して直す方が安全です。

## 決めること

| 決めること | 決める場所 | 説明 |
|---|---|---|
| ログインに使う値 | モデルの定数 `LOGIN_ID_TYPE` | [member-types.md](member-types.md) |
| 2段階目の方式 | モデルの定数 `TWO_FACTOR_METHOD` | [two-factor.md](two-factor.md) |
| バックアップコードを使うか | モデルの定数 `BACKUP_CODES` | [two-factor.md](two-factor.md) |
| パスキーを使うか | コントローラーの `use` とルート | [two-factor.md](two-factor.md) |
| ログインした後の移動先 | コントローラーの `loginRedirectUrl()` | 下の「ログインの流れ」 |
| 古い方式のパスワードを受け付けるか | `config/neobit.php` の `auth.legacy_passwords` | [legacy-password.md](legacy-password.md) |

## ガードと画面の区分（見本のサイトの例）

| | 個人会員 | 企業会員 | 管理画面 |
|---|---|---|---|
| ガード | `web`（`Member`、`t_members`） | `company`（`CompanyUser`、`t_company_users`） | `admin`（`Staff`、`t_staffs`） |
| ログインに使う値 | メールアドレス | 企業ID と、担当者ID またはメールアドレス | ログインID |
| ログインが要る画面 | `Route::middleware(['auth:web', 'auth.session'])` | `Route::middleware(['auth:company', 'auth.session', 'company.approved'])` | `Route::middleware(['auth:admin', 'auth.session'])` |
| ログイン前だけの画面 | `guest`（`guest:web` と同じ） | `guest:company` | `guest:admin` |

企業会員の `company.approved` は、運営が承認済みの企業の担当者だけを通します。申請中・停止の企業の担当者は、ログイン画面へ戻します（[member-types.md](member-types.md)）。

未ログインのときの行き先・ログイン済みでゲスト専用画面に来たときの行き先は、`bootstrap/app.php` でルート名（`admin.*`・`company.*` かどうか）から振り分けています。

ログインするモデルは、共通の型 `LoginAccount` を実装します。認証の共通部品は、相手を `Member` や `Staff` の名指しではなく、この型で受け取ります。個人会員と企業会員は、同じ共通部品を、会員のモデルを渡して使い分けます。名前の決まり、モデル・コントローラーに書くもの、企業会員だけの仕組み、会員の種類の足し方・外し方は、[member-types.md](member-types.md)にまとめてあります。

## ログインの流れ（会員・管理とも同じ形）

1. ID・パスワードを `Auth::guard(...)->validate()` で確かめる（まだログインはしない）。ここまではコントローラーに書き、合ったら `continueAfterPassword()` を呼ぶ。入力された値で、どの列から人を探すかは、モデルの定数 `LOGIN_ID_TYPE` で決まる（`loginIdCondition()`。[member-types.md](member-types.md)）。
2. 2段階目の方式が「なし」なら、そのままログインを確定する。
3. 信頼済み端末なら、そのままログインを確定する。
4. そうでなければ「パスワード確認済み・2段階目が未完了」をセッションに置き、2段階目の画面へ。2段階目の画面は `guest` にも `auth` にも入れず、トレイトがセッションで守る。
5. 2段階目が通ったら、ログインを確定する。
6. ログインが必要な画面から来た場合はその画面へ、そうでなければ既定の画面（マイページ・管理画面TOP）へ移す。移動先は `LoginRedirect::for(Member::class, route('mypage'))` で決める（2・3・5とパスキーで通ったとき）。2つ目の引数が、既定の画面。

2から5は `TwoFactorLogin` トレイトが受け持ちます（[two-factor.md](two-factor.md)）。ログインの確定（`Auth::login()` と `session()->regenerate()`）は、どの道を通ってもトレイトの中の1か所で行います。

```php
// ログインのコントローラー
use TwoFactorLogin;
private const LOGIN_ACCOUNT = Staff::class;                    // ガード・ルート・2段階目の方式は、ここから決まる
private function loginRedirectUrl(): string                    // ログインが確定した後の移動先
{
    return LoginRedirect::for(self::LOGIN_ACCOUNT, route('admin.dashboard'));
}

// パスワードが合った後は、トレイトに任せる
return $this->continueAfterPassword($request, $staff, $request->boolean('remember'));
```

未ログインでログインの要る画面を開くと、ログインの後に、その画面へ戻します。戻り先は、`LoginRedirect` がログインの種類ごとに別々に記憶します。Laravel 標準の `redirect()->intended()` は、戻り先を1つしか記憶できず、複数の種類のログインを同じブラウザで使うと取り違えるので、使いません。

開こうとした画面がどの種類の画面かは、そのルートの `auth` のミドルウェアから読みます。ログインの必要なルートには、`auth:web`・`auth:admin` のように、ガードの名前を省かずに書きます。

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

## パスワードを変えるとき

- `auth.session` が、パスワードを変えたときにほかの端末のログインと「ログイン状態を保持する」の Cookie を無効にします。
- パスワードの更新は、必ず「ログイン中のユーザーのインスタンス」に対して行います（別のインスタンスを更新すると本人までログアウトされます）。別のインスタンスを保存した場合は `Auth::guard(...)->setUser($record)` で差し替えます（実例：`StaffController::afterSave()`）。
- パスワードを変えたら、保存の直後に `PasswordChange::resetAndNotify($record, changedBy: 管理画面から変えたスタッフ)` を呼びます（トランザクションの中でよい）。信頼済み端末とパスキーをすべて無効にし、確定後に登録されているメールアドレスへお知らせを送ります（テンプレートは `member_password_changed`・`company_user_password_changed`・`staff_password_changed`）。スタッフの2段階認証（TOTP）は消しません。戻り値は削除したパスキーの件数で、画面のメッセージに使えます。お問い合わせの画面のように、サイトごとに違う案内の URL は、テンプレートの中に `{!! route('contact.create') !!}` のように書きます。

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
