<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

/**
 * 一覧・検索の共通処理。検索条件と並び順・ページの保存と復元、絞り込み、ページ分けを行う。
 * コーナーごとに違うのは、対象のモデルと検索の項目だけ。
 *
 * ■ useするクラスが用意するもの
 * 定数。書き忘れると一覧を開いたときにエラーになる
 * - INDEX_ROUTE        一覧画面のルート名
 * - FREE_WORD_COLUMNS  フリーワードのqで探す列。使わなければ空の配列
 * - PER_PAGE           1ページの件数
 * - ORDER_OPTIONS      並び順の選択肢。書き方はAdmin\StaffControllerを参照
 * メソッド
 * - srchRules()          検索の項目の検証ルール
 * - applyCustomSearch()  列と直接比べられない項目の処理
 *
 * ■ 検索条件の検証
 * 検索条件は、セッションに保存するときと取り出すときの両方でsanitizeFilters()に通す。
 * 送られてきた値をそのまま使うと、配列を送られただけで一覧がエラーになり、条件を消すまで
 * 続いてしまう。取り出すときにも通すのは、ルールを変える前の条件が残っていることがあるため。
 * 検証に落ちた項目とルールに無い項目は、エラーにせずに捨てる。普通の操作では起きないので
 * 利用者に説明する必要が無く、何を弾いたかを返さない方が安全でもあるため。
 * qとorderbyのルールはこのトレイトが自動で足すので、srchRules()には書かない。
 *
 * ■ 完全一致か部分一致か
 * srchRules()のルールから自動で決める。
 * - integer・boolean・Rule::inか'in:...'・Rule::enumのどれかがあれば完全一致
 * - どれも無ければ部分一致。メールアドレスのように一部で探したい文字列のため
 * - 値が配列ならどちらでもIN()にする。配列の項目は'.*'のルールも見る
 * コードやフラグにこれらのルールを付け忘れると部分一致になり、都道府県の1で探すと
 * 10〜19なども当たってしまうので注意する。選択肢は正しさのためにもRule::inで検証する。
 *
 * ■ applyCustomSearch()
 * 自動の判定の前に、各項目についてこれを呼ぶ。自分で処理した項目はtrueを返し、そうでなければ
 * falseを返して自動の判定に任せる。多対多の絞り込みや日付から年を取り出す比較のように、
 * 列と直接比べられない項目だけを書く。ニュースのcategory_idのように型だけでは自動で探せるか
 * 分からない項目があるので、処理を書いた側から名乗り出てもらう形にしている。
 * ここで処理する項目もsrchRules()には必ず書く。書かないと検証で捨てられて届かない。
 */
trait SearchableList
{
    /**
     * 検索の項目の検証ルール。キーはフォームのnameで、そのまま探す列の名前にもなる。
     * 列と直接比べられない項目はapplyCustomSearch()で処理する。
     * qとorderbyはこのトレイトが使う名前なので使えない。
     */
    abstract private function srchRules(): array;

    /**
     * 列と直接比べられない検索条件の処理。自分で処理したらtrue、自動の判定に任せるならfalse。
     * そういう項目が無いコーナーはいつもfalseを返すだけでよい。
     */
    abstract private function applyCustomSearch(Builder $query, string $key, mixed $value): bool;

    /** ページ番号を保存するセッションのキー */
    private function pageSessionKey(): string
    {
        return 'page_session.'.self::INDEX_ROUTE;
    }

    /** 検索条件を保存するセッションのキー */
    private function searchSessionKey(): string
    {
        return 'search_session.'.self::INDEX_ROUTE;
    }

