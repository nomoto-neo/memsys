<?php

namespace App\Enums;

/**
 * 操作ログの「操作した人」と「対象」の種類（t_operation_logs.operator_type・target_type）。
 * 値は、AppServiceProviderのRelation::enforceMorphMap()に書いたモデルの名前と同じにする。
 * 画面ではコード表code_table('operation_log_subject')として、種類の名前を出すのに使う。
 * 操作ログに残すモデルを増やしたら、enforceMorphMap()とここの両方に足す。
 */
enum OperationLogSubject: string implements CodeTableEnum
{
    case Staff = 'staff';
    case Member = 'member';
    case CompanyUser = 'company_user';
    case Company = 'company';
    case News = 'news';
    case Page = 'page';
    case Category = 'category';
    case Inquiry = 'inquiry';
    case BulkMail = 'bulk_mail';
    case BulkMailTemplate = 'bulk_mail_template';

    /** 値ごとの名前 */
    private const LABELS = [
        self::Staff->value => 'スタッフ',
        self::Member->value => '会員',
        self::CompanyUser->value => '企業の担当者',
        self::Company->value => '企業',
        self::News->value => 'ニュース',
        self::Page->value => '固定ページ',
        self::Category->value => 'ニュースカテゴリー',
        self::Inquiry->value => 'お問い合わせ',
        self::BulkMail->value => '一斉メール',
        self::BulkMailTemplate->value => '一斉メールの文面',
    ];

    /** 操作した人になれる種類。ログインできるモデルだけ */
    public const OPERATORS = [
        self::Staff,
        self::Member,
        self::CompanyUser,
    ];

    public function label(): string
    {
        return self::LABELS[$this->value];
    }
}
