@extends('layouts.admin')

@section('content')
{{-- 管理画面の最初の画面。メニューは左（admin/_menu.blade.php）にあるので、ここは見出しと挨拶だけにする --}}
<h1 class="h4 mb-4">管理画面</h1>

<p>ようこそ、{{ Auth::guard('admin')->user()->name }} さん。</p>
<p class="text-secondary">左のメニューから、作業する画面を選んでください。</p>
@endsection
