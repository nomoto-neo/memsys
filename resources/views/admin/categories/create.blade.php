@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">カテゴリー新規登録</div>
            <div class="card-body">
                {{-- 確認画面を挟まず、ここから直接store()へ送信する。 --}}
                <form method="POST" action="{{ route('admin.categories.store') }}">
                    @csrf

                    @include('admin.categories._fields', ['input' => $input])

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">登録する</button>
                        <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
