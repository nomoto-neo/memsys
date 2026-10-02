{{-- App\Mail\TemplatedMailが本文に使う最小限のビュー。
     $bodyはApp\Support\MailTemplate（テンプレートファイルの変数展開）が
     組み立てた、すでに完成しているプレーンテキストなので、{{ }}では
     なくエスケープの無い{!! !!}で出す（理由はApp\Support\MailTemplateの
     コメント参照）。このファイル自体に変数展開や表示ロジックは一切無い。 --}}
{!! $body !!}
