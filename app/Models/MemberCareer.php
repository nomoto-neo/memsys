<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 会員の経歴（学歴・職歴）1行分。年・月・内容の組。
 * 保存・削除はNeobit\Core\Crud\ChildTablesが、Member::careers()のリレーションを通して行う。
 */
class MemberCareer extends Model
{
    protected $table = 't_member_careers';

    protected $fillable = [
        'member_id',
        'year',
        'month',
        'content',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
    ];

    /** 内容の文字数の上限 */
    public const CONTENT_MAX_LENGTH = 255;

    /** 年の下限。上限は今年 */
    public const YEAR_MIN = 1900;

    /**
     * 経歴の組（careers）の項目の、要素ごとの検証ルール。管理画面とマイページのrules()に足す。
     * 年・月・内容のどれか1つでも入れた行は、3つとも要る（空の行は、検証の前に除かれる）。
     */
    public static function rules(): array
    {
        return [
            'careers_year.*' => ['required', 'integer', 'between:'.self::YEAR_MIN.','.now()->year],
            'careers_month.*' => ['required', 'integer', 'between:1,12'],
            'careers_content.*' => ['required', 'string', 'max:'.self::CONTENT_MAX_LENGTH],
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
