{{-- テスト用：$countページのテンプレート。定型紙のページの使い方（SheetPaging）を確かめる（PdfOnSheetTest） --}}
@foreach (range(1, $count) as $page)
    @if ($page > 1)
        <pagebreak />
    @endif
    <div style="position: absolute; left: 20mm; top: 20mm;">{{ $page }}ページ目</div>
@endforeach
