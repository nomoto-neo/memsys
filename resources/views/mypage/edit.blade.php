@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">プロフィール編集</div>
            <div class="card-body">
                <form method="POST" action="{{ route('mypage.update') }}">
                    @csrf
                    {{-- HTMLフォームはGET/POSTしか送れないため、実際にはPOSTで送りつつ
                         このhiddenフィールド(_method)でPATCHとして扱ってほしいことを伝える。
                         Laravelがこれを見て、routes/web.phpのRoute::patch(...)へ振り分ける。 --}}
                    @method('PATCH')

                    <div class="mb-3">
                        <label for="name" class="form-label">お名前 {!! $required['name'] !!}</label>
                        <input id="name" type="text" name="name"
                               class="form-control"
                               value="{{ $input['name'] ?? '' }}" autofocus>
                        <div class="invalid-feedback" data-item="name">{{ $errors->first('name') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="kana" class="form-label">フリガナ {!! $required['kana'] !!}</label>
                        <input id="kana" type="text" name="kana"
                               class="form-control"
                               value="{{ $input['kana'] ?? '' }}">
                        <div class="invalid-feedback" data-item="kana">{{ $errors->first('kana') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">メールアドレス {!! $required['email'] !!}</label>
                        <input id="email" type="email" name="email"
                               class="form-control"
                               value="{{ $input['email'] ?? '' }}">
                        <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">電話番号 {!! $required['phone'] !!}</label>
                        <input id="phone" type="text" name="phone"
                               class="form-control"
                               value="{{ $input['phone'] ?? '' }}" placeholder="例: 090-1234-5678">
                        <div class="invalid-feedback" data-item="phone">{{ $errors->first('phone') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="birthdate" class="form-label">生年月日 {!! $required['birthdate'] !!}</label>
                        <input id="birthdate" type="date" name="birthdate"
                               class="form-control"
                               value="{{ $input['birthdate'] ?? '' }}">
                        <div class="invalid-feedback" data-item="birthdate">{{ $errors->first('birthdate') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="prefecture" class="form-label">都道府県 {!! $required['prefecture'] !!}</label>
                        {{-- $input['prefecture']（コントローラー側でold()と$member->prefectureを
                             マージしたもの）は「バリデーション失敗直後は送信された文字列」
                             「それ以外は$member->prefectureのint（キャスト済み）」という
                             2通りの型が混ざるので、比較前に(int)で揃えている。 --}}
                        <select id="prefecture" name="prefecture"
                                class="form-select">
                            <option value="">選択してください</option>
                            @foreach (code_table('prefectures') as $code => $name)
                                <option value="{{ $code }}"
                                        @selected((int) ($input['prefecture'] ?? 0) === $code)>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" data-item="prefecture">{{ $errors->first('prefecture') }}</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">更新する</button>
                        <a href="{{ route('mypage') }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
