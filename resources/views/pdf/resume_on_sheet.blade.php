{{--
    定型紙の履歴書（Admin\MemberController::resumeOnSheet()・MypageController::resumeOnSheet()）。用紙はresources/pdf-sheets/resume_a4.pdf
    （厚生労働省の履歴書様式例のA4。2ページ）。用紙を下敷きにして、ここに書いた文字を上に載せる
    （Neobit\Core\Pdf\PdfDownload::downloadPdfOnSheet()）。

    位置は、用紙の左上からのmm。用紙の罫線の位置を測って決めている。
    <pagebreak />の後ろは2ページ目で、用紙の2ページ目が下敷きになる。
    学歴・職歴は、会員の経歴（Member::careers()）を載せる。1ページ目の欄に入りきらない分は、2ページ目の欄に続ける。
    会員の情報に無い項目（免許・資格、志望の動機など）は、書き方を見せるためのダミーの文を載せる。
--}}
<style>
    /* 位置を決めて置く文字。用紙の欄に合わせて、1つずつleft・top・widthを書く */
    .at { position: absolute; font-family: ipaexm; font-size: 10.5pt; }
</style>

{{-- ===== 1ページ目 ===== --}}

{{-- 日付（「年 月 日現在」の前） --}}
<div class="at" style="left: 104mm; top: 16.8mm; width: 12mm; text-align: right;">{{ now()->year }}</div>
<div class="at" style="left: 121mm; top: 16.8mm; width: 6mm; text-align: right;">{{ now()->month }}</div>
<div class="at" style="left: 132mm; top: 16.8mm; width: 6mm; text-align: right;">{{ now()->day }}</div>

{{-- ふりがな・氏名 --}}
<div class="at" style="left: 34mm; top: 27.5mm; width: 115mm; font-size: 9pt;">{{ $member->kana }}</div>
<div class="at" style="left: 34mm; top: 40mm; width: 115mm; height: 10mm; overflow: auto; font-size: 20pt;">{{ $member->name }}</div>

{{-- 生年月日（「年 月 日生 (満 歳)」の前に数字を入れる） --}}
@if ($member->birthdate)
    <div class="at" style="left: 54mm; top: 58.5mm; width: 14mm; text-align: right;">{{ $member->birthdate->year }}</div>
    <div class="at" style="left: 75mm; top: 58.5mm; width: 8mm; text-align: right;">{{ $member->birthdate->month }}</div>
    <div class="at" style="left: 90mm; top: 58.5mm; width: 8mm; text-align: right;">{{ $member->birthdate->day }}</div>
    <div class="at" style="left: 115mm; top: 58.5mm; width: 7mm; text-align: right;">{{ $member->birthdate->age }}</div>
@endif

{{-- 現住所。会員の情報は都道府県まで。長い住所が入るときは、欄に収まるよう縮める（overflow: auto） --}}
<div class="at" style="left: 34mm; top: 81mm; width: 116mm; height: 6mm; overflow: auto; font-size: 11pt;">{{ code_label('prefectures', $member->prefecture, '') }}</div>

{{-- 電話・E-mail（右の欄） --}}
<div class="at" style="left: 166mm; top: 67.6mm; width: 30mm; font-size: 9.5pt;">{{ $member->phone }}</div>
<div class="at" style="left: 154mm; top: 81mm; width: 42mm; height: 6mm; overflow: auto; font-size: 8pt;">{{ $member->email }}</div>

{{-- 写真の枠（横29.2mm・縦39mm）に、顔写真を貼る。mPDFは<img>に直接position: absoluteを付けても効かないので、
     位置を決めた<div>の中に入れる --}}
@if (isset($images['photo']))
    <div style="position: absolute; left: 162.8mm; top: 12.8mm; width: 29.2mm; height: 39mm;">
        <img src="{{ $images['photo'] }}" style="width: 29.2mm; height: 39mm;">
    </div>
@endif

{{--
    学歴・職歴。行は10mmずつ。1ページ目の欄は、1行目の上端126.4mm・15行で、年・月・内容の列の境目は34.7mmと49.9mm
--}}
@php
    $careers = $member->careers->values();
    $firstPageRows = 15;
@endphp
@foreach ($careers->take($firstPageRows) as $i => $career)
    <div class="at" style="left: 12.4mm; top: {{ 129.4 + $i * 10 }}mm; width: 22.3mm; text-align: center;">{{ $career->year }}</div>
    <div class="at" style="left: 34.7mm; top: {{ 129.4 + $i * 10 }}mm; width: 15.2mm; text-align: center;">{{ $career->month }}</div>
    <div class="at" style="left: 52.3mm; top: {{ 129.4 + $i * 10 }}mm; width: 142mm;">{{ $career->content }}</div>
@endforeach

<pagebreak />

{{-- ===== 2ページ目 ===== --}}

{{-- 学歴・職歴の続き。2ページ目の欄は、25.4mmからの見出しの行の下（上端35.4mm）から、免許・資格の見出しまでの7行。
     列の境目は37.6mmと52.6mm（免許・資格の欄と同じ） --}}
@foreach ($careers->slice($firstPageRows)->values() as $i => $career)
    <div class="at" style="left: 12.4mm; top: {{ 38.4 + $i * 10 }}mm; width: 25.2mm; text-align: center;">{{ $career->year }}</div>
    <div class="at" style="left: 37.6mm; top: {{ 38.4 + $i * 10 }}mm; width: 15mm; text-align: center;">{{ $career->month }}</div>
    <div class="at" style="left: 55mm; top: {{ 38.4 + $i * 10 }}mm; width: 140mm;">{{ $career->content }}</div>
@endforeach

{{-- 免許・資格（ダミーの文）。行は上下105.4〜115.4mmの見出しの下から、10mmずつ。年・月・内容の列の境目は37.6mmと52.6mm --}}
@foreach ([
    ['2020', '4', '普通自動車第一種運転免許　取得'],
    ['2022', '11', '日商簿記検定2級　合格'],
] as $i => [$year, $month, $text])
    <div class="at" style="left: 12.4mm; top: {{ 118.4 + $i * 10 }}mm; width: 25.2mm; text-align: center;">{{ $year }}</div>
    <div class="at" style="left: 37.6mm; top: {{ 118.4 + $i * 10 }}mm; width: 15mm; text-align: center;">{{ $month }}</div>
    <div class="at" style="left: 55mm; top: {{ 118.4 + $i * 10 }}mm; width: 140mm;">{{ $text }}</div>
@endforeach

{{-- 志望の動機など（ダミーの文）。書く所は上下187.8〜226.3mm。枠の中で折り返し、はみ出した分は切る（overflow: hidden） --}}
<div class="at" style="left: 15mm; top: 190mm; width: 180mm; height: 34mm; overflow: hidden; line-height: 1.7; font-size: 10pt;">
    前職では、会員制サイトの運用担当として、問い合わせへの対応と、会員情報の管理を3年間担当してまいりました。
    利用者の声をもとに、申し込みの手順を見直し、入力の途中で離れてしまう方を減らすことができました。<br>
    貴社のサービスでも、利用者の立場に立った運用と改善に取り組みたいと考え、志望いたしました。
    （この欄の文は、定型紙に文字を載せる書き方を見せるためのダミーです）
</div>
