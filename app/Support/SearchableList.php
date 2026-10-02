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
 * 一覧・検索まわりの共通処理をまとめたトレイト。
 *
 * 管理側の会員・スタッフ・ニュースの一覧と、訪問者向けのニュース一覧
 * （ログイン不要の公開ページ）で使っている。コーナーごとに違うのは対象の
 * モデルと検索項目だけなので、それ以外の共通の流れをここにまとめている。
 *
 * トレイトはクラスの中にコードをそのまま展開する仕組みなので、この
 * トレイト内でself::を使って参照している定数は、このトレイト自身のもの
 * ではなく、実際にuseした側のクラスに定義した定数がそのまま見える
 * （self::・$thisはトレイトを使っている側のクラスとして解決される）。
 * ただし定数は「未定義ならエラーにする」という強制ができないので、定義し
 * 忘れると、実際にそのメソッドを呼び出した瞬間（例: 一覧画面を開いたとき）
 * にUndefined constantエラーになる。
 *
 * 一方srchRules()・applyCustomSearch()はコーナーごとに中身が変わる
 * 「振る舞い」なので、abstractメソッドとして実装を必須にしている。
 * abstractメソッドが未実装のクラスは、そのクラスを読み込んだ時点で
 * エラーになるので、定数より早く・確実に気付ける。
 *
 * ■ このトレイトをuseするクラスが用意するもの
 *
 * 定数:
 * - private const INDEX_ROUTE       一覧画面のルート名
 * - private const FREE_WORD_COLUMNS フリーワード（q）検索の対象カラム
 *                                   （使わないコーナーは空配列）
 * - private const PER_PAGE          1ページの表示件数
 * - private const ORDER_OPTIONS     並び順の選択肢
 *                                   （書き方はAdmin\StaffController参照）
 *
 * メソッド:
 * - srchRules()          検索条件項目の検証ルール（書き方は下記）
 * - applyCustomSearch()  単純なカラム比較では書けない項目の処理（下記）
 *
 * ■ 検索条件の検証（sanitizeFilters()）
 *
 * 検索条件はフォームから送られてきた生の値なので、そのまま使うと、たとえば
 * フリーワード欄に配列を送り付けられただけで「Array to string conversion」
 * になり、一覧画面が500エラーになる（しかもセッションに保存されるので、
 * 条件がクリアされるまでエラーが続く）。訪問者向けの一覧はログイン不要なので、
 * 誰でもこれを起こせてしまう。
 *
 * そこで、セッションに保存するとき（storeSearchCondition()）と、セッション
 * から取り出すとき（buildListData()）の両方で、同じsanitizeFilters()を
 * 通すようにしている。取り出すときにも通すのは、srchRules()を変える前に
 * 保存された検索条件が、セッションに残っていることがあるため。
 * これにより、setWhere()もビューも、$filtersの形（文字列か配列か等）を
 * 信用してよくなる。
 *
 * 検証に落ちた項目は、エラーを出さずに黙って捨てる。検索フォームは
 * プルダウンと短いテキスト欄だけで、普通に操作して不正な値が送られることは
 * 無いので、不正な値が来たらそれは細工されたか壊れたリクエストであり、
 * 利用者に説明する必要が無いため（公開ページでは、何を弾いたかを返さない
 * 方が安全でもある）。ルールに載っていないキーもすべて捨てる。
 *
 * q（フリーワード）とorderby（並び順）はこのトレイト自身の仕組みなので、
 * そのルールはこのトレイトが自動で足す（commonSearchRules()参照）。
 * 各コーナーのsrchRules()には、検索条件の項目だけを書けばよい。
 *
 * ■ 検索条件の比較方法の自動判定（isEqualSearch()）
 *
 * 検索条件の各項目を、完全一致（=）と部分一致（LIKE '%値%'）のどちらで
 * 探すかは、srchRules()に書いた検証ルールから自動的に決める。
 *
 * - integer・boolean・Rule::in（または文字列の'in:...'）・Rule::enumのどれかがあれば
 *   完全一致。コード類・フラグ・選択肢は「その値と等しい」ものだけが
 *   欲しいため。特にRule::in・Rule::enumは「この中のどれかと等しいこと」という
 *   ルールそのものなので、文字列のコードでも選択肢として検証していれば
 *   完全一致になる。
 * - どれも無ければ部分一致。メールアドレスや電話番号のような文字列は
 *   「一部を入れたら当たってほしい」ため。
 * - 値が配列（複数選択）なら、どちらの場合もIN()でOR条件にする。
 *   配列の項目は'.*'側のルールも見て判定する。
 *
 * 選択肢の項目は、正しさのためにもRule::inで検証すべきもの。書くべき
 * 検証を書けば比較方法も自動的に正しくなる、という関係になっている。
 * 逆に言うと、型の宣言がSQLの演算子を決めるので、完全一致させたい項目に
 * integer・boolean・Rule::in・Rule::enumのどれも付けないと部分一致になる点に注意。
 *
 * コード値を部分一致で探すと、正しい結果にならない。例: 都道府県コード1で
 * 探すと、LIKE '%1%'は1・10〜19・21・31・41にも当たる。また、PostgreSQLは
 * booleanやintegerのカラムにLIKEを使えずエラーになる。
 *
 * ■ applyCustomSearch()
 *
 * setWhere()は、検索条件の各項目について、自動判定より先にapplyCustomSearch()
 * を呼ぶ。そのコーナーが自分で処理した項目ならtrueを返してもらい、その場合は
 * 自動判定を行わない。自分では処理しない項目はfalseを返せば、上の自動判定に
 * 回る。多対多の絞り込み（whereHas）や、日付から年を抜き出す比較のように、
 * テーブルのカラムと直接比べる形では書けないものだけをここに書けばよい。
 *
 * 自動判定より先に聞くのは、たとえばニュースのcategory_idのように、整数
 * （＝型だけ見れば完全一致の対象）でも、実際にはt_newsにそのカラムが無く
 * 中間テーブル経由で探す必要がある項目があるため。型だけでは「自動では
 * 探せない」ことが判別できないので、処理を書いたコード自身に名乗り出て
 * もらう形にしている。なお、applyCustomSearch()で処理する項目も、検証の
 * 対象にするためsrchRules()には必ず書いておくこと（書いていないキーは
 * sanitizeFilters()で捨てられ、ここまで届かない）。
 */
