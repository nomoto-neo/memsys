@extends('layouts.admin')

{{--
    一斉メールの入力画面。添付ファイルのアップロードに要るものを、この画面だけで<head>に足す。
    文面を選ぶと、その件名と本文が欄に入る。入った後は自由に直せる。
--}}
@push('head-extra')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/ajax_upload.js'])
@endpush

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card">
            <div class="card-header">一斉メールを送る</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.bulk-mails.confirm') }}" enctype="multipart/form-data">
                    @csrf

                    @if ($templates->isNotEmpty())
                        <div class="mb-3">
                            <label for="template" class="form-label">登録した文面から選ぶ</label>
                            {{-- 選んだ文面の件名と本文を欄に入れるだけで、送信はしない --}}
                            <select id="template" class="form-select">
                                <option value="">選ばない</option>
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}"
                                            data-subject="{{ $template->subject }}"
                                            data-body="{{ $template->body }}">{{ $template->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="subject" class="form-label">件名 {!! $required['subject'] ?? '' !!}</label>
                        <input id="subject" type="text" name="subject" maxlength="200"
                               class="form-control"
                               value="{{ $input['subject'] ?? '' }}">
                        <div class="invalid-feedback" data-item="subject">{{ $errors->first('subject') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="body" class="form-label">本文 {!! $required['body'] ?? '' !!}</label>
                        <textarea id="body" name="body" rows="15" class="form-control">{{ $input['body'] ?? '' }}</textarea>
                        <div class="form-text">@{{$name}} と書いたところに、宛先の氏名が入ります。件名にも使えます。</div>
                        <div class="invalid-feedback" data-item="body">{{ $errors->first('body') }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">添付ファイル</label>
                        @include('_ajax_upload_block', [
                            'model' => null,
                            'input' => $input,
                            'field' => 'attach',
                            'width' => 0,
                            'readonly' => '',
                            'uploadUrl' => route('admin.bulk-mails.ajaxUpload'),
                        ])
                        <div class="form-text">{{ implode('・', $attachTypes) }}のファイルを1つ、{{ number_format($attachMaxKb) }}KBまで付けられます。宛先の全員に送られます。</div>
                        <div class="invalid-feedback" data-item="attach">{{ $errors->first('attach') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="csv_file" class="form-label">宛先のCSVファイル {!! $required['csv_file'] ?? '' !!}</label>
                        <input id="csv_file" type="file" name="csv_file" accept=".csv,.txt" class="form-control">
                        <div class="form-text">
                            1行に「氏名,メールアドレス」の2列を書いたCSVです。見出しの行は付けません。
                            確認画面から戻ったときは、選び直してください。
                        </div>
                        <div class="invalid-feedback" data-item="csv_file">{{ $errors->first('csv_file') }}</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">確認画面へ</button>
                        <a href="{{ route('admin.bulk-mails.index') }}" class="btn btn-outline-secondary">送信の履歴へ</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    {{-- 文面を選んだら、その件名と本文を欄に入れる。「選ばない」に戻しても欄は消さない --}}
    document.getElementById('template')?.addEventListener('change', (event) => {
        const option = event.target.selectedOptions[0];

        if (! option.value) {
            return;
        }

        document.getElementById('subject').value = option.dataset.subject;
        document.getElementById('body').value = option.dataset.body;
    });
</script>
@endpush
@endsection
