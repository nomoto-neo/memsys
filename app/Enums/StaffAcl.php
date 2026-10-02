<?php

namespace App\Enums;

/**
 * スタッフの権限（t_staffs.acl）。DBには数値（value）で保存する。
 *
 * Staffモデルの$castsで'acl' => StaffAcl::classにしているので、$staff->aclは
 * この列挙型になる（判定はStaff::isManager()）。画面の選択肢・検証・CSVでは、
 * 都道府県などと同じくコード表code_table('staff_acl')として使う。
 *
 * スタッフ一覧・登録・削除など「他のスタッフを操作できる」のはManagerだけ。
 * Staffは自分自身の編集だけができる。
 */
enum StaffAcl: int implements CodeTableEnum
{
    case Staff = 0;
    case Manager = 1;

    /** 値ごとの名前（画面の選択肢・CSVなどに出す）。 */
    private const LABELS = [
        self::Staff->value => 'スタッフ',
        self::Manager->value => '管理者',
    ];

    public function label(): string
    {
        return self::LABELS[$this->value];
    }
}