    /**
     * 一覧の表示の共通処理。$queryには対象のモデルのクエリを渡す。例：Member::query()
     *
     * 詳細などから?backで戻ってきたときは、保存したページへのリダイレクトを返す。
     * それ以外は画面に渡すpaginated・filters・orderOptions・orderKeyの配列を返すので、
     * 呼ぶ側は返ってきた型でリダイレクトか画面の表示かを分ければよい。
     */
    private function buildListData(Request $request, Builder $query): array|RedirectResponse
    {
        // 詳細ページなどから戻ってきたとき
        if ($request->query->has('back')) {
            // 以前のページ番号を復元
            $page = session($this->pageSessionKey());
            // リダイレクトしても処理完了メッセージが消えないようにセッション保存
            $request->session()->keep(['status', 'error']);

            // 一覧表示 ?page=n へリダイレクト
            return redirect()->route(self::INDEX_ROUTE, $page ? ['page' => $page] : []);
        }

        // page= のパラメータが付いていないのは、メニューから入ってきたとき
        if (! $request->has('page')) {
            // 初期表示の時は検索条件のセッションをクリア
            session()->forget($this->searchSessionKey());
        }

        // 現在のページ番号をセッション保存
        session([$this->pageSessionKey() => $request->has('page') ? $request->integer('page') : null]);

        // 保存してある検索条件・並び順を$queryに反映
        [$filters, $orderKey] = $this->applyListConditions($query);

        // 画面に渡す検索条件には、指定されなかった項目もnullで入れておく。
        // 画面で$filters['email']のように、?? ''を付けずに書けるようにするため
        $emptyFilters = [];
        foreach (array_keys($this->commonSearchRules() + $this->srchRules()) as $key) {
            // '.*'は配列の要素のルールなので、項目としては扱わない
            if (! str_contains($key, '.')) {
                $emptyFilters[$key] = null;
            }
        }

        return [
            'paginated' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters + $emptyFilters,
            'orderOptions' => self::ORDER_OPTIONS,
            'orderKey' => $orderKey,
        ];
    }

    /**
     * 保存してある検索条件と並び順を$queryに当て、[検索条件, 並び順のキー]を返す。
     * 一覧とCSVのダウンロードが、同じ条件で絞り込むために使う。
     */
    private function applyListConditions(Builder $query): array
    {
        // 保存してあった検索条件を取り出して検証を通す。ルールを変える前の条件が残っていることがある
        $filters = $this->sanitizeFilters((array) session($this->searchSessionKey(), []));

        // 並び順。orderbyも検索条件と一緒に保存してある
        $orderKey = $this->resolveOrderKey($filters);

        // 検索条件の反映
        $this->setWhere($query, $filters);

        // 並び順の反映。[列, 方向]の組を、書いた順に重ねる
        foreach (self::ORDER_OPTIONS[$orderKey]['orderBy'] as [$orderColumn, $orderDirection]) {
            $query->orderBy($orderColumn, $orderDirection);
        }

        return [$filters, $orderKey];
    }

    /** 並び順のキー。ORDER_OPTIONSにあればそのまま、未指定ならORDER_OPTIONSの先頭 */
    private function resolveOrderKey(array $filters): string
    {
        $orderKey = $filters['orderby'] ?? '';

        if (array_key_exists($orderKey, self::ORDER_OPTIONS)) {
            return $orderKey;
        }

        return array_key_first(self::ORDER_OPTIONS);
    }

    /**
     * フリーワードのqと並び順のorderbyの検証ルール。このトレイトの仕組みなので、
     * srchRules()には書かせずにここで足す。フリーワードを使わないコーナーではqのルールを
     * 足さないので、送られてきても捨てられる。
     */
    private function commonSearchRules(): array
    {
        $rules = [
            'orderby' => ['nullable', 'string', Rule::in(array_keys(self::ORDER_OPTIONS))],
        ];

        if (self::FREE_WORD_COLUMNS !== []) {
            // 長さの上限は検索フォームの入力欄のmaxlengthとそろえる。
            // そろっていないと、画面で入力できた長い語句が黙って捨てられてしまう
            $rules['q'] = ['nullable', 'string', 'max:100'];
        }

        return $rules;
    }

