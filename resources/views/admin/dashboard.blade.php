@extends('layouts.admin')

@section('content')
{{-- ログアウトはヘッダー（layouts/app.blade.php）側でadmin対応にしたので、
     ここでは重複させず見出しだけにしている。 --}}
<h1 class="h4 mb-4">管理画面</h1>

<div class="list-group">
    <a href="{{ route('admin.news.index') }}" class="list-group-item list-group-item-action">
        ニュース記事一覧
    </a>
    <a href="{{ route('admin.categories.index') }}" class="list-group-item list-group-item-action">
        ニュースカテゴリー一覧
    </a>

    {{-- pageを付けずに素のURLへ。MemberController::index()側で
         「pageが無いアクセス＝ここでリセットしてよい新規入室」と
         判定しているので、ここは常にこのままで良い。 --}}
    <a href="{{ route('admin.members.index') }}" class="list-group-item list-group-item-action">
        会員一覧
    </a>

    {{-- スタッフ一覧はacl=1（管理者）専用の画面なので、ここでも
         リンク自体を出し分けている。実際にはEnsureStaffIsManagerが
         URL直打ちも防いでくれているが、そもそも「入れないリンク」を
         見せないようにしておくのが親切だと思う。 --}}
    @if (Auth::guard('admin')->user()->isManager())
        <a href="{{ route('admin.staff.index') }}" class="list-group-item list-group-item-action">
            スタッフ一覧
        </a>
        <a href="{{ route('admin.codes.index') }}" class="list-group-item list-group-item-action">
            項目見出し一覧
        </a>
    @else
        <a href="{{ route('admin.staff.show', Auth::guard('admin')->user()) }}" class="list-group-item list-group-item-action">
            自分の情報を確認
        </a>
    @endif
</div>
@endsection
