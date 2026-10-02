<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * スタッフ（管理画面ログイン）テーブル。
 *
 * - login_id：ログインに使う項目。必須・unique。unique制約は削除済み
 *   （deleted_atあり）の行も含めて効くので、削除したスタッフの
 *   ログインIDを新しいスタッフが使うことはできない。同じIDが別の人に
 *   引き継がれて操作履歴の区別が付かなくなることが無く、削除した
 *   スタッフを復元するときにIDが重複して戻せない、ということも起きない。
 * - email：ログインには使わない、連絡先としての任意項目。重複を許す
 *   （役職用の共有アドレスを複数人で登録することもできる）ので、
 *   unique制約は付けていない。
 * - acl：権限レベル。0=スタッフ、1=管理者（App\Enums\StaffAclの
 *   StaffAcl::Staff・StaffAcl::Managerと対応）。新規スタッフのデフォルトは
 *   最小権限の0。
 * - totp_secret：認証アプリと共有する秘密鍵。Staffモデル側で
 *   'encrypted'キャストを指定しており、実際にDBへ入るのはAPP_KEYで
 *   暗号化された値になる。暗号化後の文字列は元の秘密鍵より長くなる
 *   ため、varcharではなくtextにしている。
 * - totp_confirmed_at：QRコードの読み取り・確認コードの入力まで
 *   完了した日時。totp_secretはあるがこちらがnullの間は「発行はしたが
 *   まだ確定していない」状態。
 * - deleted_at：Laravel標準の論理削除（SoftDeletes）で使う削除日時。
 *   nullなら在籍中。他のテーブル（t_members.staff_id等）がスタッフの
 *   idを記録して参照するため、削除後も行自体は残して氏名を参照できる
 *   ようにしている。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_staffs', function (Blueprint $table) {
            $table->id();
            $table->string('login_id')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password');
            $table->unsignedTinyInteger('acl')->default(0);
            $table->text('totp_secret')->nullable();
            $table->timestamp('totp_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_staffs');
    }
};
