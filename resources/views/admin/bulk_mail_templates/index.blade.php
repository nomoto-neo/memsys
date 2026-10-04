@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">一斉メールの文面一覧</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.bulk-mails.create') }}" class="btn btn-outline-primary btn-sm">一斉メールを送る</a>
        <a href="{{ route('admin.bulk-mail-templates.create') }}" class="btn btn-primary btn-sm">新規登録</a>
    </div>
</div>

@if ($templates->isEmpty())
    <p class="text-muted">文面はまだありません。</p>
@else
    <table class="table table-hover align-middle bg-white">
        <thead>
            <tr>
                <th>タイトル（管理用）</th>
                <th>件名</th>
                <th>更新日時</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($templates as $template)
                <tr>
                    <td>{{ $template->title }}</td>
                    <td>{{ $template->subject }}</td>
                    <td class="text-nowrap">{{ $template->updated_at->format('Y-m-d H:i') }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.bulk-mail-templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">編集</a>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#deleteTemplateModal-{{ $template->id }}">削除</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 削除の確認。表の行の中にformを置かず、表の外にまとめる --}}
    @foreach ($templates as $template)
        <div class="modal fade" id="deleteTemplateModal-{{ $template->id }}" tabindex="-1"
             aria-labelledby="deleteTemplateModalLabel-{{ $template->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteTemplateModalLabel-{{ $template->id }}">文面の削除</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <div class="modal-body">
                        「{{ $template->title }}」を削除します。この操作は取り消せません。よろしいですか？
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <form method="POST" action="{{ route('admin.bulk-mail-templates.destroy', $template) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">削除する</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection
