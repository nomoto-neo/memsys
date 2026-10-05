FROM_MAIL: {!! $from_mail !!}
FROM_NAME: {!! $from_name !!}
TO_MAIL: {!! $staff_mail !!}
SUBJECT: 【企業会員の申請】{!! $company_name !!}

企業会員の登録の申請がありました。
管理画面で内容を確かめ、承認か却下を行ってください。

■申請日時
{!! $applied_at !!}

■企業ID
{!! $company_code !!}

■企業名
{!! $company_name !!}

■担当者
{!! $user_name !!}

■管理画面
{!! $admin_url !!}

--
このメールは、企業会員の登録フォーム（/company/regist）からの自動送信です。
