<?php

namespace App\Enums;

use Neobit\Core\Enums\CodeTableEnum;

/**
 * 企業会員の企業の状態（t_companies.status）。DBには数値（value）で保存する。
 * 画面ではコード表code_table('company_status')として使う。
 * ログインできるのは、承認済みの企業の担当者だけ。
 */
enum CompanyStatus: int implements CodeTableEnum
{
    /** 登録されたが、運営がまだ確かめていない */
    case Pending = 1;

    /** 運営が承認した */
    case Approved = 2;

    /** 運営が止めた */
    case Suspended = 9;

    /** 値ごとの名前 */
    private const LABELS = [
        self::Pending->value => '申請中',
        self::Approved->value => '承認済み',
        self::Suspended->value => '停止',
    ];

    /** 一覧と詳細に出す、状態のバッジの色。申請中は目立つ色、停止は赤 */
    private const BADGE_CLASSES = [
        self::Pending->value => 'text-bg-warning',
        self::Approved->value => 'text-bg-success',
        self::Suspended->value => 'text-bg-danger',
    ];

    public function label(): string
    {
        return self::LABELS[$this->value];
    }

    /** 状態のバッジのBootstrapのクラス */
    public function badgeClass(): string
    {
        return self::BADGE_CLASSES[$this->value];
    }
}
