{{--
    企業の担当者の情報（お名前・メールアドレス・パスワード）の入力欄一式。

    admin/members/_fields.blade.phpと同じ考え方で、edit（編集）・confirm（更新の確認画面）・
    show（詳細表示）の3画面すべてから、この同じタグをそのまま呼び出す。
    担当者を足すのは企業の側の役目なので、新規登録の画面は無い。
    企業名と担当者IDは変えないので、表示だけにしている。

    呼び出し側が用意する変数：
    - $input        画面に表示する値の配列。送信される項目だけを入れる。
    - $company      担当者の企業。
    - $user         対象の担当者。担当者IDの表示に使う。
    - $readonly     inputに付ける文字列（' readonly'または''）。
    - $required     必須マークのHTML配列。閲覧専用画面では[]でよい。
    - $showPassword パスワード欄一式を表示するかどうか。show（詳細表示）だけfalse。
--}}
<div class="mb-3">
    <label class="form-label">企業</label>
    <div class="form-control-plaintext">{{ $company->name }}（企業ID：{{ $company->code }}）</div>
</div>

<div class="mb-3">
    <label class="form-label">担当者ID</label>
    <div class="form-control-plaintext">{{ $user->login_id }}</div>
</div>

<div class="mb-3">
    <label for="name" class="form-label">お名前 {!! $required['name'] ?? '' !!}</label>
    <input id="name" type="text" name="name"
           class="form-control"
           value="{{ $input['name'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="name">{{ $errors->first('name') }}</div>
</div>

<div class="mb-3">
    <label for="email" class="form-label">メールアドレス {!! $required['email'] ?? '' !!}</label>
    <input id="email" type="email" name="email"
           class="form-control"
           value="{{ $input['email'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
</div>

@if ($showPassword)
    <hr>

    <div class="mb-3">
        <label for="password" class="form-label">新しいパスワード</label>
        <input id="password" type="password" name="password"
               class="form-control"
               autocomplete="new-password"
               value="{{ $input['password'] ?? '' }}"{{ $readonly }}>
        <div class="invalid-feedback" data-item="password">{{ $errors->first('password') }}</div>
        <div class="form-text">
            空欄のままにすると、現在のパスワードは変更されません。
            変更すると、担当者へお知らせのメールを送り、記憶済みの端末とパスキーを無効にします。
        </div>
    </div>

    <div class="mb-3">
        <label for="password_confirmation" class="form-label">新しいパスワード（確認）</label>
        <input id="password_confirmation" type="password" name="password_confirmation"
               autocomplete="new-password"
               class="form-control"
               value="{{ $input['password_confirmation'] ?? '' }}"{{ $readonly }}>
    </div>
@endif
