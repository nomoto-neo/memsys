@extends('layouts.company')

@section('title', 'マイページ')

{{--
    企業会員のマイページ。$companyはログイン中の担当者の企業、$userはログイン中の担当者。
    左にメニュー、右に企業の情報と担当者自身の情報を並べる。画面への入口はメニューにまとめ、
    入口が増えても1行足すだけで済むようにしている。幅の狭い画面では、メニューが上に並ぶ。
--}}
@section('content')
<div class="row justify-content-center g-4">
    <div class="col-md-4 col-lg-3">
        <div class="card">
            <div class="card-header">メニュー</div>
            <div class="list-group list-group-flush">
                <a href="{{ route('company.mypage.edit') }}" class="list-group-item list-group-item-action">企業の情報を編集する</a>
                {{-- 担当者の管理への入口。どの担当者も、招待と、ほかの担当者の編集・削除ができる --}}
                <a href="{{ route('company.users.index') }}" class="list-group-item list-group-item-action">担当者を管理する</a>
                <a href="{{ route('company.mypage.profile') }}" class="list-group-item list-group-item-action">あなたの情報を編集する</a>
                <a href="{{ route('company.password.edit') }}" class="list-group-item list-group-item-action">パスワードを変更する</a>
                {{-- パスキーの画面への入口。routes/web.phpにcompany.mypage.passkeysのルートがあるときだけ出す --}}
                @if (Route::has('company.mypage.passkeys'))
                    <a href="{{ route('company.mypage.passkeys') }}" class="list-group-item list-group-item-action">パスキー</a>
                @endif
                {{-- 2段階認証の画面への入口。認証アプリの方式か、バックアップコードを使うときに出す。
                     メールの方式では、できることがバックアップコードの発行だけなので、名前もそれに合わせる --}}
                @if ($user::usesTotp() || $user::usesBackupCodes())
                    <a href="{{ route('company.mypage.twoFactor') }}" class="list-group-item list-group-item-action">{{ $user::usesTotp() ? '2段階認証' : 'バックアップコード' }}</a>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-8 col-lg-7">
        <div class="card mb-4">
            <div class="card-header">企業の情報</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">企業ID</dt>
                    <dd class="col-sm-8">{{ $company->code }}</dd>

                    <dt class="col-sm-4">企業名</dt>
                    <dd class="col-sm-8">{{ $company->name }}</dd>

                    <dt class="col-sm-4">フリガナ</dt>
                    <dd class="col-sm-8">{{ $company->kana ?? '（未設定）' }}</dd>

                    <dt class="col-sm-4">代表者名</dt>
                    <dd class="col-sm-8">{{ $company->representative ?? '（未設定）' }}</dd>

                    <dt class="col-sm-4">郵便番号</dt>
                    <dd class="col-sm-8">{{ $company->zip ?? '（未設定）' }}</dd>

                    <dt class="col-sm-4">都道府県</dt>
                    <dd class="col-sm-8">{{ code_label('prefectures', $company->prefecture, '（未設定）') }}</dd>

                    <dt class="col-sm-4">住所</dt>
                    <dd class="col-sm-8">{{ $company->address ?? '（未設定）' }}</dd>

                    <dt class="col-sm-4">電話番号</dt>
                    <dd class="col-sm-8">{{ $company->tel }}</dd>

                    <dt class="col-sm-4">ホームページURL</dt>
                    <dd class="col-sm-8">
                        @if ($company->url)
                            <a href="{{ $company->url }}" target="_blank" rel="noopener noreferrer">{{ $company->url }}</a>
                        @else
                            （未設定）
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header">あなたの情報</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">担当者ID</dt>
                    <dd class="col-sm-8">{{ $user->login_id }}</dd>

                    <dt class="col-sm-4">お名前</dt>
                    <dd class="col-sm-8">{{ $user->name }}</dd>

                    <dt class="col-sm-4">メールアドレス</dt>
                    <dd class="col-sm-8">{{ $user->email }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
