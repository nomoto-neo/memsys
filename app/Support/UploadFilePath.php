<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * アップロードしたファイルの保存先の規則と、その公開URLの組み立て。
 *
 * 規則は「モデルのクラス名（小文字のスネークケース）/ idの上位の桁 /
 * 前ゼロ付き6桁のid / ファイル名」（"public"ディスク上のパス）。
 *
 *   ニュース id=12       → news/000/000012/xxxx.jpg
 *   ニュース id=123456   → news/123/123456/xxxx.jpg
 *   ニュース id=1234567  → news/1234/1234567/xxxx.jpg
 *
 * - idを前ゼロ付き6桁にするのは、ディレクトリ名の桁が揃って、ファイル
 *   マネージャー等で一覧したときに数字の順に並ぶようにするため。
 * - その上に「下3桁を除いた部分」のディレクトリを挟むのは、1つの
 *   ディレクトリの中に何万ものサブディレクトリが並ぶのを避けるため。
 *   1つのグループディレクトリに入るのは、多くてもid 1000件分になる。
 *   idが6桁（999999）以下なら、このグループ名はちょうど「上位3桁」に
 *   なる。idが7桁以上に増えた場合は、グループ名の方が4桁・5桁と伸びて
 *   いくだけで、1グループ1000件という性質はそのまま保たれる（先頭3文字を
 *   機械的に切り出す方式にすると、id 1234567と123456が同じ"123"に入って
 *   しまい、グループの大きさが崩れる）。
 * - フィールド名（list_image・attach等）のディレクトリは作らず、1件の
 *   レコードに属するファイルは、フィールドに関係なく1つのディレクトリに
 *   並べる。ファイル名はアップロード時にLaravelが生成するランダムな名前
 *   （hashName()）なので、フィールドが違っても名前が衝突することは無い。
 *   1件分のファイルが必ず1つのディレクトリにまとまるので、レコードを
 *   削除するときはこのディレクトリごと消せばよい
 *   （AjaxFileUpload::deleteAllUploads()参照）。
 *
 * モデルのクラス名の部分は、class_basename()で名前空間を除いたクラス名
 * （App\Models\News → News）を取り出し、Str::snake()で小文字の
 * スネークケース（news）にしたもの。別のモデル（例: 会員）で同じidの
 * レコードがあっても、ここで保存先が分かれる。
 *
 * idは整数であることを前提にしている（UUIDのような文字列のidには
 * 対応していない）。
 *
 * この規則を使うのは、ファイルを保存・削除するAjaxFileUploadトレイト
 * （WYSIWYG欄の本文に埋め込んだ画像のURLの書き換え・見分けを含む）、
 * 訪問者側で保存済みファイルのURLを出すモデルのアクセサ、管理画面の
 * アップロード欄でプレビューのURLを出すupload_preview_url()の3か所。
 * 規則をここ1か所に置いているので、保存する場所と表示するURLが食い違う
 * ことが無い。
 *
 * 確認画面を経て保存されるまでの間、アップロードしたファイルを置いておく
 * 一時ディレクトリ（tmp/）の場所と、そこに置いたファイルのURLも、同じ理由で
 * このクラスが持っている。「ファイルがどこに置かれ、どのURLで見えるか」は、
 * 正式な保存先・一時ディレクトリのどちらも、このクラスだけが知っている。
 *
 * ■ ニュース専用ではない
 *
 * この規則で置いたファイルなら、どのモデルのものでも、クラス名・id・
 * フィールド名・ファイル名を渡すだけでURLを組み立てられる。
 *
 *   UploadFilePath::url(News::class, 12, 'list_image', 'abc.jpg')
 *     → /storage/news/000/000012/abc.jpg
 *   UploadFilePath::url(Member::class, 5, 'photo', 'xyz.jpg')
 *     → /uploads/member/5/photo/xyz.jpg（会員の顔写真は非公開。下の「非公開」参照）
 *
 * 別のコーナーでAjaxFileUploadを使えば、保存の時点で自動的にこの規則に
 * 従うので、表示もそのままurl()で出せる。
 *
 * ■ ログインした人だけが見られるファイル（非公開）
 *
 * 会員の顔写真のように、URLを知っているだけで誰でも見られては困るファイルは、
 * 持ち主のモデルに、そのフィールド名を並べた定数を書く。
 *
 *   public const PRIVATE_FILE_FIELDS = ['photo'];
 *
 * ここに書いたフィールドのファイルは"public"ディスクではなく"local"ディスク
 * （storage/app/private。Webサーバーから直接は見えない）に、同じ規則の
 * ディレクトリで保存される。1件のレコードに公開と非公開のフィールドがあれば、
 * 同じ名前のディレクトリが2つのディスクにできる。公開か非公開かはデータ項目の
 * 性質なので、画面（コントローラー）ではなくモデルが決める。複数のフィールド
 * （attach.*）は末尾の".*"を除いた名前、WYSIWYG欄は欄の名前を書く。
 *
 * 非公開のファイルのURLは、ファイルの置き場所ではなくルート
 * （uploads.show。App\Http\Controllers\UploadedFileController）を指す。
 * ルートのURLに入れる持ち主の種類は、AppServiceProviderの
 * Relation::enforceMorphMap()に載せた名前（'member'など）なので、
 * 非公開のフィールドを持つモデルは必ずそこに載せる。見てよいかどうかは、
 * そのモデルのPolicyの viewFiles($user, $record, $field) が決める
 * （例: App\Policies\MemberPolicy）。
 *
 * 運用を始めた後にフィールドを公開から非公開へ（または逆へ）変えるときは、
 * すでにあるファイルをディスクの間で移す必要がある（例: お問い合わせの
 * 添付ファイルを移したマイグレーション）。
 *
 * ■ 一時ディレクトリ（tmp/）
 *
 * アップロードした直後のファイルは、公開・非公開に関係なく、すべて"local"
 * ディスクのtmp/に置く。確認画面を経て保存するまでのファイルを見られるのは、
 * アップロードしたブラウザ（同じセッション）だけにするため。セッションに
 * 覚えておくファイル名のキーがTMP_SESSION_KEYで、書き込むのはAjaxFileUpload、
 * 読むのはUploadedFileController。
 *
 * 次のものは、このクラスの範囲外。
 * - この規則以外の場所に置いたファイル（例: 手作業で置いた
 *   banner/top.jpg）。Storage::disk('public')->url('banner/top.jpg')で
 *   直接出す。
 * - idが整数でないモデル。
 *
 * また、ファイルが実際に存在するかどうかは確かめない。「規則どおりなら
 * ここにあるはず」というパス・URLを計算して返すだけ。
 *
 * 状態を持たない計算だけなので、staticメソッドだけのクラスにしている。
 */
