@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">カテゴリー編集</div>
            <div class="card-body">
                {{-- 確認画面を挟まず、ここから直接update()へ送信する。 --}}
                <form method="POST" action="{{ route('admin.categories.update', $category) }}">
                    @csrf
                    @method('PATCH')

                    @include('admin.categories._fields', ['input' => $input])

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">更新する</button>
                        <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
