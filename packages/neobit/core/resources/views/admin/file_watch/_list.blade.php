{{--
    改ざん監視の画面の、ファイルの一覧。$entriesは「名前・時刻」と、あれば「種類」の並び。
    種類があれば頭に見出し（$kindLabels）を付ける。無ければ「なし」。
--}}
@forelse ($entries as $entry)
    <div class="ms-3 small">
        @isset($entry['kind'])
            <span class="me-2">{{ $kindLabels[$entry['kind']] ?? $entry['kind'] }}</span>
        @endisset
        {{ $entry['label'] }}（{{ date('Y-m-d H:i', $entry['time']) }}）
    </div>
@empty
    <div class="ms-3 small text-muted">なし</div>
@endforelse
