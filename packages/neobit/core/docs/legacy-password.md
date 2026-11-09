# 古い方式のパスワード（LegacyPasswordUserProvider）

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`packages/neobit/core/src/Auth/LegacyPasswordUserProvider.php`（冒頭のコメント）・`packages/neobit/core/src/Auth/LegacyPassword.php`・`packages/neobit/core/src/Auth/Legacy/`・`config/neobit.php` の `auth`

既存のシステムから会員を移したサイトだけで使います。既存のシステムのパスワードは、MD5 や SHA-1 のような古い方式のハッシュ値で持っていることが多く、元のパスワードには戻せません。古いハッシュ値のまま移し、本人が次にログインしたときに、今の方式（bcrypt）に置き換えます。会員にパスワードを決め直してもらう必要はありません。

## 移すとき

古いハッシュ値を、今の方式でもう一度ハッシュ値にして、`legacy_password` の列に入れます。`password` の列は空にします。古い方式のハッシュ値は短い時間で破られるので、新しいシステムの DB には、そのままでは置きません。

```php
$member->forceFill([
    'password' => null,
    'legacy_password' => LegacyPasswordUserProvider::wrap($oldHash),   // $oldHash は既存のシステムのハッシュ値
])->save();
```

## 設定（`config/neobit.php` の `auth` の節。ログインする人の種類の名前ごとに書く）

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

## 決まりごと

- 照合は、Laravel がパスワードを確かめる所（`config/auth.php` の会員のプロバイダー。`driver` が `eloquent-legacy`）に入れてあります。ログインのコントローラーには、何も書きません。
- 通ったら、入力されたパスワードを今の方式で `password` に保存し、`legacy_password` を消します。次からは、標準の照合です。
- 今の方式のパスワードを持っている会員は、`legacy_password` に値があっても、今のパスワードだけで照合します。
- パスワードを変えるか再設定すると、`legacy_password` は消えます（`PasswordChange`）。
- `legacy_password` が残っている会員は、まだ一度もログインしていない会員です。件数は `Member::whereNotNull('legacy_password')->count()` で数えられます。
- `LegacyPasswordUserProvider::wrap()` は、1件ごとに時間がかかります（今の方式が、わざと時間をかける作りのため）。件数が多いときは、データの取り込みとは別に進めます。
- 包んでおくことは、既に漏れているパスワードには効きません。新しいシステムの DB が漏れたときに、古いハッシュ値から短い時間でパスワードを割り出されることを防ぐ、という備えです。
