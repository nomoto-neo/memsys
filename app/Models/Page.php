<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
