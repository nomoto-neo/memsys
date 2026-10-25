#!/bin/bash
# memsys のサーバーへ送ったファイルが最新版かどうかを確かめる。
# Laravel プロジェクトのルート（artisan のあるディレクトリ）で実行する。
#   bash check_latest.sh
#
# 確かめるのは次の2つ。
# 1. パッケージ化（共通部品を packages/neobit/core へ移した作業）で消したファイルが、残っていないこと。
#    古いマイグレーションが残っていると、サーバーの状態によっては migrate で流れて、
#    フレームワークのマイグレーションとぶつかる
# 2. 最新の版にしか無い記述があること
ng=0

has() {  # has ファイル 文字列 … ファイルに文字列が含まれていればOK
    if [ ! -f "$1" ]; then
        echo "NG  $1（ファイルがありません）"; ng=1
    elif grep -qF -- "$2" "$1"; then
        echo "OK  $1"
    else
        echo "NG  $1（古い版です）"; ng=1
    fi
}

lacks() {  # lacks ファイル 文字列 … ファイルに文字列が含まれていなければOK
    if [ ! -f "$1" ]; then
        echo "NG  $1（ファイルがありません）"; ng=1
    elif grep -qF -- "$2" "$1"; then
        echo "NG  $1（古い版です）"; ng=1
    else
        echo "OK  $1"
    fi
}

gone() {  # gone ファイル … ファイル自体が無ければOK（移動・削除したファイル用）
    if [ -e "$1" ]; then
        echo "NG  $1（削除し忘れです）"; ng=1
    fi
}

gone_dir() {  # gone_dir ディレクトリ … 中にファイルが1つも無ければOK
    if [ -d "$1" ] && [ -n "$(find "$1" -type f -print -quit)" ]; then
        echo "NG  $1（中のファイルの削除し忘れです）"; ng=1
    fi
}

# ---- 1. 消したファイルが残っていないこと ----
# 問題が無いものは、数が多いので1行ずつは出さない

# 共通部品は、ディレクトリごとフレームワークへ移した
gone_dir app/Support

# フレームワークへ移した、アプリのそのほかのファイル
for f in \
    app/Console/Commands/CleanupTemporaryData.php \
    app/Console/Commands/ReportOperationLogs.php \
    app/Console/Commands/ResetTwoFactor.php \
    app/Console/Commands/SetNextId.php \
    app/Enums/CodeTableEnum.php \
    app/Enums/CodeType.php \
    app/Enums/CsvEncoding.php \
    app/Enums/CsvImportMode.php \
    app/Enums/LoginIdType.php \
    app/Enums/OperationLogAction.php \
    app/Enums/SpamCheckResult.php \
    app/Enums/TwoFactorMethod.php \
    app/Http/Controllers/Admin/CodeController.php \
    app/Http/Controllers/UploadedFileController.php \
    app/Http/Middleware/AjaxFormResponse.php \
    app/Http/Middleware/NormalizeInput.php \
    app/Http/Middleware/RestrictAdminAccess.php \
    app/Http/Middleware/RestrictSiteAccess.php \
    app/Http/Middleware/SecurityHeaders.php \
    app/Mail/TemplatedMail.php \
    app/Models/Code.php \
    app/Models/CsvDownloadLog.php \
    app/Models/CsvImportLog.php \
    app/Models/OperationLog.php \
    app/Models/Passkey.php \
    app/Models/TeamInvitation.php \
    app/Models/TrustedDevice.php \
    app/Models/TwoFactorBackupCode.php \
    app/Models/TwoFactorSecret.php \
    app/Rules/HiraganaRule.php \
    app/Rules/KatakanaRule.php \
    app/Rules/PhoneNumberRule.php \
    app/Rules/SameCountAsRule.php \
    app/helpers.php \
    resources/css/app.css \
    resources/css/wysiwyg_suneditor_content.css \
    resources/js/ajax_upload.js \
    resources/js/app.js \
    resources/js/code_editor.js \
    resources/js/passkeys.js \
    resources/js/upload_request.js \
    resources/js/wysiwyg_summernote.js \
    resources/js/wysiwyg_suneditor.js \
    resources/mail-templates/error_notify.blade.php \
    resources/mail-templates/operation_log_report.blade.php \
    resources/views/_ajax_upload_block.blade.php \
    resources/views/_ajax_upload_group.blade.php \
    resources/views/_confirm_hidden.blade.php \
    resources/views/_passkey_suggest.blade.php \
    resources/views/_passkeys.blade.php \
    resources/views/_spam_guard.blade.php \
    resources/views/_two_factor.blade.php \
    resources/views/_two_factor_backup_code_form.blade.php \
    resources/views/_two_factor_backup_codes.blade.php \
    resources/views/_two_factor_manage.blade.php \
    resources/views/_two_factor_reset.blade.php \
    resources/views/admin/codes/index.blade.php \
    resources/views/admin/csv_import/confirm.blade.php \
    resources/views/admin/csv_import/form.blade.php \
    resources/views/admin/too_many_requests.blade.php \
    resources/views/mail/_raw_html.blade.php \
    resources/views/mail/_raw_text.blade.php \
    resources/views/maintenance.blade.php
