<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 会員テーブル。
 *
 * - kana・phone・birthday・prefectureは任意項目。prefectureは
 *   都道府県コード（code/prefectures.csvのキー）をunsignedTinyIntegerで持つ。
 * - password・legacy_password：既存のシステムから移した会員は、古い方式（MD5など）のハッシュ値を、今の方式で
 *   もう一度ハッシュ値にしたものをlegacy_passwordに入れ、passwordは空にする。本人が次にログインしたときに、
 *   今の方式のパスワードに置き換えてlegacy_passwordを空にする（Neobit\Core\Auth\LegacyPasswordUserProvider）。
 *   新しく登録した会員では、legacy_passwordはずっと空。
 * - notice_mail：お知らせメールを受け取るかどうか。値はApp\Enums\NoticeMail（1＝受け取る、0＝受け取らない）。
 *   一覧の抽出の条件に使うので、索引を付ける。
 * - photo・photo_origin：顔写真のファイル名と元のファイル名。保存先はNeobit\Core\Upload\UploadFilePathの規則で
 *   組み立てる。会員のファイルは非公開（Member::PRIVATE_FILES）なので、storage/app/private/member/... に置かれる。
 * - staff_memo：スタッフが、問い合わせへの対応の経緯などを書き残す欄。管理画面でだけ読み書きし、
 *   会員には見せない（マイページ・履歴書のPDF・会員へのメールには出さない）。
 * - staff_id：/adminから最後にこの会員情報を更新したスタッフのid。
 *   foreignId()でStaff::idと型は揃えているが、constrained()による
 *   外部キー制約は付けていない（このプロジェクトの他のテーブルも
 *   外部キー制約を使っておらず、t_staffs側は論理削除にしているので、行が
 *   物理的に消えて参照が壊れる、という制約が本来防ぎたい事故もそもそも
 *   起きない）。nullableなのは、/adminから一度も更新されていない会員
 *   （自己登録のまま）ではstaff_idが無い状態を正しく表現するため。
 * - remember_token：Auth::attempt()の「ログイン状態を保持する」
 *   (remember me)機能が使う専用カラム。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kana')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('legacy_password')->nullable();
            $table->string('phone', 20)->nullable();
            $table->date('birthday')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->unsignedTinyInteger('prefecture')->nullable();
            $table->unsignedTinyInteger('notice_mail')->default(1)->index();
            $table->string('photo')->nullable();
            $table->string('photo_origin')->nullable();
            $table->text('staff_memo')->nullable();
            $table->foreignId('staff_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_members');
    }
};
