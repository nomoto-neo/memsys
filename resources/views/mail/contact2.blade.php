{{-- App\Mail\Contact2Notificationの本文ビュー。App\Mail\TemplatedMailの
     resources/views/mail/_raw_text.blade.php（{!! $body !!}だけの
     プレーンテキスト用ビュー）とは違い、こちらは"view:"（HTMLメール）
     として使う普通のBladeビューなので、{{ }}で普通にエスケープしてよい
     （このビュー自体がこのメール専用に書かれたもので、担当者が
     プレーンテキストとして直接編集する対象ではないため）。

     改行を含む可能性がある項目（お問い合わせ内容）だけ、e()で
     エスケープしてからnl2br()で<br>に変換し、{!! !!}で出す
     （{{ nl2br(...) }}のように書くと、nl2br()が生成した<br>タグ自体が
     エスケープされて文字列としてそのまま表示されてしまう）。 --}}
<p>ウェブサイトのお問い合わせフォーム（/contact2）より、下記の内容で問い合わせがありました。</p>

<dl>
    <dt>お名前</dt>
    <dd>{{ $data['name'] }}</dd>

    <dt>メールアドレス</dt>
    <dd>{{ $data['email'] }}</dd>

    <dt>お問い合わせ内容</dt>
    <dd>{!! nl2br(e($data['message'] ?? '（本文なし）')) !!}</dd>
</dl>
