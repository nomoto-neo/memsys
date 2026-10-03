<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\News;
use App\Models\Staff;

/**
 * ニュース記事1件ごとに「誰が何をしてよいか」の判断。
 *
 * App\Models\NewsとApp\Policies\NewsPolicyという名前の対応から、Laravelが自動で
 * このクラスを見つけるので、どこかに登録する必要は無い。
 */
class NewsPolicy
{
    /**
     * 記事のファイル（一覧用画像・添付ファイル・本文の画像。News::PRIVATE_FILE_FIELDS）を
     * 見てよいか。$userはログイン中の会員かスタッフで、ログインしていない訪問者ならnull
     * （引数をnullableにしておくと、ログインしていない人についてもLaravelがこのメソッドを呼ぶ）。
     *
     * スタッフは、非表示・会員限定の記事も含めて全部見られる（管理画面の編集・確認・詳細で使う）。
     * それ以外は、訪問者側で記事そのものを見てよいときだけ見られる（News::isVisibleTo()）。
     */
    public function viewFiles(Member|Staff|null $user, News $news, string $field): bool
    {
        if ($user instanceof Staff) {
            return true;
        }

        return $news->isVisibleTo($user);
    }
}
