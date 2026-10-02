{{--
    スタッフ情報（お名前・メールアドレス・権限・パスワード）の入力欄一式。

    create（新規登録）・edit（編集）・confirm（登録/更新の確認画面）・
    show（詳細表示）の4画面すべてから、この同じタグをそのまま呼び出す。
    項目を1つ増やす・ラベルを直す、といった変更は、このファイル1箇所を
    直せば4画面すべてに反映される。

    呼び出し側が用意する変数：
    - $isCreate     新規登録（画面・確認画面）ならtrue、編集（画面・確認画面・
                     詳細表示）ならfalse。「パスワードは必須か」「空欄なら
                     変更しないと案内するか」を、この1つの真偽値だけで
                     決めている（StaffController::rules()の
                     `$passwordRequired = $staff === null`と同じ判断基準だが、
                     このパーツ自体はスタッフのモデルそのものを必要としない
                     ——名前もメールも権限もパスワードも、表示する値は
                     すべて$inputから取るため——ので、モデルを渡さず
                     この真偽値だけを渡している）。
    - $input        画面に表示する値の配列（name/login_id/email/acl/password/
                     password_confirmation）。create/editではold()を
                     優先したデフォルト値、confirmでは直前に検証した
                     確認前データ、showでは対象スタッフの現在値を、
                     それぞれ呼び出し側（コントローラー）で組み立てて渡す。
                     $inputには送信される項目だけを入れ、表示専用の値は
                     混ぜない、というのがこのプロジェクト全体の規約（詳しくは
                     resources/views/_confirm_hiddenのコメント参照）。
    - $readonly     text/email/password系inputに付ける文字列
                     （' readonly'または''）。
    - $disabled     select（権限）に付ける文字列（' disabled'または''）。
    - $required     必須マークのHTML配列。閲覧専用画面では[]でよい。
    - $showAcl      権限欄そのものを表示するかどうか
                     （本人が非管理者のときfalse。show画面では常にtrue）。
    - $showPassword パスワード欄一式を表示するかどうか。create/edit/confirmは
                     常にtrue。show（詳細表示）だけfalse
                     （ハッシュ化された値しか無く、再現して見せられる
                     ものが無いため、そもそも欄自体を出さない）。
--}}
<div class="mb-3">
    <label for="name" class="form-label">お名前 {!! $required['name'] ?? '' !!}</label>
    <input id="name" type="text" name="name"
           class="form-control"
           value="{{ $input['name'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="name">{{ $errors->first('name') }}</div>
</div>

<div class="mb-3">
    <label for="login_id" class="form-label">ログインID {!! $required['login_id'] ?? '' !!}</label>
    <input id="login_id" type="text" name="login_id"
           autocomplete="off"
           class="form-control"
           value="{{ $input['login_id'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="login_id">{{ $errors->first('login_id') }}</div>
</div>

<div class="mb-3">
    <label for="email" class="form-label">メールアドレス {!! $required['email'] ?? '' !!}</label>
    <input id="email" type="email" name="email"
           class="form-control"
           value="{{ $input['email'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
</div>

@if ($showAcl)
    <div class="mb-3">
        <label for="acl" class="form-label">権限 {!! $required['acl'] ?? '' !!}</label>
        <select id="acl" name="acl"
                class="form-select"{{ $disabled }}>
            @foreach (code_table('staff_acl') as $value => $label)
                <option value="{{ $value }}" @selected((string) ($input['acl'] ?? '') === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="form-text">スタッフは自分自身の情報の編集だけができます。管理者は、スタッフ一覧の表示と、スタッフの登録・削除もできます。</div>
        <div class="invalid-feedback" data-item="acl">{{ $errors->first('acl') }}</div>
    </div>
@endif

@if ($showPassword)
    <hr>

    <div class="mb-3">
        <label for="password" class="form-label">
            @if ($isCreate)
                パスワード {!! $required['password'] ?? '' !!}
            @else
                新しいパスワード
            @endif
        </label>
        <input id="password" type="password" name="password"
               autocomplete="new-password"
               class="form-control"
               value="{{ $input['password'] ?? '' }}"{{ $readonly }}>
        <div class="invalid-feedback" data-item="password">{{ $errors->first('password') }}</div>
        @if (! $isCreate)
            <div class="form-text">空欄のままにすると、現在のパスワードは変更されません。</div>
        @endif
    </div>

    <div class="mb-3">
        <label for="password_confirmation" class="form-label">
            @if ($isCreate)
                パスワード（確認） {!! $required['password_confirmation'] ?? '' !!}
            @else
                新しいパスワード（確認）
            @endif
        </label>
        <input id="password_confirmation" type="password" name="password_confirmation"
               autocomplete="new-password"
               class="form-control"
               value="{{ $input['password_confirmation'] ?? '' }}"{{ $readonly }}>
    </div>
@endif
