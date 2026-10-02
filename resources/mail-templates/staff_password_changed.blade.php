FROM_MAIL: {!! $from_mail !!}
FROM_NAME: {!! $from_name !!}
TO_MAIL: {!! $to_mail !!}
SUBJECT: 【管理画面】パスワード変更のお知らせ

{!! $name !!} 様

{!! $changed_at !!}に、管理画面のアカウント（ログインID：{!! $login_id !!}）のパスワードが変更されました。
@if ($changed_by_other)
（管理者の{!! $changed_by_name !!}さんが変更しました）
@endif

あわせて、「この端末を信頼する」で信頼した端末@if ($passkeys_deleted)と、登録されていたパスキー@endifを、すべて無効にしました。
次回のログインから、新しいパスワードと、認証アプリの確認コードでログインしてください。
@if ($passkeys_deleted)
パスキーをお使いになる場合は、ログインした後、「自分の情報」から登録し直してください。
@endif

このお手続きに心当たりがない場合は、すぐに管理者へご連絡ください。

管理画面
{!! $login_url !!}

--
このメールは自動送信されています。返信いただいてもお答えできません。
