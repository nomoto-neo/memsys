<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 入力 → 確認 → 保存、というフォームの流れの共通処理。管理画面の登録・編集と、
 * 訪問者向けのお問い合わせフォームで使っている。
 *
 * コントローラーの入口（store()・update()などルートから呼ばれるpublicメソッド）は
 * コントローラー側に残し、その中身の「検証して整形する」「確認画面用の$inputを
 * 組み立てる」「保存する」といった手順だけをこのトレイトが受け持つ。
 * 入口を残すのは、ルートとメソッドを1対1に保つためと、ルートモデルバインディング
 * （/admin/news/{news}のidからNewsを探して渡す仕組み）が、引数に具体的なモデルの型
 * （News $news）が書かれていないと働かないため。
 *
 * 確認画面を挟まないコーナー（カテゴリーなど）は、confirmInput()を使わず、store()・update()から
 * そのままsaveData()を呼べばよい。検証に失敗すれば、Laravelの標準の動きで入力画面へ戻る。
 *
 * ■ 使う側のコントローラーが用意するもの（必須）
 * - rules($record)            入力の検証ルール。$recordは新規登録ならnull、更新なら対象のモデル。
 *                             $recordを使わないコントローラーは、引数なしのrules()でよい
 *                             （PHPは、定義より多く渡された引数を黙って無視する）。
 * - saveFieldNames($validated, $record)
 *                             保存する項目（カラム名）の一覧。ここに書いた項目だけを保存する。
 * - inputFromModel($record)   モデルの今の値から、フォームに渡す$inputを組み立てる（詳細・編集用）。
 *                             詳細・編集の画面が無いコーナー（お問い合わせなど）では不要。
 *
 * ■ 必要なときだけ用意するもの（用意しなければ何もしない）
 * - defaultInput()                    新規登録フォームの初期値（項目名 => 値）。
 * - prepareInput($validated)          検証の後、確認画面の表示・保存の前に値を整形する
 *                                     （例: WYSIWYG欄のsafe_html()）。
 * - additionalFields($validated, $record)
 *                                     saveFieldNames()に加えて保存する項目（項目名 => 値）。
 *                                     入力値をそのまま使わないものを書く
 *                                     （例: ハッシュ化したパスワード、操作したスタッフのid）。
 *                                     saveFieldNames()と同じ項目があれば、こちらの値が優先される。
 * - afterSave($record, $validated)    保存の直後の処理（例: 関連テーブルのsync()）。
 * - beforeDelete($record)             削除の直前の処理（例: 関連テーブルのdetach()）。
 *
 * 「必要なときだけ」のものは、このトレイトに何もしない版を置いてある。コントローラーに
 * 同じ名前のメソッドを書くと、そちらが優先される（トレイトより、クラス自身に書いた
 * メソッドが勝つ）。
 *
 * 必須の3つはトレイト側でabstract宣言していない。abstract宣言すると、コントローラー側の
 * 引数の型をトレイトと同じModelにしなければならず（PHPの決まり）、コントローラーで
 * News $newsのように具体的な型を書けなくなるため。書き忘れた場合は、呼ばれた時点で
 * 「メソッドが無い」というエラーになる。
 *
 * ■ トランザクション
 * saveData()・deleteData()は、DBへの書き込み（本体・アップロード項目・afterSave()・
 * beforeDelete()の中の書き込みまで）をまとめて1つのトランザクションで行う。途中で
 * 例外が起きれば、どれも書き込まれなかった状態に戻る。afterSave()・beforeDelete()の
 * 中で自分でトランザクションを書く必要は無い。
 *
 * ■ アップロード（AjaxFileUpload）との関係
 * コントローラーがAjaxFileUploadも使っていれば、$inputの組み立てと保存のときに、
 * そちらの処理（ajaxUploadInput()・commitUploads()・deleteAllUploads()）も呼ぶ。
 * 使っていなければ呼ばない。コントローラー側の書き方は、アップロードの有無で変わらない。
 */
trait FormFlow
{
    /**
     * rules()から必須マークの配列を作る（中身はrequired_fields()。$alsoRequiredの意味もそちら参照）。
     * 例: 新規登録のpassword_confirmationは $this->requiredFields(null, ['password_confirmation'])
     */
    private function requiredFields(?Model $record = null, array $alsoRequired = []): array
    {
        return required_fields($this->rules($record), $alsoRequired);
    }

    /**
     * 入力値を検証し、prepareInput()で整形して返す。確認画面・保存のどちらもここを通るので、
     * 確認画面に出る値と保存される値は必ず一致する。
     */
    private function validatedInput(Request $request, ?Model $record = null): array
    {
        return $this->prepareInput($request->validate($this->rules($record)));
    }

