<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * ログイン画面の入力を、ブラウザに覚えさせる。企業会員の「企業IDと担当者IDを記憶する」で使う。
 * 次にログイン画面を開いたときに、覚えた値を入力済みで出すので、パスワードを入れるだけで済む。
 *
 * 覚えるのは、ログインに使うIDだけ。パスワードは覚えない（ブラウザのパスワードの保存に任せる）。
 * 値はCookieに置く。Laravelが暗号化するので、中身はブラウザからは読めない。
 *
 * 「ログイン状態を保持する」「このデバイスを記憶する」とは別のもの。こちらは、入力の手間を
 * 省くだけで、ログインそのものや2段階目は省かない。
 */
final class LoginIdMemory
{
    /** 覚えておく日数 */
    private const VALID_DAYS = 365;

    /**
     * @param  string  $cookieName  Cookieの名前。ログイン画面ごとに変える
     */
    public function __construct(private readonly string $cookieName)
    {
    }

    /**
     * 値を覚える。チェックが無いときは、覚えていた値を消す。
     *
     * @param  array<string, string>  $ids  入力欄の名前 => 値
     */
    public function store(bool $remember, array $ids): void
    {
        if (! $remember) {
            Cookie::queue(Cookie::forget($this->cookieName));

            return;
        }

        // 有効期間は「分」単位。引数に名前を付けて渡さないこと（TrustedDeviceManager::remember()参照）
        Cookie::queue($this->cookieName, json_encode($ids), self::VALID_DAYS * 24 * 60);
    }

    /**
     * 覚えている値。入力欄の名前 => 値。覚えていなければ空の配列。
     *
     * @return array<string, string>
     */
    public function recall(Request $request): array
    {
        $ids = json_decode((string) $request->cookie($this->cookieName), true);

        return is_array($ids) ? array_filter($ids, 'is_string') : [];
    }
}
