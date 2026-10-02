<?php

namespace App\Models;

use App\Support\UploadFilePath;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ニュース記事。
 */
class News extends Model
{
    /**
     * 一覧用画像(list_image)の横幅(px)。登録される画像は、この横幅を
     * 超えないようにリサイズして保存される。どの画面から登録しても
     * 変わらない、このデータ項目の仕様なのでモデルに持たせている。
     * 管理画面のアップロード処理（NewsController::UPLOAD_FILES）と
     * 入力欄の表示（admin/news/_fields.blade.php）の両方がここを参照する。
     */
    public const LIST_IMAGE_WIDTH = 1000;

    /**
     * 本文(body)のWYSIWYGエディタで挿入する画像の横幅(px)。挿入された画像は、
     * この横幅を超えないようにリサイズして保存される。LIST_IMAGE_WIDTHと
     * 同じく、このデータ項目の仕様なのでモデルに持たせている
     * （NewsController::WYSIWYG_FIELDSが参照する）。
     */
    public const BODY_IMAGE_WIDTH = 1000;

    protected $table = 't_news';

    protected $fillable = [
        'title',
        'body',
        'article_date',
        'disp_flg',
        'list_image',
        'list_image_origin',
    ];

    protected $casts = [
        // dateキャストにしておくと、$news->article_dateがCarbonインスタンスに
        // なるので、訪問者側のformat()表示や、SearchableList越しの
        // ->orderBy('article_date', ...)がそのまま扱える。
        'article_date' => 'date',
        // DBはtinyint(1)などで返るところを、Eloquentのキャストで
        // 明示的にbool型へ変換する。$news->disp_flgが常にtrue/falseで
        // あることを保証し、===での厳密比較に安心して使える。
        'disp_flg' => 'boolean',
    ];

    /**
     * 訪問者に見せてよい（表示にしてある）記事だけに絞る。
     * News::visible()->...のように使う（#[Scope]を付けたメソッドは、
     * クエリの条件としてメソッド名で呼べる）。訪問者側の一覧（NewsController）と
     * TOPページ（TopController）が同じ条件を使うので、ここにまとめている。
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('disp_flg', true);
    }

    /**
     * この記事が属しているカテゴリー（複数）。
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 't_news_category', 'news_id', 'category_id');
    }

    /**
     * この記事に付いている添付ファイル(複数)。
     *
     * App\Support\AjaxFileUploadトレイトは、UPLOAD_FILESのキーが
     * "attach.*"(複数展開)のとき、末尾の".*"を除いた"attach"という
     * 名前のメソッドをこのモデルに対して呼び出す、という規約で
     * 動いている。テーブル名や外部キーの詳細はトレイト側は一切知らず、
     * このリレーション定義だけを頼りに保存・削除を行う。
     */
    public function attach(): HasMany
    {
        return $this->hasMany(NewsAttachment::class, 'news_id');
    }

    /**
     * 一覧用画像の公開URL（未登録ならnull）。ビューからは
     * $news->list_image_url と、カラムと同じ感覚で読める。
     *
     * Laravelのアクセサという仕組みで、メソッド名listImageUrl()を
     * スネークケースにした名前（list_image_url）でプロパティのように
     * 読めるようになる。戻り値の型をAttributeにしておくことが、
     * 「これはアクセサである」という目印になっている。
     *
     * 訪問者向けの一覧・詳細のように、フォームの無い「モデルをそのまま
     * 表示する」画面では$inputを作らないので、こうした表示用の値は
     * モデル自身に持たせている。保存先の規則はApp\Support\UploadFilePathに
     * あり、管理画面側のアップロード処理（AjaxFileUpload）と共通。
     *
     * 管理画面の入力フォーム・確認画面・詳細画面のアップロード欄では、
     * これではなくupload_preview_url()を使う。こちらは「今DBに保存されて
     * いるファイル」のURLで、あちらは「入力中の値（差し替えたばかりの
     * 一時ファイルや、削除の指定）を反映した」URL、という違いがある。
     */
    protected function listImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => UploadFilePath::url(self::class, $this->getKey(), $this->list_image),
        );
    }
}