trait SearchableList
{
    /**
     * 検索条件の個別項目の検証ルール。書き方は通常のバリデーションルールと
     * 同じで、キーはフォームのname属性。キーはそのまま検索対象のカラム名としても
     * 使う（カラムと直接比べられない項目は、applyCustomSearch()で処理する）。
     * q・orderbyはこのトレイトが使う名前なので、項目名には使えない。
     */
    abstract private function srchRules(): array;

    /**
     * 単純なカラム比較では書けない検索条件の処理。
     * 自分で処理したらtrue、処理しない（自動判定に任せる）ならfalseを返す。
     * 該当する項目が無いコーナーは、常にfalseを返すだけでよい。
     */
    abstract private function applyCustomSearch(Builder $query, string $key, mixed $value): bool;

    private function pageSessionKey(): string
    {
        return 'page_session.'.self::INDEX_ROUTE;
    }

    private function searchSessionKey(): string
    {
        return 'search_session.'.self::INDEX_ROUTE;
    }

    /**
     * 一覧表示のための共通処理。
     *
     * 「詳細ページなどから戻ってきたとき」のリダイレクトが必要な場合は
     * RedirectResponseを返す。それ以外のときは、ビューにそのまま渡せる
     * 形の配列（paginated・filters・orderOptions・orderKey）を返すので、
     * 呼び出し側のindex()は戻り値の型で分岐して、リダイレクトかview()かを
     * 決めるだけでよい。
     *
     * $queryは呼び出し側で対象モデルのquery()を渡す（例: Member::query()）。
     * モデル固有の絞り込み（グローバルスコープなど）は、この$queryを
     * 作った時点ですでに反映されているので、ここでは関知しない。
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

        return [
            'paginated' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'orderOptions' => self::ORDER_OPTIONS,
            'orderKey' => $orderKey,
        ];
    }

    /**
     * セッションに保存してある検索条件と並び順を$queryに反映し、
     * [検索条件, 並び順のキー] を返す。一覧（buildListData()）と
     * CSVダウンロード（CsvDownload::downloadCsv()）が、同じ条件で絞り込むために使う。
     */
    private function applyListConditions(Builder $query): array
    {
        // 保存してあった検索条件を取り出し、検証を通す。保存時にも検証して
        // いるが、srchRules()を変える前に保存された検索条件が残っていることが
        // あるので、取り出すときにも同じ検証を通しておく。
        $filters = $this->sanitizeFilters((array) session($this->searchSessionKey(), []));

        // 並び順の決定（orderbyもfiltersの一部として同じセッションに
        // 保存されているので、他の検索条件項目と同じ仕組みで復元できる）。
        $orderKey = $this->resolveOrderKey($filters);

        // 検索条件の反映
        $this->setWhere($query, $filters);

        // ORDER_OPTIONSのorderByは[カラム名, 方向]の組のリストなので、
        // 書かれている順にそのまま->orderBy()を重ねて呼ぶ。
        foreach (self::ORDER_OPTIONS[$orderKey]['orderBy'] as [$orderColumn, $orderDirection]) {
            $query->orderBy($orderColumn, $orderDirection);
        }

        return [$filters, $orderKey];
    }

