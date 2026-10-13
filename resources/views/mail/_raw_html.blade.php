{{-- Neobit\Core\Mail\TemplatedMailが、{テンプレート名}.htmlが用意されている
     ときだけ使う最小限のビュー。$htmlBodyはNeobit\Core\Mail\MailTemplateが
     すでにhtmlspecialchars()でエスケープ済みの状態で変数展開している
     ので、ここで{{ }}によって二重にエスケープしないよう{!! !!}で出す
     （理由はNeobit\Core\Mail\MailTemplateのコメント参照）。このファイル自体に
     変数展開や表示ロジックは一切無い（resources/views/mail/_raw_text.blade.php
     と対になる、HTML版）。 --}}
{!! $htmlBody !!}
