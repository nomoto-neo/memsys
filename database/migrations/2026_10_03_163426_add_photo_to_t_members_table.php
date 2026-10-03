<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_membersに顔写真（photo・photo_origin）を足す。
 *
 * DBに保存するのはファイル名（photo）と元のファイル名（photo_origin）だけで、
 * 保存先はApp\Support\UploadFilePathの規則で組み立てる。会員のファイルは
 * 非公開（Member::PRIVATE_FILES）なので、storage/app/private/member/... に置かれる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_members', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('prefecture');
            $table->string('photo_origin')->nullable()->after('photo');
        });
    }

    public function down(): void
    {
        Schema::table('t_members', function (Blueprint $table) {
            $table->dropColumn(['photo', 'photo_origin']);
        });
    }
};