    /**
     * 検索条件を検証し、ルールにある項目のうち、送られてきて検証に通ったものだけを返す。
     * 配列の項目は要素の1つでも落ちたら項目ごと捨てる。一部だけ残すと、選んだつもりの
     * 条件と実際の絞り込みがずれるため。
     */
    private function sanitizeFilters(array $raw): array
    {
        $rules = $this->commonSearchRules() + $this->srchRules();

        $validator = Validator::make($raw, $rules);
        $validator->passes();

        // 検証に落ちた項目。prefecture.3のような配列の要素が落ちたら、prefectureの項目ごと落とす
        $failedKeys = [];
        foreach (array_keys($validator->failed()) as $failedAttribute) {
            $failedKeys[explode('.', $failedAttribute)[0]] = true;
        }

        $filters = [];
        foreach (array_keys($rules) as $key) {
            // '.*'は配列の要素のルールなので、項目としては扱わない
            if (str_contains($key, '.')) {
                continue;
            }
            // 送られてこなかった項目と、検証に落ちた項目は捨てる
            if (! array_key_exists($key, $raw) || isset($failedKeys[$key])) {
                continue;
            }
            $filters[$key] = $raw[$key];
        }

        return $filters;
    }

    /**
     * その項目を完全一致で探すかを、srchRules()のルールから決める。判定の決まりは
     * このファイルの冒頭にある。配列の項目は'.*'のルールも見る。
     */
    private function isEqualSearch(string $key): bool
    {
        $rules = $this->srchRules();

        // ルールが文字列1本やオブジェクト1つで書かれていても、配列にそろえて調べる。
        // (array)を使わないのは、オブジェクトのプロパティが配列に展開されてしまうため
        $toList = fn ($rule) => is_array($rule) ? $rule : [$rule];

        $candidates = array_merge(
            $toList($rules[$key] ?? []),
            $toList($rules["{$key}.*"] ?? []),
        );

        foreach ($candidates as $rule) {
            // Rule::inとRule::enum
            if ($rule instanceof In || $rule instanceof Enum) {
                return true;
            }

            if (! is_string($rule)) {
                continue;
            }

            // 文字列のルール。'nullable|integer'のような書き方も含む
            foreach (explode('|', $rule) as $part) {
                if ($part === 'integer' || $part === 'boolean' || str_starts_with($part, 'in:')) {
                    return true;
                }
            }
        }

        return false;
    }

    /** 検索条件をクエリに当てる。$filtersは検証を通った後なので、形はルールのとおり */
    private function setWhere(Builder $query, array $filters): void
    {
        // フリーワード検索
        $freeword = (string) ($filters['q'] ?? '');
        if ($freeword != '') {
            // 半角スペース・全角スペース(\x{3000})・タブ・改行で単語を分解する
            $words = preg_split('/[ \x{3000}\t\r\n]+/u', trim($freeword), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($words as $word) {
                // 語ごとの条件はANDでつなぐ
                $query->where(function (Builder $q) use ($word) {
                    // 検索対象項目をORで繋ぐ
                    foreach (self::FREE_WORD_COLUMNS as $column) {
                        $q->orWhere($column, 'like', "%{$word}%");
                    }
                });
            }
        }

        $srchRules = $this->srchRules();

        foreach ($filters as $column => $value) {
            // SQLの列の名前になるので、srchRules()にある項目だけを扱う。検証で絞ってあるが、念のため
            if (! array_key_exists($column, $srchRules)) {
                continue;
            }

            // 未指定かどうかは===で判定する。empty()だと非表示やスタッフの権限などの'0'まで
            // 未指定になり、その条件での絞り込みが黙って無視されてしまう
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            // まずコーナーに、自分で処理するかを聞く
            if ($this->applyCustomSearch($query, $column, $value)) {
                continue;
            }

            if (is_array($value)) {
                // 複数選択なら、IN()でどれかに当たるもの
                $query->whereIn($column, $value);
            } elseif ($this->isEqualSearch($column)) {
                // 完全一致。'1'のような文字列のままでも、DBが列の型に合わせて比べる
                $query->where($column, $value);
            } else {
                // 部分一致
                $query->where($column, 'like', "%{$value}%");
            }
        }
    }

    /** 検索条件の保存 */
    public function storeSearchCondition(Request $request): RedirectResponse
    {
        // 検証を通った項目だけを保存する。_tokenやpageのような値もここで捨てられる
        session([$this->searchSessionKey() => $this->sanitizeFilters($request->all())]);

        // 一覧表示するため ?page=1 へリダイレクト
        return redirect()->route(self::INDEX_ROUTE, ['page' => 1]);
    }
}
