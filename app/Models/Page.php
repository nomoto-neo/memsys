<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

/**
 * 固定ページ。会社概要のように、管理画面で本文を書いて、訪問者の側に決まったURLで出すページ。
 * URLは、URLの名前（slug）がそのままパスになる。aboutusなら /aboutus で開く。
 *
 * 本文はWYSIWYGエディタで書く。見本のサイトでは、ニュースの本文をsummernoteで、
 * 固定ページの本文をSunEditorで書くようにして、2つのエディタの動作の見本にしている。
 * 本文に入れた画像は、誰でも見られる公開の場所に置く。固定ページには会員限定の区別が無いため。
 */
class Page extends Model
{
    // 本文のエディタで挿入する画像の横幅(px)。これより大きい画像は、この横幅に縮めて保存する。
    public const BODY_IMAGE_WIDTH = 1000;

    // 入力の全角と半角をそろえない項目（App\Support\InputNormalizer）。
    // 本文は、書いたとおりに残す。タイトルとURLの名前はそろえる
    public const RAW_INPUT_FIELDS = ['body'];

    // URLの名前に使える文字。半角の英小文字・数字・ハイフンで、先頭は英小文字か数字。
    // 検証のルールと、訪問者の側のルートの両方で使う。食い違うと、登録できるのに開けないページができる
    public const SLUG_PATTERN = '[a-z0-9][a-z0-9-]*';

    protected $table = 't_pages';

    protected $fillable = [
        'title',
        'slug',
        'body',
        'disp_flg',
    ];

    protected $casts = [
        // 表示・非表示。===で比べられるよう、true・falseにそろえる
        'disp_flg' => 'boolean',
    ];

    // 訪問者の側に出してよいページだけに絞る。Page::visible()のように使う
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('disp_flg', true);
    }

    /**
     * URLの名前として使えない語かを返す。ほかの画面のURLの最初の区切りと同じ語は、使えない。
     * 固定ページのルートは、ほかの全部のルートの後ろで受けるので、たとえばloginという名前の
     * ページを作っても、ログイン画面が先に当たって、開けないため。
     * ルートの一覧から調べるので、画面を足しても、ここを直す必要は無い。
     */
    public static function isReservedSlug(string $slug): bool
    {
        foreach (Route::getRoutes() as $route) {
            // 固定ページのルート自身（/{slug}）は、変数なので比べない
            $first = explode('/', trim($route->uri(), '/'))[0];

            if ($first === $slug) {
                return true;
            }
        }

        return false;
    }
}