do
    gone "$f"
done

# フレームワークへ集めた、共通のテーブルのマイグレーション
for f in \
    database/migrations/2026_09_27_110000_create_trusted_devices_table.php \
    database/migrations/2026_09_27_110001_create_two_factor_backup_codes_table.php \
    database/migrations/2026_09_29_100000_create_t_csv_download_logs_table.php \
    database/migrations/2026_09_30_100000_create_t_csv_import_logs_table.php \
    database/migrations/2026_10_01_100000_create_passkeys_table.php \
    database/migrations/2026_10_01_110000_create_t_codes_table.php \
    database/migrations/2026_10_04_160000_create_t_operation_logs_table.php \
    database/migrations/2026_10_05_172426_create_t_company_invitations_table.php \
    database/migrations/2026_10_11_132351_add_totp_to_t_members_and_t_company_users_table.php \
    database/migrations/2026_10_11_154809_create_two_factor_secrets_table.php \
    database/migrations/2026_10_11_180000_rename_log_tables.php \
    database/migrations/2026_10_12_100000_store_operation_log_detail_as_unicode.php \
    database/migrations/2026_10_12_110000_rename_company_invitations_to_team_invitations.php \
    database/migrations/2026_10_12_120000_add_inviter_to_team_invitations_table.php
do
    gone "$f"
done

# フレームワークの中で消したファイル
gone packages/neobit/core/src/Security/AllowedIps.php
gone packages/neobit/core/src/Models/OperationLogSubjectModel.php
gone packages/neobit/core/src/Rules/MaxUploadCountRule.php
gone packages/neobit/core/resources/views/_ajax_upload_group.blade.php
gone packages/neobit/core/src/Crud/RepeatableGroup.php
gone packages/neobit/core/src/Crud/RepeatableGroupDefinitions.php

# フレームワークへ移した操作ログの画面と、名前を変えた列挙型
gone app/Http/Controllers/Admin/OperationLogController.php
gone app/Enums/OperationLogSubject.php
gone app/Enums/OperationLogModel.php
gone_dir resources/views/admin/operation_logs

echo "--  消したファイルの確認を終えました（NG が無ければ、残っていません）"

# ---- 2. 最新の版であること ----

# フレームワーク。composer install が済み、vendor/ の下にリンクができていること
has   vendor/neobit/core/composer.json                                  '"name": "neobit/core"'
has   packages/neobit/core/src/Mail/MailTemplate.php                    'private static function locate('
has   packages/neobit/core/src/NeobitServiceProvider.php                'neobit-mail-templates'
has   packages/neobit/core/src/Code/CodeTable.php                       "'operation_log_action' => [OperationLog::class, 'actionOptions']"
has   packages/neobit/core/src/Models/OperationLog.php                  'public static function actionOptions('
has   packages/neobit/core/src/Http/Controllers/CodeTableController.php "config('neobit.code_table_db'"
has   packages/neobit/core/src/Security/IpBlocker.php                   'final class IpBlocker'
has   packages/neobit/core/src/Security/AccessRestriction.php           'final class AccessRestriction'
has   packages/neobit/core/src/Http/Controllers/OperationLogController.php 'private function indexRoute()'
has   packages/neobit/core/src/Models/OperationLogNamedModel.php        'interface OperationLogNamedModel'
has   packages/neobit/core/src/Pdf/PdfDownload.php                      'private function downloadPdfOnSheet('
has   packages/neobit/core/src/Upload/UploadFieldDefinitions.php        'final class UploadFieldDefinitions'
has   packages/neobit/core/resources/js/ajax_upload.js                  'repeatable:add-row'
has   packages/neobit/core/src/Http/Middleware/RunScheduleOnRequest.php 'class RunScheduleOnRequest'
has   packages/neobit/core/src/Operations/RequestScheduler.php          'final class RequestScheduler'
has   packages/neobit/core/src/Operations/TemporaryDataCleaner.php      'public static function allExclusively('
has   packages/neobit/core/src/Operations/TemporaryDataCleaner.php      'public static function oldJobBatches('
has   packages/neobit/core/config/neobit.php                            "'cron_enabled'"
has   app/Http/Controllers/Admin/NewsController.php                     "'attach.*.file' => ['type' => UploadType::File]"
has   packages/neobit/core/database/migrations/2026_10_16_000000_create_settings_table.php "Schema::create('settings'"
has   packages/neobit/core/database/migrations/2026_10_15_000000_create_ip_blocks_table.php "Schema::create('ip_blocks'"
has   packages/neobit/core/config/neobit.php                            "'code_table_enum_namespaces'"
has   packages/neobit/core/stubs/config/neobit.php                      "// 'code_table_method'"
has   packages/neobit/core/resources/mail-templates/verification_code.blade.php '【{!! $site_name !!}】'
has   packages/neobit/core/resources/js/global.js                       'ajax_submit'
has   packages/neobit/core/resources/css/global.css                     '.invalid-feedback:not(:empty)'
has   packages/neobit/core/database/migrations/2026_10_13_000000_create_neobit_core_tables.php 'OLD_CODE_TABLE'

