<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Neobit\Core\Seo\HasSeoMeta;
use Neobit\Core\Seo\SeoSubject;

/**
 * 固定ページ。会社概要のように、管理画面で本文を書いて、訪問者の側に決まったURLで出すページ。
 * URLは、URLの名前（slug）がそのままパスになる。aboutusなら /aboutus で開く。
 * 名前には / を含められる。service/priceなら /service/price で開く。親に当たる /service の
 * ページが無くても作れる。
 *
 * 本文はWYSIWYGエディタで書く。見本のサイトでは、ニュースの本文をsummernoteで、
 * 固定ページの本文をSunEditorで書くようにして、2つのエディタの動作の見本にしている。
 * 本文に入れた画像は、誰でも見られる公開の場所に置く。固定ページには会員限定の区別が無いため。
 */
class Page extends Model implements SeoSubject
{
    // 記事ごとのSEOの欄（共通のテーブルseo_metas）。管理画面の登録・編集の画面で入れる
    use HasSeoMeta;

    /** 本文のエディタで挿入する画像の横幅(px)。これより大きい画像は、この横幅に縮めて保存する。 */
    public const BODY_IMAGE_WIDTH = 1000;

    /**
     * 入力の全角と半角をそろえない項目（Neobit\Core\Crud\InputNormalizer）。
     * 本文は、書いたとおりに残す。タイトルとURLの名前はそろえる
     */
    public const RAW_INPUT_FIELDS = ['body'];

    /**
     * URLの名前に使える文字。半角の英小文字・数字・ハイフン・アンダーバーで、先頭は英小文字か数字。
     * / で区切って、いくつでもつなげられる。先頭と末尾の / と、// は通さない。
     * 検証のルールと、訪問者の側のルートの両方で使う。食い違うと、登録できるのに開けないページができる
     */
    public const SLUG_PATTERN = '[a-z0-9][a-z0-9_-]*(?:/[a-z0-9][a-z0-9_-]*)*';

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

    /** 訪問者の側に出してよいページだけに絞る。Page::visible()のように使う */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('disp_flg', true);
    }

    /**
     * URLの名前として使えない名前かを返す。名前の最初の区切りが、ほかの画面のURLの最初の区切りと
     * 同じなら、使えない。固定ページのルートは、ほかの全部のルートの後ろで受けるので、たとえば
     * loginという名前のページを作っても、ログイン画面が先に当たって、開けないため。
     * admin/helpのように下に続く名前も、その画面のURLと紛らわしく、後から同じURLの画面を足すと
     * 開けなくなるので、同じく使えない。
     * ルートの一覧から調べるので、画面を足しても、ここを直す必要は無い。
     */
    public static function isReservedSlug(string $slug): bool
    {
        $slugFirst = explode('/', $slug)[0];

        foreach (Route::getRoutes() as $route) {
            // 固定ページのルート自身（/{slug}）は、変数なので比べない
            $first = explode('/', trim($route->uri(), '/'))[0];

            if ($first === $slugFirst) {
                return true;
            }
        }

        return false;
    }

    // ---- SEO（Neobit\Core\Seo\SeoSubject） ----

    /** SEOのタイトル。記事のタイトル */
    public function seoTitle(): string
    {
        return $this->title;
    }

    /** 説明文の元。本文（タグを除いた先頭がSEOの説明文になる） */
    public function seoDescriptionSource(): ?string
    {
        return $this->body;
    }

    /** シェアの画像。固定ページには画像の欄が無いので、サイトの既定の画像になる */
    public function seoImageUrl(): ?string
    {
        return null;
    }
}
