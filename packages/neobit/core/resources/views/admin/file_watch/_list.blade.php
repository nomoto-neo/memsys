{{--
    改ざん監視の画面の、ファイルの一覧。$entriesは「名前・時刻」と、あれば「種類」の並び。無ければ「なし」。
    種類があれば、承認のメールと同じく、種類ごとに見出し（$kindLabels）を付けてまとめる。0件の種類は出さない。
    $limitを渡すと、全部でその件数まで並べ、多いときは「ほか○件」にする。見出しの件数は、切る前の件数。
--}}
@php($limit ??= null)
@if ($entries === [])
    <div class="ms-3 small text-muted">なし</div>
@elseif (! isset($entries[0]['kind']))
    {{-- 種類の無い一覧（許可されていないアップロードファイル） --}}
    @foreach ($entries as $entry)
        <div class="ms-3 small">{{ $entry['label'] }}（{{ date('Y-m-d H:i', $entry['time']) }}）</div>
    @endforeach
@else
    @php($shown = 0)
    @foreach ($kindLabels as $kind => $kindLabel)
        @php($ofKind = array_values(array_filter($entries, fn (array $entry) => $entry['kind'] === $kind)))
        @if ($ofKind !== [] && ($limit === null || $shown < $limit))
            <div class="ms-3 small fw-bold mt-1">{{ $kindLabel }}（{{ count($ofKind) }}件）</div>
            @foreach ($limit === null ? $ofKind : array_slice($ofKind, 0, $limit - $shown) as $entry)
                @php($shown++)
                <div class="ms-4 small">{{ $entry['label'] }}（{{ date('Y-m-d H:i', $entry['time']) }}）</div>
            @endforeach
        @endif
    @endforeach
    @if ($limit !== null && count($entries) > $limit)
        <div class="ms-3 small fw-bold mt-1">ほか{{ count($entries) - $limit }}件</div>
    @endif
@endif
