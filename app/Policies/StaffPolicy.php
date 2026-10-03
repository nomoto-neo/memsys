<?php

namespace App\Policies;

use App\Models\Staff;

/**
 * スタッフ1件ごとに、誰が何をしてよいかの判断。
 *
 * 同じ判断を、ルートのcanミドルウェア、Bladeの@can、コントローラーのcan()の3か所から使う。
 * 1つ目の引数はログイン中のスタッフ、2つ目は操作の対象のスタッフ。名前の対応で
 * Laravelが自動で見つけるので、登録は要らない。
 *
 * 削除済みのスタッフにできるのは、詳細の表示と削除の取り消しだけ。
 * 一覧や新規登録のような、特定の1件に対するものでない管理者だけの機能は、ここではなく
 * ルートのacl.managerで守る。
 */
class StaffPolicy
{
    // 詳細画面を表示してよいか。本人か管理者なら表示できる。
    // 削除済みのスタッフはログインできないので、その詳細は管理者だけが開くことになる。
    public function view(Staff $actor, Staff $staff): bool
    {
        return $actor->isManager() || $actor->id === $staff->id;
    }

    /**
     * 編集してよいか。本人か管理者なら編集できる。
     * 今はview()とほぼ同じ条件だが、見るのと変えるのとで条件が分かれたときに、
     * ここだけを変えられるよう、別のメソッドにしている。
     */
    public function update(Staff $actor, Staff $staff): bool
    {
        return ! $staff->trashed()
            && ($actor->isManager() || $actor->id === $staff->id);
    }

    // 権限を変えてよいか。管理者だけ。
    // 新規登録では対象がまだ無いので、クラス名で呼ばれ、$staffはnullになる。
    public function updateAcl(Staff $actor, ?Staff $staff = null): bool
    {
        return $actor->isManager();
    }

    // 削除してよいか。管理者だけ、かつ自分自身は削除できない。
    public function delete(Staff $actor, Staff $staff): bool
    {
        return ! $staff->trashed()
            && $actor->isManager() && $actor->id !== $staff->id;
    }

    // 論理削除したスタッフを元に戻してよいか。管理者だけ、かつ削除済みのスタッフだけ。
    public function restore(Staff $actor, Staff $staff): bool
    {
        return $staff->trashed() && $actor->isManager();
    }

    // 2段階認証の登録を、管理者が代わりに解除してよいか。管理者だけ、かつ自分自身は対象外。
    // 自分の分は、詳細画面から、認証アプリのコードで本人確認してから解除する。
    public function resetTwoFactor(Staff $actor, Staff $staff): bool
    {
        return ! $staff->trashed()
            && $actor->isManager() && $actor->id !== $staff->id;
    }
}