    /**
     * filtersに入っているorderbyの値を見て、ORDER_OPTIONSに実在する
     * キーであればそのまま、未指定・存在しないキーであれば
     * ORDER_OPTIONSの先頭をデフォルトとして返す。
     *
     * orderbyはsanitizeFilters()でORDER_OPTIONSのキーに含まれるかを検証
     * 済みだが、未指定の場合の既定値を決める役目があるので、この判定も
     * そのまま残している。
     */
    private function resolveOrderKey(array $filters): string
    {
        $orderKey = $filters['orderby'] ?? '';

        return array_key_exists($orderKey, self::ORDER_OPTIONS)
            ? $orderKey
            : array_key_first(self::ORDER_OPTIONS);
    }

    /**
     * q（フリーワード）とorderby（並び順）の検証ルール。どちらもこの
     * トレイト自身の仕組みなので、各コーナーのsrchRules()に書かせず、
     * ここで自動的に足す。
     *
     * qは、FREE_WORD_COLUMNSが空の（フリーワード検索を使わない）コーナー
     * ではルール自体を足さない。そうすると、送られてきてもsanitizeFilters()
     * で捨てられる。
     */
    private function commonSearchRules(): array
    {
        $rules = [
            'orderby' => ['nullable', 'string', Rule::in(array_keys(self::ORDER_OPTIONS))],
        ];

        if (self::FREE_WORD_COLUMNS !== []) {
            // 長さの上限は、検索フォームのinput要素のmaxlength属性と
            // 揃えておくこと（揃っていないと、画面から普通に入力した
            // 長い語句が、黙って捨てられてしまう）。
            $rules['q'] = ['nullable', 'string', 'max:100'];
        }

        return $rules;
    }

    /**
     * 検索条件を検証し、通った項目だけを返す。
     *
     * ルールに載っているキー（'.*'のような配列の要素用のキーは除く）
     * のうち、送られてきていて、かつ検証に通ったものだけを残す。
     * 検証に落ちた項目・ルールに載っていないキーは、エラーを出さずに捨てる
     * （理由はこのファイル冒頭のコメント参照）。
     *
     * 配列の項目は、要素の1つでも検証に落ちたら項目ごと捨てる（例:
     * prefecture[]に存在しない都道府県コードが1つ混ざっていたら、
     * 都道府県の条件全体を捨てる）。一部だけ残すと、利用者が選んだつもりの
     * 条件と実際の絞り込みがずれるため。
     */
    private function sanitizeFilters(array $raw): array
    {
        $rules = $this->commonSearchRules() + $this->srchRules();

        $validator = Validator::make($raw, $rules);
        $validator->passes();

        // 検証に落ちた項目のキー。配列の要素（prefecture.3など）が
        // 落ちた場合も、先頭の部分（prefecture）を落ちた項目として扱う。
        $failedKeys = [];
        foreach (array_keys($validator->failed()) as $failedAttribute) {
            $failedKeys[explode('.', $failedAttribute)[0]] = true;
        }

        $filters = [];
        foreach (array_keys($rules) as $key) {
            if (str_contains($key, '.')) {
                // '.*'は配列の要素用のルールなので、項目としては扱わない。
                continue;
            }
            if (! array_key_exists($key, $raw) || isset($failedKeys[$key])) {
                continue;
            }
            $filters[$key] = $raw[$key];
        }

        return $filters;
    }

