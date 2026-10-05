<?php

namespace Database\Seeders;

use App\Enums\StaffAcl;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    /**
     * 最初の管理者アカウントを1件作る。
     *
     * 会員登録と違い、管理者は自己登録の画面を用意しない方針にしたので、
     * 最初の1人はこのシーダー経由で作る。firstOrCreate()にしているので、
     * 何度シーダーを実行しても、login_idが重複してエラーになることはない。
     * すでにあれば何もしない。画面から変えたパスワードや権限を、シーダーの値で上書きしないため。
     * 作り直したいときは、その行を消してから実行する。
     *
     * 配置後、login_id・passwordは必ずご自身のものに書き換えてから実行してください。
     * ログインはlogin_id（必須）で行うようにしたので、emailはここでは
     * 設定していない（連絡先として使いたい場合は、管理画面のスタッフ編集
     * から任意で入力する）。
     *
     * acl => StaffAcl::Managerを明示しているのは、これから初めてこの
     * プロジェクトをセットアップする人（将来の自分を含む）が、この
     * シーダーだけを実行して最初の1人を作ったときに、その1人が
     * スタッフ一覧・登録・削除を一切できない「スタッフ(0)」のまま
     * 締め出されてしまわないようにするため。t_staffsのacl列自体の
     * デフォルトは0（新規スタッフは最小権限から）だが、最初の1人は
     * 管理者でなければ誰もスタッフを管理できず詰んでしまうので、
     * ここだけは明示的にStaffAcl::Managerを指定している。
     */
    public function run(): void
    {
        Staff::firstOrCreate(
            ['login_id' => 'admin'],
            [
                'name' => '管理者',
                'password' => Hash::make('testtest'),
                'acl' => StaffAcl::Manager,
            ]
        );
    }
}
