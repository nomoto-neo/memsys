{{-- App\Mail\Contact2Notificationのプレーンテキスト版本文。
     resources/views/mail/contact2.blade.php（HTML版）と違い、$dataの
     各項目を{!! !!}でそのまま出す（{{ }}のHTMLエスケープはHTMLに
     差し込むためのものなので、プレーンテキストには不要かつ有害。
     "&"が"&amp;"に化けるなど、そのままメール本文に出てしまう）。 --}}
{!! $data['name'] !!} 様よりお問い合わせがありました。

■お名前
{!! $data['name'] !!}

■メールアドレス
{!! $data['email'] !!}

■お問い合わせ内容
{!! $data['message'] ?? '（本文なし）' !!}
