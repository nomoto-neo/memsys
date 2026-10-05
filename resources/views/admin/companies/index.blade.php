@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">企業会員一覧・検索</h1>
    <div class="d-flex gap-2">
        {{-- 今の検索条件・並び順で、全件をCSVにする（ページ分けはしない） --}}
        <a href="{{ route('admin.companies.csv') }}" class="btn btn-outline-secondary btn-sm">CSVダウンロード</a>
    </div>
</div>

{{--
    name属性は検索対象のカラム名のまま。CompanyController::srchRules()に載っている項目だけが
    検索条件として保存される。

    - q：フリーワード（対象の列はCompanyController::FREE_WORD_COLUMNS）
    - code・tel：単項目検索（文字列なので部分一致）
    - status：状態での絞り込み（Rule::inで検証しているので完全一致）。
      承認を待っている企業を探すときは、「申請中」で絞る
    - orderby：並び順。CompanyController::ORDER_OPTIONSに定義した選択肢をそのまま並べている
--}}
<form method="POST" action="{{ route('admin.companies.search') }}" class="row g-2 mb-4">
    @csrf
    <div class="col-sm-3">
        <input type="text" name="q" maxlength="100" class="form-control" placeholder="フリーワード（企業名・カナなど）"
               value="{{ $filters['q'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        <input type="text" name="code" class="form-control" placeholder="企業ID"
               value="{{ $filters['code'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        <input type="text" name="tel" class="form-control" placeholder="電話番号"
               value="{{ $filters['tel'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        <select name="status" class="form-select">
            <option value="">状態（すべて）</option>
            @foreach (code_table('company_status') as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === (string) $value)>{{ $label }}</option>
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
    <div class="col-sm-1">
        <button type="submit" class="btn btn-primary w-100">検索</button>
    </div>
</form>

<table class="table table-striped align-middle">
    <thead>
        <tr>
            <th>企業ID</th>
            <th>企業名</th>
            <th>電話番号</th>
            <th>状態</th>
            <th>担当者</th>
            <th>登録日</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($companies as $company)
            <tr>
                <td>{{ $company->code }}</td>
                <td>{{ $company->name }}</td>
                <td>{{ $company->tel }}</td>
                <td>@include('admin.companies._status', ['company' => $company])</td>
                <td>{{ $company->users_count }}人</td>
                <td>{{ $company->created_at->format('Y-m-d') }}</td>
                <td class="text-end">
                    {{-- 承認・却下・停止・再開は、詳細画面で行う --}}
                    <a href="{{ route('admin.companies.show', $company) }}"
                       class="btn btn-sm btn-outline-secondary">詳細</a>
                    <a href="{{ route('admin.companies.edit', $company) }}"
                       class="btn btn-sm btn-outline-primary">編集</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center text-muted">該当する企業が見つかりません。</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{ $companies->links('pagination::bootstrap-5') }}
@endsection
