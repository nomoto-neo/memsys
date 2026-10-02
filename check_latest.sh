#!/bin/bash
# memsys の各ファイルが最新版かどうかを、最新版にしか無い記述の有無で確かめる。
# Laravel プロジェクトのルート（artisan のあるディレクトリ）で実行する。
#   bash check_latest.sh
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
    if [ -f "$1" ]; then
        echo "NG  $1（削除し忘れです。移動先の同名ファイルと二重になっています）"; ng=1
    else
        echo "OK  $1（無いことを確認）"
    fi
}

has   app/Support/UploadFilePath.php                     'function tmpUrl('
has   app/Support/AjaxFileUpload.php                     "defined('self::WYSIWYG_FIELDS')"
has   app/Support/HtmlSanitizer.php                      "'Attr.DefaultImageAlt', ''"
has   app/Rules/SameCountAsRule.php                      'class SameCountAsRule'
has   app/helpers.php                                    'function upload_preview_url('
has   app/Models/News.php                                'BODY_IMAGE_WIDTH ='
has   app/Http/Controllers/Admin/NewsController.php      'private const WYSIWYG_FIELDS'
lacks app/Http/Controllers/Admin/StaffController.php     'submitRoute'
lacks app/Http/Controllers/Admin/MemberController.php    'submitRoute'
has   resources/js/upload_request.js                     'export function postUploadFile('
has   resources/js/ajax_upload.js                        'link.download = data.origin_name;'
has   resources/js/wysiwyg_ckeditor.js                   'function setAltOnUpload('
has   resources/js/wysiwyg_summernote.js                 "\$image.attr('alt', data.origin_name)"
lacks resources/js/app.js                                'ClassicEditor'
has   resources/views/layouts/_form_support.blade.php    '.invalid-feedback:not(:empty)'
has   resources/views/layouts/admin.blade.php            "@include('layouts._form_support')"
has   resources/views/layouts/app.blade.php              '.wysiwyg-content img'
lacks resources/views/_confirm_hidden.blade.php          'textareaFields'
has   resources/views/admin/news/_fields.blade.php       'class="form-control wysiwyg"'
has   resources/views/admin/news/create.blade.php        'resources/js/wysiwyg_ckeditor.js'
has   resources/views/admin/news/edit.blade.php          'resources/js/wysiwyg_ckeditor.js'
has   resources/views/admin/news/confirm.blade.php       "route('admin.news.store')"
has   resources/views/admin/staff/confirm.blade.php      "route('admin.staff.store')"
has   resources/views/admin/members/confirm.blade.php    "route('admin.members.update', \$member)"
has   resources/views/news/show.blade.php                'download="{{ $attachment->original_name }}"'

# _ajax_upload_block.blade.php・_ajax_upload_group.blade.phpは、問い合わせ
# フォームからも使えるよう、admin/配下からresources/views直下へ移した
# （_confirm_hidden.blade.phpと同じ理由）。古い場所に残っていると、
# どちらが読まれているか分かりにくくなるので、両方確認する。
has    resources/views/_ajax_upload_block.blade.php      'download="{{ $origin }}"'
has    resources/views/_ajax_upload_group.blade.php      'ajax_add_block'
gone   resources/views/admin/_ajax_upload_block.blade.php
gone   resources/views/admin/_ajax_upload_group.blade.php

# 問い合わせフォーム（/contact）関連。
has   app/Http/Controllers/ContactController.php         'class ContactController'
has   app/Mail/TemplatedMail.php                         'class TemplatedMail'
has   app/Support/MailTemplateParser.php                 'class MailTemplateParser'
has   app/Support/MailTemplate.php                       'class MailTemplate'
has   config/contact.php                                 "'staff_email'"
has   resources/mail-templates/contact_staff.blade.php   'FROM_MAIL:'
has   resources/js/contact_form.js                       'setupZipLookup'
has   resources/views/contact/create.blade.php           'id="contact_submit"'
has   resources/views/contact/confirm.blade.php          "route('contact.store')"
has   resources/views/contact/thanks.blade.php           'ホームへ戻る'
has   resources/views/layouts/app.blade.php              "route('contact.create')"
has   routes/web.php                                     'contact.ajaxUpload'

# 問い合わせメールのHTML/テキスト マルチパート対応（contact_staff_html.blade.php等の
# 「同名+_htmlサフィックス」の companion ファイルを自動検出する機能）。
has   app/Support/MailTemplate.php                       'renderHtmlCompanion'
has   app/Mail/TemplatedMail.php                         "view: 'mail._raw_html'"
has   resources/views/mail/_raw_html.blade.php           '{!! $htmlBody !!}'
has   resources/mail-templates/contact_staff_html.blade.php  'white-space: pre-wrap;'

# メールテンプレートの展開エンジンを、独自の{{変数名}}置換からBladeへ
# 切り替えた（本文中で@if・@foreachなどの制御構文を使えるようにするため）。
# 拡張子も.txt/.htmlから.blade.phpへ統一したので、古い名前のファイルが
# 残っていないことも確認する。
has    app/Support/MailTemplate.php                      'Blade::render('
gone   resources/mail-templates/contact_staff.txt
gone   resources/mail-templates/contact_staff.html

# /contactのセキュリティ強化（スロットル・confirm_token・二重送信防止）。
has   routes/web.php                                     "throttle:5,1"
has   app/Http/Controllers/ContactController.php         'CONFIRM_TOKEN_SESSION_KEY'
has   app/Http/Controllers/ContactController.php         'hash_equals('
has   resources/views/contact/confirm.blade.php          'name="confirm_token"'
has   resources/views/contact/confirm.blade.php          'data-guard-double-submit'
has   resources/js/contact_form.js                       'setupSubmitOnceGuard'

echo
if [ $ng -eq 0 ]; then
    echo 'すべて最新版です。'
else
    echo 'NG のファイルを、この zip の中の同じパスのファイルで置き換えてください。'
fi
