<?php

namespace App\Enums;

/**
 * 操作ログの操作の種類（t_operation_logs.action）。DBには文字列（value）で保存する。
 * 画面ではコード表code_table('operation_log_action')として使う。
 * 記録するところはApp\Support\OperationRecorderの冒頭のコメントにまとめてある。
 */
enum OperationLogAction: string implements CodeTableEnum
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
    case BulkMailSend = 'bulk_mail_send';
    case MailUnsubscribe = 'mail_unsubscribe';
    case InvitationSend = 'invitation_send';
    case InvitationCancel = 'invitation_cancel';
    case Throttled = 'throttled';

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
        self::BulkMailSend->value => '一斉メールの送信',
        self::MailUnsubscribe->value => 'お知らせメールの配信停止',
        self::InvitationSend->value => '担当者の招待の送信',
        self::InvitationCancel->value => '担当者の招待の取り消し',
        self::Throttled->value => '回数の制限',
    ];

    public function label(): string
    {
        return self::LABELS[$this->value];
    }
}
