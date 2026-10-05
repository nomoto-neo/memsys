<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BulkMailTemplate;
use App\Rules\BulkMailPlaceholderRule;
use App\Support\FormFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 一斉メールの文面の管理。よく送る案内の件名と本文を登録しておき、送るときに選んで使う。
 * 項目が少なく間違えてもすぐ直せるので、カテゴリーと同じく確認画面を挟まずに保存する。
 */
class BulkMailTemplateController extends Controller
{
    // ---- 共通処理（トレイト） ----

    use FormFlow;

    // ---- 一覧の設定 ----

    // 一覧画面のルート名。登録・更新・削除の後の戻り先
    private const INDEX_ROUTE = 'admin.bulk-mail-templates.index';

    // ---- 入力をそろえる処理（InputNormalizer）の設定 ----

    // 全角と半角をそろえない項目。データの仕様なので、モデルの指定を引く
    private const RAW_INPUT_FIELDS = BulkMailTemplate::RAW_INPUT_FIELDS;

    // ---- このコーナーの項目の定義 ----

    // 本文の初期値。宛先の氏名の差し込みと敬称を1行目に入れておく
    private const DEFAULT_BODY = "{{\$name}} 様\n";

    // 入力の検証ルール。件名に改行があるとメールの見出しとして読まれてしまうので、改行は許さない
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:200', 'not_regex:/[\r\n]/', new BulkMailPlaceholderRule()],
            'body' => ['required', 'string', 'max:20000', new BulkMailPlaceholderRule()],
        ];
    }

    // 保存する項目
    private function saveFieldNames(array $validated, BulkMailTemplate $template): array
    {
        return ['title', 'subject', 'body'];
    }

    // 新規登録の初期値
    private function defaultInput(): array
    {
        return ['body' => self::DEFAULT_BODY];
    }

    // モデルの今の値から、_fields.blade.phpに渡す$inputを組み立てる
    private function inputFromModel(BulkMailTemplate $template): array
    {
        return [
            'title' => $template->title,
            'subject' => $template->subject,
            'body' => $template->body,
        ];
    }

    // ---- 一覧 ----

    // 文面の一覧。数が多くならないので、ページ分けせずに新しく直したものから並べる
    public function index(): View
    {
        return view('admin.bulk_mail_templates.index', [
            'templates' => BulkMailTemplate::orderByDesc('updated_at')->get(),
        ]);
    }

    // ---- 登録 ----

    // 新規登録フォームの表示
    public function create(): View
    {
        return view('admin.bulk_mail_templates.create', [
            'input' => $this->formInput(null, old()),
            'required' => $this->requiredFields(),
        ]);
    }

    // 新規登録の実行
    public function store(Request $request): RedirectResponse
    {
        $this->saveData(new BulkMailTemplate(), $request);

        return redirect()->route(self::INDEX_ROUTE)
            ->with('status', '文面を登録しました。');
    }

    // ---- 編集・削除 ----

    // 編集フォームの表示
    public function edit(BulkMailTemplate $template): View
    {
        return view('admin.bulk_mail_templates.edit', [
            'template' => $template,
            'input' => $this->formInput($template, old()),
            'required' => $this->requiredFields($template),
        ]);
    }

    // 更新の実行
    public function update(Request $request, BulkMailTemplate $template): RedirectResponse
    {
        $this->saveData($template, $request);

        return redirect()->route(self::INDEX_ROUTE)
            ->with('status', '文面を更新しました。');
    }

    // 削除の実行。送信の記録は件名と本文を写して持っているので、文面を消しても影響しない
    public function destroy(BulkMailTemplate $template): RedirectResponse
    {
        $this->deleteData($template);

        return redirect()->route(self::INDEX_ROUTE)
            ->with('status', '文面を削除しました。');
    }
}
