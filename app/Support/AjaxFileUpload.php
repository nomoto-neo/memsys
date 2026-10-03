<?php

namespace App\Support;

use App\Rules\SameCountAsRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * 画像・添付ファイルの「確認画面をはさむAjaxアップロード」の仕組みをまとめたトレイト。
 *
 * 流れの全体像:
 * 1. 画面上でファイルが選択(またはドロップ)されると、その場でajaxUploadRoute()
 *    (このトレイトのuploadAjaxFile()につながるルート)へ1ファイルだけPOSTする。
 * 2. サーバー側はファイル種別を検証し、画像なら必要に応じてリサイズしてから
 *    "local"ディスクのtmp/へランダムなファイル名で保存し、そのファイル名と
 *    元のファイル名をJSONで返す。この時点ではまだDBには何も書き込まない
 *    (対象のレコードがまだ無い=新規登録時にはidが決まっていないため)。
 *    tmpのファイルは、アップロードしたセッションからだけ見られる
 *    (App\Support\UploadFilePathの「一時ディレクトリ」参照)。
 * 3. 画面はhiddenの各フィールド(name_tmp・name_origin・name_del)にその情報を
 *    詰めて、確認画面を経て実際の登録/更新が行われる。
 * 4. store()/update()側で対象レコードの保存が終わり、idが確定した後に
 *    commitUploads()を呼ぶ。ここで初めて、tmp/から正式なディレクトリへ
 *    ファイルを移動し、DBのカラム(または子テーブル)へ書き込む。
 *    正式な保存先のディスクはフィールドで決まる。普通は"public"、
 *    持ち主のモデルのPRIVATE_FILE_FIELDSにあるフィールドは"local"
 *    (ログインした人だけが見られる。UploadFilePathの「非公開」参照)。
 *
 * 使う側のコントローラーが用意するもの:
 * - private const UPLOAD_FILES  ['フィールド名' => 横幅(px), ...]。
 *   キーの末尾が".*"なら複数展開されるフィールド(例: 'attach.*')。
 *   横幅が0なら添付ファイル(リサイズしない・ALLOW_ATTACH_TYPESで検証)、
 *   0以外なら画像(その横幅を超えていたら縮小・ALLOW_IMAGE_TYPESで検証)。
 *   横幅はデータ項目の仕様なので、モデルの定数を参照させるとよい
 *   (例: 'list_image' => News::LIST_IMAGE_WIDTH)。
 * - 複数展開フィールドを使う場合、対象のEloquentモデルに、末尾の".*"を
 *   除いた名前と同じ名前のHasManyリレーション(例: News::attach())を
 *   用意しておくこと。トレイト側はテーブル名や外部キーの詳細を一切知らず、
 *   このリレーション経由でのみ複数展開フィールドを操作する。
 * - ルーティングで、このトレイトのuploadAjaxFile()を指すPOSTルートを
 *   1本用意すること(例: admin.news.ajaxUpload)。
 * - (省略可) private const WYSIWYG_FIELDS  ['フィールド名' => 横幅(px), ...]。
 *   画像を埋め込めるWYSIWYGエディタの欄。下の「WYSIWYG欄の画像」参照。
 *
 * ■ WYSIWYG欄の画像
 *
 * WYSIWYG_FIELDSに書いた欄(例: body)では、エディタに挿入した画像も、
 * 一覧用画像などと同じ流れで扱う。
 *
 * 1. エディタで画像を挿入すると、uploadAjaxFile()へfield=フィールド名で
 *    POSTされ、tmp/に保存(リサイズ)される。エディタは返ってきたtmpの
 *    URLを<img src>にして本文に入れる。
 * 2. 確認画面・「戻る」の間は、本文のHTMLの中にtmpのURLが入ったまま
 *    hiddenで持ち回られる(画像のための専用のhiddenは無い)。
 * 3. commitUploads()で、本文の<img>のうちtmpを指しているものを正式な
 *    保存先へ移し、srcを正式なURLに書き換えて保存し直す。
 *
 * 本文に保存するのは画像のURLそのもの(例: /storage/news/000/000012/xxxx.jpg)。
 * このトレイトが扱うのは、srcが「tmpのURL」か「このレコードの保存先の
 * URL」に完全に一致する画像だけで、他の記事の画像・サーバーに置いた
 * 静的な画像・外部サイトの画像などは、移動も削除もせずそのままにする。
 *
 * 本文の中の<img>は正規表現(IMG_SRC_PATTERN)で探す。対象は必ず
 * HtmlSanitizer(HTML Purifier)を通した後のHTMLで、属性の値は常に"で
 * 囲まれ、値の中の"は&quot;に置き換えられているので、<img>の中の
 * どの位置にsrcがあっても、この形に限定したパターンで取りこぼしなく
 * 拾える。書き換えるのは一致したsrcの値だけで、本文のそれ以外の部分は
 * 1文字も変えない。
 *
 * 本文そのものは、コントローラーが他の項目と一緒にcreate()/update()で
 * 保存する(WYSIWYG欄だけ書き方を変えなくてよいように)。そのため
 * commitUploads()の時点では本文はすでに新しい内容になっていて、
 * 更新前の本文はEloquentのgetPrevious()(直前の保存で変わった項目の、
 * 変更前の値)から取り出す。これが正しく取れるように、コントローラーでは
 * 次の順序を守ること。
 *
 *   $news->update([...]);                 // 1. 本体の保存
 *   $this->commitUploads($news, ...);     // 2. すぐにファイルの確定
 *   // 関連テーブルの更新はここから        // 3. カテゴリー等はその後
 *
 * 1と2の間で同じモデルを保存し直す(update()・save()・touch()など)と、
 * 更新前の本文が分からなくなる。その場合も、本文から外された画像が
 * 削除されずに残るだけで、使っている画像が消えることは無い。
 *
 * 許可する拡張子(ALLOW_IMAGE_TYPES・ALLOW_ATTACH_TYPES)は、コーナーごとに
 * 変えるべきものではなく、サイト全体で共通のセキュリティ方針なので、
 * このトレイト自身が持つ固定値としている(使う側のコントローラーからは
 * 上書きできない)。
 *
 * 画面へ渡す$inputと、プレビューURLについて:
 * このプロジェクト全体の規約として、コントローラーが画面へ渡す$inputには、
 * 実際にフォームから送信される（＝次の画面へhiddenで持ち越す）項目だけを
 * 入れ、表示専用の値は1つも混ぜない。確認画面のhidden展開
 * (_confirm_hidden)のように「$inputを丸ごと機械的に展開する」汎用処理を、
 * 安全に書けるようにするため。
 *
 * このトレイトのajaxUploadInput()は、それに合わせて{field}・{field}_origin・
 * {field}_tmp・{field}_del（複数展開フィールドは同名の並行配列）だけを返す。
 * 呼び出し側は次の形になる（createの例）。
 *
 *   $input = old() + [...] + $this->ajaxUploadInput(null, old());
 *
 * アップロード欄に表示するプレビューのURLは、コントローラーでは作らない。
 * ビュー（_ajax_upload_block）の中でupload_preview_url()を呼び、$inputの
 * 値からその場で求める。都道府県の名称をcode_table()で引くのと同じ考え方。
 *
 * _fields.blade.php側は$readonlyの値に応じて
 * _ajax_upload_block/_ajax_upload_groupへ$readonlyを渡すだけで、
 * 入力用UIと表示専用プレビューが自動的に切り替わる（詳しくは
 * resources/views/admin/_ajax_upload_block.blade.phpのコメント参照）。
 */
