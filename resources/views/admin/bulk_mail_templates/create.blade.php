@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">一斉メールの文面の新規登録</div>
            <div class="card-body">
                {{-- 確認画面を挟まず、ここから直接store()へ送る --}}
                <form method="POST" action="{{ route('admin.bulk-mail-templates.store') }}">
                    @csrf

                    @include('admin.bulk_mail_templates._fields')

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">登録する</button>
                        <a href="{{ route('admin.bulk-mail-templates.index') }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
