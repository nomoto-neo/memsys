<?php

namespace App\Enums;

/**
 * 会員がお知らせメールを受け取るかどうか（t_members.notice_mail）。DBには数値（value）で保存する。
 * 画面の選択肢・検証・CSVでは、コード表code_table('notice_mail')として使う。
 *
 * お知らせメールは、管理画面の一斉メールのこと。確認コードやパスワードの変更のお知らせのように、
 * 本人の操作に応えて送るメールは、この値に関係なく送る。
 * 「受け取らない」の会員のアドレスが宛先のCSVにあると、一斉メールは送れない
 * （Admin\BulkMailController）。メールの中の配信停止のURLからも「受け取らない」に変わる
 * （App\Support\MailUnsubscribe）。
 */
enum NoticeMail: int implements CodeTableEnum
{
    case Receive = 1;
    case Stop = 0;

    /** 値ごとの名前 */
    private const LABELS = [
        self::Receive->value => '受け取る',
        self::Stop->value => '受け取らない',
    ];

    public function label(): string
    {
        return self::LABELS[$this->value];
    }
}
