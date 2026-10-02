<p>ウェブサイトのお問い合わせフォームより、下記の内容で問い合わせがありました。</p>

<table cellpadding="4" cellspacing="0" border="0">
    <tr><td><strong>お名前</strong></td><td>{{ $name }}</td></tr>
    <tr><td><strong>フリガナ</strong></td><td>{{ $furigana }}</td></tr>
    <tr><td><strong>メールアドレス</strong></td><td>{{ $email }}</td></tr>
    <tr><td><strong>携帯電話</strong></td><td>{{ $phone }}</td></tr>
    <tr><td><strong>郵便番号</strong></td><td>{{ $zip }}</td></tr>
    <tr><td><strong>住所</strong></td><td>{{ $prefecture }}{{ $city }}{{ $address_other }}</td></tr>
</table>

<p><strong>お問い合わせ内容</strong></p>
{{-- 改行入りの値は、変換せずそのままstyle="white-space: pre-wrap;"の中に
     出している。{{ }}なのでBladeが自動でhtmlspecialchars()する
     （$bodyは訪問者の入力値なので、無変換のままHTMLへ差し込むと
     HTMLインジェクションが成立してしまう。App\Support\MailTemplateの
     コメント参照）。このファイルは本当にBladeとしてコンパイルされる
     （App\Support\MailTemplate::renderHtmlCompanion()がBlade::render()
     に通す）ので、このコメント自体もふつうに出力からは消える。 --}}
<div style="white-space: pre-wrap;">{{ $body }}</div>

<p style="color: #666; font-size: 12px;">
    ---<br>
    このメールは、お問い合わせフォーム（/contact）からの自動送信です。<br>
    返信すると、上記メールアドレス宛に送られます。
</p>
