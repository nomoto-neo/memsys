FROM_MAIL: {!! $from_mail !!}
FROM_NAME: {!! $from_name !!}
TO_MAIL: {!! $to !!}
SUBJECT: 【要確認：{!! $app_name !!}】{!! $level !!}：{!! $title !!}

{!! $app_name !!}（{!! $app_env !!}）で、注意すべきログが記録されました。

■日時
{!! $datetime !!}　{!! $level !!}

■内容
{!! $message !!}

■起きたところ
{!! $where !!}
@if ($exception_class !== '')

■例外
{!! $exception_class !!}
{!! $location !!}
@endif
@if ($context !== '')

■添えられた情報
{!! $context !!}
@endif
@if ($suppressed > 0)

■間引いた件数
前の通知の後に、同じ内容のものが{!! $suppressed !!}件ありました。
@endif
@if ($trace !== '')

■スタックトレースの先頭
{!! $trace !!}
@endif

--
このメールは、ERROR_NOTIFY_LEVELの設定による自動送信です。
詳しくはサーバーの storage/logs を見てください。
