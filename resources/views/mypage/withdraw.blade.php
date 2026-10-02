@extends('layouts.app')

@section('title', '退会')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-header">退会</div>
            <div class="card-body">
                <p>退会すると、ご登録いただいている会員情報（お名前・メールアドレス・電話番号など）はすべて削除されます。</p>

                <div class="alert alert-danger">
                    削除した会員情報は、元に戻すことができません。<br>
                    再度ご利用いただく場合は、新たに会員登録が必要です。
                </div>

                <p>よろしければ「退会する」を押してください。退会が完了すると、ご登録のメールアドレスにお知らせをお送りします。</p>

                <div class="d-flex gap-2 mt-4">
                    <a href="{{ route('mypage') }}" class="btn btn-outline-secondary">マイページに戻る</a>

                    <form method="POST" action="{{ route('mypage.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">退会する</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
