@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                マイページ
                <a href="{{ route('password.edit') }}" class="btn btn-sm btn-outline-primary">パスワードを変更する</a>
                {{-- パスキーの画面への入口。routes/web.phpにmypage.passkeysのルートがあるときだけ出す --}}
                @if (Route::has('mypage.passkeys'))
                    <a href="{{ route('mypage.passkeys') }}" class="btn btn-sm btn-outline-primary">パスキー</a>
                @endif
                <a href="{{ route('mypage.resume') }}" class="btn btn-sm btn-outline-primary" target="_blank">履歴書PDF</a>
                <a href="{{ route('mypage.edit') }}" class="btn btn-sm btn-outline-primary">編集する</a>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">お名前</dt>
                    <dd class="col-sm-8">{{ $member->name }}</dd>

                    <dt class="col-sm-4">フリガナ</dt>
                    <dd class="col-sm-8">{{ $member->kana ?? '（未設定）' }}</dd>

                    <dt class="col-sm-4">メールアドレス</dt>
                    <dd class="col-sm-8">{{ $member->email }}</dd>

                    <dt class="col-sm-4">電話番号</dt>
                    <dd class="col-sm-8">{{ $member->phone ?? '（未設定）' }}</dd>

                    <dt class="col-sm-4">生年月日</dt>
                    <dd class="col-sm-8">{{ optional($member->birthdate)->format('Y年n月j日') ?? '（未設定）' }}</dd>

                    <dt class="col-sm-4">都道府県</dt>
                    <dd class="col-sm-8">{{ code_label('prefectures', $member->prefecture, '（未設定）') }}</dd>

                    <dt class="col-sm-4">お知らせメール</dt>
                    <dd class="col-sm-8">{{ code_label('notice_mail', $member->notice_mail) }}</dd>

                    {{-- 顔写真のURLは、本人とスタッフだけが開けるもの（Member::photo_url） --}}
                    <dt class="col-sm-4">顔写真</dt>
                    <dd class="col-sm-8">
                        @if ($member->photo_url)
                            <img src="{{ $member->photo_url }}" alt="顔写真" class="border rounded" style="max-width: 150px;">
                        @else
                            （未設定）
                        @endif
                    </dd>
                </dl>
            </div>
            <div class="card-footer bg-white text-end">
                <a href="{{ route('mypage.withdraw') }}" class="small text-muted">退会をご希望の方はこちら</a>
            </div>
        </div>
    </div>
</div>
@endsection