trait AjaxFileUpload
{
    private const ALLOW_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    private const ALLOW_ATTACH_TYPES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'zip'];

    // アップロードファイル1件あたりの上限(KB)。必要に応じて見直すこと。
    private const MAX_UPLOAD_KB = 10240;

    // 画像1枚あたりの画素数（横×縦）の上限。4000万画素は、たとえば
    // 8000×5000程度。スマートフォンの通常の写真（1200万画素前後）は十分
    // 収まる。サーバーのメモリに余裕があっても、これを超える画像は受け付けない
    // （checkImagePixels()参照）。
    private const MAX_IMAGE_PIXELS = 40_000_000;

    // GDが画像を展開したときに使う、1画素あたりのおおよそのメモリ量（バイト）。
    // GDのフルカラー画像は1画素を4バイトの整数で持つ。
    private const GD_BYTES_PER_PIXEL = 4;

    // WYSIWYG欄の本文(HtmlSanitizerを通した後)から<img>のsrcを探すパターン。
    // 1: srcの値の直前まで 2: srcの値 3: 閉じの"
    // "\ssrc"と直前に空白を求めているのは、data-srcのような別の属性に
    // 一致させないため(HtmlSanitizerはdata-〜属性を残さないが念のため)。
    private const IMG_SRC_PATTERN = '/(<img\b[^>]*?\ssrc=")([^"]*)(")/i';

    // .tmpに置いたままにしてよい最大時間。これを超えたファイルは、
    // 次に誰かが何かをアップロードしたタイミングでまとめて掃除される。
    private const TMP_MAX_AGE_HOURS = 36;

    // 一時ディレクトリの名前（tmp）と、hiddenで持ち回るファイル名の形式
    // （SAFE_FILENAME）は、App\Support\UploadFilePathが持っている。プレビュー用の
    // URLを組み立てるUploadFilePath::previewUrl()も同じ値を使うので、1か所に
    // まとめている。

    /**
     * Ajaxアップロード処理本体。
     *
     * 1回のPOSTにつき1ファイル。どのUPLOAD_FILESフィールド宛かは、
     * リクエストの'field'パラメータ(末尾の".*"や"[]"を含まない、
     * 素のフィールド名)で判定する。
     */
    public function uploadAjaxFile(Request $request): JsonResponse
    {
        $field = (string) $request->input('field');
        $config = $this->resolveUploadFieldConfig($field);

        if ($config === null) {
            // UPLOAD_FILESに定義の無いfield名が来た場合(実装ミス・改ざん)。
            Log::warning('AjaxFileUpload: 未定義のfieldが指定されました。', ['field' => $field]);

            return response()->json(['message' => 'アップロードに失敗しました。'], 422);
        }

        [$width, $allowTypes] = $config;

        if (! $request->hasFile('file')) {
            return response()->json(['message' => 'アップロードに失敗しました。'], 422);
        }

        $file = $request->file('file');

        if (! $file->isValid()) {
            // isValid()がfalseになるのは、PHP自身がupload_max_filesizeなどの
            // 制限で弾いたケース。Laravelのバリデーションより手前の話。
            Log::warning('AjaxFileUpload: アップロードが無効です。', [
                'field' => $field,
                'error' => $file->getErrorMessage(),
            ]);

            return response()->json(['message' => 'アップロードに失敗しました。'.$file->getErrorMessage()], 422);
        }

        // ファイル種別の検証は、Laravelの標準的なmimesルールをそのまま
        // 使う(拡張子だけでなく、実際のファイル内容から判定したMIMEタイプが
        // 一致するかまで検証してくれる)。
        $validator = Validator::make(
            ['file' => $file],
            ['file' => ['required', 'file', 'mimes:'.implode(',', $allowTypes), 'max:'.self::MAX_UPLOAD_KB]]
        );

        if ($validator->fails()) {
            Log::warning('AjaxFileUpload: 許可されていないファイルです。', [
                'field' => $field,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'errors' => $validator->errors()->all(),
            ]);

            return response()->json(['message' => '許可されたファイルタイプではありません。'], 422);
        }

        // 画像は、保存・リサイズする前に画素数を確認する（理由は
        // checkImagePixels()のコメント参照）。
        if ($width > 0) {
            $pixelError = $this->checkImagePixels($file->getRealPath(), $field);

            if ($pixelError !== null) {
                return response()->json(['message' => $pixelError], 422);
            }
        }

        $originalName = $file->getClientOriginalName();

        try {
            $this->cleanupTmpDirectory();

            $tmpName = $file->hashName();
            $storedPath = $file->storeAs(UploadFilePath::TMP_DIR, $tmpName, UploadFilePath::TMP_DISK);

            if ($storedPath === false) {
                throw new \RuntimeException('storeAs()に失敗しました。');
            }

            if ($width > 0) {
                $this->resizeIfNeeded(Storage::disk(UploadFilePath::TMP_DISK)->path($storedPath), $width);
            }

            // tmpのファイルを見られるのは、アップロードしたセッションだけにする
            // （UploadedFileController::tmp()がこの一覧で確かめる）。
            $request->session()->put(UploadFilePath::TMP_SESSION_KEY, array_slice(
                [...$request->session()->get(UploadFilePath::TMP_SESSION_KEY, []), $tmpName],
                -UploadFilePath::TMP_SESSION_MAX
            ));
        } catch (\Throwable $e) {
            Log::error('AjaxFileUpload: 保存処理に失敗しました。', [
                'field' => $field,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'アップロードに失敗しました。'], 500);
        }

        return response()->json([
            'tmp_name' => $tmpName,
            'origin_name' => $originalName,
            'url' => UploadFilePath::tmpUrl($tmpName),
        ]);
    }

    /**
     * 画像の画素数を、GDで展開する前に確認する。問題があれば利用者向けの
     * エラーメッセージを、無ければnullを返す。
     *
     * ファイルサイズが小さくても、縦横の画素数が極端に大きい画像（いわゆる
     * 解凍爆弾）を送られると、resizeIfNeeded()でGDが画像を展開した時点で
     * 「画素数×4バイト」のメモリを確保しようとして、PHPのメモリの上限
     * （memory_limit）を超え、処理が異常終了する。getimagesize()は
     * ファイルの先頭にある寸法の情報を読むだけで画像を展開しないので、
     * 展開の前にここで弾ける。
     *
     * 判定は2段階。
     *
     * 1. MAX_IMAGE_PIXELSを超えていたら、メモリに関係なく断る
     * 2. 展開に必要なメモリの見込みが、残りのメモリに収まらなければ断る。
     *    スマートフォンで縦向きに撮った写真は、EXIFの回転補正で同じ大きさの
     *    画像をもう1枚作るので、見込みは「画素数×4バイト×2枚分」にしている。
     *    memory_limitが無制限（-1）の環境では、この判定は行わない
     *
     * 2段目があるので、同じ画像でもmemory_limitの設定が違う環境（開発機と
     * 本番機など）では結果が変わりうる。PHPの既定のmemory_limitは128Mで、
     * このときは縦向きの1200万画素の写真でぎりぎりになるので、本番では
     * 256M以上にしておくことをおすすめする。どちらの理由で断ったかは
     * ログに残す。
     */
    private function checkImagePixels(string $path, string $field): ?string
    {
        $info = @getimagesize($path);

        if ($info === false) {
            Log::warning('AjaxFileUpload: 画像の寸法を読み取れませんでした。', ['field' => $field]);

            return '画像ファイルを読み込めませんでした。';
        }

        [$imageWidth, $imageHeight] = $info;
        $pixels = $imageWidth * $imageHeight;

        $message = sprintf(
            '画像の画素数が大きすぎます（%d×%d）。縦横のサイズを小さくしてからアップロードしてください。',
            $imageWidth,
            $imageHeight
        );

        if ($pixels > self::MAX_IMAGE_PIXELS) {
            Log::warning('AjaxFileUpload: 画像の画素数が上限を超えています。', [
                'field' => $field,
                'pixels' => $pixels,
                'max_pixels' => self::MAX_IMAGE_PIXELS,
            ]);

            return $message;
        }

        // ini_parse_quantity()は"128M"のような書き方をバイト数に直す
        // PHP 8.2以降の標準関数。無制限（-1）なら-1が返る。
        $memoryLimit = ini_parse_quantity((string) ini_get('memory_limit'));

        if ($memoryLimit > 0) {
            $needed = $pixels * self::GD_BYTES_PER_PIXEL * 2;

            if (memory_get_usage() + $needed > $memoryLimit) {
                Log::warning('AjaxFileUpload: 画像の展開に必要なメモリが足りない見込みです。', [
                    'field' => $field,
                    'pixels' => $pixels,
                    'needed_bytes' => $needed,
                    'memory_limit' => ini_get('memory_limit'),
                ]);

                return $message;
            }
        }

        return null;
    }

    /**
     * 指定フィールドの設定(横幅・許可タイプ)を引く。$fieldは素の
     * フィールド名(例: 'list_image'・'attach'・'body')。定義に無ければnull。
     */
    private function resolveUploadFieldConfig(string $field): ?array
    {
        foreach ($this->uploadFieldDefinitions() as $def) {
            if ($def['field'] !== $field) {
                continue;
            }

            $width = $def['width'];
            $allowTypes = $width > 0 ? self::ALLOW_IMAGE_TYPES : self::ALLOW_ATTACH_TYPES;

            return [$width, $allowTypes];
        }

        return null;
    }

    /**
     * UPLOAD_FILESとWYSIWYG_FIELDSを解析して、["field" => 素のフィールド名,
     * "kind" => 種類, "width" => 横幅(px)]の配列にして返す。種類は次の3つ。
     *
     * - 'single'      UPLOAD_FILESの単数フィールド(例: list_image)
     * - 'repeatable'  UPLOAD_FILESの".*"付きのフィールド(例: attach)
     * - 'wysiwyg'     WYSIWYG_FIELDSのフィールド(例: body)
     *
     * このトレイトの他のメソッド(ajaxUploadRules・ajaxUploadInput・
     * commitUploads・deleteAllUploads等)は、2つの定数を自分で解析せず、
     * 必ずこのメソッドの結果を使う。解析の方法をここ1か所に置いておけば、
     * 定数の書式を変えたときも、ここを直すだけで済む。
     *
     * WYSIWYG_FIELDSは省略できる(WYSIWYG欄の無いコントローラーは定義
     * しなくてよい)ので、defined()で有無を確かめてから読む。
     *
     * このトレイト内の他のメソッドだけが使う内部ヘルパー（確認画面の
     * hidden展開は_confirm_hiddenが$inputから直接組み立てるので、こちらを
     * 経由しない。詳しくはこのファイル冒頭のコメント参照）。
     */
    private function uploadFieldDefinitions(): array
    {
        $fields = [];

        foreach (self::UPLOAD_FILES as $rawField => $width) {
            $isRepeatable = str_ends_with($rawField, '.*');

            $fields[] = [
                'field' => $isRepeatable ? substr($rawField, 0, -2) : $rawField,
                'kind' => $isRepeatable ? 'repeatable' : 'single',
                'width' => $width,
            ];
        }

        $wysiwygFields = defined('self::WYSIWYG_FIELDS') ? self::WYSIWYG_FIELDS : [];

        foreach ($wysiwygFields as $field => $width) {
            $fields[] = [
                'field' => $field,
                'kind' => 'wysiwyg',
                'width' => $width,
            ];
        }

        return $fields;
    }

    /**
     * .tmp配下の古いファイルを削除する。アップロードのたびに毎回チェックする
     * ことで、専用のバッチ処理を別途組まなくても、放置されたファイルが
     * 際限なく溜まり続けることを防ぐ。
     */
    private function cleanupTmpDirectory(): void
    {
        $disk = Storage::disk(UploadFilePath::TMP_DISK);
        $cutoff = now()->subHours(self::TMP_MAX_AGE_HOURS)->timestamp;

        foreach ($disk->files(UploadFilePath::TMP_DIR) as $path) {
            if ($disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
            }
        }
    }

    /**
     * 指定した横幅を超えている画像だけ縮小する。指定サイズ以下の画像は
     * そのまま(拡大はしない)。
     *
     * PHP標準のGD拡張だけで実装しているが、1点だけ通常のGDの単純な
     * リサイズでは抜け落ちる「スマートフォン写真のEXIF回転情報」を
     * 明示的に読んで補正している。これをやらないと、縦向きに撮った
     * 写真がリサイズ後に横倒しで表示される、という定番の不具合が起きる。
     */
    private function resizeIfNeeded(string $absolutePath, int $maxWidth): void
    {
        $imageInfo = @getimagesize($absolutePath);

        if ($imageInfo === false) {
            return;
        }

        [$width, $height, $type] = $imageInfo;

        $orientation = null;

        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($absolutePath);
            $orientation = $exif['Orientation'] ?? null;
        }

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($absolutePath),
            default => null,
        };

        if (! $image) {
            return;
        }

        if ($orientation && $orientation !== 1) {
            $image = $this->applyExifOrientation($image, (int) $orientation);
            $width = imagesx($image);
            $height = imagesy($image);
        }

        if ($width <= $maxWidth) {
            // 縮小は不要でも、回転補正だけは反映させて保存し直す。
            if ($orientation && $orientation !== 1) {
                $this->saveImage($image, $absolutePath, $type);
            }
            imagedestroy($image);

            return;
        }

        $newHeight = (int) round($height * ($maxWidth / $width));
        $resized = imagecreatetruecolor($maxWidth, $newHeight);

        // PNG・WebPの透過を保持する。
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);

        $this->saveImage($resized, $absolutePath, $type);

        imagedestroy($image);
        imagedestroy($resized);
    }

    private function applyExifOrientation($image, int $orientation)
    {
        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function saveImage($image, string $path, int $type): void
    {
        match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, 85),
            IMAGETYPE_PNG => imagepng($image, $path),
            IMAGETYPE_WEBP => imagewebp($image, $path, 85),
            default => null,
        };
    }

    /**
     * UPLOAD_FILESを元に、確認画面用のバリデーションルールを組み立てる。
     * (WYSIWYG_FIELDSの欄には、追加するルールは無い。)
     * 呼び出し側のrules()から、戻り値をそのまま+演算子でマージして使う。
     */
    public function ajaxUploadRules(): array
    {
        $rules = [];

        foreach ($this->uploadFieldDefinitions() as $def) {
            // WYSIWYG欄の本文そのもののルールは、呼び出し側のrules()に書く
            // (hiddenの類は無いので、ここで足すものは無い)。
            if ($def['kind'] === 'wysiwyg') {
                continue;
            }

            $base = $def['field'];
            $isRepeatable = $def['kind'] === 'repeatable';
            $suffix = $isRepeatable ? '.*' : '';

            $rules["{$base}{$suffix}"] = ['nullable', 'regex:'.UploadFilePath::SAFE_FILENAME];
            $rules["{$base}_tmp{$suffix}"] = ['nullable', 'regex:'.UploadFilePath::SAFE_FILENAME];
            $rules["{$base}_origin{$suffix}"] = ['nullable', 'string', 'max:255'];
            $rules["{$base}_del{$suffix}"] = ['nullable', 'in:0,1'];

            if ($isRepeatable) {
                // 配列本体としての形も検証しておく(文字列などを直接
                // 送りつけられても、ここで弾ける)。
                //
                // 4本の配列は.ajax_upload_blockという単位でまとめて増減させて
                // いるので、画面から普通に操作していれば要素数は必ずそろう。
                // そろっていなければ、hiddenが書き換えられるなどした想定外の
                // リクエストなので、SameCountAsRuleで弾く。4本それぞれに
                // 「他の3本と同じ要素数か」を付けているのは、どれか1本だけが
                // 送られてこなかった場合（要素数0扱い）も見逃さないため。
                $keys = [$base, "{$base}_tmp", "{$base}_origin", "{$base}_del"];

                foreach ($keys as $key) {
                    $others = array_values(array_diff($keys, [$key]));
                    $rules[$key] = ['array', new SameCountAsRule($others)];
                }
            }
        }

        return $rules;
    }

    /**
     * 対象レコードの保存(create/update)が終わり、idが確定した後に呼ぶ。
     *
     * $inputは$request->all()相当(バリデーション済みかどうかは問わない。
     * 各フィールドの値の形式自体はrules()側で既に検証済みの前提)。
     *
     * ■ 物理ファイルの削除は「DBの更新前後の差分」で決める
     *
     * hiddenで届く値（既存のファイル名・_delフラグ）は、画面の側で
     * 書き換えられる可能性がある。それをそのまま信用して「このファイルを
     * 消す」と判断すると、1件分のファイルは同じディレクトリに並んでいる
     * ので、書き換えによって同じ記事の別のフィールドのファイル（例: 一覧用
     * 画像の欄から添付ファイル）を消せてしまう。
     *
     * そこで、次の3段階にしている。
     *
     * 1. 更新前に、このレコードがDB上で参照しているファイル名の一覧を取る
     *    （storedFilenames()。フィールドをまたいだレコード全体の一覧）
     * 2. 各フィールドの確定処理で、DBの値だけを更新する（ここでは物理
     *    ファイルは消さない）。hiddenで届いた既存のファイル名は、その
     *    フィールドのDBの現在の値と一致する場合だけ採用し、一致しなければ
     *    「既存のファイルは無い」として扱う
     * 3. 更新後にもう一度一覧を取り、更新前にはあって更新後に無くなった
     *    ファイル名だけを物理削除する
     *
     * 削除の判断材料が、書き換えられないDBの値だけになるので、hiddenを
     * どう書き換えても「このレコードがもう参照しなくなったファイル」以外は
     * 消えない。
     *
     * ■ 物理ファイルの削除は、トランザクションが確定した後に行う
     *
     * 呼び出し側（FormFlow::saveData()など）はトランザクションの中でこのメソッドを
     * 呼ぶ。3の物理削除をその場で行うと、後の処理で例外が起きてDBの更新が
     * 取り消されたときに、元に戻ったDBが参照しているファイルだけが消えてしまう。
     * そこで3はDB::afterCommit()に渡し、トランザクションが確定してから消す
     * （トランザクションの外で呼ばれた場合は、その場で消える）。
     * tmpから正式な保存先への移動（moveTmpToFinal()）は、DBに書き込むファイル名を
     * 決めるためにその場で行う。取り消されたときは、どこからも参照されない
     * ファイルが保存先に残るだけで、表示が壊れることは無い。
     *
     * WYSIWYG欄は、本文をコントローラーがすでに保存しているので、
     * 1の「更新前」の本文はgetPrevious()から取り出す（このファイル冒頭の
     * 「WYSIWYG欄の画像」参照。呼び出し側は、本体のcreate()/update()の
     * 直後にこのメソッドを呼ぶこと）。
     *
     * ■ 入力にキーが無い項目は何もしない
     *
     * 単数の項目は{field}・{field}_tmp・{field}_origin・{field}_delの4つ、
     * 複数の項目は{field}のキーが$inputに1つも無ければ、その項目のファイルは
     * 今のまま触らない。画面のフォームは4つのhiddenを必ず送るので、画面からの
     * 登録・更新の動きは変わらない。CSV取り込み（App\Support\CsvImport）で、
     * CSVにアップロードの列が無いときに、既存のファイルを消さないため。
     *
     * ■ CSV取り込みから呼ぶとき（$fromImport = true）
     *
     * 画面からの登録では、hiddenで届いたファイル名はDBの今の値と一致するときだけ
     * 採用する（hiddenは書き換えられるため）。CSV取り込みでは、バッチ処理などで
     * 実ファイルの名前を変えた後に、新しい名前をCSVで反映できるようにするため、
     * DBの値と違うファイル名も採用する。そのファイル名が安全な形式で、この
     * レコードの保存先に実在し、項目の種類に合った拡張子であることは、
     * 取り込みの検証（checkImportedUploadFilename()）で確かめ済みの前提。
     * 表示名（{field}_origin）も、DBの値ではなく入力の値をそのまま使う。
     * 古いファイルの削除は、画面からの登録と同じく更新前後の差分で決まる。
     */
    public function commitUploads(Model $model, array $input, bool $fromImport = false): void
    {
        $before = $this->storedFilenames($model, true);

        foreach ($this->uploadFieldDefinitions() as $def) {
            match ($def['kind']) {
                'single' => $this->commitSingularUploadField($model, $def['field'], $input, $fromImport),
                'repeatable' => $this->commitRepeatableUploadField($model, $def['field'], $input, $fromImport),
                'wysiwyg' => $this->commitWysiwygField($model, $def['field']),
            };
        }

        $after = $this->storedFilenames($model);
        $removed = array_diff_key($before, $after);

        DB::afterCommit(function () use ($model, $removed) {
            foreach ($removed as $filename => $field) {
                $this->deleteUploadedFile($model, $field, $filename);
            }
        });
    }

    /**
     * このレコードがDB上で参照しているファイル名の一覧（全フィールド分を
     * まとめたもの。ファイル名 => フィールド名）。フィールド名は、ファイルを
     * 消すときに置き場所のディスク（公開・非公開）を決めるのに使う。
     * 単数フィールドはモデルのカラムの値、複数展開フィールドは子テーブルの行、
     * WYSIWYG欄は本文の<img>から取る。子テーブルは、読み込み済みのリレーション
     * （古いかもしれない）ではなく、毎回DBに問い合わせる。
     *
     * $beforeCommitがtrueなら、commitUploads()の「更新前」の一覧。
     * WYSIWYG欄の本文だけは、コントローラーが直前に保存した新しい内容に
     * なっているので、getPrevious()にある変更前の値を使う（直前の保存で
     * 本文が変わっていなければ、getPrevious()に本文は含まれないので、
     * 今の値を使う）。
     */
    private function storedFilenames(Model $model, bool $beforeCommit = false): array
    {
        $filenames = [];
        $previous = $beforeCommit ? $model->getPrevious() : [];

        foreach ($this->uploadFieldDefinitions() as $def) {
            $field = $def['field'];

            if ($def['kind'] === 'repeatable') {
                $filenames += array_fill_keys($model->{$field}()->pluck('filename')->all(), $field);

                continue;
            }

            if ($def['kind'] === 'wysiwyg') {
                $html = array_key_exists($field, $previous) ? $previous[$field] : $model->{$field};
                $filenames += array_fill_keys($this->wysiwygImageFilenames($model, $field, $html), $field);

                continue;
            }

            if ($model->{$field}) {
                $filenames[$model->{$field}] = $field;
            }
        }

        return $filenames;
    }

    /**
     * 単数フィールド(t_newsのlist_imageのような、1レコードにつき1カラム)
     * の確定処理。DBの値だけを決め、物理ファイルは消さない（削除は
     * commitUploads()が更新前後の差分で行う）。
     *
     * _tmpあり（移動に成功） → 新しいファイルをセット（差し替え・新規）
     * _del=1                 → 空にする
     * どちらも無し           → 既存のファイルのまま
     *
     * 「既存のファイル」は、hiddenで届いたファイル名がこのフィールドの
     * DBの現在の値と一致する場合だけ採用する。一致しない（書き換えられた、
     * または画面を開いた後に別の人が更新した）場合は、既存のファイルは
     * 無いものとして扱う。元のファイル名(_origin)も、既存のファイルの分は
     * hiddenではなくDBの値を使う（新しくアップロードしたファイルの元の
     * 名前だけは、hiddenで届いたものしか手がかりが無いのでそれを使う）。
     */
    private function commitSingularUploadField(Model $model, string $field, array $input, bool $fromImport): void
    {
        $originColumn = "{$field}_origin";
        $current = $model->{$field};
        $currentOrigin = $model->{$originColumn};

        if (! array_intersect_key($input, array_flip([$field, "{$field}_tmp", $originColumn, "{$field}_del"]))) {
            return;
        }

        if ($fromImport) {
            // CSVにファイル名の列があれば、その値にする（空欄ならファイルを外す）。
            // 表示名は、CSVに列があればその値、無ければ同じファイルのままのときだけ今の値。
            $filename = array_key_exists($field, $input) ? ($input[$field] ?: null) : $current;
            $origin = array_key_exists($originColumn, $input)
                ? ($input[$originColumn] ?: null)
                : ($filename !== null && $filename === $current ? $currentOrigin : null);

            $model->update([$field => $filename, $originColumn => $filename !== null ? $origin : null]);

            return;
        }

        $del = ($input["{$field}_del"] ?? null) == '1';
        $tmp = $input["{$field}_tmp"] ?? null;
        $kept = ($current !== null && ($input[$field] ?? null) === $current) ? $current : null;

        if ($tmp) {
            $filename = $this->moveTmpToFinal($model, $field, $tmp);

            if ($filename !== null) {
                $model->update([$field => $filename, $originColumn => $input[$originColumn] ?? null]);

                return;
            }

            // tmpの実ファイルが見つからなかった場合(moveTmpToFinal()内で
            // ログ済み)は、_tmpが最初から無かったときと同じ扱いにして
            // 下のdel/既存分岐へ合流させる。
        }

        if ($del) {
            $model->update([$field => null, $originColumn => null]);

            return;
        }

        // _delも_tmpも無ければ、既存の値を書き戻す(通常は実質何も変わらない)。
        // 「何もしない」にせず必ずUPDATEするのは、複数展開のフィールド
        // (commitRepeatableUploadField())と同じく「送られてきた内容でDBの値を
        // 決め直す」形にそろえるため。
        $model->update([$field => $kept, $originColumn => $kept !== null ? $currentOrigin : null]);
    }

    /**
     * 複数展開フィールド(t_news_attachmentsのような子テーブル)の確定処理。
     * 単数フィールドと同じく、DBの行だけを決め、物理ファイルは消さない。
     *
     * 個々の行を差分更新するのではなく、「このモデルに属する既存行を
     * 全削除してから、生き残った枠だけ作り直す」方式にしている。
     *
     * hiddenで届いた既存のファイル名は、このフィールドの現在の行の中に
     * あるものだけを採用する（無いものは、空の予備枠と同じく読み飛ばす）。
     * 同じファイル名が2回送られてきても、行は1つしか作らない。既存の
     * ファイルの元のファイル名は、hiddenではなくDBの行の値を使う。新しくアップロードした
     * ファイルの元のファイル名が届かなければ、空（NULL）のままにする（保存ファイル名は
     * ランダムな文字列なので、代わりに入れても意味が無いため）。
     */
    private function commitRepeatableUploadField(Model $model, string $field, array $input, bool $fromImport): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }

        // DBの現在の行: ファイル名 => 元のファイル名
        $current = $model->{$field}()->pluck('original_name', 'filename')->all();

        if ($fromImport) {
            // CSVの並び順のまま、ファイル名と表示名で行を作り直す（CSVに無いファイルは外れる）
            $rows = [];
            foreach ((array) $input[$field] as $index => $filename) {
                if ($filename && ! in_array($filename, array_column($rows, 'filename'), true)) {
                    $rows[] = ['filename' => $filename, 'original_name' => ($input["{$field}_origin"][$index] ?? null) ?: null];
                }
            }

            $model->{$field}()->delete();
            if ($rows !== []) {
                $model->{$field}()->createMany($rows);
            }

            return;
        }

        $names = $input[$field] ?? [];
        $tmps = $input["{$field}_tmp"] ?? [];
        $origins = $input["{$field}_origin"] ?? [];
        $dels = $input["{$field}_del"] ?? [];

        $rows = [];
        $used = [];

        foreach ($names as $index => $existing) {
            $tmp = $tmps[$index] ?? null;
            $del = ($dels[$index] ?? null) == '1';
            $kept = ($existing !== null && $existing !== '' && array_key_exists($existing, $current)) ? $existing : null;

            if (! $kept && ! $tmp) {
                // 一度も使われなかった予備枠、または既存のファイル名が
                // DBの行に無かった（書き換えられた等）枠。何もしない。
                continue;
            }

            if ($tmp) {
                $filename = $this->moveTmpToFinal($model, $field, $tmp);

                if ($filename !== null) {
                    $rows[] = [
                        'filename' => $filename,
                        'original_name' => ($origins[$index] ?? null) ?: null,
                    ];

                    continue;
                }

                // tmpの実ファイルが見つからなかった場合は、_tmpが
                // 最初から無かったときと同じ扱いにして下へ合流させる。
            }

            if (! $del && $kept && ! isset($used[$kept])) {
                // 触られなかった既存分。ファイルはそのままなので、
                // 同じファイル名・DBにあった元のファイル名で行だけ作り直す。
                $rows[] = [
                    'filename' => $kept,
                    'original_name' => $current[$kept],
                ];
                $used[$kept] = true;
            }

            // ここに来て$rowsに追加されないのは、削除確定の枠
            // ($del=1かつ有効な$tmp無し)、tmpが無効で既存のファイルも
            // 無かった枠、または同じ既存ファイルの2回目以降のどれか。
        }

        $model->{$field}()->delete();

        if ($rows !== []) {
            $model->{$field}()->createMany($rows);
        }
    }

    /**
     * WYSIWYG欄の確定処理。本文の<img>のうち、srcがtmpのURLのものを
     * 正式な保存先へ移し、srcを正式なURLに書き換えて保存し直す。
     * 本文から外された画像の物理削除は、他のフィールドと同じく
     * commitUploads()が更新前後の差分で行う。
     *
     * 本文はコントローラーがHtmlSanitizerを通してから保存したものなので、
     * IMG_SRC_PATTERNでそのまま探せる。
     *
     * 同じ画像を本文の中で2回以上使っている（エディタ上でコピーした）
     * 場合、2回目以降はtmpにもうファイルが無いので、1回目に移した結果を
     * $movedから使う。tmpにファイルが無かった画像（確認画面に長く置いた
     * ままにして、tmpの掃除で消えた等）は、srcを書き換えずにそのまま残す
     * （画像は表示されなくなる）。
     */
    private function commitWysiwygField(Model $model, string $field): void
    {
        $html = $model->{$field};

        if ($html === null || $html === '') {
            return;
        }

        $moved = [];

        $newHtml = preg_replace_callback(self::IMG_SRC_PATTERN, function (array $m) use ($model, $field, &$moved) {
            $tmp = $this->tmpImageFilenameFromUrl($m[2]);

            if ($tmp === null) {
                return $m[0];
            }

            $moved[$tmp] ??= $this->moveTmpToFinal($model, $field, $tmp);

            if ($moved[$tmp] === null) {
                return $m[0];
            }

            return $m[1].UploadFilePath::url($model::class, $model->getKey(), $field, $moved[$tmp]).$m[3];
        }, $html);

        if ($newHtml !== $html) {
            $model->update([$field => $newHtml]);
        }
    }

    /**
     * WYSIWYG欄の本文から、このレコードの保存先にある画像のファイル名を
     * 集める。srcが「このレコードの保存先のURL」に完全に一致するものだけが
     * 対象で、tmpの画像・他の記事の画像・静的な画像・外部の画像は含めない。
     *
     * 呼ばれる本文には、DBを直接書き換えた（一括パッチなど）ものも
     * ありうるので、IMG_SRC_PATTERNにかける前に必ずHtmlSanitizerを通して
     * 形をそろえる。ここで画像を取りこぼすと、使っている画像が「更新後に
     * 無くなった」とみなされて削除されてしまうため。
     */
    private function wysiwygImageFilenames(Model $model, string $field, ?string $html): array
    {
        $html = HtmlSanitizer::clean($html);

        if ($html === null || $html === '' || $model->getKey() === null) {
            return [];
        }

        preg_match_all(self::IMG_SRC_PATTERN, $html, $matches);

        $filenames = [];

        foreach ($matches[2] as $src) {
            $filename = basename(parse_url($src, PHP_URL_PATH) ?? '');

            if (preg_match(UploadFilePath::SAFE_FILENAME, $filename)
                && $src === UploadFilePath::url($model::class, $model->getKey(), $field, $filename)) {
                $filenames[] = $filename;
            }
        }

        return $filenames;
    }

    /**
     * srcがtmpに置いた画像のURLならそのファイル名を、そうでなければnullを
     * 返す。URLがUploadFilePath::tmpUrl()で組み立てたものと完全に一致し、
     * ファイル名がSAFE_FILENAMEの形式で、拡張子が画像のもの
     * （ALLOW_IMAGE_TYPES）だけを認める（添付ファイル欄にアップロードした
     * PDFなどのtmpのURLを本文に書かれても、画像として取り込まない）。
     */
    private function tmpImageFilenameFromUrl(string $src): ?string
    {
        $filename = basename(parse_url($src, PHP_URL_PATH) ?? '');

        if (! preg_match(UploadFilePath::SAFE_FILENAME, $filename)) {
            return null;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOW_IMAGE_TYPES, true)) {
            return null;
        }

        return $src === UploadFilePath::tmpUrl($filename) ? $filename : null;
    }

    /**
     * CSV取り込みで指定されたファイル名を確かめる（App\Support\CsvImportから呼ぶ）。
     * 問題があれば利用者向けのメッセージを、無ければnullを返す。
     *
     * - 今のアップロードと同じ安全な形式（SAFE_FILENAME。「/」や「..」を含まない）であること
     * - 項目の種類に合った拡張子であること（画像の項目なら画像の拡張子だけ。同じ保存先に
     *   ある別の項目のファイル、たとえば添付ファイルのPDFを画像の項目に指定させないため）
     * - このレコードの保存先に実在すること（ファイル名だけを受け取り、必ずこのレコードの
     *   保存先の中で探す）
     */
    public function checkImportedUploadFilename(Model $model, string $field, string $filename): ?string
    {
        $config = $this->resolveUploadFieldConfig($field);

        if ($config === null) {
            return null;
        }

        [, $allowTypes] = $config;

        if (! preg_match(UploadFilePath::SAFE_FILENAME, $filename)) {
            return "ファイル名「{$filename}」の形が正しくありません（英数字と「_」、拡張子だけにしてください）。";
        }

        if (! in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $allowTypes, true)) {
            return "ファイル名「{$filename}」の種類は、この項目では使えません（".implode('・', $allowTypes).'）。';
        }

        if (! Storage::disk(UploadFilePath::disk($model::class, $field))->exists($this->uploadDirectory($model).'/'.$filename)) {
            return "ファイル「{$filename}」がサーバーにありません。";
        }

        return null;
    }

    /**
     * 保存先ディレクトリ。規則そのもの（モデルのクラス名とidから組み立てる。
     * 例: news/000/000012）はApp\Support\UploadFilePathが持っていて、ここは
     * それを呼ぶだけ。訪問者側の表示（モデルのアクセサ）も同じUploadFilePathを
     * 使うので、保存する場所と表示するURLが食い違うことが無い。
     *
     * 1件のレコードのファイルは、フィールド（list_image・attach等）に
     * 関係なく同じディレクトリに置く（理由はUploadFilePathのコメント参照）。
     * 非公開のフィールドがあれば、同じ名前のディレクトリが非公開のディスクにもできる。
     */
    private function uploadDirectory(Model $model): string
    {
        return UploadFilePath::directory($model::class, $model->getKey());
    }

    // 置き場所のディスク（公開・非公開）は、フィールドで決まる。
    private function deleteUploadedFile(Model $model, string $field, string $filename): void
    {
        Storage::disk(UploadFilePath::disk($model::class, $field))->delete($this->uploadDirectory($model).'/'.$filename);
    }

    /**
     * tmp/のファイルを、idが確定した正式なディレクトリへ移動する。
     * DBに保存するのはファイル名だけなので、戻り値もファイル名のみ。
     *
     * 呼び出し前にrules()のregexで名前の「形式」は検証済みだが、
     * それとは別に「実ファイルが本当にtmp/に存在するか」もここで
     * 確認する。hiddenの値は最後まで改ざん可能な入力なので、確認画面を
     * 経由する間にcleanupTmpDirectory()で削除された場合や、そもそも
     * 存在しないファイル名が送られてきた場合にStorage::move()が
     * 例外を投げて処理全体が失敗するのを防ぐため。存在しなければnullを
     * 返し、呼び出し側で「_tmpが最初から無かった」場合と同じ扱いにする。
     */
    private function moveTmpToFinal(Model $model, string $field, string $tmpName): ?string
    {
        $tmpPath = UploadFilePath::TMP_DIR.'/'.$tmpName;
        $tmpDisk = Storage::disk(UploadFilePath::TMP_DISK);

        if (! $tmpDisk->exists($tmpPath)) {
            Log::warning('AjaxFileUpload: tmpファイルが存在しません。', [
                'field' => $field,
                'tmp' => $tmpName,
            ]);

            return null;
        }

        $finalPath = $this->uploadDirectory($model).'/'.$tmpName;
        $finalDiskName = UploadFilePath::disk($model::class, $field);

        if ($finalDiskName === UploadFilePath::TMP_DISK) {
            $tmpDisk->move($tmpPath, $finalPath);
        } else {
            // tmp（"local"）と保存先（公開のフィールドなら"public"）のディスクが違うときは、
            // ディスクをまたいだmoveができないので、書き写してからtmpを消す。
            $stream = $tmpDisk->readStream($tmpPath);
            Storage::disk($finalDiskName)->writeStream($finalPath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $tmpDisk->delete($tmpPath);
        }

        return $tmpName;
    }

    /**
     * 対象レコードを削除する前に呼ぶ。全フィールドについて物理ファイルを
     * 削除する(複数展開フィールドはDBの行ごと削除する。WYSIWYG欄は本文が
     * 参照している、このレコードの保存先の画像を削除する)。
     * モデル自体の削除はこのメソッドの責務外(呼び出し側で別途delete()する)。
     *
     * 子テーブルの行はその場で消し、物理ファイルはcommitUploads()と同じく
     * トランザクションが確定した後に消す（削除が取り消されたときに、
     * 残ったレコードのファイルだけが消えてしまわないように）。
     */
    public function deleteAllUploads(Model $model): void
    {
        // ファイル名 => フィールド名（置き場所のディスクを決めるのに使う）
        $filenames = [];

        foreach ($this->uploadFieldDefinitions() as $def) {
            $base = $def['field'];

            if ($def['kind'] === 'wysiwyg') {
                $filenames += array_fill_keys($this->wysiwygImageFilenames($model, $base, $model->{$base}), $base);

                continue;
            }

            if ($def['kind'] === 'repeatable') {
                $filenames += array_fill_keys($model->{$base}()->pluck('filename')->all(), $base);
                $model->{$base}()->delete();

                continue;
            }

            if ($model->{$base}) {
                $filenames[$model->{$base}] = $base;
            }
        }

        DB::afterCommit(function () use ($model, $filenames) {
            foreach ($filenames as $filename => $field) {
                $this->deleteUploadedFile($model, $field, $filename);
            }

            // 1件のレコードのファイルは必ず1つのディレクトリ（例:
            // news/000/000012）にまとまっているので、最後にそのディレクトリ
            // ごと消しておく。上でファイルを1つずつ消しているのは、DBに記録が
            // あるものを確実に消すため。ここでディレクトリを消すのは、空の
            // ディレクトリが残り続けないようにするため（DBに記録の無い
            // 迷子のファイルがあれば、それもここで一緒に消える）。
            // 上位のグループディレクトリ（news/000）は他のレコードと共有して
            // いるので消さない。公開・非公開の両方のディスクにありうるので、両方で消す。
            Storage::disk(UploadFilePath::PUBLIC_DISK)->deleteDirectory($this->uploadDirectory($model));
            Storage::disk(UploadFilePath::PRIVATE_DISK)->deleteDirectory($this->uploadDirectory($model));
        });
    }

    /**
     * $input側（＝実際にフォームから送信される項目だけ）を組み立てる。
     *
     * UPLOAD_FILESの各フィールド（WYSIWYG_FIELDSの欄は対象外）について、"{field}"・"{field}_origin"・
     * "{field}_tmp"・"{field}_del"の4つを返す。複数展開フィールドの場合は
     * 同じ4つのキーが、それぞれ同じ要素数の並行配列になる（HTML側の
     * name="attach[]"・name="attach_tmp[]"…という送信のされ方と同じ形）。
     *
     * 表示専用の値（プレビューURL等）はここには一切含めない。プレビューURLは
     * ビューの中でupload_preview_url()を呼んで、この戻り値から求める
     * （詳しくはこのファイル冒頭のコメント参照）。
     *
     * $sourceは「もし入力し直そうとしていた値があればそれ」を保持する
     * 配列で、キーに無いフィールドは$model(渡っていれば)の現在値から
     * 補う。
     * - create: old()（何も無ければ空配列）、$modelはnull
     * - edit  : old()、$modelは対象レコード
     * - confirm: バリデーション済み配列($validated)、$modelは
     *            新規登録ならnull、更新なら対象レコード
     * - show  : 空配列（＝常に$modelの現在値を使う）、$modelは対象レコード
     */
    public function ajaxUploadInput(?Model $model, array $source = []): array
    {
        $result = [];

        foreach ($this->uploadFieldDefinitions() as $def) {
            $field = $def['field'];

            // WYSIWYG欄の本文は、呼び出し側が他の項目と同じように$inputへ
            // 入れる(画像は本文のHTMLの中に入っているので、足すものは無い)。
            if ($def['kind'] === 'wysiwyg') {
                continue;
            }

            if ($def['kind'] === 'repeatable') {
                $result += $this->repeatableUploadInput($model, $field, $source);

                continue;
            }

            $originKey = "{$field}_origin";

            $result[$field] = array_key_exists($field, $source)
                ? $source[$field]
                : ($model->{$field} ?? null);
            $result[$originKey] = array_key_exists($originKey, $source)
                ? $source[$originKey]
                : ($model->{$originKey} ?? null);
            $result["{$field}_tmp"] = $source["{$field}_tmp"] ?? null;
            $result["{$field}_del"] = ($source["{$field}_del"] ?? null) == '1' ? '1' : '';
        }

        return $result;
    }

    /**
     * ajaxUploadInput()の複数展開フィールド1つ分。4本の並行配列を、
     * 必ず同じ要素数に揃えて返す。
     *
     * $sourceにそのフィールドのキーが無ければ（old()に無い、またはshowの
     * ように$sourceが空）、$modelのHasManyリレーションの現在値が入力値に
     * なる。
     */
    private function repeatableUploadInput(?Model $model, string $field, array $source): array
    {
        if (! array_key_exists($field, $source)) {
            $rows = $model ? $model->{$field} : collect();
            $count = count($rows);

            return [
                $field => $rows->pluck('filename')->all(),
                "{$field}_origin" => $rows->pluck('original_name')->all(),
                "{$field}_tmp" => array_fill(0, $count, null),
                "{$field}_del" => array_fill(0, $count, ''),
            ];
        }

        $names = array_values((array) ($source[$field] ?? []));
        $origins = (array) ($source["{$field}_origin"] ?? []);
        $tmps = (array) ($source["{$field}_tmp"] ?? []);
        $dels = (array) ($source["{$field}_del"] ?? []);

        $result = [
            $field => $names,
            "{$field}_origin" => [],
            "{$field}_tmp" => [],
            "{$field}_del" => [],
        ];

        // 4本の配列は.ajax_upload_blockという単位で同時に増減させている
        // ので通常はズレないが、old()経由の値は、要素数のチェック
        // （SameCountAsRule）で弾かれて戻ってきた入力そのものの場合もある
        // ため、ここでも??で欠けを埋めておく。
        foreach ($names as $index => $name) {
            $result["{$field}_origin"][] = $origins[$index] ?? null;
            $result["{$field}_tmp"][] = $tmps[$index] ?? null;
            $result["{$field}_del"][] = ($dels[$index] ?? null) == '1' ? '1' : '';
        }

        return $result;
    }
}
