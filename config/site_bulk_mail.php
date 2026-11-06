<?php

/**
 * 一斉メール（App\Http\Controllers\Admin\BulkMailController）の設定。
 */
return [

    // 送る速さ。1分に送る通数の上限で、SMTPの送信の上限に合わせる。
    // App\Jobs\SendBulkMailが、AppServiceProviderのbulk-mailの制限として使う
    'per_minute' => (int) env('MAIL_BULK_PER_MINUTE', 5),

    // 宛先のCSVを読むときに、メールアドレスのドメインがDNSにあるかを確かめるか
    // （App\Rules\MailDomainRule）。ドメインの打ち間違いを、送る前に見つける。
    // DNSを引けないサーバーと、外のネットワークに頼らないテストでは、falseにする
    'check_domain' => (bool) env('MAIL_BULK_CHECK_DOMAIN', true),

];
