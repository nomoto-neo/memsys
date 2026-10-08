@extends('layouts.app')

@section('title', $page->title.' - '.config('app.site_name'))

{{-- 本文を、エディタの中と同じ見た目で表示するためのCSS。layouts/app.blade.phpの@stack('head-extra')に入る --}}
@push('head-extra')
    @vite(['resources/css/wysiwyg_suneditor_content.css'])
@endpush

@section('content')
<h1 class="h4 mb-3">{{ $page->title }}</h1>

<div class="card">
    <div class="card-body">
        {{--
            本文はSunEditorで入力したHTMLなので、{!! !!}で出す。必ずsafe_html()で、許可していない
            タグと属性を取り除いてから出す（App\Support\HtmlSanitizer）。保存のときにも同じ処理を
            掛けているが、DBを直接書き換えた本文があっても安全に出せるよう、表示のときにも掛ける。
            class="sun-editor-editable"は、SunEditorの表示用のCSSが効く印。
        --}}
        <div class="wysiwyg-content sun-editor-editable">
            {!! safe_html($page->body) !!}
        </div>
    </div>
</div>
@endsection
