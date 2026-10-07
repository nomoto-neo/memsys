@extends('layouts.app')

@section('content')
<div class="text-center py-5">
    <h1 class="mb-4">{{ config('app.site_name') }}</h1>

    @guest
        <p class="mb-4">会員登録がお済みでない方は、まずは会員登録をお願いします。</p>
        <div class="d-flex justify-content-center gap-2">
            <a href="{{ route('regist.create') }}" class="btn btn-primary">会員登録</a>
            <a href="{{ route('login') }}" class="btn btn-outline-secondary">ログイン</a>
        </div>
    @else
        <p class="mb-4">{{ auth()->user()->name }} さん、ようこそ。</p>
        <a href="{{ route('mypage') }}" class="btn btn-primary">マイページへ</a>
    @endguest
</div>

{{-- 最新のお知らせ（件数はTopController::NEWS_COUNT）。全部の記事はお知らせのコーナーで見てもらう。
     「もっと見る」は検索条件を付けずに開くので、コーナー側の絞り込みはリセットされる。 --}}
<div class="row justify-content-center">
    <div class="col-md-8">
        <h2 class="h5 mb-3">お知らせ</h2>

        @include('news._list', ['newsList' => $newsList, 'emptyMessage' => 'お知らせはまだありません。'])

        @if ($newsList->isNotEmpty())
            <p class="text-end">
                <a href="{{ route('news.index') }}">もっと見る</a>
            </p>
        @endif
    </div>
</div>
@endsection
