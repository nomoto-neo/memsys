{{--
    履歴書（A4縦1枚）。App\Support\PdfDownloadがmPDFでPDFにする。
    マイページ（MypageController::resume()）と管理画面
    （Admin\MemberController::resume()）の両方から使う。

    受け取る変数：
    - $member  対象の会員
    - $images  埋め込める画像（名前 => imgのsrcに書く値）。顔写真は
               $images['photo']。写真が無ければキーが無い。写真は
               Member::PHOTO_ASPECT（横3:縦4）に切り抜き済み。

    mPDFが解釈できるCSSはブラウザより少ない（flex・gridは使えない）ので、
    枠と罫線は<table>で組んでいる。大きさはmmで指定する。
    学歴・職歴、免許・資格は会員情報に項目が無いので、手書き用の空欄にしている。
--}}
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<style>
    @page {
        margin: 15mm 15mm 12mm 15mm;
    }
    body {
        font-family: ipaexm;
        font-size: 10pt;
        color: #000;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    td, th {
        border: 0.3mm solid #000;
        padding: 1mm 2mm;
        vertical-align: middle;
    }
    th {
        font-weight: normal;
        text-align: center;
    }
    .title {
        font-size: 20pt;
        letter-spacing: 6mm;
        border: none;
        padding: 0;
    }
    .as-of {
        text-align: right;
        border: none;
        padding: 0;
        vertical-align: bottom;
    }
    .label {
        font-size: 8pt;
    }
    .kana {
        border-bottom: 0.2mm dashed #000;
    }
    .name {
        font-size: 18pt;
        height: 16mm;
    }
    .photo-cell {
        border: none;
        width: 36mm;
        text-align: center;
        vertical-align: top;
        padding: 0 0 0 4mm;
    }
    .photo-box {
        width: 30mm;
        height: 40mm;
        border: 0.2mm dashed #000;
        font-size: 7pt;
        text-align: center;
        vertical-align: middle;
        padding: 0;
    }
    .history th.year,
    .history td.year {
        width: 16mm;
        text-align: center;
    }
    .history th.month,
    .history td.month {
        width: 10mm;
        text-align: center;
    }
    .history td {
        height: 7.5mm;
    }
    .spacer {
        height: 5mm;
    }
</style>
</head>
<body>

<table>
    <tr>
        <td class="title">履歴書</td>
        <td class="as-of">{{ now()->format('Y年n月j日') }}現在</td>
    </tr>
</table>

<div class="spacer"></div>

<table>
    <tr>
        <td style="padding: 0; border: none;">
            <table>
                <tr>
                    <td class="kana" style="width: 22mm;"><span class="label">ふりがな</span></td>
                    <td class="kana">{{ $member->kana }}</td>
                </tr>
                <tr>
                    <td class="label">氏名</td>
                    <td class="name">{{ $member->name }}</td>
                </tr>
                <tr>
                    <td class="label">生年月日</td>
                    <td>
                        @if ($member->birthdate)
                            {{ $member->birthdate->format('Y年n月j日') }}生（満{{ $member->birthdate->age }}歳）
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="label">現住所</td>
                    <td style="height: 12mm;">{{ code_label('prefectures', $member->prefecture, '') }}</td>
                </tr>
                <tr>
                    <td class="label">電話</td>
                    <td>{{ $member->phone }}</td>
                </tr>
                <tr>
                    <td class="label">メール</td>
                    <td>{{ $member->email }}</td>
                </tr>
            </table>
        </td>
        <td class="photo-cell">
            @if (isset($images['photo']))
                <img src="{{ $images['photo'] }}" style="width: 30mm; height: 40mm;">
            @else
                <table>
                    <tr>
                        <td class="photo-box">写真をはる位置<br><br>縦40mm×横30mm</td>
                    </tr>
                </table>
            @endif
        </td>
    </tr>
</table>

<div class="spacer"></div>

<table class="history">
    <tr>
        <th class="year">年</th>
        <th class="month">月</th>
        <th>学歴・職歴</th>
    </tr>
    @for ($i = 0; $i < 14; $i++)
        <tr>
            <td class="year"></td>
            <td class="month"></td>
            <td></td>
        </tr>
    @endfor
</table>

<div class="spacer"></div>

<table class="history">
    <tr>
        <th class="year">年</th>
        <th class="month">月</th>
        <th>免許・資格</th>
    </tr>
    @for ($i = 0; $i < 5; $i++)
        <tr>
            <td class="year"></td>
            <td class="month"></td>
            <td></td>
        </tr>
    @endfor
</table>

</body>
</html>