final class UploadFilePath
{
    // アップロード直後のファイルを置く一時ディレクトリ（TMP_DISK上）。
    // 確認画面を経て登録/更新が確定した時点で、正式な保存先へ移される
    // （AjaxFileUpload::commitUploads()参照）。
    public const TMP_DIR = 'tmp';

    // 一時ディレクトリを置くディスク。Webサーバーから直接は見えない"local"に置き、
    // 表示はuploads.tmpのルートから行う（このクラスの冒頭のコメント参照）。
    public const TMP_DISK = 'local';

    // アップロードしたtmpのファイル名を覚えておくセッションのキー。
    // uploads.tmpのルートは、ここに名前があるファイルだけを返す。
    public const TMP_SESSION_KEY = 'ajax_upload_tmp_files';

    // セッションに覚えておくtmpのファイル名の数の上限（古いものから忘れる）。
    public const TMP_SESSION_MAX = 50;

    // 公開のファイルと、非公開のファイル（モデルのPRIVATE_FILE_FIELDSのフィールド）を置くディスク。
    public const PUBLIC_DISK = 'public';

    public const PRIVATE_DISK = 'local';

    // 安全とみなすファイル名の形式。
    // 文字数を固定していないのは、Laravel内部の生成文字数が将来変わっても
    // 壊れないようにするため。スラッシュを一切許可しないことで、
    // ディレクトリトラバーサルの類を形式チェックの時点で防いでいる。
    // hiddenで持ち回るファイル名のバリデーション（AjaxFileUpload::
    // ajaxUploadRules()）と、previewUrl()の両方で使う。
    public const SAFE_FILENAME = '/^\w+\.\w+$/';

    /**
     * 保存先ディレクトリパスを組み立てる。$ownerClassはファイルを持っているモデルのクラス名
     * （例: News::class）、$ownerKeyはそのid。例: news/000/000012
     *
     * モデルのインスタンスではなくクラス名とidを受け取るのは、添付ファイル
     * （NewsAttachment）のように「親のid（news_id）は手元にあるが、親の
     * モデル自体は読み込んでいない」場面でも、余計なSQLを発行せずに
     * 使えるようにするため。
     */
    public static function directory(string $ownerClass, int|string $ownerKey): string
    {
        // 前ゼロ付き6桁。6桁を超えるidはそのままの桁数になる（sprintfの
        // %06dは「最低6桁」という意味で、切り詰めはしない）。
        $padded = sprintf('%06d', (int) $ownerKey);

        // 下3桁を除いた部分（6桁なら上位3桁）。
        $group = substr($padded, 0, -3);

        return Str::snake(class_basename($ownerClass)).'/'.$group.'/'.$padded;
    }

    /**
     * そのフィールドのファイルを非公開にするか（モデルのPRIVATE_FILE_FIELDSにあるか）。
     * $fieldは素のフィールド名（複数のフィールドなら".*"を除いた名前）。
     */
    public static function isPrivate(string $ownerClass, string $field): bool
    {
        $privateFields = defined($ownerClass.'::PRIVATE_FILE_FIELDS') ? constant($ownerClass.'::PRIVATE_FILE_FIELDS') : [];

        return in_array($field, $privateFields, true);
    }

