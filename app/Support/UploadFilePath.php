<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * アップロードしたファイルの保存先の規則と、そのURLの組み立て。保存する場所と表示するURLが
 * 食い違わないよう、規則はこのクラスだけが持つ。使うのは保存と削除をするAjaxFileUpload、
 * モデルのURLのアクセサ、画面のupload_preview_url()。
 *
 * ■ 保存先の規則
 * 「モデルのクラス名 / idの下3桁を除いた部分 / 前ゼロ付き6桁のid / ファイル名」。
 *
 *   ニュース id=12       → news/000/000012/xxxx.jpg
 *   ニュース id=1234567  → news/1234/1234567/xxxx.jpg
 *
 * - idを6桁にそろえるのは、ディレクトリを並べたときに番号の順になるようにするため
 * - 下3桁を除いたディレクトリを挟むのは、1つのディレクトリに入るのを1000件までにするため。
 *   idが7桁を超えてもこの部分が伸びるだけで、1000件ずつにまとまる
 * - フィールドごとのディレクトリは作らず、1件分のファイルは1つのディレクトリに置く。
 *   ファイル名はランダムなので重ならず、レコードを消すときはディレクトリごと消せる
 * - クラス名を含めるので、会員とニュースで同じidがあっても保存先は分かれる
 * - idは整数が前提。文字列のidには対応しない
 *
 * ■ ログインした人だけが見られる非公開のファイル
 * URLを知っているだけで見られては困るファイルは、持ち主のモデルにそのフィールドを並べる。
 *
 *   public const PRIVATE_FILE_FIELDS = ['photo'];
 *
 * 公開か非公開かはデータの性質なので、モデルが決める。複数のフィールドは「.*」を除いた名前、
 * エディタの欄は欄の名前を書く。非公開のファイルは、Webサーバーから直接は見えない
 * storage/app/privateに同じ規則のディレクトリで置く。
 * URLはファイルの場所ではなく、UploadedFileControllerのuploads.showのルートになる。
 * URLに入れる持ち主の種類はenforceMorphMap()の名前なので、そのモデルは必ずそこに載せる。
 * 見てよいかは、そのモデルのPolicyのviewFiles($user, $record, $field)が決める。
 * 運用を始めた後に公開と非公開を入れ替えるときは、すでにあるファイルを移すマイグレーションが要る。
 *
 *   公開のフィールド
 *   UploadFilePath::url(News::class, 12, 'list_image', 'abc.jpg')
 *     → /storage/news/000/000012/abc.jpg
 *   非公開のフィールド
 *   UploadFilePath::url(Member::class, 5, 'photo', 'xyz.jpg')
 *     → /uploads/member/5/photo/xyz.jpg
 *
 * ■ 一時ディレクトリ
 * アップロードした直後のファイルは、公開か非公開かに関係なくstorage/app/private/tmpに置く。
 * 保存するまでのファイルは、アップロードしたブラウザだけが見られるようにするため。
 * そのファイル名を覚えておくセッションのキーがTMP_SESSION_KEYで、書くのはAjaxFileUpload、
 * 読むのはUploadedFileController。
 *
 * 手で置いたファイルなど、この規則の外にあるものは扱わない。ファイルが本当にあるかも
 * 確かめず、規則どおりのパスとURLを計算するだけ。
 */
final class UploadFilePath
{
    /**
     * アップロード直後のファイルを置く、TMP_DISKの中の一時ディレクトリ。
     * 確認画面を経て登録か更新が確定したときに、AjaxFileUpload::commitUploads()が正式な保存先へ移す
     */
    public const TMP_DIR = 'tmp';

    /**
     * 一時ディレクトリを置くディスク。Webサーバーから直接は見えない"local"に置き、
     * 表示はuploads.tmpのルートから行う
     */
    public const TMP_DISK = 'local';

    /**
     * アップロードした一時ファイルの名前を覚えておくセッションのキー。
     * uploads.tmpのルートはここに名前があるファイルだけを返す
     */
    public const TMP_SESSION_KEY = 'ajax_upload_tmp_files';

    /** セッションに覚えておく一時ファイルの名前の数の上限。超えたら古いものから忘れる */
    public const TMP_SESSION_MAX = 50;

    /** 公開のファイルと、モデルのPRIVATE_FILE_FIELDSにある非公開のファイルを置くディスク */
    public const PUBLIC_DISK = 'public';

    public const PRIVATE_DISK = 'local';

