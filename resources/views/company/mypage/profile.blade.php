@extends('layouts.company')

@section('title', 'あなたの情報の編集')

{{-- ログイン中の担当者が、自分の情報を変える。担当者IDは変えられないので、表示だけにしている --}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">あなたの情報の編集</div>
            <div class="card-body">
                <form method="POST" action="{{ route('company.mypage.profile.update') }}">
                    @csrf
                    {{-- HTMLフォームはGET/POSTしか送れないため、実際にはPOSTで送りつつ
                         このhiddenフィールド(_method)でPATCHとして扱ってほしいことを伝える。 --}}
                    @method('PATCH')

                    <div class="mb-3">
                        <label class="form-label">担当者ID</label>
                        <div class="form-control-plaintext">{{ $user->login_id }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">お名前 {!! $required['name'] !!}</label>
                        <input id="name" type="text" name="name"
                               class="form-control"
                               value="{{ $input['name'] ?? '' }}" autofocus>
                        <div class="invalid-feedback" data-item="name">{{ $errors->first('name') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">メールアドレス {!! $required['email'] !!}</label>
                        <input id="email" type="email" name="email"
                               class="form-control"
                               value="{{ $input['email'] ?? '' }}">
                        <div class="invalid-feedback" data-item="email">{{ $errors->first('email') }}</div>
                        <div class="form-text">ログインの確認コードと、お知らせのメールの宛先になります。</div>
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
