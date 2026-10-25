{{--
    リダイレクト（Neobit\Core\Http\Controllers\RedirectController）。「URLを試す」の欄と、一覧。
    一覧のいちばん上が追加の行で、ほかの行は「編集」を押すとその行だけが入力欄になる（redirects.js）。
    行ごとに1つのフォームにして、エラーの欄を赤くする範囲（global.js）が、その行に収まるようにしている。
    入力エラーで戻ったときは、送った行（editing_idで見分ける。無ければ追加の行）だけに、入れた値とエラーを出す。
    ルートの名前（$routes）は、アプリが付けた名前をコントローラーが渡す。
--}}
@extends(config('neobit.layouts.admin'))

@push('head-extra')
@vite(['vendor/neobit/core/resources/js/redirects.js'])
@endpush

@php
    // 入力エラーで戻ってきた行のid。追加の行ならnull
    $editingId = old('editing_id');
    $hasOld = session()->hasOldInput();
    // 見出しと文字の行の左の余白。入力欄の枠線と内側の余白（form-control-sm）と同じにして、文字の頭をそろえる
    $textIndent = 'padding-left: calc(.5rem + var(--bs-border-width));';
    // ボタンの欄の幅。「更新」「キャンセル」が並ぶ幅に決め、余りは最終HIT日の欄に回す
    $buttonColumn = 'width: 10rem;';
@endphp

@section('content')
<h1 class="h4 mb-3">リダイレクト</h1>

@if (! $enabled)
    <div class="alert alert-warning">設定（seo.redirects）で無効化されているので、登録しても機能しません。</div>
@endif

{{-- URLを試す --}}
<form method="GET" action="{{ route($routes['index']) }}" class="card mb-4">
    <div class="card-body">
        <h2 class="h6 mb-3">URLを試す</h2>
        <div class="d-flex gap-2">
            <input type="text" name="test" value="{{ $testUrl }}" class="form-control" placeholder="/blog/2020/abc" aria-label="試すURL">
            <button type="submit" class="btn btn-outline-primary text-nowrap">試す</button>
        </div>
        @if ($test !== null)
            <div class="mt-3 small">
                @if ($test['redirect'] !== null)
                    <div>→ 当たるリダイレクト：{{ $test['redirect']['old_url'] }}（{{ $test['redirect']['wildcard'] ? 'ワイルドカード' : '完全一致' }}）</div>
                @endif
                <div>{{ $test['message'] }}</div>
            </div>
        @endif
    </div>
