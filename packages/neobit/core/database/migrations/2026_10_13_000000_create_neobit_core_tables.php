<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * フレームワークが持つ、共通のテーブルを作る。
 *
 * どのサイトにも同じ形で要る、認証の仕組みと記録のためのテーブルで、業務のデータではない。
 * 名前に t_ は付けない。誰のものかは、種類とid（authenticatable_type・authenticatable_id など）で持つ。
 * すでにあるテーブルは作り直さない（以前のマイグレーションで作った既存のDBを、そのまま使うため）。
 *
 * - code_tables：DBで管理するコード表。以前の名前 t_codes があるときは、行を残して改名する
 * - operation_logs・csv_download_logs・csv_import_logs：操作の記録
 * - trusted_devices・two_factor_backup_codes・two_factor_secrets・passkeys：2段階認証とパスキー
 * - team_invitations：企業などの集団への、担当者の招待
 */
return new class extends Migration
{
    /** 以前のコード表のテーブルの名前 */
    private const OLD_CODE_TABLE = 't_codes';

    public function up(): void
    {
        $this->createCodeTables();
        $this->createOperationLogs();
        $this->createCsvLogs();
        $this->createTwoFactorTables();
        $this->createPasskeys();
        $this->createTeamInvitations();
    }

    public function down(): void
    {
        foreach ([
            'team_invitations', 'passkeys', 'two_factor_secrets', 'two_factor_backup_codes',
            'trusted_devices', 'csv_import_logs', 'csv_download_logs', 'operation_logs', 'code_tables',
        ] as $name) {
            Schema::dropIfExists($name);
        }
    }

    /**
     * コード表。
     * type がコード名、code がコード値（数字でも文字でもよいので文字列）、name が表示名、
     * sort_order が表示順、staff_id が最後に更新したスタッフ。更新は、そのコード表の行を全部消して
     * 入れ直すので、ほかのデータは行のidでなくコード値で参照する。
     */
    private function createCodeTables(): void
    {
        if (Schema::hasTable('code_tables')) {
            return;
        }

        // 以前の名前のテーブルがあれば、行を残して改名する
        if (Schema::hasTable(self::OLD_CODE_TABLE)) {
            Schema::rename(self::OLD_CODE_TABLE, 'code_tables');

            if (Schema::hasIndex('code_tables', 't_codes_type_code_unique')) {
                Schema::table('code_tables', function (Blueprint $table) {
                    $table->renameIndex('t_codes_type_code_unique', 'code_tables_type_code_unique');
                });
            }

            return;
        }

        Schema::create('code_tables', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('code', 50);
            $table->string('name')->default('');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->timestamps();

            $table->unique(['type', 'code']);
        });
    }

    /** 操作ログ。誰が・いつ・どこから・何に・何をしたかを残す */
    private function createOperationLogs(): void
    {
        if (Schema::hasTable('operation_logs')) {
            return;
        }

        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            // 操作した人。ログインの失敗のように誰か分からないときは空
            $table->string('operator_type', 30)->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            // 操作の種類（config('neobit.operation_log_actions')の列挙型の値）
            $table->string('action', 30);
            // 操作の対象。CSVのダウンロードのように対象が1件に決まらないときは空
            $table->string('target_type', 30)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            // 更新のときに値が変わった列の名前の一覧。値は入れない
            $table->json('changed_fields')->nullable();
            // 種類ごとの補足。CSVの名前、ログインに失敗したログインIDなど
            $table->json('detail')->nullable();
            $table->string('ip', 45)->nullable();
            // 端末の種類（例：iPhone の Safari）
            $table->string('device', 100)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['operator_type', 'operator_id', 'created_at']);
            $table->index(['target_type', 'target_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /** CSVのダウンロードと取り込みの記録。件数と内訳は、操作ログの画面に出す */
    private function createCsvLogs(): void
    {
        if (! Schema::hasTable('csv_download_logs')) {
            Schema::create('csv_download_logs', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('operator_id')->nullable()->index();
                $table->unsignedInteger('row_count')->default(0);
                $table->json('conditions')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        if (! Schema::hasTable('csv_import_logs')) {
            Schema::create('csv_import_logs', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('operator_id')->nullable()->index();
                $table->string('filename');
                $table->string('encoding', 20);
                $table->unsignedInteger('row_count')->default(0);
                $table->unsignedInteger('inserted_count')->default(0);
                $table->unsignedInteger('updated_count')->default(0);
                $table->unsignedInteger('unchanged_count')->default(0);
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
    }

    /** 2段階認証：信頼済みの端末、バックアップコード、認証アプリ（TOTP）の登録 */
    private function createTwoFactorTables(): void
    {
        if (! Schema::hasTable('trusted_devices')) {
            Schema::create('trusted_devices', function (Blueprint $table) {
                $table->id();
                $table->morphs('authenticatable');
                $table->string('token_hash');
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('two_factor_backup_codes')) {
            Schema::create('two_factor_backup_codes', function (Blueprint $table) {
                $table->id();
                // indexの名前を自動で付けると、MariaDB・MySQLの識別子の上限（64文字）を超えるので、
                // 短い名前を指定している
                $table->morphs('authenticatable', 'two_factor_backup_codes_authenticatable_index');
                $table->string('code_hash');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('two_factor_secrets')) {
            Schema::create('two_factor_secrets', function (Blueprint $table) {
                $table->id();
                $table->string('authenticatable_type');
                $table->unsignedBigInteger('authenticatable_id');
                // 認証アプリと共有する秘密鍵。モデルの側で暗号化して保存する
                $table->text('secret');
                $table->timestamp('confirmed_at');
                $table->timestamps();

                // 1人につき1行。同じ人の行が2つできないようにする
                $table->unique(['authenticatable_type', 'authenticatable_id'], 'two_factor_secrets_authenticatable_unique');
            });
        }
    }

    /** パスキー */
    private function createPasskeys(): void
    {
        if (Schema::hasTable('passkeys')) {
            return;
        }

        Schema::create('passkeys', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('name');
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_device')->nullable();
            $table->timestamps();
        });
    }

    /** 集団（企業など）への担当者の招待。どの集団か、誰が招待したかは、種類とidで持つ */
    private function createTeamInvitations(): void
    {
        if (Schema::hasTable('team_invitations')) {
            return;
        }

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('team_type');
            $table->unsignedBigInteger('team_id');
            // 招待した人。発行したときに入れ、その後は変えない
            $table->string('inviter_type')->nullable();
            $table->unsignedBigInteger('inviter_id')->nullable();
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at')->index();
            $table->timestamps();

            $table->index(['team_type', 'team_id']);
        });
    }
};
