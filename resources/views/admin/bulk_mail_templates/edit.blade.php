@extends('layouts.admin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">一斉メールの文面の編集</div>
            <div class="card-body">
                {{-- 確認画面を挟まず、ここから直接update()へ送る --}}
                <form method="POST" action="{{ route('admin.bulk-mail-templates.update', $template) }}">
                    @csrf
                    @method('PATCH')

                    @include('admin.bulk_mail_templates._fields')

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">更新する</button>
                        <a href="{{ route('admin.bulk-mail-templates.index') }}" class="btn btn-outline-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
