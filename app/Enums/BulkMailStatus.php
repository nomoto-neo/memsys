<?php

namespace App\Enums;

use Neobit\Core\Enums\CodeTableEnum;

/**
 * 一斉メールの送信の状態（t_bulk_mails.status）。DBには数値（value）で保存する。
 * 画面ではコード表code_table('bulk_mail_status')として使う。
 */
enum BulkMailStatus: int implements CodeTableEnum
{
    /** キューに積んで、裏で送っている */
    case Sending = 1;

    /** 全部のジョブが終わった。失敗した宛先があっても、試し直しが済めば完了にする */
    case Finished = 2;

    /** 値ごとの名前 */
    private const LABELS = [
        self::Sending->value => '送信中',
        self::Finished->value => '完了',
    ];

    public function label(): string
    {
        return self::LABELS[$this->value];
    }
}
