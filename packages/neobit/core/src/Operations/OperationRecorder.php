<?php

namespace Neobit\Core\Operations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Neobit\Core\Auth\UserAgentLabel;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\OperationLog;

/**
 * 操作ログ（operation_logs）を書く。誰が・いつ・どこから・何に・何をしたかを1行ずつ残す。
 * 情報が漏れたときや、身に覚えのない変更の問い合わせがあったときに、後からたどれるようにするため。
 *
 * ■ 残さないもの
 * 氏名などの個人情報と、変更の前後の値は残さない。操作ログが個人情報の写しにならないようにするため。
 * 操作した人と対象は種類とidで持ち、更新は値が変わった列の名前だけを持つ。
 * 書く項目をこのクラスに集めているのは、呼ぶ側で入力値などを書き足させないため。
 *
 * ■ 操作した人
 * 渡さなければ、その画面のガードでログインしている人になる。管理画面ならスタッフ、
 * マイページなら会員。誰もログインしていなければ空になり、画面では「訪問者」と出す。
 * 2段階目の途中やパスワードの再設定のように、ログインはしていないが誰か分かっている人の
 * 操作は、呼ぶ側が$operatorで渡す。
 *
 * ■ 対象
 * 操作の対象の1件を、$targetで渡す。対象の欄には、その種類とidが残る。
 * ログインの失敗のように、どの1件かは分からないが種類は分かるときは、$targetClassでモデルの
 * クラスを渡す。対象の欄には種類だけが残り、どのログイン画面での失敗かを見分けられる。
 *
 * ■ 記録するところ
 * - 登録・更新・削除      FormFlowのsaveData()・deleteData()。訪問者のお問い合わせも残す。
 *                         CSV取り込みの1件ずつは記録しない
 * - ログイン・ログアウト  AppServiceProviderが、Laravelのログイン・ログアウトのイベントで記録する
 * - ログインの失敗        ログインのコントローラーが、試行制限の回数を足すところで記録する。
 *                         誰の失敗か分からないときは、対象にログインの種類を残す
 * - 詳細の閲覧            個人情報を持つコーナーの詳細画面（会員・スタッフ・企業会員）
 * - CSV・PDF              CsvDownload・CsvImportと、PDFを出すコントローラー
 * - パスワードの変更      PasswordChange
 * - パスキーの登録・削除  PasskeyManagement
 * - 担当者の招待          TeamInvitationManager。送信と取り消し
 * - お知らせメールの配信停止  MailUnsubscribe。メールの中のURLからの停止。会員がいないときだけ、
 *                         メールアドレスを補足に残す
 * 一覧の表示と検索は記録しない。
 *
 * ■ 片付け
 * config('cleanup.operation_log_days')の日数を過ぎた行は、TemporaryDataCleanerが消す。
 */
final class OperationRecorder
{
    /** 更新の記録で、変わった列に数えない列。どのテーブルでも、保存のたびに変わるもの */
    private const IGNORED_FIELDS = ['created_at', 'updated_at', 'deleted_at', 'remember_token'];

    /**
     * 変わった列に数えない列の、名前の終わり。アップロードの元のファイル名の列は、
     * ファイルの列と一緒に変わるので数えない（Neobit\Core\Upload\AjaxFileUpload）
     */
    private const IGNORED_SUFFIX = '_origin';

    /**
     * 操作ログを1行書く。
     *
     * @param  Model|null  $target  操作の対象。CSVのダウンロードのように1件に決まらないときはnull
     * @param  string[]  $changedFields  更新で値が変わった列の名前
     * @param  array  $detail  種類ごとの補足。個人情報と入力値は入れない
     * @param  Model|null  $operator  操作した人。nullなら、その画面のガードでログインしている人
     * @param  class-string<Model>|null  $targetClass  対象の種類だけを残すときの、モデルのクラス。
     *                                                 $targetを渡したときは使わない
     */
    public static function record(
        OperationLogAction $action,
        ?Model $target = null,
        array $changedFields = [],
        array $detail = [],
        ?Model $operator = null,
        ?string $targetClass = null,
    ): void {
        $operator ??= Auth::user();

        // 対象の種類。1件が分かっていればその種類、種類だけ渡されたときはそのクラスの種類
        $targetType = $target?->getMorphClass();
        if ($target === null && $targetClass !== null) {
            $targetType = (new $targetClass())->getMorphClass();
        }

        // コマンドやキューから呼ばれたときは、IPアドレスも端末も無い
        $fromBrowser = ! app()->runningInConsole();

        OperationLog::create([
            'operator_type' => $operator?->getMorphClass(),
            'operator_id' => $operator?->getKey(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $target?->getKey(),
            'changed_fields' => $changedFields !== [] ? array_values($changedFields) : null,
            'detail' => $detail !== [] ? $detail : null,
            'ip' => $fromBrowser ? request()->ip() : null,
            'device' => $fromBrowser ? UserAgentLabel::of(request()->userAgent()) : null,
        ]);
    }

    /**
     * 保存で値が変わった列の名前から、操作ログに残すものだけを返す。
     * 保存のたびに変わる列と、モデルの定数OPERATION_LOG_IGNOREに書いた列を除く。
     * OPERATION_LOG_IGNOREには、最後に更新したスタッフのidのように、入力とは関係なく変わる列を書く。
     *
     * @param  string[]  $fields
     * @return string[]
     */
    public static function loggableFields(Model $record, array $fields): array
    {
        $ignored = self::IGNORED_FIELDS;

        if (defined($record::class.'::OPERATION_LOG_IGNORE')) {
            $ignored = [...$ignored, ...$record::OPERATION_LOG_IGNORE];
        }

        $fields = array_filter(
            array_unique($fields),
            fn (string $field) => ! in_array($field, $ignored, true) && ! str_ends_with($field, self::IGNORED_SUFFIX),
        );

        return array_values($fields);
    }
}
