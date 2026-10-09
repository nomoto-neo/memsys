<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\News;
use App\Support\SearchableList;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 訪問者向けのニュース一覧・詳細。
 *
 * 会員限定の記事（members_only）は、ログイン中の会員にだけ見せる。ログインして
 * いない人には、一覧にも詳細にも出さない（記事があること自体を見せない）。
 * ログイン状態や掲載日時の制御も含めて、見せてよいかの判断は
 * News::visibleTo()・isVisibleTo()にまとめてある。
 *
 * Admin\NewsControllerとは違い、認証を必要としない（routes/web.phpで
 * どのmiddleware()にも入れていない）公開ページ。
 */
class NewsController extends Controller
{
    use SearchableList;

    /**
     * セッションキー名の識別子（SearchableListがpage_session.・
     * search_session.をこの値から組み立てる）。
     */
    private const INDEX_ROUTE = 'news.index';

    /** フリーワード検索は訪問者側では使わない。 */
    private const FREE_WORD_COLUMNS = [];

    private const PER_PAGE = 20;

    /**
     * 訪問者側は並び順の選択肢自体を出していない（常に記事日付の
     * 新しい順）。ORDER_OPTIONSは1件でもSearchableListの仕組みが
     * そのまま動く（配列の先頭＝デフォルトが常に選ばれる）。
     */
    private const ORDER_OPTIONS = [
        'article_date_desc' => [
            'label' => '記事日付が新しい順',
            'orderBy' => [
                ['article_date', 'desc'],
                ['id', 'desc'],
            ],
        ],
    ];

    /** 一覧・検索 */
    public function index(Request $request): View|RedirectResponse
    {
        // 一覧データの読み込みとページング
        // ログイン中の会員（ログインしていなければnull）に見せてよい記事だけ
        $result = $this->buildListData($request, News::visibleTo(Auth::guard('web')->user()));

        if ($result instanceof RedirectResponse) {
            // リダイレクトが要求された場合
            return $result;
        }

        return view('news.index', [
            'newsList' => $result['paginated'],
            'filters' => $result['filters'],
            'categories' => Category::orderBy('display_order')->get(),
            'years' => $this->availableYears(),
        ]);
    }

    /**
     * 検索対象項目の検証ルール（SearchableListが要求する）。
     *
     * この訪問者向け一覧はログイン不要の公開ページなので、誰でも任意の値を
     * 送れる。ここで形を保証しておくことで、細工したリクエストで一覧画面が
     * 500エラーになることを防いでいる（詳しくはSearchableListのコメント参照）。
     *
     * カテゴリー・年度はどちらも単純なカラム比較では表現できない
     * （多対多の絞り込み・日付からの年抽出）ため、applyCustomSearch()で
     * 個別に処理する。
     */
    private function srchRules(): array
    {
        return [
            'category_id' => ['nullable', 'integer'],
            'year' => ['nullable', 'digits:4'],
        ];
    }

    /**
     * イレギュラーな検索条件の追加処理
     * category_id（多対多の絞り込み）・year（記事日付の年の一致）を、
     * この中で個別に処理して、処理済み(true)を返す。
     */
    private function applyCustomSearch(Builder $query, string $key, mixed $value): bool
    {
        if ($key === 'category_id') {
            $query->whereHas('categories', function (Builder $q) use ($value) {
                $q->where('t_categories.id', $value);
            });

            return true;
        }

        if ($key === 'year') {
            // 記事日付の年の一致。
            $query->whereRaw('YEAR(article_date) = ?', [$value]);

            return true;
        }

        return false;
    }

    /**
     * 年度プルダウンの選択肢。その人に見せてよい記事に実際に
     * 存在する年だけを、新しい順で返す。
     */
    private function availableYears(): array
    {
        return News::visibleTo(Auth::guard('web')->user())
            ->select(DB::raw('DISTINCT YEAR(article_date) as year'))
            ->orderByDesc('year')
            ->pluck('year')
            ->all();
    }

    /** 詳細画面の表示 */
    public function show(News $news): View
    {
        // 非表示(disp_flg=false)の記事と、ログインしていない人が開いた会員限定の
        // 記事は、URLを直接指定されても見えないようにする。403（権限が無い）では
        // なく404にしているのは、「そもそも存在しない」という扱いにして、記事の
        // 存在自体を訪問者に気付かせないため。
        abort_unless($news->isVisibleTo(Auth::guard('web')->user()), 404);

        // 添付ファイルは登録した順（idの昇順）に並べる。並び順を
        // News::attach()リレーション自体に書かないのは、管理画面側の
        // アップロード処理（AjaxFileUpload）もこのリレーション経由で
        // 削除・作り直しをしていて、そちらに影響させたくないため。
        $news->load([
            'categories',
            'attach' => fn ($query) => $query->orderBy('id'),
        ]);

        return view('news.show', [
            'news' => $news,
        ]);
    }
}
