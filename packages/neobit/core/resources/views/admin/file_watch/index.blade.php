{{--
    改ざん監視（Neobit\Core\Http\Controllers\FileWatchController）。開いたときにその場でチェックし直した状況（$status）と、
    最近取り込んだ更新（$recent）を出す。改ざんの疑いと判断待ちは、まとめて承認できる。
    承認は、確かめのモーダルを開いてから送る。送るのは、画面に出した一覧のしるし（FileWatcher::signature()）。
    承認を受け付けて、まだcronが取り込んでいない間（$approval）は、その旨を出して、承認のボタンは出さない。
    ルートの名前（$routes）は、アプリが付けた名前をコントローラーが渡す。
--}}
@extends(config('neobit.layouts.admin'))

@section('content')
<h1 class="h4 mb-3">改ざん監視</h1>

@if (! $enabled)
    {{-- 監視を使っていない --}}
    <p>改ざん監視は使っていません（設定の file_watch.release_file が空です）。</p>
@elseif ($status === null)
    {{-- 基準がまだ無い --}}
    <p>改ざん監視の基準がまだありません。サーバーで php artisan app:watch-files --init を実行してください。</p>
@else
    <p class="mb-4">{{ date('Y-m-d H:i:s', $status['at']) }} 現在のチェック状況です。</p>

    {{-- 改ざんの疑いと判断待ち --}}
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6">改ざんの疑い（{{ count($status['alerts']) }}件）</h2>
            @include('neobit::admin.file_watch._list', ['entries' => $status['alerts']])

            <h2 class="h6 mt-4">判断待ち（{{ count($status['pending']) }}件）</h2>
            <p class="small text-muted mb-2">15分たっても通常の更新の期間に入らなければ、改ざんの疑いになります。</p>
            @include('neobit::admin.file_watch._list', ['entries' => $status['pending']])

            @if ($approval !== null)
                <div class="alert alert-info mt-4 mb-0">
                    {{ $approval['by'] }}さんの承認を受け付けました（{{ date('Y-m-d H:i:s', $approval['at']) }}）。次のチェックのタイミング（1分以内）で取り込みます。
                </div>
            @elseif ($status['alerts'] !== [] || $status['pending'] !== [])
                @php($items = [...$status['alerts'], ...$status['pending']])
                <div class="mt-4">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fileWatchApprove">承認する</button>
                    <p class="small text-muted mt-2 mb-1">自分たちの更新だと確かめた変化を、通常の更新として取り込みます。次のチェックのタイミング（1分以内）で取り込みます。</p>
                    <p class="small text-danger fw-bold mb-0">許可されていないアップロードファイルと、確かめられなかったディレクトリは、承認では取り込めません。</p>
                </div>

                {{-- 確かめのモーダル。押すと、画面に出した一覧を送る --}}
                <div class="modal fade" id="fileWatchApprove" tabindex="-1" aria-labelledby="fileWatchApproveLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <form method="POST" action="{{ route($routes['approve']) }}" class="modal-content">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title" id="fileWatchApproveLabel">変更内容の承認</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                            </div>
                            <div class="modal-body">
                                <p>次の{{ count($items) }}件を、通常の更新として基準に取り込みます。よろしいですか？</p>
                                {{-- 多いときは決めた件数で切り、「ほか○件」にする --}}
                                @include('neobit::admin.file_watch._list', ['entries' => array_slice($items, 0, $shownItems)])
                                @if (count($items) > $shownItems)
                                    <div class="ms-3 small">ほか{{ count($items) - $shownItems }}件</div>
                                @endif
                                <p class="small text-muted mt-3 mb-0">承認した人の名前と一覧は、操作ログとメールに残ります。</p>
                                <input type="hidden" name="signature" value="{{ \Neobit\Core\Security\FileWatcher::signature($items) }}">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">やめる</button>
                                <button type="submit" class="btn btn-primary">承認する</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- 承認では取り込めないもの --}}
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6">許可されていないアップロードファイル（{{ count($status['dangers']) }}件）</h2>
            @include('neobit::admin.file_watch._list', ['entries' => $status['dangers']])

            <h2 class="h6 mt-4">確かめられなかったディレクトリ（{{ count($status['unreadable']) }}件）</h2>
            @forelse ($status['unreadable'] as $directory)
                <div class="ms-3 small">{{ $directory }}</div>
            @empty
                <div class="ms-3 small text-muted">なし</div>
            @endforelse
        </div>
    </div>

    {{-- 最近取り込んだ更新。開くとファイルの一覧 --}}
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6">最近取り込んだ更新</h2>
            @forelse ($recent as $entry)
                @php($files = array_values(array_unique($entry['files'])))
                <details class="ms-3 small mb-1">
                    <summary>
                        @if (! empty($entry['manual']) && ! empty($entry['by']))
                            {{ date('Y-m-d H:i', $entry['from']) }} に{{ $entry['by'] }}さんが承認した更新（{{ count($files) }}件）
                        @elseif (! empty($entry['manual']))
                            {{ date('Y-m-d H:i', $entry['from']) }} にサーバーで認めた更新（{{ count($files) }}件）
                        @else
                            {{ date('Y-m-d H:i', $entry['from']) }}〜{{ date('H:i', $entry['to']) }} の更新（{{ count($files) }}件）
                        @endif
                    </summary>
                    @foreach ($files as $file)
                        <div class="ms-3">{{ $file }}</div>
                    @endforeach
                </details>
            @empty
                <div class="ms-3 small text-muted">なし</div>
            @endforelse
        </div>
    </div>
@endif
@endsection
