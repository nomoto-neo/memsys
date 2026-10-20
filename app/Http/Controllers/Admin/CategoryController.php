<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Neobit\Core\Crud\FormFlow;

/**
 * ニュースカテゴリーの管理。
 *
 * 項目が名前だけで、間違えてもすぐ直せるので、確認画面を挟まずに保存する。
 * 確認画面のある型とは別の、もう1つの型の実例。検証・保存・削除はFormFlowを使う。
 */
class CategoryController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 入力→保存の共通処理はFormFlowトレイトが提供する。
    // クラス側は rules() formValues() saveValues() を用意する。
    use FormFlow;

    // ---- 一覧の設定 ----

    /** 一覧画面のルート名。登録・更新・削除・並び替えの後の戻り先。 */
    private const INDEX_ROUTE = 'admin.categories.index';

    // ---- このコーナーの項目の定義 ----

    /** 入力バリデーションルール */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /** モデルの今の値から、_fields.blade.phpに渡す$inputを組み立てる（編集で使う）。 */
    private function formValues(Category $category): array
    {
        return [
            'name' => $category->name,
        ];
    }

    // ---- 一覧・並び替え ----

    /**
     * カテゴリー一覧。並び替え（ドラッグ操作）には全件が画面に無いといけないので、
     * ほかの一覧と違ってページ分けしない。
     */
    public function index(): View
    {
        // カテゴリーごとの記事数も一緒に取る（withCount()で1回のクエリにまとめ、
        // 1件ずつ数えるN+1を避ける。使用中の件数の表示と、削除ボタンの無効化に使う）
        $categories = Category::withCount('news')
            ->orderBy('seq')
            ->get();

        return view('admin.categories.index', [
            'categories' => $categories,
        ]);
    }

    /**
     * 並び替えの保存（PATCH /admin/categories/reorder）。
     *
     * 一覧でドラッグした後の順番に、カテゴリーのidが並んだ配列が送られてくるので、
     * その順番をそのまま表示順にする。入力フォームの送信ではないので、検証のルールは
     * rules()ではなく、ここに直接書いている。
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', Rule::exists('t_categories', 'id')],
        ]);

        // 送られてきた順番（配列の添え字）を、そのまま表示順にする。1件ずつ更新するので、
        // 途中で失敗して一部だけ書き換わらないよう、トランザクションでまとめる
        DB::transaction(function () use ($validated) {
            foreach ($validated['order'] as $index => $categoryId) {
                Category::whereKey($categoryId)->update(['seq' => $index]);
            }
        });

        return redirect()->route(self::INDEX_ROUTE)
            ->with('status', '表示順を更新しました。');
    }

    // ---- 登録 ----

    /** 新規登録フォームの表示 */
    public function create(): View
    {
        return view('admin.categories.create', [
            // old() があればそちらを優先
            'input' => $this->inputPageValues(null, old()),
            'required' => $this->requiredFields(),
        ]);
    }

    /** 新規登録の実行（確認画面は挟まない） */
    public function store(Request $request): RedirectResponse
    {
        $this->saveData(new Category(), $request);

        return redirect()->route(self::INDEX_ROUTE)
            ->with('status', 'カテゴリーを登録しました。');
    }

    // ---- 編集・削除 ----

    /** 編集フォームの表示 */
    public function edit(Category $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
            // old() があればそちらを優先
            'input' => $this->inputPageValues($category, old()),
            'required' => $this->requiredFields($category),
        ]);
    }

    /** 更新の実行（確認画面は挟まない） */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->saveData($category, $request);

        return redirect()->route(self::INDEX_ROUTE)
            ->with('status', 'カテゴリーを更新しました。');
    }

    /**
     * 削除の実行。記事で使われているカテゴリーは消さない。
     * 一覧でも削除ボタンを押せなくしているが、URLを直接送られたときのために、ここでも確かめる。
     */
    public function destroy(Category $category): RedirectResponse
    {
        // 記事で使われているカテゴリーは消さない
        if ($category->news()->exists()) {
            return redirect()->route(self::INDEX_ROUTE)
                ->with('error', 'このカテゴリーは記事で使われているため削除できません。');
        }

        $this->deleteData($category);

        return redirect()->route(self::INDEX_ROUTE)
            ->with('status', 'カテゴリーを削除しました。');
    }

    // ---- 保存・削除処理の補助（FormFlow） ----

    /** 保存する値（t_categoriesの列 => 値）。ここで返した列だけを保存する。 */
    private function saveValues(array $validated, Category $category): array
    {
        // 入力値をそのまま保存する列。送られてこなかった列は null になる
        $saveFieldNames = ['name'];

        $values = $this->pickValues($validated, $saveFieldNames);

        // 新しいカテゴリーは常に一覧の末尾に追加する（既存の最大seq + 1。1件も無ければ0）。
        // 更新では表示順を変えない（並び替えはreorder()で行う）
        if (! $category->exists) {
            $values['seq'] = (int) (Category::max('seq') ?? -1) + 1;
        }

        return $values;
    }
}
