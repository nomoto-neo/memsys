@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">メルマガ希望者</h1>
    {{-- 一斉メールの宛先のCSVは、一斉メールの入力の画面から落とす --}}
    <a href="{{ route('admin.bulk-mails.create') }}" class="btn btn-outline-primary btn-sm">一斉メールを送る</a>
</div>

{{-- 会員の「受け取るか」は会員の管理画面にあり、ここには会員ではない人だけが出る --}}
<p class="text-muted small">会員登録をしていない人の、お知らせメール（メルマガ）の登録と配信停止です。会員の設定は、会員の管理画面で確かめてください。</p>

{{--
    name属性は検索対象のカラム名のまま。NewsletterSubscriberController::srchRules()に載っている項目だけが
    検索条件として保存される。

    - q：フリーワード（対象の列はNewsletterSubscriberController::FREE_WORD_COLUMNS）
    - notice_mail：受け取るか（Rule::inで検証しているので完全一致）
    - source：どこから来たか（同じく完全一致）
    - orderby：並び順。NewsletterSubscriberController::ORDER_OPTIONSに定義した選択肢をそのまま並べている
--}}
<form method="POST" action="{{ route('admin.newsletter-subscribers.search') }}" class="row g-2 mb-4">
    @csrf
    <div class="col-sm-4">
        <input type="text" name="q" maxlength="100" class="form-control" placeholder="フリーワード（お名前・メールアドレス）"
               value="{{ $filters['q'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        <select name="notice_mail" class="form-select">
            <option value="">状態（すべて）</option>
            @foreach (code_table('notice_mail') as $value => $label)
                <option value="{{ $value }}" @selected(($filters['notice_mail'] ?? '') === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <select name="source" class="form-select">
            <option value="">経路（すべて）</option>
            @foreach (code_table('newsletter_source') as $value => $label)
                <option value="{{ $value }}" @selected(($filters['source'] ?? '') === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <select name="orderby" class="form-select">
            @foreach ($orderOptions as $key => $option)
                <option value="{{ $key }}" @selected($selectedOrder === $key)>{{ $option['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <button type="submit" class="btn btn-primary w-100">検索</button>
    </div>
</form>

<table class="table table-striped align-middle">
    <thead>
        <tr>
            <th>お名前</th>
            <th>メールアドレス</th>
            <th>状態</th>
            <th>経路</th>
            <th>登録日時</th>
            <th>停止日時</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($records as $subscriber)
            <tr>
                <td>{{ $subscriber->name }}</td>
                <td>{{ $subscriber->email }}</td>
                <td class="{{ $subscriber->receivesNoticeMail() ? '' : 'text-muted' }}">{{ code_label('notice_mail', $subscriber->notice_mail) }}</td>
                {{-- 経路は、最後に「受け取るか」を変えたところ --}}
                <td>{{ code_label('newsletter_source', $subscriber->source) }}</td>
                <td class="text-nowrap">{{ $subscriber->subscribed_at?->format('Y-m-d H:i') ?? '－' }}</td>
                <td class="text-nowrap">{{ $subscriber->unsubscribed_at?->format('Y-m-d H:i') ?? '－' }}</td>
                <td class="text-end">
                    @if ($subscriber->receivesNoticeMail())
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#stopSubscriberModal-{{ $subscriber->id }}">停止する</button>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center text-muted">該当するメルマガ希望者が見つかりません。</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{ $records->links('pagination::bootstrap-5') }}

{{--
    停止の確認のモーダルは<table>の外にまとめて置く。<tbody>の直下に置ける要素は<tr>だけで、
    <tr>と並べて<div>を置くとHTMLとして不正になるため（admin/staff/index.blade.phpと同じ）。
--}}
@foreach ($records as $subscriber)
    @if ($subscriber->receivesNoticeMail())
        <div class="modal fade" id="stopSubscriberModal-{{ $subscriber->id }}" tabindex="-1"
             aria-labelledby="stopSubscriberModalLabel-{{ $subscriber->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="stopSubscriberModalLabel-{{ $subscriber->id }}">配信の停止</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <div class="modal-body">
                        {{ $subscriber->email }} への、お知らせメールの配信を停止します。よろしいですか？
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <form method="POST" action="{{ route('admin.newsletter-subscribers.stop', $subscriber) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-danger">停止する</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach
@endsection
