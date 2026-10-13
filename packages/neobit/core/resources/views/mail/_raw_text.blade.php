{{-- Neobit\Core\Mail\TemplatedMailが本文に使う最小限のビュー。
     $bodyはNeobit\Core\Mail\MailTemplate（テンプレートファイルの変数展開）が
     組み立てた、すでに完成しているプレーンテキストなので、{{ }}では
     なくエスケープの無い{!! !!}で出す（理由はNeobit\Core\Mail\MailTemplateの
     コメント参照）。このファイル自体に変数展開や表示ロジックは一切無い。 --}}
{!! $body !!}
