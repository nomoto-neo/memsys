<?php

namespace Neobit\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Route;

/**
 * URLの直下（/{slug}）に置く名前が、ほかの画面のURLと重ならないかの検証ルール。名前の最初の区切りが、
 * ほかのルートのURLの最初の区切りと同じなら断る。
 *
 * URLの直下のルートは、ほかの全部のルートの後ろで受けるので、たとえばloginという名前を作っても、
 * ログイン画面が先に当たって開けないため。admin/helpのように下に続く名前も、その画面のURLと紛らわしく、
 * 後から同じURLの画面を足すと開けなくなるので、同じく断る。ルートの一覧から調べるので、画面を足しても直す所は無い。
 * /news/{slug}のように、頭の付いたURLに置くコーナーでは要らない。
 */
class NotReservedPathRule implements ValidationRule
{
    /** 検証。ほかの画面のURLと重なれば$fail()を呼ぶ */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::isReserved($value)) {
            $fail('この名前は、ほかの画面のURLで使っているので使えません。');
        }
    }

    /** 名前の最初の区切りが、ほかのルートのURLの最初の区切りと同じか */
    public static function isReserved(string $path): bool
    {
        $first = explode('/', trim($path, '/'))[0];

        foreach (Route::getRoutes() as $route) {
            // URLの直下のルート自身（/{slug}）は、最初の区切りが変数なので重ならない
            if (explode('/', trim($route->uri(), '/'))[0] === $first) {
                return true;
            }
        }

        return false;
    }
}