</form>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    {{-- 検索と並び順 --}}
    <form method="GET" action="{{ route($routes['index']) }}" class="d-flex flex-wrap gap-2 align-items-center">
        <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm w-auto" placeholder="古いURL" aria-label="古いURL">
        <select name="order" class="form-select form-select-sm w-auto" aria-label="並び順">
            @foreach ($orderOptions as $value => $label)
                <option value="{{ $value }}" @selected($order === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-sm btn-outline-primary">検索</button>
    </form>
    <div class="ms-auto d-flex gap-2">
        <a href="{{ route($routes['csv']) }}" class="btn btn-sm btn-outline-secondary">CSVダウンロード</a>
        <a href="{{ route($routes['csv-import']) }}" class="btn btn-sm btn-outline-secondary">CSV取り込み</a>
    </div>
</div>

<p class="small text-muted mb-2">
    * は末尾に1つだけ書けます。古いURLの * は、その下の全部に当たります。新しいURLにも * を書くと、当たった残りを引き継ぎます。
</p>

{{-- 見出し --}}
<div class="row g-2 small fw-bold border-bottom pb-1 d-none d-md-flex">
    <div class="col-md-3" style="{{ $textIndent }}">古いURL</div>
    <div class="col-md-3" style="{{ $textIndent }}">新しいURL</div>
    <div class="col-md-1" style="{{ $textIndent }}">種類</div>
    <div class="col-md-1">有効</div>
    <div class="col-md-1 text-end">HIT回数</div>
    <div class="col-md">最終HIT日</div>
    <div class="col-md-auto" style="{{ $buttonColumn }}"></div>
</div>

{{-- 追加の行 --}}
<form method="POST" action="{{ route($routes['store']) }}" class="row g-2 align-items-start border-bottom py-2" data-redirect-add>
    @csrf
    <div class="col-md-3">
        <input type="text" name="old_url" value="{{ $editingId === null ? old('old_url') : '' }}" class="form-control form-control-sm" placeholder="/blog/*" aria-label="古いURL">
        <div class="invalid-feedback" data-item="old_url">{{ $editingId === null ? $errors->first('old_url') : '' }}</div>
    </div>
    <div class="col-md-3">
        <input type="text" name="new_url" value="{{ $editingId === null ? old('new_url') : '' }}" class="form-control form-control-sm" placeholder="/news/*" aria-label="新しいURL">
        <div class="invalid-feedback" data-item="new_url">{{ $editingId === null ? $errors->first('new_url') : '' }}</div>
    </div>
    <div class="col-md-1">
        <select name="status_code" class="form-select form-select-sm" aria-label="種類">
            @foreach ($statusOptions as $code => $label)
                <option value="{{ $code }}" @selected((string) ($editingId === null ? old('status_code', 301) : 301) === (string) $code)>{{ $code }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-1">
        <div class="form-check">
            {{-- チェックを外したときも値が届くよう、先に0を置く --}}
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" id="redirect-add-enabled" name="enabled" value="1" class="form-check-input"
                   @checked($editingId !== null || ! $hasOld || old('enabled') == '1')>
            <label for="redirect-add-enabled" class="form-check-label small">有効</label>
        </div>
    </div>
    <div class="col-md-1"></div>
    <div class="col-md"></div>
    <div class="col-md-auto" style="{{ $buttonColumn }}">
        <button type="submit" class="btn btn-sm btn-primary" data-redirect-button>追加</button>
    </div>
</form>

{{-- 登録済みの行。ふだんは文字で、「編集」を押すと入力欄になる --}}
@forelse ($redirects as $redirect)
    @php($editing = $editingId !== null && (string) $editingId === (string) $redirect->id)
    <form method="POST" action="{{ route($routes['update'], $redirect) }}" class="row g-2 align-items-start border-bottom py-2"
          data-redirect-row data-editing="{{ $editing ? '1' : '0' }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="editing_id" value="{{ $redirect->id }}">
        <div class="col-md-3 text-break">
            <span data-view class="d-inline-block" style="{{ $textIndent }}">{{ $redirect->old_url }}</span>
            <div data-edit class="d-none">
                <input type="text" name="old_url" value="{{ $editing ? old('old_url') : $redirect->old_url }}" data-original="{{ $redirect->old_url }}"
                       class="form-control form-control-sm" aria-label="古いURL">
                <div class="invalid-feedback" data-item="old_url">{{ $editing ? $errors->first('old_url') : '' }}</div>
            </div>
        </div>
        <div class="col-md-3 text-break">
            <span data-view class="d-inline-block" style="{{ $textIndent }}">{{ $redirect->new_url }}</span>
            <div data-edit class="d-none">
                <input type="text" name="new_url" value="{{ $editing ? old('new_url') : $redirect->new_url }}" data-original="{{ $redirect->new_url }}"
                       class="form-control form-control-sm" aria-label="新しいURL">
                <div class="invalid-feedback" data-item="new_url">{{ $editing ? $errors->first('new_url') : '' }}</div>
            </div>
        </div>
        <div class="col-md-1">
            <span data-view class="d-inline-block" style="{{ $textIndent }}">{{ $redirect->status_code }}</span>
            <div data-edit class="d-none">
                <select name="status_code" class="form-select form-select-sm" data-original="{{ $redirect->status_code }}" aria-label="種類">
                    @foreach ($statusOptions as $code => $label)
                        <option value="{{ $code }}" @selected((string) ($editing ? old('status_code') : $redirect->status_code) === (string) $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-1">
            <span data-view>{{ $redirect->enabled ? '有効' : '無効' }}</span>
            <div data-edit class="d-none form-check">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" id="redirect-enabled-{{ $redirect->id }}" name="enabled" value="1" class="form-check-input"
                       data-original="{{ $redirect->enabled ? '1' : '0' }}"
                       @checked($editing ? old('enabled') == '1' : $redirect->enabled)>
                <label for="redirect-enabled-{{ $redirect->id }}" class="form-check-label small">有効</label>
            </div>
        </div>
        <div class="col-md-1 text-md-end">{{ number_format($redirect->hits) }}</div>
        <div class="col-md small">{{ $redirect->last_hit_at?->format('Y-m-d') ?? '－' }}</div>
        <div class="col-md-auto" style="{{ $buttonColumn }}">
            <div data-view class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-redirect-button data-redirect-edit>編集</button>
                <button type="button" class="btn btn-sm btn-outline-danger text-nowrap" data-redirect-button
                        data-bs-toggle="modal" data-bs-target="#delete-redirect-{{ $redirect->id }}">削除</button>
            </div>
            <div data-edit class="d-none d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary text-nowrap">更新</button>
                <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" data-redirect-cancel>キャンセル</button>
            </div>
        </div>
    </form>
@empty
    <p class="text-muted small py-2">リダイレクトはありません。</p>
@endforelse

<div class="mt-3">
    {{ $redirects->links('pagination::bootstrap-5') }}
</div>

{{-- 削除の確認の窓。行ごとに1つ。行のフォームの中には置けないので、外に並べる --}}
@foreach ($redirects as $redirect)
    <div class="modal fade" id="delete-redirect-{{ $redirect->id }}" tabindex="-1" aria-labelledby="delete-redirect-label-{{ $redirect->id }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="delete-redirect-label-{{ $redirect->id }}">リダイレクトの削除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body text-break">
                    「{{ $redirect->old_url }}」のリダイレクトを削除します。この操作は取り消せません。よろしいですか？
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <form method="POST" action="{{ route($routes['destroy'], $redirect) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">削除する</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endforeach
@endsection
