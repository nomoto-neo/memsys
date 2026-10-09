<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 会員の登録と退会をログに記録する。書き出し先はstorage/logs/member-日付.log。
 *
 * 退会した会員の行はDBから消すので、退会後に残る記録はこのログだけになる。
 * そのためログにも個人情報は書かず、会員の種類とid・登録日時・IPアドレスだけを書く。
 * 退会した後は会員idから誰だったかはたどれず、登録と退会の時期だけが分かる。
 * 書く項目をこのクラスに集めているのは、呼ぶ側でメールアドレスなどを書き足させないため。
 */
class MemberActivityLog
{
    /** 会員登録 */
    public static function registered(MemberAccount $member, Request $request): void
    {
        Log::channel('member')->info('会員登録', [
            'member_type' => $member->getMorphClass(),
            'member_id' => $member->getKey(),
            'ip' => $request->ip(),
        ]);
    }

    /** 退会。delete()の後でもidなどは読めるので、行を消した後の$memberを渡してよい */
    public static function withdrawn(MemberAccount $member, Request $request): void
    {
        Log::channel('member')->info('会員退会', [
            'member_type' => $member->getMorphClass(),
            'member_id' => $member->getKey(),
            'registered_at' => $member->created_at?->format('Y-m-d H:i:s'),
            'ip' => $request->ip(),
        ]);
    }
}
