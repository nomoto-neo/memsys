<?php

namespace Neobit\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Neobit\Core\Code\CodeTable;
use Neobit\Core\Models\Code;

/**
 * 項目見出し一覧：DBで管理するコード表（code_tables）の編集。
 *
 * 編集できるコード表は、config('neobit.code_table_db')に載せたもの。画面のプルダウンで
 * コード表を選ぶと（?type=gender）、そのコード表の行を表示順に並べ、
 * コード値・表示名の書き換え、行の追加・削除、ドラッグでの並び替えをして、
 * 「更新する」でまとめて保存する。画面の動き（行の追加・削除・並び替え、
 * 保存していない変更があるときの確認）はresources/js/code_editor.jsが行う。
 *
 * 設定でfixedをtrueにしたコード表は、並び替えと表示名の変更だけができる
 * （コード値は書き換えられず、行の追加・削除もできない）。画面で入力欄を
 * 読み取り専用にしているだけでなく、update()でも同じ条件を確かめる。
 *
 * ルートはアプリが書く。管理者だけが入れるグループの中に、「頭.index」（GET）と
 * 「頭.update」（PATCH）の組で名前を付ける。頭はアプリが決めてよい。このコントローラーは、
 * 呼ばれたルートの名前から、組になるもう一方の名前を作る。
 *
 * 1行ずつの登録・編集画面は無く、確認画面も挟まない（項目が少なく、間違えてもすぐ直せるため）。
 * 複数の行をまとめて保存するので、FormFlowは使わない。
 */
class CodeTableController
{
    // ---- ルート ----

    /** 一覧のルートの名前の末尾。更新の後の戻り先 */
    private const INDEX_ACTION = 'index';

    /** 更新のルートの名前の末尾 */
    private const UPDATE_ACTION = 'update';

    // ---- このコーナーの項目の定義 ----

    /**
     * 入力バリデーションルール。codesは画面の行の並び順どおりの配列。
     * TrimStrings・ConvertEmptyStringsToNullにより、空欄はnullで届く。
     */
    private function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys($this->codeTables()))],
            'codes' => ['array'],
            'codes.*.code' => ['nullable', 'string', 'max:50'],
            'codes.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    // ---- 一覧・更新 ----

    /** 項目見出し一覧。 */
    public function index(Request $request): View
    {
        $tables = $this->codeTables();

        // 編集できるコード表が設定に1つも無ければ、画面は無い
        abort_if($tables === [], 404);

        // コード表の指定が無い・不正なときは、プルダウンの1番目を表示する。
        $type = (string) $request->query('type');
        if (! array_key_exists($type, $tables)) {
            $type = (string) array_key_first($tables);
        }

        // 検証エラーで戻ってきたときは、送信した内容（行の並び順も）をそのまま出す。
        $oldRows = old('type') === $type ? old('codes') : null;

        if (is_array($oldRows)) {
            // 再表示のとき
            $rows = array_values(array_map(fn ($row) => [
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
            ], $oldRows));
        } else {
            // 初期表示のとき
            $rows = Code::where('type', $type)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['code', 'name'])
                ->map(fn (Code $code) => ['code' => $code->code, 'name' => $code->name])
                ->all();
        }

        return view('neobit::admin.code_tables.index', [
            'types' => array_map(fn (array $table) => $table['label'], $tables),
            'type' => $type,
            'fixed' => $this->isFixed($type),
            'rows' => $rows,
            // 送信された内容を再表示する時は、最初から「変更あり」として扱う。
            'unsaved' => is_array($oldRows),
            'indexRoute' => $this->routeName($request, self::INDEX_ACTION),
            'updateRoute' => $this->routeName($request, self::UPDATE_ACTION),
        ]);
    }

    /**
     * 更新の実行。
     *
     * コード値が空欄の行は保存しない（削除される）。残った行を画面の並び順で
     * sort_orderにし、そのコード表の行を一旦全削除してから再登録する。
     *
     * コード値の重複はエラーにして、何も保存しない。数字だけの値は前ゼロを
     * 除いて比べる（'01'と'1'は重複）。code_table()が数字だけの値を
     * int型にして返すので、前ゼロ違いの2行を保存すると片方が読めなくなるため。
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $type = $validated['type'];

        // コード値が空欄の行を除去して、元の行番号（エラーの表示先）を保ったまま揃える
        $rows = [];
        foreach ($validated['codes'] ?? [] as $index => $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code !== '') {
                $rows[$index] = ['code' => $code, 'name' => (string) ($row['name'] ?? '')];
            }
        }

        // コード値の重複をチェック
        $errors = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $key = (string) CodeTable::normalizeKey($row['code']);
            if (array_key_exists($key, $seen)) {
                $errors["codes.{$index}.code"] = "コード値「{$row['code']}」が重複しています（数字は前ゼロを除いて比べます）。";
            }
            $seen[$key] = true;
        }

        if ($this->isFixed($type)) {
            // 追加・削除が不可に設定されているコード表は、保存済みのコード値と変わっていないかをチェックする
            $saved = Code::where('type', $type)->pluck('code')->sort()->values()->all();
            $sent = collect($rows)->pluck('code')->sort()->values()->all();

            if ($saved !== $sent) {
                $errors['codes'] = 'このコード表は、コード値の変更・追加・削除はできません。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // 保存した人。管理画面のガードで、ログイン中のスタッフ
        $staffId = $request->user()?->getKey();

        DB::transaction(function () use ($type, $rows, $staffId) {
            Code::where('type', $type)->delete();

            $order = 0;
            foreach ($rows as $row) {
                Code::create([
                    'type' => $type,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'sort_order' => $order++,
                    'staff_id' => $staffId,
                ]);
            }
        });

        return redirect()->route($this->routeName($request, self::INDEX_ACTION), ['type' => $type])
            ->with('status', '「'.$this->codeTables()[$type]['label'].'」を更新しました。');
    }

    // ---- 設定とルート ----

    /**
     * 編集できるコード表。コード名 => ['label' => 名前, 'fixed' => コード値を固定するか]
     *
     * @return array<string, array{label: string, fixed?: bool}>
     */
    private function codeTables(): array
    {
        return (array) config('neobit.code_table_db', []);
    }

    /** コード値を固定するコード表か。並び替えと表示名の変更だけができる */
    private function isFixed(string $type): bool
    {
        return (bool) ($this->codeTables()[$type]['fixed'] ?? false);
    }

    /** 呼ばれたルートと組になるルートの名前。「頭.index」から「頭.update」のように、末尾だけを替える */
    private function routeName(Request $request, string $action): string
    {
        return Str::beforeLast((string) $request->route()?->getName(), '.').'.'.$action;
    }
}
