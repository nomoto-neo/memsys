<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 企業会員の担当者の招待（App\Models\CompanyInvitation）。担当者は、招待のメールのリンクから
 * 本人が登録して足す。発行・照合・取り消しはApp\Support\CompanyInvitationManagerが行う。
 * 設計は docs/member-types-spec.md。
 *
 * - email      招待のメールの宛先。登録した担当者のメールアドレスになる
 * - token_hash リンクに入れた値の、SHA-256のハッシュ値。リンクの値そのものは持たない
 * - expires_at 招待の期限。過ぎた行は使われず、App\Support\TemporaryDataCleanerが消す
 *
 * 登録が済んだ招待と、取り消した招待は、行を消す。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_company_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_company_invitations');
    }
};
