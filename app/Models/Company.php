<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 企業会員の企業。企業の情報だけを持ち、ログインはしない。
 * ログインするのは、この企業に属する担当者（CompanyUser）。設計は docs/member-types-spec.md。
 */
class Company extends Model
{
    // 操作ログで、変わった列に数えない列。最後に更新したスタッフのidは、入力とは関係なく変わるため
    public const OPERATION_LOG_IGNORE = ['staff_id'];

    protected $table = 't_companies';

    protected $fillable = [
        // 企業ID。ログイン画面で入力する。新しく登録した企業では、idと同じ番号
        'code',
        'name',
        'kana',
        'representative',
        'zip',
        'prefecture',
        'address',
        'tel',
        'url',
        'status',
        // スタッフが書き残す管理メモ。企業の側には見せない
        'staff_memo',
        // 管理画面から最後に更新したスタッフのid
        'staff_id',
    ];

    protected $casts = [
        'status' => CompanyStatus::class,
        // 都道府県はコード表の値と===で比べるので、intにそろえる
        'prefecture' => 'integer',
        'staff_id' => 'integer',
    ];

    // 企業IDを書かずに作った企業には、idと同じ番号を企業IDとして入れる。新しく登録した企業が当たる。
    // idは保存してから決まるので、作った直後に入れる。既存のシステムから移す企業は、
    // 今までのログインIDを企業IDとして書いて作るので、そのまま残る
    protected static function booted(): void
    {
        static::created(function (self $company) {
            if ($company->code === null) {
                $company->forceFill(['code' => (string) $company->id])->saveQuietly();
            }
        });
    }

    // この企業の担当者
    public function users(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    // 担当者がログインできる状態か。承認済みのときだけ
    public function isApproved(): bool
    {
        return $this->status === CompanyStatus::Approved;
    }
}
