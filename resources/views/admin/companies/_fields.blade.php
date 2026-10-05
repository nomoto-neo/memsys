{{--
    企業の情報（企業名・フリガナ・代表者名・郵便番号・都道府県・住所・電話番号・
    ホームページURL・管理メモ）の入力欄一式。

    admin/staff/_fields.blade.phpと同じ考え方で、create（新規登録）・edit（編集）・
    confirm（確認画面）・show（詳細表示）の4画面すべてから、この同じタグをそのまま呼び出す。

    企業IDと状態は、ここでは変えない。企業IDは登録のときに自動で決まるので、登録の後の画面で
    表示だけにし、状態は詳細画面の承認・却下・停止・再開のボタンで変える。
    新規登録のときだけ、最初の担当者に招待を送るメールアドレスの欄を出す。

    呼び出し側が用意する変数：
    - $isCreate  新規登録（とその確認画面）ならtrue。
    - $input     画面に表示する値の配列。送信される項目だけを入れる。
    - $company   対象の企業。新規登録ではnull。企業IDの表示に使う。
    - $readonly  text系inputとtextareaに付ける文字列（' readonly'または''）。
    - $disabled  select（都道府県）に付ける文字列（' disabled'または''）。
    - $required  必須マークのHTML配列。閲覧専用画面では[]でよい。
--}}
@if (! $isCreate)
    <div class="mb-3">
        <label class="form-label">企業ID</label>
        <div class="form-control-plaintext">{{ $company->code }}</div>
    </div>
@endif

<div class="mb-3">
    <label for="name" class="form-label">企業名 {!! $required['name'] ?? '' !!}</label>
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
    <label for="representative" class="form-label">代表者名 {!! $required['representative'] ?? '' !!}</label>
    <input id="representative" type="text" name="representative"
           class="form-control"
           value="{{ $input['representative'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="representative">{{ $errors->first('representative') }}</div>
</div>

<div class="mb-3">
    <label for="zip" class="form-label">郵便番号 {!! $required['zip'] ?? '' !!}</label>
    <input id="zip" type="text" name="zip"
           class="form-control"
           value="{{ $input['zip'] ?? '' }}" placeholder="例: 100-0001"{{ $readonly }}>
    <div class="invalid-feedback" data-item="zip">{{ $errors->first('zip') }}</div>
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

<div class="mb-3">
    <label for="address" class="form-label">住所 {!! $required['address'] ?? '' !!}</label>
    <input id="address" type="text" name="address"
           class="form-control"
           value="{{ $input['address'] ?? '' }}"{{ $readonly }}>
    <div class="invalid-feedback" data-item="address">{{ $errors->first('address') }}</div>
</div>

<div class="mb-3">
    <label for="tel" class="form-label">電話番号 {!! $required['tel'] ?? '' !!}</label>
    <input id="tel" type="text" name="tel"
           class="form-control"
           value="{{ $input['tel'] ?? '' }}" placeholder="例: 03-1234-5678"{{ $readonly }}>
    <div class="invalid-feedback" data-item="tel">{{ $errors->first('tel') }}</div>
</div>

<div class="mb-3">
    <label for="url" class="form-label">ホームページURL {!! $required['url'] ?? '' !!}</label>
    <input id="url" type="text" name="url"
           class="form-control"
           value="{{ $input['url'] ?? '' }}" placeholder="例: https://example.com/"{{ $readonly }}>
    <div class="invalid-feedback" data-item="url">{{ $errors->first('url') }}</div>
</div>

{{-- 管理メモ。スタッフが対応の経緯などを書き残す欄で、企業の側には見せない --}}
<div class="mb-3">
    <label for="staff_memo" class="form-label">管理メモ {!! $required['staff_memo'] ?? '' !!}</label>
    <textarea id="staff_memo" name="staff_memo" rows="4" class="form-control"{{ $readonly }}>{{ $input['staff_memo'] ?? '' }}</textarea>
    <div class="form-text">企業会員には表示されません。</div>
    <div class="invalid-feedback" data-item="staff_memo">{{ $errors->first('staff_memo') }}</div>
</div>

{{-- 最初の担当者への招待。担当者IDとパスワードは、招待のメールのリンクから本人が決める --}}
@if ($isCreate)
    <hr>

    <div class="mb-3">
        <label for="invite_email" class="form-label">最初の担当者のメールアドレス {!! $required['invite_email'] ?? '' !!}</label>
        <input id="invite_email" type="email" name="invite_email"
               class="form-control"
               value="{{ $input['invite_email'] ?? '' }}"{{ $readonly }}>
        <div class="form-text">登録すると、このアドレスへ招待のメールを送ります。担当者IDとパスワードは、招待された方がご自身で決めます。</div>
        <div class="invalid-feedback" data-item="invite_email">{{ $errors->first('invite_email') }}</div>
    </div>
@endif
