<?php

namespace Neobit\Core\Enums;

use Neobit\Core\Models\CsvDownloadLog;
use Neobit\Core\Models\CsvImportLog;
use Neobit\Core\Models\OperationLog;

/**
 * 操作ログの操作の種類（operation_logs.action）。DBには文字列（value）で保存する。
 * サイトに固有の操作は、App側の列挙型が足す（Neobit\Core\Enums\OperationLogActionType）。
 * 画面では、足した分と合わせて、コード表code_table('operation_log_action')として使う。
 * 記録するところはNeobit\Core\Operations\OperationRecorderの冒頭のコメントにまとめてある。
 */
enum OperationLogAction: string implements OperationLogActionType
{
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Restore = 'restore';
    case CsvDownload = 'csv_download';
    case CsvImport = 'csv_import';
    case Pdf = 'pdf';
    case PasswordChange = 'password_change';
    case PasskeyAdd = 'passkey_add';
    case PasskeyDelete = 'passkey_delete';
    case TwoFactorReset = 'two_factor_reset';
    case InvitationSend = 'invitation_send';
    case InvitationCancel = 'invitation_cancel';
    case Throttled = 'throttled';
    case IpBlock = 'ip_block';
    case IpUnblock = 'ip_unblock';
    case AccessRestrictionChange = 'access_restriction_change';

    /** 値ごとの名前 */
    private const LABELS = [
        self::Login->value => 'ログイン',
        self::LoginFailed->value => 'ログインの失敗',
        self::Logout->value => 'ログアウト',
        self::View->value => '詳細の閲覧',
        self::Create->value => '登録',
        self::Update->value => '更新',
        self::Delete->value => '削除',
        self::Restore->value => '削除の取り消し',
        self::CsvDownload->value => 'CSVダウンロード',
        self::CsvImport->value => 'CSV取り込み',
        self::Pdf->value => 'PDF出力',
        self::PasswordChange->value => 'パスワードの変更',
        self::PasskeyAdd->value => 'パスキーの登録',
        self::PasskeyDelete->value => 'パスキーの削除',
        self::TwoFactorReset->value => '2段階認証の登録解除',
        self::InvitationSend->value => '担当者の招待の送信',
        self::InvitationCancel->value => '担当者の招待の取り消し',
        self::Throttled->value => '回数の制限',
        self::IpBlock->value => 'IPアドレスのブロック',
        self::IpUnblock->value => 'IPアドレスのブロックの解除',
        self::AccessRestrictionChange->value => 'アクセス制限の変更',
    ];

    public function label(): string
    {
        return self::LABELS[$this->value];
    }

    /** CSVのダウンロードと取り込みは、それぞれの記録に件数を持つ。記録のidは、補足に入っている */
    public function relatedKey(OperationLog $log): array
    {
        return match ($this) {
            self::CsvDownload => [CsvDownloadLog::class, $log->detail['csv_download_log_id'] ?? null],
            self::CsvImport => [CsvImportLog::class, $log->detail['csv_import_log_id'] ?? null],
            default => [null, null],
        };
    }
}
