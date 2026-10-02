<?php

namespace App\Policies;

use App\Models\Staff;

/**
 * スタッフのデータ1件ごとに「誰が何をしてよいか」の判断。
 *
 * 3か所から同じ判断を使う。
 * - routes/web.phpのcanミドルウェア（例: ->middleware('can:update,staff')）。
 *   falseを返すと、コントローラーに入る前に403になる
 * - Blade（例: @can('update', $staff)）。ボタンやリンク、入力欄を出すかどうか
 * - コントローラー（例: $actor->can('updateAcl', $staff)）
 *
 * 1つ目の引数$actorは操作しているスタッフ。管理画面のルートはauth:adminの内側に
 * あるので、adminガードでログイン中のスタッフが渡ってくる（auth:adminが、その
 * リクエストの間だけ「今のユーザー」をadminガードに切り替えるため）。
 * 2つ目の引数$staffは操作の対象のスタッフ（ルートの{staff}から、ルートモデル
 * バインディングで取り出したもの）。
 *
 * App\Models\StaffとApp\Policies\StaffPolicyという名前の対応から、Laravelが自動で
 * このクラスを見つけるので、どこかに登録する必要は無い。
 *
 * 削除済み（論理削除）のスタッフに対してできるのは、詳細画面の表示と削除の取り消し
 * （restore()）だけ。編集・削除・2段階認証の代理解除は、削除済みならfalseを返す。
 * 詳細画面のボタンの出し分けも、この判断をそのまま@canで使っている。
 *
 * 一覧・新規登録のように、特定の1件に対する操作ではない「管理者だけの機能」は、
 * ここではなくroutes/web.phpのacl.manager（EnsureStaffIsManager）で守っている。
 * 管理者かどうかの判定そのものは、どちらもStaff::isManager()を使う。
 */
class StaffPolicy
{
    /**
     * 詳細画面を表示してよいか。本人か管理者なら表示できる。
     * 削除済みのスタッフの詳細画面は、管理者だけが開ける（削除済みのスタッフは
     * ログインできないので、本人が開くことは無い）。
     */
    public function view(Staff $actor, Staff $staff): bool
    {
        return $actor->isManager() || $actor->id === $staff->id;
    }

    /**
     * 編集してよいか（編集画面・確認画面・戻る・更新）。本人か管理者なら編集できる。
     * 今はview()と同じ条件だが、「見るのはよいが変えるのは管理者だけ」のように
     * 分かれたときに、ここだけを変えられるよう別のメソッドにしている。
     */
    public function update(Staff $actor, Staff $staff): bool
    {
        return ! $staff->trashed()
            && ($actor->isManager() || $actor->id === $staff->id);
    }

    /**
     * 権限（acl）を変えてよいか。管理者だけ。
     * 新規登録の画面では対象がまだ無いので、$staffはnullのこともある
     * （$actor->can('updateAcl', Staff::class)のように、クラス名で呼ぶ）。
     */
    public function updateAcl(Staff $actor, ?Staff $staff = null): bool
    {
        return $actor->isManager();
    }

    /**
     * 削除してよいか。管理者だけ、かつ自分自身は削除できない。
     */
    public function delete(Staff $actor, Staff $staff): bool
    {
        return ! $staff->trashed()
            && $actor->isManager() && $actor->id !== $staff->id;
    }

    /**
     * 削除を取り消してよいか（論理削除したスタッフを元に戻す）。管理者だけ、かつ削除済みのスタッフだけ。
     */
    public function restore(Staff $actor, Staff $staff): bool
    {
        return $staff->trashed() && $actor->isManager();
    }

    /**
     * 2段階認証の登録を、管理者として代理で解除してよいか。管理者だけ、かつ自分自身は対象外
     * （本人は自分の詳細画面の「2段階認証の登録を解除する」から、TOTPの確認付きで解除する）。
     */
    public function resetTwoFactor(Staff $actor, Staff $staff): bool
    {
        return ! $staff->trashed()
            && $actor->isManager() && $actor->id !== $staff->id;
    }
}