    /**
     * 安全とみなすファイル名の形。hiddenで持ち回るファイル名を確かめる
     * AjaxFileUpload::ajaxUploadRules()と、previewUrl()の両方で使う。
     * 文字数を決めていないのは、Laravelが作る名前の長さが将来変わっても壊れないようにするため。
     * 「/」を許さないので、ほかのディレクトリを指す名前は形を確かめた時点で弾ける
     */
    public const SAFE_FILENAME = '/^\w+\.\w+$/';

    /**
     * 保存先のディレクトリを組み立てる。例：news/000/000012
     * $ownerClassはファイルを持っているモデルのクラス名で、$ownerKeyはそのid。
     *
     * モデルではなくクラス名とidを受け取るのは、NewsAttachmentのように親のidは手元にあるが
     * 親のモデルは読み込んでいない場面でも、余計なSQLを出さずに使えるようにするため。
     */
    public static function directory(string $ownerClass, int|string $ownerKey): string
    {
        // 前ゼロ付き6桁。sprintfの%06dは最低6桁という意味で、6桁を超えるidは切り詰めずにそのままの桁数になる
        $padded = sprintf('%06d', (int) $ownerKey);

        // 下3桁を除いた部分。6桁なら上の3桁
        $group = substr($padded, 0, -3);

        return Str::snake(class_basename($ownerClass)).'/'.$group.'/'.$padded;
    }

    /**
     * そのフィールドのファイルを非公開にするか。モデルのPRIVATE_FILE_FIELDSにあれば非公開。
     * $fieldは素のフィールド名で、複数のフィールドなら".*"を除いた名前
     */
    public static function isPrivate(string $ownerClass, string $field): bool
    {
        $privateFields = defined($ownerClass.'::PRIVATE_FILE_FIELDS') ? constant($ownerClass.'::PRIVATE_FILE_FIELDS') : [];

        return in_array($field, $privateFields, true);
    }

    /** そのフィールドのファイルを置くディスクの名前。 */
    public static function disk(string $ownerClass, string $field): string
    {
        return self::isPrivate($ownerClass, $field) ? self::PRIVATE_DISK : self::PUBLIC_DISK;
    }

    /**
     * 保存したファイルのURLを組み立てる。ファイルが無いときや、保存する前で持ち主の
     * idがまだ無いときはnullを返す。
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
     * 保存したファイルのサーバー上の絶対パス。PDFに画像を埋め込むときやメールに添付するときに使う。
     * ファイル名が無いときや持ち主のidがまだ無いときはnullを返す。
     */
    public static function path(string $ownerClass, int|string|null $ownerKey, string $field, ?string $filename): ?string
    {
        if (! $filename || $ownerKey === null) {
            return null;
        }

        return Storage::disk(self::disk($ownerClass, $field))->path(self::directory($ownerClass, $ownerKey).'/'.$filename);
    }

    /**
     * 一時ディレクトリに置いたファイルの、uploads.tmpのルートのURL。
     * アップロード直後に画面へ返すURLと、エディタの本文から一時ファイルの画像を見分けるときに
     * 比べるURLは、どちらもここで組み立てる。食い違うと本文の画像が正式な保存先へ移されなくなるため。
     */
    public static function tmpUrl(string $filename): string
    {
        return route('uploads.tmp', ['filename' => $filename], false);
    }

    /**
     * 非公開のファイルのURLに入れる持ち主の種類の名前で、enforceMorphMap()に載せた名前。
     * 載っていなければURLからモデルを探せないので、例外にする。
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
     * 入力フォーム・確認画面・詳細画面で、アップロードの欄に表示するURLを組み立てる。
     * この編集を確定したらどのファイルが表示されるかを表す。
     *
     * 次の順に判断する。
     * - $tmpがある … 今回アップロードした一時ファイルのURL
     * - $delがtrue … 「ファイルを削除する」が押された状態なので、null
     * - $ownerと$filenameがそろっている … 保存したファイルのURL
     * - それ以外 … null
     *
     * $tmpを$delより先に見ているのは、ファイルを差し替えたときに、resources/js/ajax_upload.jsが
     * 新しいファイルの_tmpと一緒に、古いファイルを消す印の_del=1も立てるため。_delを先に見ると
     * 差し替えた直後のプレビューが表示されなくなる。
     *
     * $filenameと$tmpはhiddenで持ち回る値で、画面の外から書き換えられる。検証を通る前の
     * old()の値で呼ばれることもあるので、ここでもSAFE_FILENAMEの形に合わないものはURLを作らずnullにする。
     *
     * ビューからは、$inputから値を取り出す手間を省いたapp/helpers.phpのupload_preview_url()で呼ぶ。
     *
     * 訪問者の画面で使うNews::listImageUrl()のようなモデルのアクセサとは役割が違う。
     * あちらは今DBに保存されているファイルのURLで、こちらは入力中の値から見たURL。
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
