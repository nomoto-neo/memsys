<?php

namespace App\Enums;

/**
 * 企業会員の企業の状態（t_companies.status）。DBには文字列（value）で保存する。
 * 画面ではコード表code_table('company_status')として使う。
 * ログインできるのは、承認済みの企業の担当者だけ。
 */
enum CompanyStatus: string implements CodeTableEnum
{
    /** 登録されたが、運営がまだ確かめていない */
    case Pending = 'pending';

    /** 運営が承認した */
    case Approved = 'approved';

    /** 運営が止めた */
    case Suspended = 'suspended';

    /** 値ごとの名前 */
    private const LABELS = [
        self::Pending->value => '申請中',
        self::Approved->value => '承認済み',
        self::Suspended->value => '停止',
    ];

    public function label(): string
    {
        return self::LABELS[$this->value];
    }
}
