<?php

namespace App\Support;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 会員の登録・退会を、サイトの動きの記録としてログに残す。
 *
 * 書き出し先はconfig/logging.phpの'member'チャンネル
 * （storage/logs/member-日付.log）。
 *
 * 退会した会員の行はDBから物理削除するので、退会後に残る記録はこのログだけになる。
 * そのため、ログにも氏名・メールアドレス・電話番号などの個人情報は書かず、
 * 会員id・登録日時・IPアドレスだけを書く。会員idは退会後に誰のものか
 * たどれない番号で、「id 123 の会員が○月に登録し、△月に退会した」という
 * 動きだけが分かる。
 *
 * どの項目をログに書くかをこのクラスに集めているのは、呼び出し側で
 * うっかり$member->emailなどを書き足してしまうことを防ぐため。
 */
class MemberActivityLog
{
    public static function registered(Member $member, Request $request): void
    {
        Log::channel('member')->info('会員登録', [
            'member_id' => $member->id,
            'ip' => $request->ip(),
        ]);
    }

    /**
     * 退会。行を削除した後の$memberを渡してよい（delete()の後も、
     * インスタンスが持つidやcreated_atはそのまま読める）。
     */
    public static function withdrawn(Member $member, Request $request): void
    {
        Log::channel('member')->info('会員退会', [
            'member_id' => $member->id,
            'registered_at' => $member->created_at?->format('Y-m-d H:i:s'),
            'ip' => $request->ip(),
        ]);
    }
}
