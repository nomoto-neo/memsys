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
     * 一覧用画像の横幅(px)。これより大きい画像は、この横幅に縮めて保存する。
     * どの画面から登録しても同じ、このデータ項目の仕様なので、モデルに持たせている。
     */
    public const LIST_IMAGE_WIDTH = 1000;

    /** 本文のエディタで挿入する画像の横幅(px)。これより大きい画像は、この横幅に縮めて保存する。 */
    public const BODY_IMAGE_WIDTH = 1000;

    /**
     * ログインした人だけが見られる場所に置くアップロードのフィールド。記事は一般公開と
     * 会員限定を切り替えられるので、すべてのフィールドを非公開の場所に置き、見せるかどうかを
     * 記事の今の状態で決める。切り替えのたびにファイルを移さなくて済むようにするため。
     * 見てよいかはApp\Policies\NewsPolicyで判断する。
     */
    public const PRIVATE_FILE_FIELDS = ['list_image', 'attach', 'body'];

    /**
     * 入力の全角と半角をそろえない項目（App\Support\InputNormalizer）。
     * 本文は、書いたとおりに残す。件名はそろえる
     */
    public const RAW_INPUT_FIELDS = ['body'];

    protected $table = 't_news';

    protected $fillable = [
        'title',
        'body',
        'article_date',
        'disp_flg',
        'members_only',
        'publish_start_at',
        'publish_end_at',
        'list_image',
        'list_image_origin',
    ];

    protected $casts = [
        'article_date' => 'date',
        // 表示・非表示。===で比べられるよう、true・falseにそろえる
        'disp_flg' => 'boolean',
        // falseなら一般公開、trueなら会員限定。
        'members_only' => 'boolean',
        // 掲載期間。どちらも空なら、その側の制限は無い（isWithinPublishPeriod()）。
        'publish_start_at' => 'datetime',
        'publish_end_at' => 'datetime',
    ];

    /**
     * 訪問者側で、その人に見せてよい記事だけに絞る。News::visibleTo($member)のように使う。
     * $memberはログイン中の会員で、ログインしていなければnull。
     *
     * 見せるのは、表示にしてあり、掲載期間の中にある記事。ログインしていなければ、
     * そのうちの一般公開の記事だけ。1件ずつの判断はisVisibleTo()で、条件は同じ。
     *
     * 掲載期間の日時は分までの指定なので、開始も終了もその分を含む。19:30開始なら19:30:00から
     * 見え、19:30終了なら19:30:59まで見える。終了は、今の時刻を分に切り捨てて比べる。
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?Member $member): void
    {
        $now = now();
        $thisMinute = now()->startOfMinute();

        // 表示にしてあり、掲載期間の中にある記事
        $query->where('disp_flg', true)
            ->where(fn (Builder $q) => $q->whereNull('publish_start_at')->orWhere('publish_start_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('publish_end_at')->orWhere('publish_end_at', '>=', $thisMinute));

        // ログインしていなければ、一般公開の記事だけ
        if ($member === null) {
            $query->where('members_only', false);
        }
    }

    /** この記事を、訪問者側でその人に見せてよいか（条件はvisibleTo()と同じ）。 */
    public function isVisibleTo(?Member $member): bool
    {
        return $this->disp_flg
            && $this->isWithinPublishPeriod()
            && (! $this->members_only || $member !== null);
    }

    /** 今が掲載期間の中か。掲載開始日時・掲載終了日時の空の側は、制限しない。 */
    public function isWithinPublishPeriod(): bool
    {
        return ! $this->isBeforePublishStart() && ! $this->isAfterPublishEnd();
    }

    /** 掲載開始日時が来ていないか。管理画面の一覧の「掲載前」の表示にも使う。 */
    public function isBeforePublishStart(): bool
    {
        return $this->publish_start_at !== null && $this->publish_start_at->isFuture();
    }

    /**
     * 掲載終了日時の分を過ぎたか。19:30終了なら、19:31:00からtrueになる。
     * 管理画面の一覧の「掲載終了」の表示にも使う。
     */
    public function isAfterPublishEnd(): bool
    {
        return $this->publish_end_at !== null && $this->publish_end_at->lt(now()->startOfMinute());
    }

    /** この記事が属しているカテゴリー（複数）。 */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 't_news_category', 'news_id', 'category_id');
    }

    /**
     * この記事に付いている添付ファイル。アップロードの処理（App\Support\AjaxFileUpload）は、
     * 複数のフィールド'attach.*'と同じ名前のこのリレーションを通して、保存・削除を行う。
     */
    public function attach(): HasMany
    {
        return $this->hasMany(NewsAttachment::class, 'news_id');
    }

    /**
     * 一覧用画像のURL。未登録ならnull。画面からは$news->list_image_urlで読める。
     * 訪問者向けの一覧・詳細のような、フォームの無い画面で使う。非公開のフィールドなので、
     * 記事を見てよい人にだけ画像を返すURLになる。
     *
     * 管理画面の入力・確認・詳細の画面では、これではなくupload_preview_url()を使う。
     * こちらは保存済みのファイルのURLで、あちらは入力中の値を反映したURL。
     */
    protected function listImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => UploadFilePath::url(self::class, $this->getKey(), 'list_image', $this->list_image),
        );
    }
}
