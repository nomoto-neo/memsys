{{--
    カテゴリー情報（名前）の入力欄。

    create（新規登録）・edit（編集）の2画面から、この同じタグを
    そのまま呼び出す。項目が名前1つだけなので、admin/staff・
    admin/membersの_fields.blade.phpほどの効果は無いが、「同じ入力欄を
    複数画面に書き分けない」という考え方自体は変えていない。

    呼び出し側が用意する変数：
    - $input    画面に表示する値の配列（name）。createではold()、
                editではold()を優先しつつ対象カテゴリーの現在値を、
                それぞれ呼び出し側（コントローラー）で組み立てて渡す。
                $inputには送信される項目だけを入れ、表示専用の値は
                混ぜない、というのがこのプロジェクト全体の規約（詳しくは
                resources/views/_confirm_hiddenのコメント参照）。
    - $required 必須マークの配列（項目名 => マーク）。コントローラーが
                rules()から組み立てる（FormFlow::requiredFields()）。
--}}
<div class="mb-3">
    <label for="name" class="form-label">カテゴリー名 {!! $required['name'] ?? '' !!}</label>
    <input id="name" type="text" name="name"
           class="form-control"
           value="{{ $input['name'] ?? '' }}">
    <div class="invalid-feedback" data-item="name">{{ $errors->first('name') }}</div>
</div>
