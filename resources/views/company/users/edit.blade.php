@extends('layouts.company')

@section('title', '担当者の編集')

{{-- 同じ企業の、ほかの担当者の情報を変える。担当者IDとパスワードは変えられないので、
     担当者IDは表示だけにしている。変えたことは、その担当者へメールで知らせる --}}
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">担当者の編集</div>
            <div class="card-body">
                <form method="POST" action="{{ route('company.users.update', $user) }}">
                    @csrf
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
                        <div class="form-text">ログインの確認コードと、お知らせのメールの宛先になります。変更したことは、この担当者へメールでお知らせします。</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">更新する</button>
                        <a href="{{ route('company.users.index') }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
