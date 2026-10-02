<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\FormFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ニュースカテゴリーの管理。
 *
 * スタッフ・会員のような「編集→確認画面→更新」の3段階は、あえて
 * このコントローラーでは採用していない。項目が名前1つだけで、
 * 確認画面を挟む複雑さに見合わないと判断したため。管理画面の全部を
 * 同じパターンに揃える必要は無く、「入力項目が少なく、間違えても
 * すぐ直せるものは確認画面無しで直接保存する」という、もう1つの
 * 型の実例として位置づけている。
 *
 * 確認画面が無いだけで、検証・保存・削除の手順はほかのコーナーと同じく
 * FormFlowを使う（store()・update()からそのままsaveData()を呼ぶ）。
 */
class CategoryController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 入力→保存の共通処理はFormFlowトレイトが提供する。
    // クラス側は rules() saveFieldNames() inputFromModel() と、必要なら additionalFields() などを用意する。
    use FormFlow;

    // ---- 一覧の設定 ----

    // 一覧画面のルート名。登録・更新・削除・並び替えの後の戻り先。
    private const INDEX_ROUTE = 'admin.categories.index';

    // ---- このコーナーの項目の定義 ----

    // 入力バリデーションルール
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    // 保存する項目（t_categoriesのカラム）。ここに書いた項目だけを保存する。
    // 表示順（display_order）は入力値ではないので、additionalFields()で扱う。
    private function saveFieldNames(array $validated, Category $category): array
    {
        return ['name'];
    }

    // saveFieldNames()に加えて保存する項目（項目名 => 値）。
    private function additionalFields(array $validated, Category $category): array
    {
        if ($category->exists) {
            // 更新では表示順を変えない（並び替えはreorder()で行う）
            return [];
        }

        // 新しいカテゴリーは常に一覧の末尾に追加する（既存の最大display_order + 1。1件も無ければ0）
        return ['display_order' => (int) (Category::max('display_order') ?? -1) + 1];
    }

    // モデルの今の値から、_fields.blade.phpに渡す$inputを組み立てる（編集で使う）。
    private function inputFromModel(Category $category): array
    {
        return [
            'name' => $category->name,
        ];
    }

    // ---- 一覧・並び替え ----

    // カテゴリー一覧
    // 並び替え（ドラッグ操作）は「今存在する全件」が画面上に無いと
    // 成立しないので、ここだけは他の一覧と違ってページングをしない。
    public function index(): View
    {
        // withCount('news')で、カテゴリーごとに紐づく記事数を1回の
        // クエリでまとめて取得する。1件ずつnews()->count()を呼ぶと
        // カテゴリー件数分のクエリが飛ぶN+1になってしまうため。
        $categories = Category::withCount('news')
            ->orderBy('display_order')
            ->get();

        return view('admin.categories.index', [
            'categories' => $categories,
        ]);
    }

    /**
     * 並び替えの保存（PATCH /admin/categories/reorder）。
     *
     * 一覧画面でドラッグして並べ替えた後の順番どおりに、カテゴリーidが
     * 並んだ配列（order）がPOSTされてくる。その配列のインデックス
     * （0, 1, 2, ...）をそのまま新しいdisplay_orderとして書き込む。
     *
     * 入力フォーム（必須マークを出す画面）の送信ではないので、検証ルールは
     * rules()に入れず、ここに直接書いている。
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', Rule::exists('t_categories', 'id')],
        ]);

        // 1件ずつUPDATEを発行するが、途中で失敗したときに一部だけ
        // 順番が書き換わった中途半端な状態にしないよう、トランザクションで
        // まとめて実行する。
        DB::transaction(function () use ($validated) {
            foreach ($validated['order'] as $index => $categoryId) {
                Category::whereKey($categoryId)->update(['display_order' => $index]);
            }
        });

        return redirect()->route(self::INDEX_ROUTE)
            ->with('status', '表示順を更新しました。');
    }

    // ---- 登録 ----

    // 新規登録フォームの表示
    public function create(): View
    {
        return view('admin.categories.create', [
            // old() があればそちらを優先
            'input' => $this->formInput(null, old()),
            'required' => $this->requiredFields(),
        ]);
    }

    // 新規登録の実行（確認画面は挟まない）
    public function store(Request $request): RedirectResponse
    {
        $this->saveData(new Category(), $request);

        return redirect()->route(self::INDEX_ROUTE)
            ->with('status', 'カテゴリーを登録しました。');
    }

    // ---- 編集・削除 ----

    // 編集フォームの表示
    public function edit(Category $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
            // old() があればそちらを優先
            'input' => $this->formInput($category, old()),
            'required' => $this->requiredFields($category),
        ]);
    }

    // 更新の実行（確認画面は挟まない）
    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->saveData($category, $request);

        return redirect()->route(self::INDEX_ROUTE)
            ->with('status', 'カテゴリーを更新しました。');
    }

    /**
     * 削除の実行。
     *
     * 使用中（t_news_categoryに紐づく記事が1件でもある）カテゴリーは、
     * 一覧画面側で削除ボタン自体をdisabledにしているが、URLを直接
     * 叩かれた場合に備えてサーバー側でも同じ条件を再チェックする
     * （画面上のガードとサーバー側のガードは必ず両方持つ）。
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->news()->exists()) {
            return redirect()->route(self::INDEX_ROUTE)
                ->with('error', 'このカテゴリーは記事で使われているため削除できません。');
        }

        $this->deleteData($category);

        return redirect()->route(self::INDEX_ROUTE)
            ->with('status', 'カテゴリーを削除しました。');
    }
}
