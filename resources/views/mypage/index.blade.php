@extends('layouts.app')

{{--
    マイページ。左にメニュー、右に登録内容を並べる。画面への入口はメニューにまとめ、入口が増えても
    1行足すだけで済むようにしている。幅の狭い画面では、メニューが上、登録内容が下に並ぶ。
--}}
@section('content')
<div class="row justify-content-center g-4">
    <div class="col-md-4 col-lg-3">
        <div class="card">
            <div class="card-header">メニュー</div>
            <div class="list-group list-group-flush">
                <a href="{{ route('mypage.edit') }}" class="list-group-item list-group-item-action">登録内容を編集する</a>
                <a href="{{ route('password.edit') }}" class="list-group-item list-group-item-action">パスワードを変更する</a>
                {{-- パスキーの画面への入口。routes/web.phpにmypage.passkeysのルートがあるときだけ出す --}}
                @if (Route::has('mypage.passkeys'))
                    <a href="{{ route('mypage.passkeys') }}" class="list-group-item list-group-item-action">パスキー</a>
                @endif
                {{-- 2段階認証の画面への入口。認証アプリの方式か、バックアップコードを使うときに出す。
                     メールの方式では、できることがバックアップコードの発行だけなので、名前もそれに合わせる --}}
                @if ($member::usesTotp() || $member::usesBackupCodes())
                    <a href="{{ route('mypage.twoFactor') }}" class="list-group-item list-group-item-action">{{ $member::usesTotp() ? '2段階認証' : 'バックアップコード' }}</a>
                @endif
                <a href="{{ route('mypage.resume') }}" class="list-group-item list-group-item-action" target="_blank">履歴書PDF</a>
                <a href="{{ route('mypage.withdraw') }}" class="list-group-item list-group-item-action text-muted small">退会する</a>
            </div>
        </div>
    </div>

    <div class="col-md-8 col-lg-7">
        <div class="card">
            <div class="card-header">登録内容</div>
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
                    <dd class="col-sm-8">{{ $member->birthdate?->format('Y年n月j日') ?? '（未設定）' }}</dd>

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
        </div>
    </div>
</div>
@endsection
