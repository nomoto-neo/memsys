<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\Staff;

/**
 * 会員のデータ1件ごとに「誰が何をしてよいか」の判断。
 *
 * App\Models\MemberとApp\Policies\MemberPolicyという名前の対応から、Laravelが自動で
 * このクラスを見つけるので、どこかに登録する必要は無い。
 *
 * 1つ目の引数$userは、ログイン中の会員（webガード）かスタッフ（adminガード）。
 * App\Http\Controllers\UploadedFileControllerは、ログイン中のガードごとに
 * Gate::forUser()で順に聞くので、どちらも渡ってくる。
 */
class MemberPolicy
{
    /**
     * 非公開のフィールド（Member::PRIVATE_FILE_FIELDS）のファイルを見てよいか。
     * $fieldはフィールド名（'photo'など）。今は顔写真だけなので、フィールドに関係なく
     * 本人とスタッフなら見られる。スタッフは全員が管理画面の会員のコーナーを使えるので、
     * ここでもスタッフなら許す。
     */
    public function viewFiles(Member|Staff $user, Member $member, string $field): bool
    {
        if ($user instanceof Staff) {
            return true;
        }

        return $user->id === $member->id;
    }
}
