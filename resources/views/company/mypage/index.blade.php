@extends('layouts.company')

@section('title', 'マイページ')

{{-- 企業会員のマイページ。$companyはログイン中の担当者の企業、$userはログイン中の担当者 --}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
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
