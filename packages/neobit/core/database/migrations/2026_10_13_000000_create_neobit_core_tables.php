<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * フレームワークが持つ、共通のテーブルを作る。
 *
 * どのサイトにも同じ形で要る、認証の仕組みと記録と管理画面の設定のためのテーブルで、業務のデータではない。
 * 名前に t_ は付けない。誰のものかは、種類とid（authenticatable_type・authenticatable_id など）で持つ。
 *
 * - code_tables：DBで管理するコード表
 * - operation_logs・csv_download_logs・csv_import_logs：操作の記録
 * - trusted_devices・two_factor_backup_codes・two_factor_secrets・passkeys：2段階認証とパスキー
 * - team_invitations：企業などの集団への、担当者の招待
 * - ip_blocks・settings・basic_auth_users：IPブロック管理、管理画面から変える設定、ベーシック認証
 * - seo_metas・redirects：記事ごとのSEOの欄と、リダイレクト
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createCodeTables();
        $this->createOperationLogs();
        $this->createCsvLogs();
        $this->createTwoFactorTables();
        $this->createPasskeys();
        $this->createTeamInvitations();
        $this->createIpBlocks();
        $this->createSettings();
        $this->createSeoMetas();
        $this->createRedirects();
        $this->createBasicAuthUsers();
    }

    public function down(): void
    {
        foreach ([
            'basic_auth_users', 'redirects', 'seo_metas', 'settings', 'ip_blocks',
            'team_invitations', 'passkeys', 'two_factor_secrets', 'two_factor_backup_codes',
            'trusted_devices', 'csv_import_logs', 'csv_download_logs', 'operation_logs', 'code_tables',
        ] as $name) {
            Schema::dropIfExists($name);
        }
    }

    /**
     * コード表。
     * type がコード名、code がコード値（数字でも文字でもよいので文字列）、label が画面に出す文字、
     * seq が表示順、staff_id が最後に更新したスタッフ。更新は、そのコード表の行を全部消して
     * 入れ直すので、ほかのデータは行のidでなくコード値で参照する。
     */
    private function createCodeTables(): void
    {
        Schema::create('code_tables', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('code', 50);
            $table->string('label')->default('');
            $table->unsignedInteger('seq')->default(0);
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->timestamps();

            $table->unique(['type', 'code']);
        });
    }

    /** 操作ログ。誰が・いつ・どこから・何に・何をしたかを残す */
    private function createOperationLogs(): void
    {
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            // 操作した人。ログインの失敗のように誰か分からないときは空
            $table->string('operator_type', 30)->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            // 操作の種類（config('neobit.operation_log.actions')の列挙型の値）
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
        Schema::create('csv_download_logs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('operator_id')->nullable()->index();
            $table->unsignedInteger('row_count')->default(0);
            $table->json('conditions')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

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

    /** 2段階認証：信頼済みの端末、バックアップコード、認証アプリ（TOTP）の登録 */
    private function createTwoFactorTables(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('token_hash');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('two_factor_backup_codes', function (Blueprint $table) {
            $table->id();
            // indexの名前を自動で付けると、MariaDB・MySQLの識別子の上限（64文字）を超えるので、
            // 短い名前を指定している
            $table->morphs('authenticatable', 'two_factor_backup_codes_authenticatable_index');
            $table->string('code_hash');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

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

    /** パスキー。列の形は laravel/passkeys に合わせる */
    private function createPasskeys(): void
    {
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

    /**
     * IPブロック管理（Neobit\Core\Security\IpBlocker）で、アクセスを止めているIPアドレス。
     * 1行が1つの止め方で、解除したら行を消す。いつ誰が止めて解除したかは、操作ログに残る。
     */
    private function createIpBlocks(): void
    {
        Schema::create('ip_blocks', function (Blueprint $table) {
            $table->id();
            // IPアドレス1つか、192.0.2.0/24のようなまとまり
            $table->string('ip', 64)->unique();
            // 自動で止めたものならtrue、管理画面から登録したものならfalse
            $table->boolean('auto')->default(false);
            $table->string('reason')->nullable();
            // 自動で止めたときの、数える時間の中のアクセスの回数
            $table->unsignedInteger('hits')->nullable();
            // 止めるのをやめる日時。nullなら期限なし
            $table->dateTime('expires_at')->nullable()->index();
            // 管理画面から登録したスタッフの、登録した時点の名前
            $table->string('registered_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * 管理画面から変えられる設定（Neobit\Core\Models\Setting）。キーと値の組で、1つの設定が1行。
     * 設定ファイルと違い、サーバーに入らなくても、管理画面から運用する人が変えられる。
     * 今はアクセス制限（Neobit\Core\Security\AccessRestriction）と、SEO設定（Neobit\Core\Seo\SeoSettings）が使う。
     */
    private function createSettings(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // 設定の名前。例：access.site_restricted
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * 記事ごとのSEOの欄（Neobit\Core\Models\SeoMeta）。どのコーナーの記事でも、このテーブルに1記事1行で持つ。
     * seoable_typeはモデルの短い名前（Relation::enforceMorphMap()）。空欄の項目は、記事から自動で補う（Neobit\Core\Seo\Seo）。
     */
    private function createSeoMetas(): void
    {
        Schema::create('seo_metas', function (Blueprint $table) {
            $table->id();
            $table->string('seoable_type', 50);
            $table->unsignedBigInteger('seoable_id');
            $table->string('title', 60)->nullable();
            $table->string('description', 300)->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });
    }

    /**
     * 管理画面の「リダイレクト」で登録する、古いURLから新しいURLへの移し方（Neobit\Core\Models\Redirect）。
     * 1行が1つの移し方。当てるのはNeobit\Core\Http\Middleware\ApplyRedirectsで、.htaccessは書き換えない。
     */
    private function createRedirects(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            // 古いURLのパス。末尾に*を書くと、その下の全部に当たる。大文字と小文字、末尾の/の有無を別物にするため、
            // 照合順序は文字をそのまま比べるutf8mb4_bin
            $table->string('old_url', 500)->collation('utf8mb4_bin')->unique();
            // 新しいURL。/から始まるサイトの中のパスか、http(s)://から始まる外のURL
            $table->string('new_url', 1000);
            // 301（恒久）か302（一時）
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('enabled')->default(true);
            // 当たった回数と、最後に当たった日時。使われなくなったものを片付けるため
            $table->unsignedInteger('hits')->default(0);
            $table->dateTime('last_hit_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * 管理画面の「ベーシック認証」のユーザー（Neobit\Core\Models\BasicAuthUser）。訪問者画面と管理画面で、別々のユーザーを持つ。
     * 認証するのはApacheで、追加と削除のたびに、範囲ごとにstorage/app/basic_auth/範囲.htpasswdを書き直す
     * （Neobit\Core\Security\BasicAuth）。
     */
    private function createBasicAuthUsers(): void
    {
        Schema::create('basic_auth_users', function (Blueprint $table) {
            $table->id();
            // 掛ける範囲。site（訪問者画面）かadmin（管理画面）
            $table->string('area', 10);
            // ユーザー名。Apacheは大文字と小文字を別物にするので、照合順序は文字をそのまま比べるutf8mb4_bin
            $table->string('username', 50)->collation('utf8mb4_bin');
            // bcryptのハッシュ（$2y$）。.htpasswdにそのまま書く
            $table->string('password');
            $table->timestamps();

            // ユーザー名は範囲の中で一意。同じユーザー名でも、範囲が違えば別のユーザー
            $table->unique(['area', 'username']);
        });
    }
};
