@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">バックアップコード</div>
            <div class="card-body">
                <p>
                    スマートフォンを紛失した場合などに、1回だけ使える緊急用のコードです。
                    <strong>この画面はこの1回しか表示されません。</strong>
                    印刷するかパスワード管理アプリなどに保存し、他の人に見られない場所に保管してください。
                </p>

                <div class="row row-cols-2 g-2 my-3">
                    @foreach ($codes as $code)
                        <div class="col"><code class="fs-5">{{ $code }}</code></div>
                    @endforeach
                </div>

                <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">確認しました。管理画面へ進む</a>
            </div>
        </div>
    </div>
</div>
@endsection
