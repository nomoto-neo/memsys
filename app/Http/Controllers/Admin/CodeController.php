<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CodeType;
use App\Http\Controllers\Controller;
use App\Models\Code;
use App\Support\CodeTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 項目見出し一覧：DBで管理するコード表（t_codes）の編集。
 *
 * 編集できるコード表は、App\Enums\CodeTypeに載せたもの。画面のプルダウンで
 * コード表を選ぶと（?type=gender）、そのコード表の行を表示順に並べ、
 * コード値・表示名の書き換え、行の追加・削除、ドラッグでの並び替えをして、
 * 「更新する」でまとめて保存する。画面の動き（行の追加・削除・並び替え、
 * 保存していない変更があるときの確認）はresources/js/code_editor.jsが行う。
 *
 * CodeType::isFixed()がtrueのコード表は、並び替えと表示名の変更だけができる
 * （コード値は書き換えられず、行の追加・削除もできない）。画面で入力欄を
 * 読み取り専用にしているだけでなく、update()でも同じ条件を確かめる。
 *
 * 1行ずつの登録・編集画面は無く、確認画面も挟まない（カテゴリーと同じく、
 * 項目が少なく、間違えてもすぐ直せるため）。複数の行をまとめて保存するので、
 * FormFlowは使わない。
 */
class CodeController extends Controller
{
    // ---- 一覧の設定 ----

    // 一覧画面のルート名。更新の後の戻り先。
    private const INDEX_ROUTE = 'admin.codes.index';

    // ---- このコーナーの項目の定義 ----

    // 入力バリデーションルール。codesは画面の行の並び順どおりの配列。
    // TrimStrings・ConvertEmptyStringsToNullにより、空欄はnullで届く。
    private function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CodeType::class)],
            'codes' => ['array'],
            'codes.*.code' => ['nullable', 'string', 'max:50'],
            'codes.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    // ---- 一覧・更新 ----

    // 項目見出し一覧。コード表の指定が無い・不正なときは、プルダウンの1番目を表示する。
    public function index(Request $request): View
    {
        $type = CodeType::tryFrom((string) $request->query('type')) ?? CodeType::cases()[0];

        // 検証エラーで戻ってきたときは、送信した内容（行の並び順も）をそのまま出す。
        // 表示しているのは保存していない内容なので、最初から「変更あり」として扱う。
        $oldRows = old('type') === $type->value ? old('codes') : null;

        $rows = is_array($oldRows)
            ? array_values(array_map(fn ($row) => [
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
            ], $oldRows))
            : Code::where('type', $type->value)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['code', 'name'])
                ->map(fn (Code $code) => ['code' => $code->code, 'name' => $code->name])
                ->all();

        return view('admin.codes.index', [
            'types' => code_table('code_type'),
            'type' => $type,
            'rows' => $rows,
            'unsaved' => is_array($oldRows),
        ]);
    }

    /**
     * 更新の実行（PATCH /admin/codes）。
     *
     * コード値が空欄の行は保存しない（削除される）。残った行を画面の並び順で
     * sort_orderにし、そのコード表の行をすべて入れ替える。
     *
     * コード値の重複はエラーにして、何も保存しない。数字だけの値は前ゼロを
     * 除いて比べる（'01'と'1'は重複）。code_table()が数字だけの値をint型にして
     * 返すので、前ゼロ違いの2行を保存すると、片方が読めなくなるため。
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $type = CodeType::from($validated['type']);

        // コード値が空欄の行を除き、元の行番号（エラーの表示先）を保ったまま揃える
        $rows = [];
        foreach ($validated['codes'] ?? [] as $index => $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code !== '') {
                $rows[$index] = ['code' => $code, 'name' => (string) ($row['name'] ?? '')];
            }
        }

        $errors = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $key = (string) CodeTable::normalizeKey($row['code']);
            if (array_key_exists($key, $seen)) {
                $errors["codes.{$index}.code"] = "コード値「{$row['code']}」が重複しています（数字は前ゼロを除いて比べます）。";
            }
            $seen[$key] = true;
        }

        if ($type->isFixed()) {
            // コード値を固定するコード表は、保存済みのコード値と1対1で同じでなければならない
            $saved = Code::where('type', $type->value)->pluck('code')->sort()->values()->all();
            $sent = collect($rows)->pluck('code')->sort()->values()->all();

            if ($saved !== $sent) {
                $errors['codes'] = 'このコード表は、コード値の変更・追加・削除はできません。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $staffId = Auth::guard('admin')->id();

        DB::transaction(function () use ($type, $rows, $staffId) {
            Code::where('type', $type->value)->delete();

            $order = 0;
            foreach ($rows as $row) {
                Code::create([
                    'type' => $type->value,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'sort_order' => $order++,
                    'staff_id' => $staffId,
                ]);
            }
        });

        return redirect()->route(self::INDEX_ROUTE, ['type' => $type->value])
            ->with('status', '「'.$type->label().'」を更新しました。');
    }
}