# アプリ
has   vite.config.js                                                    'preserveSymlinks: true'
has   vite.config.js                                                    'ip_block.js'
has   bootstrap/app.php                                                 'RejectBlockedIp::class'
has   bootstrap/app.php                                                 'RunScheduleOnRequest::class'
has   routes/web.php                                                    'IpBlockController'
has   routes/web.php                                                    'AccessRestrictionController'
has   resources/pdf-sheets/resume_a4.pdf                                '%PDF'
has   routes/web.php                                                    'use Neobit\Core\Http\Controllers\OperationLogController;'
has   config/neobit.php                                                 "'operation_log_models' => ["
has   resources/views/layouts/admin.blade.php                           'vendor/neobit/core/resources/css/global.css'
has   resources/views/layouts/app.blade.php                             'vendor/neobit/core/resources/css/global.css'
has   resources/views/layouts/company.blade.php                         'vendor/neobit/core/resources/css/global.css'
has   config/neobit.php                                                 "'code_table_method' => ["
lacks config/neobit.php                                                 'code_table_merge'
has   app/Models/Staff.php                                              'public static function nameOptions('
lacks config/app.php                                                    'admin_requests_per_minute'
lacks config/logging.php                                                'operation_report_thresholds'
has   resources/mail-templates/member_verification_code.blade.php       '【{!! $site_name !!}】'
has   routes/web.php                                                    'CodeTableController'
has   app/Http/Controllers/ContactController.php                        'catch (MailTemplateException'
has   config/cleanup.php                                                'BulkMailCleanup::class'
has   routes/web.php                                                    'bulk-mails.failedCsv'
has   app/Rules/MailDomainRule.php                                      'class MailDomainRule'
has   config/mail.php                                                   "'bulk_check_domain'"
has   config/mail.php                                                   "'return_path'"
has   app/Services/NewsletterSubscription.php                           'final class NewsletterSubscription'
has   routes/web.php                                                    'newsletter-subscribers.index'
has   packages/neobit/core/src/Auth/LoginVerificationCode.php           '?string $purposeLabel = null'
has   packages/neobit/core/src/Crud/ChildTables.php                 'trait ChildTables'
has   packages/neobit/core/src/Crud/ChildTables.php                 'private function childTableRelation('
has   packages/neobit/core/src/Crud/ChildTableDefinitions.php     "'fixed'と'max'を同時に書けません"
has   packages/neobit/core/resources/views/_repeatable_group.blade.php  'data-fixed'
has   packages/neobit/core/resources/js/repeatable_group.js             'group.dataset.fixed'
has   app/Http/Controllers/Admin/NewsController.php                     "'fixed' => 4"
has   packages/neobit/core/src/Crud/ChildTableDefinitions.php     "'initial'と'fixed'を同時に書けません"
has   database/migrations/2026_10_25_000000_create_t_member_careers_table.php "Schema::create('t_member_careers'"
has   app/Models/MemberCareer.php                                       'class MemberCareer'
has   app/Http/Controllers/MypageController.php                         'deleteChildTables($member)'
has   resources/views/_member_career_row.blade.php                      'careers_content[]'
has   resources/views/pdf/resume_on_sheet.blade.php                     '$firstPageRows = 15'
has   packages/neobit/core/src/Csv/CsvColumnSet.php                     'private function readChildGroup('
has   packages/neobit/core/src/Csv/CsvReader.php                        'private function checkCsvImportRequiredUploads('
has   packages/neobit/core/src/Crud/ChildTables.php                     'private function childTableImportedFileValue('
has   packages/neobit/core/src/Seo/Seo.php                              'final class Seo'
has   packages/neobit/core/src/helpers.php                              "function_exists('seo')"
has   packages/neobit/core/resources/views/_seo_head.blade.php          'twitter:card'
has   resources/views/layouts/app.blade.php                             "@include('neobit::_seo_head')"
has   packages/neobit/core/database/migrations/2026_10_25_000000_create_seo_metas_table.php "Schema::create('seo_metas'"
has   packages/neobit/core/src/Seo/SeoFields.php                        'trait SeoFields'
has   packages/neobit/core/src/Crud/FormFlow.php                        'saveSeoFields'
has   packages/neobit/core/resources/views/_seo_fields.blade.php        'seo_noindex'
has   app/Http/Controllers/Admin/NewsController.php                     '$this->seoRules()'
has   packages/neobit/core/src/Rules/SlugRule.php                       'class SlugRule'
has   packages/neobit/core/src/Rules/NotReservedPathRule.php            'class NotReservedPathRule'
has   routes/web.php                                                    'SlugRule::NESTED_PATTERN'
lacks app/Models/Page.php                                               'isReservedSlug'
has   packages/neobit/core/src/Crud/FormFlow.php                        'saveChildTables'
has   packages/neobit/core/resources/js/repeatable_group.js             'repeatable_template'
has   vite.config.js                                                    'repeatable_group.js'
has   app/Http/Controllers/Admin/NewsController.php                     'CHILD_TABLES'
has   packages/neobit/core/src/Csv/CsvWriter.php                       'final class CsvWriter'
has   packages/neobit/core/src/Csv/CsvDownload.php                     'CsvWriter::download('
has   packages/neobit/core/resources/js/repeatable_group.js             'repeatable:add-row'
has   database/migrations/2026_10_21_000000_rename_columns_on_t_news_attachments_table.php "renameColumn('original_name', 'file_origin')"
has   app/Models/NewsAttachment.php                                     "'file_origin'"
has   resources/views/admin/news/_attach_row.blade.php                  "'field' => 'attach_file'"
has   packages/neobit/core/database/migrations/2026_10_21_000000_rename_sort_order_to_seq_on_code_tables_table.php "renameColumn('sort_order', 'seq')"
has   packages/neobit/core/src/Models/Code.php                          "'seq'"
has   database/migrations/2026_10_21_100000_rename_display_order_to_seq_on_t_categories_table.php "renameColumn('display_order', 'seq')"
has   app/Models/Category.php                                           "'seq'"
has   database/migrations/2026_10_21_200000_rename_display_order_to_seq_on_news_child_tables.php "'t_news_attachments'"
has   packages/neobit/core/resources/js/repeatable_group.js             'repeatable:remove-row'
has   packages/neobit/core/resources/views/_repeatable_group.blade.php  'data-files-only'
has   packages/neobit/core/src/Crud/ChildTableDefinitions.php     "'delete_insert'"
has   packages/neobit/core/src/Crud/ChildTables.php                 '作り直す保存。今の行は下でまとめて消す'
has   database/migrations/2026_10_21_300000_drop_seq_on_t_news_attachments_table.php "dropColumn('seq')"
has   app/Http/Controllers/Admin/NewsController.php                     "'delete_insert' => true"
has   packages/neobit/core/src/Rules/UploadRequiredRule.php             'class UploadRequiredRule'
has   packages/neobit/core/src/Crud/FormFlow.php                        'requiredUploadRules'
has   packages/neobit/core/resources/views/_ajax_upload_block.blade.php 'errorKey'
has   app/Http/Controllers/Admin/NewsController.php                     "News::LIST_IMAGE_WIDTH, 'required' => true"

echo
if [ $ng -eq 0 ]; then
    echo 'すべて最新版です。'
else
    echo '「ファイルがありません」「古い版です」の NG は、そのファイルを送り直してください。'
    echo '「削除し忘れです」の NG は、サーバーからそのファイルを消してください。'
fi
