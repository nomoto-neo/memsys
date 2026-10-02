@extends('layouts.admin')

@section('content')
{{-- ログアウトはヘッダー（layouts/admin.blade.php）側でadmin対応にしたので、
     ここでは重複させず見出しだけにしている。 --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">会員一覧・検索</h1>
    <div class="d-flex gap-2">
        {{-- 今の検索条件・並び順で、全件をCSVにする（ページ分けはしない） --}}
        <a href="{{ route('admin.members.csv') }}" class="btn btn-outline-secondary btn-sm">CSVダウンロード</a>
        {{-- ダウンロードしたCSVを直して、そのまま取り込める（更新だけ） --}}
        <a href="{{ route('admin.members.csv-import') }}" class="btn btn-outline-secondary btn-sm">CSV取り込み</a>
    </div>
</div>

{{--
    name属性は検索対象のカラム名のまま。MemberController::srchRules()に載っている項目だけが
    検索条件として保存される。

    - q：フリーワード。空白区切りで複数語に分解し、氏名かカナに
      それぞれの語が含まれるかをAND条件で絞り込む。
    - email・phone：単項目検索（文字列なので部分一致）
    - prefecture[]：Rule::inで区分一覧と照合しているので完全一致。
      name="...[]"で複数選択の配列として送ることで、controller側は「値が配列ならIN()」
      という分岐で自動的にOR条件（複数県のいずれか）にしてくれる。
    - orderby：並び順。MemberController::ORDER_OPTIONSに定義した選択肢を
      そのまま並べているだけなので、選択肢を増減させてもここは直さなくてよい。
      他の検索条件と同じくsearch_session経由で保存・復元される。
--}}
<form method="POST" action="{{ route('admin.members.search') }}" class="row g-2 mb-4">
    @csrf
    <div class="col-sm-3">
        <input type="text" name="q" maxlength="100" class="form-control" placeholder="フリーワード（氏名・カナ）"
               value="{{ $filters['q'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        <input type="text" name="email" class="form-control" placeholder="メールアドレス"
               value="{{ $filters['email'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        <input type="text" name="phone" class="form-control" placeholder="電話番号"
               value="{{ $filters['phone'] ?? '' }}">
    </div>
    <div class="col-sm-2">
        {{-- $filters['prefecture']はセッションに保存された生のPOST値なので、
             要素はすべて文字列（例: "13"）。$codeはconfig側のint値なので、
             (string)キャストしてから比較している。 --}}
        <select name="prefecture[]" class="form-select" multiple size="1"
                title="Ctrl（Macは⌘）キーを押しながらクリックで複数選択できます">
            @foreach (code_table('prefectures') as $code => $name)
                <option value="{{ $code }}"
                        @selected(in_array((string) $code, $filters['prefecture'] ?? [], true))>
                    {{ $name }}
                </option>
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
            <th>お名前</th>
            <th>フリガナ</th>
            <th>メールアドレス</th>
            <th>電話番号</th>
            <th>都道府県</th>
            <th>登録日</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($members as $member)
            <tr>
                <td>{{ $member->name }}</td>
                <td>{{ $member->kana ?? '（未設定）' }}</td>
                <td>{{ $member->email }}</td>
                <td>{{ $member->phone ?? '（未設定）' }}</td>
                <td>{{ code_label('prefectures', $member->prefecture, '（未設定）') }}</td>
                <td>{{ $member->created_at->format('Y-m-d') }}</td>
                <td class="text-end">
                    {{-- 検索条件・ページ番号は一覧を開くたびにセッション側で
                         覚えているので、ここのリンクにbackのようなものを
                         付ける必要はない。 --}}
                    <a href="{{ route('admin.members.show', $member) }}"
                       class="btn btn-sm btn-outline-secondary">詳細</a>
                    <a href="{{ route('admin.members.edit', $member) }}"
                       class="btn btn-sm btn-outline-primary">編集</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center text-muted">該当する会員が見つかりません。</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{-- 検索条件はセッション側にあり、今のGETリクエストのクエリには
     ?page=n しか乗っていないので、ここが生成するページ送りの
     リンクにも検索ワードは含まれない。 --}}
{{ $members->links('pagination::bootstrap-5') }}
@endsection
