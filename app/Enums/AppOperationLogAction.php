<?php

namespace App\Enums;

use App\Models\BulkMail;
use Neobit\Core\Enums\OperationLogActionType;
use Neobit\Core\Models\OperationLog;

/**
 * このサイトに固有の、操作ログの操作の種類（operation_logs.action）。
 * フレームワークの操作は、Neobit\Core\Enums\OperationLogActionにある。
 * config('neobit.operation_log_actions')に書いてあるので、操作ログの読み書きと、
 * コード表code_table('operation_log_action')で、フレームワークの操作と一緒に扱われる。
 */
enum AppOperationLogAction: string implements OperationLogActionType
{
    case BulkMailSend = 'bulk_mail_send';
    case MailUnsubscribe = 'mail_unsubscribe';

    /** 値ごとの名前 */
    private const LABELS = [
        self::BulkMailSend->value => '一斉メールの送信',
        self::MailUnsubscribe->value => 'お知らせメールの配信停止',
    ];

    public function label(): string
    {
        return self::LABELS[$this->value];
    }

    /** 一斉メールの送信は、送信の記録（BulkMail）に件数を持つ。対象のidが、その記録のid */
    public function relatedKey(OperationLog $log): array
    {
        return match ($this) {
            self::BulkMailSend => [BulkMail::class, $log->target_id],
            default => [null, null],
        };
    }
}
