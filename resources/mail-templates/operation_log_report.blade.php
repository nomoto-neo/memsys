FROM_MAIL: {!! $from_mail !!}
FROM_NAME: {!! $from_name !!}
TO_MAIL: {!! $to !!}
SUBJECT: 【要確認：{!! $app_name !!}】操作ログの報告（{!! $date !!}）

{!! $app_name !!}の、{!! $date !!} の操作ログの報告です。

■気になる点
@if ($notable !== '')
{!! $notable !!}
@else
ありません。
@endif

■件数
{!! $counts !!}

■操作の多い人
{!! $operators !!}

詳しくは、管理画面の「操作ログ」で見てください。
{!! $url !!}

--
このメールは、気になる点があった日にだけ自動で送信されています。
