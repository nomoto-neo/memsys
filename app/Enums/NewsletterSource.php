<?php

namespace App\Enums;

use Neobit\Core\Enums\CodeTableEnum;

/**
 * メルマガの行が、どこから来たか（t_newsletter_subscribers.source）。DBには文字列（value）で保存する。
 * 管理画面の一覧ではコード表code_table('newsletter_source')として使う。
 * 「受け取る」になるのは申し込みのフォームだけ。ほかは「受け取らない」の行を作る。
 */
enum NewsletterSource: string implements CodeTableEnum
{
    /** メルマガの申し込みのフォーム。確認コードが通って「受け取る」にした */
    case Form = 'form';

    /** お知らせメールの末尾の配信停止のURL */
    case UnsubscribeLink = 'unsubscribe_link';

    /** 「受け取る」だった会員の退会 */
    case Withdrawal = 'withdrawal';

    /** 管理画面のメルマガ希望者の一覧での停止 */
    case Admin = 'admin';

    /** 値ごとの名前 */
    private const LABELS = [
        self::Form->value => '申し込みのフォーム',
        self::UnsubscribeLink->value => '配信停止のURL',
        self::Withdrawal->value => '退会',
        self::Admin->value => '管理画面',
    ];

    public function label(): string
    {
        return self::LABELS[$this->value];
    }
}
