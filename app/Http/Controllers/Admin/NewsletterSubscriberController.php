<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppOperationLogAction;
use App\Enums\NewsletterSource;
use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Services\NewsletterSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Neobit\Core\Crud\SearchableList;
use Neobit\Core\Operations\OperationRecorder;

/**
 * メルマガ希望者（会員ではない人）の一覧・検索と、手での停止。
 * 問い合わせを受けたときに、状態を確かめて止められるようにするため。行を作るのは申し込みのフォームと
 * 配信停止で、ここでは作らない（App\Services\NewsletterSubscription）。
 * 会員の「受け取るか」は、会員の管理画面で見る。一斉メールの宛先のCSVは、一斉メールの入力の画面から落とす。
 */
class NewsletterSubscriberController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 一覧・検索まわりの共通処理はSearchableListトレイトが提供する。
    // クラス側は必要な定数と srchRules() applyCustomSearch() だけ用意する。
    use SearchableList;

    // ---- 一覧・検索（SearchableList）の設定 ----

    /** 一覧画面のルート名。セッションキー名の識別子としても使用。停止の後の戻り先にも使う */
    private const INDEX_ROUTE = 'admin.newsletter-subscribers.index';

    /** フリーワード検索の検索対象とするカラムの一覧。 */
    private const FREE_WORD_COLUMNS = ['name', 'email'];

    /** 1ページに表示する件数。 */
    private const PER_PAGE = 20;

    /** 一覧の並び順の選択肢。 */
    private const ORDER_OPTIONS = [
        'updated_desc' => [
            'label' => '更新日が新しい順',
            'orderBy' => [
                ['updated_at', 'desc'],
                ['id', 'desc'],
            ],
        ],
        'created_desc' => [
            'label' => '登録日が新しい順',
            'orderBy' => [
                ['created_at', 'desc'],
                ['id', 'desc'],
            ],
        ],
    ];

    /** 一覧と検索 */
    public function index(Request $request): View|RedirectResponse
    {
        $result = $this->listPageValues($request, NewsletterSubscriber::query());

        if ($result instanceof RedirectResponse) {
            // リダイレクトが要求された場合
            return $result;
        }

        return view('admin.newsletter_subscribers.index', [
            'records' => $result['records'],
            'filters' => $result['filters'],
            'orderOptions' => $result['orderOptions'],
            'selectedOrder' => $result['selectedOrder'],
        ]);
    }

    /**
     * 検索対象項目の検証ルール（SearchableListが要求する）。
     * integer・boolean・Rule::inのどれかがあれば完全一致、無ければ部分一致、配列ならIN()条件
     */
    private function srchRules(): array
    {
        return [
            'notice_mail' => [
                'nullable', 'integer',
                // コードテーブルとの一致を確認
                Rule::in(code_keys('notice_mail')),
            ],
            'source' => [
                'nullable', 'string',
                // コードテーブルとの一致を確認
                Rule::in(code_keys('newsletter_source')),
            ],
        ];
    }

    /** イレギュラーな検索条件の追加処理。このコーナーには無い */
    private function applyCustomSearch(Builder $query, string $key, mixed $value): bool
    {
        return false;
    }

    /** 1件を「受け取らない」にする。すでに止まっていれば何もしない */
    public function stop(NewsletterSubscriber $subscriber): RedirectResponse
    {
        if (! $subscriber->receivesNoticeMail()) {
            return redirect()->route(self::INDEX_ROUTE, ['back'])
                ->with('status', 'すでに停止しています。');
        }

        DB::transaction(function () use ($subscriber) {
            $stopped = NewsletterSubscription::stop($subscriber->email, NewsletterSource::Admin, null);

            OperationRecorder::record(AppOperationLogAction::NewsletterStop, $stopped, changedFields: ['notice_mail']);
        });

        return redirect()->route(self::INDEX_ROUTE, ['back'])
            ->with('status', "{$subscriber->email} への配信を停止しました。");
    }
}
