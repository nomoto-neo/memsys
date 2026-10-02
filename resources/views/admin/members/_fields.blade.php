{{--
    会員情報（お名前・フリガナ・メールアドレス・電話番号・生年月日・
    都道府県・パスワード）の入力欄一式。

    admin/staff/_fields.blade.phpと同じ考え方で、edit（編集）・
    confirm（更新の確認画面）・show（詳細表示）の3画面すべてから、
    この同じタグをそのまま呼び出す。項目を1つ増やす・ラベルを直す、
    といった変更は、このファイル1箇所を直せば3画面すべてに反映される。

    管理画面から会員を新規作成することは無い（会員は自分で登録する）ので、
    スタッフ版の_fields.blade.phpにあった$isCreate（パスワード必須の
    切り替え）は無い。パスワードは常に「空欄なら変更しない」の1択。

    呼び出し側が用意する変数：
    - $input        画面に表示する値の配列（name/kana/email/phone/
                     birthdate/prefecture/password/password_confirmation）。
                     editではold()を優先したデフォルト値、confirmでは
                     直前に検証した確認前データ、showでは対象会員の
                     現在値を、それぞれ呼び出し側（コントローラー）で
                     組み立てて渡す。$inputには送信される項目だけを入れ、
                     表示専用の値は混ぜない、というのがこのプロジェクト
                     全体の規約（詳しくはresources/views/_confirm_hiddenの
                     コメント参照）。
    - $readonly     text/email/password/date系inputに付ける文字列
                     （' readonly'または''）。
    - $disabled     select（都道府県）に付ける文字列（' disabled'または''）。
    - $required     必須マークのHTML配列。閲覧専用画面では[]でよい。
    - $showPassword パスワード欄一式を表示するかどうか。edit/confirmは
                     常にtrue。show（詳細表示）だけfalse（ハッシュ化された
                     値しか無く、再現して見せられるものが無いため）。
--}}
<div class="mb-3">
    <label for="name" class="form-label">お名前 {!! $required['name'] ?? '' !!}</label>
    <input id="name" type="text" name="name"
           class="form-control"
           value="{{ $input['name'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="name">{{ $errors->first('name') }}</div>
</div>

<div class="mb-3">
    <label for="kana" class="form-label">フリガナ {!! $required['kana'] ?? '' !!}</label>
    <input id="kana" type="text" name="kana"
           class="form-control"
           value="{{ $input['kana'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="kana">{{ $errors->first('kana') }}</div>
</div>

<div class="mb-3">
    <label for="email" class="form-label">メールアドレス {!! $required['email'] ?? '' !!}</label>
    <input id="email" type="email" name="email"
           class="form-control"
           value="{{ $input['email'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
</div>

<div class="mb-3">
    <label for="phone" class="form-label">電話番号 {!! $required['phone'] ?? '' !!}</label>
    <input id="phone" type="text" name="phone"
           class="form-control"
           value="{{ $input['phone'] ?? '' }}" placeholder="例: 090-1234-5678"{{ $readonly }}>
    <div class="invalid-feedback" data-item="phone">{{ $errors->first('phone') }}</div>
</div>

<div class="mb-3">
    <label for="birthdate" class="form-label">生年月日 {!! $required['birthdate'] ?? '' !!}</label>
    <input id="birthdate" type="date" name="birthdate"
           class="form-control"
           value="{{ $input['birthdate'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="birthdate">{{ $errors->first('birthdate') }}</div>
</div>

<div class="mb-3">
    <label for="prefecture" class="form-label">都道府県 {!! $required['prefecture'] ?? '' !!}</label>
    <select id="prefecture" name="prefecture"
            class="form-select"{{ $disabled }}>
        <option value="">選択してください</option>
        @foreach (code_table('prefectures') as $code => $name)
            <option value="{{ $code }}" @selected((int) ($input['prefecture'] ?? 0) === $code)>
                {{ $name }}
            </option>
        @endforeach
    </select>
    <div class="invalid-feedback" data-item="prefecture">{{ $errors->first('prefecture') }}</div>
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
        <div class="form-text">空欄のままにすると、現在のパスワードは変更されません。</div>
    </div>

    <div class="mb-3">
        <label for="password_confirmation" class="form-label">新しいパスワード（確認）</label>
        <input id="password_confirmation" type="password" name="password_confirmation"
               autocomplete="new-password"
               class="form-control"
               value="{{ $input['password_confirmation'] ?? '' }}"{{ $readonly }}>
    </div>
@endif
