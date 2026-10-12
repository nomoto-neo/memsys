FROM: {!! $from_name !!} <{!! $from_mail !!}>
TO: {!! $email !!}
SUBJECT: {!! $subject !!}
LIST_UNSUBSCRIBE: <{!! $unsubscribe_url !!}>
LIST_UNSUBSCRIBE_POST: List-Unsubscribe=One-Click

{!! $body !!}

--------------------------------------------------
このメールの配信を停止するには、下のURLを開いてください。
{!! $unsubscribe_url !!}
