@extends('layouts.company')

@section('title', '企業の情報の編集')

{{-- 企業の情報の編集。どの担当者も変えられる。企業IDは変えられないので、表示だけにしている --}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">企業の情報の編集</div>
            <div class="card-body">
                <form method="POST" action="{{ route('company.mypage.update') }}">
                    @csrf
                    {{-- HTMLフォームはGET/POSTしか送れないため、実際にはPOSTで送りつつ
                         このhiddenフィールド(_method)でPATCHとして扱ってほしいことを伝える。 --}}
                    @method('PATCH')

                    <div class="mb-3">
                        <label class="form-label">企業ID</label>
                        <div class="form-control-plaintext">{{ $company->code }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">企業名 {!! $required['name'] !!}</label>
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
                        <label for="representative" class="form-label">代表者名 {!! $required['representative'] !!}</label>
                        <input id="representative" type="text" name="representative"
                               class="form-control"
                               value="{{ $input['representative'] ?? '' }}">
                        <div class="invalid-feedback" data-item="representative">{{ $errors->first('representative') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="zip" class="form-label">郵便番号 {!! $required['zip'] !!}</label>
                        <input id="zip" type="text" name="zip"
                               class="form-control"
                               value="{{ $input['zip'] ?? '' }}" placeholder="例: 100-0001">
                        <div class="invalid-feedback" data-item="zip">{{ $errors->first('zip') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="prefecture" class="form-label">都道府県 {!! $required['prefecture'] !!}</label>
                        {{-- $input['prefecture']は、入力エラーの直後は送信された文字列、それ以外は
                             企業の今の値（int）なので、比べる前に(int)でそろえている --}}
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

                    <div class="mb-3">
                        <label for="address" class="form-label">住所 {!! $required['address'] !!}</label>
                        <input id="address" type="text" name="address"
                               class="form-control"
                               value="{{ $input['address'] ?? '' }}">
                        <div class="invalid-feedback" data-item="address">{{ $errors->first('address') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="tel" class="form-label">電話番号 {!! $required['tel'] !!}</label>
                        <input id="tel" type="text" name="tel"
                               class="form-control"
                               value="{{ $input['tel'] ?? '' }}" placeholder="例: 03-1234-5678">
                        <div class="invalid-feedback" data-item="tel">{{ $errors->first('tel') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="url" class="form-label">ホームページURL {!! $required['url'] !!}</label>
                        <input id="url" type="text" name="url"
                               class="form-control"
                               value="{{ $input['url'] ?? '' }}" placeholder="例: https://example.com/">
                        <div class="invalid-feedback" data-item="url">{{ $errors->first('url') }}</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">更新する</button>
                        <a href="{{ route('company.mypage') }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
