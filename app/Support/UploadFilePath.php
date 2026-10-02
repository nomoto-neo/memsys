<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
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
 * この規則で"public"ディスクに置いたファイルなら、どのモデルのものでも、
 * クラス名・id・ファイル名を渡すだけでURLを組み立てられる。
 *
 *   UploadFilePath::url(News::class, 12, 'abc.jpg')
 *     → /storage/news/000/000012/abc.jpg
 *   UploadFilePath::url(Member::class, 5, 'xyz.jpg')
 *     → /storage/member/000/000005/xyz.jpg
 *
 * 別のコーナーでAjaxFileUploadを使えば、保存の時点で自動的にこの規則に
 * 従うので、表示もそのままurl()で出せる。
 *
 * 次のものは、このクラスの範囲外。
 * - この規則以外の場所に置いたファイル（例: 手作業で置いた
 *   banner/top.jpg）。Storage::disk('public')->url('banner/top.jpg')で
 *   直接出す。
 * - "public"ディスク以外（ログインしないと見られないファイル等）に置いた
 *   ファイル。そもそもURLで直接見せる対象ではない。
 * - idが整数でないモデル。
 *
 * また、ファイルが実際に存在するかどうかは確かめない。「規則どおりなら
 * ここにあるはず」というパス・URLを計算して返すだけ。
 *
 * 状態を持たない純粋な計算なので、staticメソッドだけのクラスにしている。
 */
final class UploadFilePath
{
    // アップロード直後のファイルを置く一時ディレクトリ（"public"ディスク上）。
    // 確認画面を経て登録/更新が確定した時点で、正式な保存先へ移される
    // （AjaxFileUpload::commitUploads()参照）。
    public const TMP_DIR = 'tmp';

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
     * 保存済みファイルの公開URLを組み立てる。ファイル名が無い（未登録）場合や、持ち主の
     * idがまだ無い（保存前）場合はnullを返す。
     */
    public static function url(string $ownerClass, int|string|null $ownerKey, ?string $filename): ?string
    {
        if (! $filename || $ownerKey === null) {
            return null;
        }

        return Storage::disk('public')->url(self::directory($ownerClass, $ownerKey).'/'.$filename);
    }

    /**
     * 一時ディレクトリ（tmp/）に置いたファイルの公開URL。アップロード直後に
     * 画面へ返すURLと、WYSIWYG欄の本文の中からtmpの画像を見分けるときに
     * 比べるURLは、どちらもここで組み立てる（食い違うと、本文の画像が
     * 正式な保存先へ移されなくなる）。
     */
    public static function tmpUrl(string $filename): string
    {
        return Storage::disk('public')->url(self::TMP_DIR.'/'.$filename);
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
    public static function previewUrl(?Model $owner, ?string $filename, ?string $tmp, bool $del): ?string
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

        return self::url($owner::class, $owner->getKey(), $filename);
    }
}