    /**
     * 検索対象の項目を完全一致（=）で探すかどうかを、srchRules()に書かれた
     * 検証ルールから判定する。integer・boolean・Rule::in（または'in:...'）・Rule::enumの
     * どれかがあれば完全一致、どれも無ければ部分一致（詳しくはこのファイル
     * 冒頭のコメント参照）。配列の項目はsrchRulesに書かれた'.*'のルールも見る。
     */
    private function isEqualSearch(string $key): bool
    {
        $rules = $this->srchRules();

        // ルールは配列で書くのが普通だが、'nullable|integer'のような
        // 文字列1本や、ルールオブジェクト1つで書かれていても判定できるように
        // しておく（(array)でキャストすると、オブジェクトのプロパティが
        // 配列に展開されてしまうので使わない）。
        $toList = fn ($rule) => is_array($rule) ? $rule : [$rule];

        $candidates = array_merge(
            $toList($rules[$key] ?? []),
            $toList($rules["{$key}.*"] ?? []),
        );

        foreach ($candidates as $rule) {
            if ($rule instanceof In || $rule instanceof Enum) {
                return true;
            }

            if (! is_string($rule)) {
                continue;
            }

            foreach (explode('|', $rule) as $part) {
                if ($part === 'integer' || $part === 'boolean' || str_starts_with($part, 'in:')) {
                    return true;
                }
            }
        }

        return false;
    }

    // 検索条件の組み立て
    // $filtersはsanitizeFilters()を通った後の値なので、各項目の形は
    // srchRules()・commonSearchRules()の通りであることが保証されている。
    private function setWhere(Builder $query, array $filters): void
    {
        // フリーワード検索
        $freeword = (string) ($filters['q'] ?? '');
        if ($freeword != '') {
            // 半角スペース・全角スペース(\x{3000})・タブ・改行で単語を分解する
            $words = preg_split('/[ \x{3000}\t\r\n]+/u', trim($freeword), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($words as $word) {
                // 1語ごとのORの塊をAND（通常のwhere）でつなぐ。
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
            // カラム名はリクエストのキーから作っているので、srchRules()に
            // 載っているカラムだけを扱う（許可リスト方式）。sanitizeFilters()で
            // 既に絞っているが、SQLのカラム名になる部分なので念のためここでも
            // 確かめておく。
            if (! array_key_exists($column, $srchRules)) {
                continue;
            }

            // 未指定の判定は必ず===の厳密比較で行う。empty()や!$valueで
            // 判定すると、'0'（例: 表示フラグの「非表示」、権限の
            // StaffAcl::Staff=0）まで「未指定」扱いになり、その条件での絞り込みが
            // 黙って無視されてしまう。
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            // まずコーナー側に「自分で処理するか」を聞く。
            if ($this->applyCustomSearch($query, $column, $value)) {
                continue;
            }

            if (is_array($value)) {
                // 複数選択の配列で来たらIN()でOR条件にする。
                $query->whereIn($column, $value);
            } elseif ($this->isEqualSearch($column)) {
                // 完全一致。'1'/'0'のような文字列のまま比較してよい。
                // 真偽値のカラムでも整数のカラムでも、各DBが比較相手の型に
                // 合わせて解釈する。
                $query->where($column, $value);
            } else {
                // 部分一致
                $query->where($column, 'like', "%{$value}%");
            }
        }
    }

    // 検索条件の保存
    public function storeSearchCondition(Request $request): RedirectResponse
    {
        // 検証を通った項目だけをセッションに保存する。sanitizeFilters()は
        // ルールに載っているキーしか残さないので、_token・pageのような
        // 制御用のフィールドも自動的に捨てられる。
        session([$this->searchSessionKey() => $this->sanitizeFilters($request->all())]);

        // 一覧表示するため ?page=1 へリダイレクト
        return redirect()->route(self::INDEX_ROUTE, ['page' => 1]);
    }
}
