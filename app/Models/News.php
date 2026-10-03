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

    /**
     * ログインした人だけが見られる場所に置くアップロードのフィールド。記事は
     * 一般公開と会員限定を切り替えられるので、切り替えのたびにファイルを
     * 移さなくて済むよう、すべてのフィールドを非公開の場所に置き、見せるか
     * どうかは記事の今の状態で決める（App\Policies\NewsPolicy::viewFiles()）。
     */
    public const PRIVATE_FILE_FIELDS = ['list_image', 'attach', 'body'];

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
        // dateキャストにしておくと、$news->article_dateがCarbonインスタンスに
        // なるので、訪問者側のformat()表示や、SearchableList越しの
        // ->orderBy('article_date', ...)がそのまま扱える。
        'article_date' => 'date',
        // DBはtinyint(1)などで返るところを、Eloquentのキャストで
        // 明示的にbool型へ変換する。$news->disp_flgが常にtrue/falseで
        // あることを保証し、===での厳密比較に安心して使える。
        'disp_flg' => 'boolean',
        // falseなら一般公開、trueなら会員限定。
        'members_only' => 'boolean',
        // 掲載期間。どちらも空なら、その側の制限は無い（isWithinPublishPeriod()）。
        'publish_start_at' => 'datetime',
        'publish_end_at' => 'datetime',
    ];

    /**
     * 訪問者側で、その人に見せてよい記事だけに絞る。$memberはログイン中の会員
     * （ログインしていなければnull）。表示にしてあり、掲載期間の中にある記事のうち、
     * 会員なら全部、ログインしていなければ一般公開の記事だけ。
     * News::visibleTo($member)->...のように使う（#[Scope]を付けたメソッドは、
     * クエリの条件としてメソッド名で呼べる）。訪問者側の一覧（NewsController）と
     * TOPページ（TopController）が同じ条件を使うので、ここにまとめている。
     * 1件ずつの判断（詳細画面・画像）はisVisibleTo()で、条件は同じ。
     *
     * 掲載期間は、表示するたびに今の時刻と比べる。日時は分までの指定なので、開始も
     * 終了もその分を含む。19:30開始なら19:30:00から見え、19:30終了なら19:30:59まで
     * 見えて19:31:00に見えなくなる（終了は、今の時刻を分に切り捨てて比べる）。
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?Member $member): void
    {
        $now = now();
        $thisMinute = now()->startOfMinute();

        $query->where('disp_flg', true)
            ->where(fn (Builder $q) => $q->whereNull('publish_start_at')->orWhere('publish_start_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('publish_end_at')->orWhere('publish_end_at', '>=', $thisMinute));

        if ($member === null) {
            $query->where('members_only', false);
        }
    }

    /**
     * この記事を、訪問者側でその人に見せてよいか（条件はvisibleTo()と同じ）。
     */
    public function isVisibleTo(?Member $member): bool
    {
        return $this->disp_flg
            && $this->isWithinPublishPeriod()
            && (! $this->members_only || $member !== null);
    }

    /**
     * 今が掲載期間の中か。掲載開始日時・掲載終了日時の空の側は、制限しない。
     */
    public function isWithinPublishPeriod(): bool
    {
        return ! $this->isBeforePublishStart() && ! $this->isAfterPublishEnd();
    }

    /**
     * 掲載開始日時が来ていないか（管理画面の一覧の「掲載前」の表示にも使う）。
     */
    public function isBeforePublishStart(): bool
    {
        return $this->publish_start_at !== null && $this->publish_start_at->isFuture();
    }

    /**
     * 掲載終了日時の分を過ぎたか（管理画面の一覧の「掲載終了」の表示にも使う）。
     * 19:30終了なら、19:31:00からtrue（visibleTo()と同じく、今の時刻を分に切り捨てて比べる）。
     */
    public function isAfterPublishEnd(): bool
    {
        return $this->publish_end_at !== null && $this->publish_end_at->lt(now()->startOfMinute());
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
     * 一覧用画像は非公開のフィールドなので、URLはuploads.showのルートになり、
     * 記事を見てよい人にだけ画像が返る（App\Policies\NewsPolicy）。
     *
     * 管理画面の入力フォーム・確認画面・詳細画面のアップロード欄では、
     * これではなくupload_preview_url()を使う。こちらは「今DBに保存されて
     * いるファイル」のURLで、あちらは「入力中の値（差し替えたばかりの
     * 一時ファイルや、削除の指定）を反映した」URL、という違いがある。
     */
    protected function listImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => UploadFilePath::url(self::class, $this->getKey(), 'list_image', $this->list_image),
        );
    }
}