    /**
     * 確認画面に渡す$input。表示にもhidden展開にもこれを使う。
     * アップロード項目は組み立て直した値を、入力値より優先する。
     */
    private function confirmInput(Request $request, ?Model $record = null): array
    {
        $validated = $this->validatedInput($request, $record);

        return $this->uploadInput($record, $validated) + $validated;
    }

    /**
     * 新規登録・詳細・編集の画面に渡す$input。
     *
     * $recordがnullなら新規登録で、モデルの値の代わりにdefaultInput()の初期値を使う。
     * $sourceは入力し直し中の値（確認画面から戻ったとき・入力エラーのときのold()）で、
     * あればそちらを優先する。優先順位は次のとおり。
     *   1. $source（old()）
     *   2. モデルの今の値（inputFromModel()）、新規登録ならdefaultInput()
     *   3. アップロード項目（uploadInput()が、$sourceとモデルから組み立てる）
     * 再表示したくない項目（パスワードなど）は、呼び出し側で$sourceから外して渡す。
     */
    private function formInput(?Model $record, array $source = []): array
    {
        $base = $record === null ? $this->defaultInput() : $this->inputFromModel($record);

        return $source + $base + $this->uploadInput($record, $source);
    }

    /**
     * アップロード項目の$input。AjaxFileUploadを使っていなければ空。
     */
    private function uploadInput(?Model $record, array $source = []): array
    {
        return method_exists($this, 'ajaxUploadInput') ? $this->ajaxUploadInput($record, $source) : [];
    }

    /**
     * 登録・更新の実行。新規登録は new モデル() を、更新は対象のモデルを渡す。
     */
    private function saveData(Model $record, Request $request): void
    {
        // 保存前に入力値の最終チェック（新規登録のrules()には、確認画面と同じくnullを渡す）
        $validated = $this->validatedInput($request, $record->exists ? $record : null);

        DB::transaction(fn () => $this->saveValidated($record, $validated, $request->all()));
    }

    /**
     * 検証済みの値を保存する（トランザクションは呼び出し側で始めておく）。
     * 画面からの保存（saveData()）とCSV取り込み（App\Support\CsvImport）の両方がここを通るので、
     * 保存する項目・追加で保存する項目・アップロード・保存後の処理は、どちらも同じになる。
     * save()は、モデルがまだDBに無ければINSERT、あればUPDATEを実行する。
     *
     * $input       アップロード項目の確定（commitUploads()）に渡す入力。画面からなら$request->all()。
     * $fromImport  CSV取り込みから呼ぶときtrue（アップロード項目のファイル名の変更を認める。
     *              AjaxFileUpload::commitUploads()参照）。
     */
    private function saveValidated(Model $record, array $validated, array $input, bool $fromImport = false): void
    {
        // saveFieldNames()の項目だけを入力値から取り出して保存（送られてこなかった項目はnull）。
        // additionalFields()の項目も加える（同じ項目があれば、そちらの値を優先する）。
        $attributes = [];
        foreach ($this->saveFieldNames($validated, $record) as $field) {
            $attributes[$field] = $validated[$field] ?? null;
        }

        $record->fill($this->additionalFields($validated, $record) + $attributes)->save();

        // 本データを保存した後に、アップロードしたファイルを確定
        // (※ 新規登録ではディレクトリを決めるidが必要、更新ではWYSIWYG欄の画像整理に
        //  saveで変わる前の本文を取り出して使うので、必ずsave直後に行う事)
        if (method_exists($this, 'commitUploads')) {
            $this->commitUploads($record, $input, $fromImport);
        }

        $this->afterSave($record, $validated);
    }

    /**
     * 削除の実行。関連データとアップロードファイルを先に消してから、本データを消す。
     * アップロードファイルの実物は、トランザクションが確定した後に消える（AjaxFileUpload参照）。
     */
    private function deleteData(Model $record): void
    {
        DB::transaction(function () use ($record) {
            $this->beforeDelete($record);

            if (method_exists($this, 'deleteAllUploads')) {
                $this->deleteAllUploads($record);
            }

            $record->delete();
        });
    }

    // ---- 必要なときだけコントローラーで書き換える処理（ここでは何もしない） ----

    private function defaultInput(): array
    {
        return [];
    }

    private function prepareInput(array $validated): array
    {
        return $validated;
    }

    private function additionalFields(array $validated, Model $record): array
    {
        return [];
    }

    private function afterSave(Model $record, array $validated): void
    {
    }

    private function beforeDelete(Model $record): void
    {
    }
}