    /**
     * そのフィールドのファイルを置くディスクの名前。
     */
    public static function disk(string $ownerClass, string $field): string
    {
        return self::isPrivate($ownerClass, $field) ? self::PRIVATE_DISK : self::PUBLIC_DISK;
    }

    /**
     * 保存済みファイルのURLを組み立てる。ファイル名が無い（未登録）場合や、持ち主の
     * idがまだ無い（保存前）場合はnullを返す。
     *
     * 非公開のフィールドのファイルは、uploads.showのルートのURLになる。どちらも
     * 「/」で始まるパスで、ホスト名は含めない。
     */
    public static function url(string $ownerClass, int|string|null $ownerKey, string $field, ?string $filename): ?string
    {
        if (! $filename || $ownerKey === null) {
            return null;
        }

        if (self::isPrivate($ownerClass, $field)) {
            return route('uploads.show', [
                'type' => self::morphAlias($ownerClass),
                'id' => (int) $ownerKey,
                'field' => $field,
                'filename' => $filename,
            ], false);
        }

        return Storage::disk(self::PUBLIC_DISK)->url(self::directory($ownerClass, $ownerKey).'/'.$filename);
    }

    /**
     * 保存済みファイルの、サーバー上の絶対パス（PDFに画像を埋め込む、メールに添付する
     * ときなど）。ファイル名が無い場合や、持ち主のidがまだ無い場合はnullを返す。
     */
    public static function path(string $ownerClass, int|string|null $ownerKey, string $field, ?string $filename): ?string
    {
        if (! $filename || $ownerKey === null) {
            return null;
        }

        return Storage::disk(self::disk($ownerClass, $field))->path(self::directory($ownerClass, $ownerKey).'/'.$filename);
    }

    /**
     * 一時ディレクトリ（tmp/）に置いたファイルのURL（uploads.tmpのルート）。
     * アップロード直後に画面へ返すURLと、WYSIWYG欄の本文の中からtmpの画像を
     * 見分けるときに比べるURLは、どちらもここで組み立てる（食い違うと、本文の
     * 画像が正式な保存先へ移されなくなる）。
     */
    public static function tmpUrl(string $filename): string
    {
        return route('uploads.tmp', ['filename' => $filename], false);
    }

    /**
     * 非公開のファイルのURLに入れる、持ち主の種類の名前（enforceMorphMap()の名前）。
     * 載っていなければ、URLからモデルを探せないので例外にする。
     */
    private static function morphAlias(string $ownerClass): string
    {
        $alias = Relation::getMorphAlias($ownerClass);

        if ($alias === $ownerClass) {
            throw new \LogicException("{$ownerClass}はPRIVATE_FILE_FIELDSのあるモデルなので、AppServiceProviderのenforceMorphMap()に載せてください。");
        }

        return $alias;
    }

    /**
     * 入力フォーム・確認画面・詳細画面で、アップロード欄に表示するURLを組み立てる。
     * 「この編集を確定したら、どのファイルが表示されるか」を表す。
     *
     * 次の順に判断する。
     * - $tmpあり … 今回アップロードした一時ファイルのURL
     * - $delがtrue … 「ファイルを削除する」が押された状態なので、null
     * - $ownerと$filenameがそろっている … 保存済みファイルのURL
     * - それ以外 … null
     *
     * $tmpを$delより先に見ているのは、ファイルを差し替えたとき、画面の
     * JavaScript（resources/js/ajax_upload.js）が、新しいファイルの_tmpと
     * 同時に「古いファイルを消す」印の_del=1も立てるため。_delを先に見ると、
     * 差し替えた直後のプレビューが表示されなくなる。
     *
     * $filename・$tmpはhiddenで持ち回る値で、画面の外から書き換えられる。
     * バリデーションを通る前の値（old()）で呼ばれることもあるので、ここでも
     * SAFE_FILENAMEの形式に合わないものはURLを作らずnullにしている。
     *
     * ビューからは、$inputから値を取り出す手間を省いた
     * upload_preview_url()（app/helpers.php）経由で呼ぶ。
     *
     * 訪問者側の画面で使うモデルのアクセサ（News::listImageUrl()など）とは
     * 役割が違う。あちらは「今DBに保存されているファイル」のURLで、
     * こちらは「入力中の値から見た」URL。
     */
    public static function previewUrl(?Model $owner, string $field, ?string $filename, ?string $tmp, bool $del): ?string
    {
        if ($tmp !== null && $tmp !== '') {
            return preg_match(self::SAFE_FILENAME, $tmp)
                ? self::tmpUrl($tmp)
                : null;
        }

        if ($del) {
            return null;
        }

        if ($owner === null || $filename === null || ! preg_match(self::SAFE_FILENAME, $filename)) {
            return null;
        }

        return self::url($owner::class, $owner->getKey(), $field, $filename);
    }
}
