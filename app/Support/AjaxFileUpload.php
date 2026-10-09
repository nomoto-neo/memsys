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
 * 画像と添付ファイルを、確認画面をはさんで保存するAjaxアップロードのトレイト。
 *
 * ■ 流れ
 * 1. 画面でファイルを選ぶと、その場でuploadAjaxFile()へ1ファイルずつ送る
 * 2. 種類を確かめて画像なら縮小し、一時ディレクトリにランダムな名前で置いて、
 *    その名前と元の名前を返す。新規登録ではidがまだ無いので、DBには書かない。
 *    一時ファイルはアップロードしたブラウザだけが見られる
 * 3. 画面はその名前を{field}_tmp・{field}_origin・{field}_delのhiddenに入れ、
 *    確認画面を通って登録か更新へ進む
 * 4. レコードを保存してidが決まった後に、commitUploads()でファイルを正式な保存先へ移し、
 *    DBのカラムか子テーブルに書く。保存先は普通は公開のディスクで、持ち主のモデルの
 *    PRIVATE_FILE_FIELDSにあるフィールドは非公開のディスク。保存先の規則はUploadFilePathにある
 *
 * ■ コントローラーが用意するもの
 * - UPLOAD_FILES：['フィールド名' => 横幅(px)]。横幅が0なら添付ファイルで縮小しない。
 *   0より大きければ画像で、その横幅を超えたら縮小する。横幅はモデルの定数を参照する。
 *   例：'list_image' => News::LIST_IMAGE_WIDTH
 * - 名前の末尾が「.*」のフィールドは、いくつでも足せる欄になる。例：'attach.*'。
 *   モデルには「.*」を除いた名前のHasManyのリレーションを用意する。例：News::attach()。
 *   このトレイトは子テーブルの名前や外部キーを知らず、そのリレーションだけを使う
 * - uploadAjaxFile()を指すPOSTのルート。例：admin.news.ajaxUpload
 * - WYSIWYG_FIELDS：['フィールド名' => 横幅(px)]。画像を入れられるエディタの欄。省略できる
 *
 * 使える拡張子のALLOW_IMAGE_TYPESとALLOW_ATTACH_TYPESはサイト全体のセキュリティの方針なので、
 * このトレイトが持ってコーナーごとには変えさせない。
 *
 * ■ エディタの欄の画像
 * エディタに入れた画像も、ほかの画像と同じ流れで扱う。
 * 1. 画像を入れるとuploadAjaxFile()へ送られて一時ディレクトリに置かれ、
 *    そのURLが<img src>として本文に入る
 * 2. 確認画面の間は、一時ファイルのURLが入った本文のまま持ち回る。画像のためのhiddenは無い
 * 3. commitUploads()で、一時ファイルを指す<img>の画像を正式な保存先へ移してsrcを書き換える
 *
 * 本文には画像のURLをそのまま保存する。例：/storage/news/000/000012/xxxx.jpg
 * 扱うのは、srcが一時ファイルのURLか、このレコードの保存先のURLに完全に一致する画像だけ。
 * ほかの記事の画像、サーバーに置いた画像、外のサイトの画像は移しも消しもしない。
 *
 * <img>は正規表現のIMG_SRC_PATTERNで探す。HtmlSanitizerを通した後のHTMLは属性の値が
 * 必ず"で囲まれ、値の中の"は&quot;になっているので、この形に絞ったパターンで取りこぼさない。
 * 書き換えるのは一致したsrcの値だけで、本文のほかの部分は1文字も変えない。
 *
 * エディタの欄だけ書き方を変えずに済むよう、本文はコントローラーがほかの項目と一緒に保存する。
 * そのためcommitUploads()のときには本文はもう新しくなっていて、更新前の本文は
 * Eloquentのモデルが覚えている直前の値をgetPrevious()で取る。これが取れるよう、
 * コントローラーでは次の順に書く。
 *
 *   $news->update([...]);                 // 1. 本体を保存する
 *   $this->commitUploads($news, ...);     // 2. すぐにファイルを確定する
 *   // 関連テーブルの更新はここから        // 3. カテゴリーなどはその後
 *
 * 1と2の間で同じモデルを保存し直すと、更新前の本文が分からなくなる。そのときも
 * 本文から外した画像が消えずに残るだけで、使っている画像が消えることは無い。
 *
 * ■ 画面へ渡す$input
 * コントローラーが画面へ渡す$inputにはフォームから送る項目だけを入れ、表示のためだけの値は
 * 入れない。確認画面の_confirm_hiddenが$inputをそのままhiddenに並べられるようにするため。
 * ajaxUploadInput()もそれに合わせて{field}・{field}_origin・{field}_tmp・{field}_delだけを
 * 返す。複数の欄ではどれも同じ要素数の配列になる。
 *
 *   $input = old() + [...] + $this->ajaxUploadInput(null, old());
 *
 * プレビューのURLはコントローラーでは作らず、_ajax_upload_blockの中でupload_preview_url()が
 * $inputの値から求める。都道府県の名前をcode_table()で引くのと同じ考え方。
 * _fields.blade.phpは_ajax_upload_blockと_ajax_upload_groupに$readonlyを渡すだけで、
 * 入力の欄と表示だけのプレビューが切り替わる。
 */
trait AjaxFileUpload
{
    private const ALLOW_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    private const ALLOW_ATTACH_TYPES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'zip'];

    /** 1ファイルの大きさの上限(KB) */
    private const MAX_UPLOAD_KB = 10240;

    /**
     * 画像1枚の画素数の上限。4000万画素は8000×5000くらいで、スマートフォンの写真は十分に収まる。
     * サーバーのメモリに余裕があってもこれを超える画像は受け付けない
     */
    private const MAX_IMAGE_PIXELS = 40_000_000;

    /** GDが画像を展開したときに1画素あたりに使うバイト数。フルカラーは1画素を4バイトで持つ */
    private const GD_BYTES_PER_PIXEL = 4;

    /**
     * HtmlSanitizerを通した本文から、<img>のsrcを探すパターン。1はsrcの値の前まで、2はsrcの値、3は閉じの"。
     * srcの前に空白を求めるのは、data-srcのような別の属性に一致させないため
     */
    private const IMG_SRC_PATTERN = '/(<img\b[^>]*?\ssrc=")([^"]*)(")/i';

    // 一時ディレクトリの名前とhiddenで持ち回るファイル名の形は、プレビューのURLを作るときにも
    // 使うのでUploadFilePathが持っている。

    /**
     * Ajaxでアップロードを受け取る。1回に1ファイルで、どのフィールドの分かはfieldで受け取る。
     * fieldは末尾の「.*」や「[]」を付けない名前。
     */
    public function uploadAjaxFile(Request $request): JsonResponse
    {
        $field = (string) $request->input('field');
        $config = $this->resolveUploadFieldConfig($field);

        if ($config === null) {
            // 定義に無いフィールドは、作りの誤りか書き換えられた送信
            Log::warning('AjaxFileUpload: 未定義のfieldが指定されました。', ['field' => $field]);

            return response()->json(['message' => 'アップロードに失敗しました。'], 422);
        }

        [$width, $allowTypes] = $config;

        if (! $request->hasFile('file')) {
            return response()->json(['message' => 'アップロードに失敗しました。'], 422);
        }

        $file = $request->file('file');

        if (! $file->isValid()) {
            // PHPがupload_max_filesizeなどの上限で受け取らなかった
            Log::warning('AjaxFileUpload: アップロードが無効です。', [
                'field' => $field,
                'error' => $file->getErrorMessage(),
            ]);

            return response()->json(['message' => 'アップロードに失敗しました。'.$file->getErrorMessage()], 422);
        }

        // 種類はLaravelのmimesで確かめる。拡張子だけでなくファイルの中身から見た種類も確かめる
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

        // 画像は展開する前に画素数を確かめる
        if ($width > 0) {
            $pixelError = $this->checkImagePixels($file->getRealPath(), $field);

            if ($pixelError !== null) {
                return response()->json(['message' => $pixelError], 422);
            }
        }

        $originalName = $file->getClientOriginalName();

        try {
            $this->cleanupTmpDirectory();

            // 一時ディレクトリにランダムな名前で置き、画像なら縮小する
            $tmpName = $file->hashName();
            $storedPath = $file->storeAs(UploadFilePath::TMP_DIR, $tmpName, UploadFilePath::TMP_DISK);

            if ($storedPath === false) {
                throw new \RuntimeException('storeAs()に失敗しました。');
            }

            if ($width > 0) {
                $this->resizeIfNeeded(Storage::disk(UploadFilePath::TMP_DISK)->path($storedPath), $width);
            }

            // 一時ファイルを見られるのは、アップロードしたセッションだけ。UploadedFileControllerがこの一覧で確かめる
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
     * 画像の画素数をGDで展開する前に確かめる。問題があれば利用者へのメッセージを返し、無ければnullを返す。
     *
     * ファイルが小さくても縦横の画素数が極端に大きい画像は、GDが展開するときに「画素数×4バイト」の
     * メモリを取ろうとしてmemory_limitを超え、処理が止まる。getimagesize()はファイルの先頭の寸法を
     * 読むだけで展開しないので、その前に断れる。
     *
     * 1. MAX_IMAGE_PIXELSを超えていたら、メモリに関係なく断る
     * 2. 展開に要るメモリの見込みが残りのメモリに収まらなければ断る。縦向きに撮った写真は
     *    回転を直すときに同じ大きさの画像をもう1枚作るので、見込みは2枚分にする。
     *    memory_limitが無制限なら確かめない
     *
     * 2があるので、memory_limitの違う開発機と本番機では同じ画像でも結果が変わりうる。
     * PHPの既定の128Mでは縦向きの1200万画素の写真がぎりぎりなので、本番は256M以上をすすめる。
     * どちらで断ったかはログに残す。
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

        // 1. 画素数の上限
        if ($pixels > self::MAX_IMAGE_PIXELS) {
            Log::warning('AjaxFileUpload: 画像の画素数が上限を超えています。', [
                'field' => $field,
                'pixels' => $pixels,
                'max_pixels' => self::MAX_IMAGE_PIXELS,
            ]);

            return $message;
        }

        // 2. 残りのメモリ。ini_parse_quantity()は"128M"のような書き方をバイト数に直し、無制限なら-1を返す
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

    /** フィールドの横幅と使える拡張子。$fieldは「.*」を付けない名前で、定義に無ければnull。 */
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
     * UPLOAD_FILESとWYSIWYG_FIELDSを、['field' => 名前, 'kind' => 種類, 'width' => 横幅]の並びにする。
     *
     * - single      UPLOAD_FILESの1つだけのフィールド。例：list_image
     * - repeatable  UPLOAD_FILESの「.*」付きのフィールド。例：attach
     * - wysiwyg     WYSIWYG_FIELDSのフィールド。例：body
     *
     * このトレイトのほかのメソッドは2つの定数を自分で読まず、必ずこの結果を使う。
     * 定数の書き方を変えてもここを直すだけで済む。
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

        // WYSIWYG_FIELDSは省略できるので、あるときだけ読む
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
     * 一時ディレクトリの古いファイルを消す。本来はスケジューラーが1時間ごとに消すが、
     * cronが動いていなくても溜まり続けないようアップロードのたびにも消す。
     */
    private function cleanupTmpDirectory(): void
    {
        TemporaryDataCleaner::uploadTmpFiles();
    }

    /**
     * 指定の横幅を超える画像だけを縮小する。拡大はしない。
     * 縦向きに撮った写真が横に倒れないよう、EXIFの向きを読んで回転も直す。
     */
    private function resizeIfNeeded(string $absolutePath, int $maxWidth): void
    {
        $imageInfo = @getimagesize($absolutePath);

        if ($imageInfo === false) {
            return;
        }

        [$width, $height, $type] = $imageInfo;

        // JPEGならEXIFの向きを読む
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

        // 向きを直す
        if ($orientation && $orientation !== 1) {
            $image = $this->applyExifOrientation($image, (int) $orientation);
            $width = imagesx($image);
            $height = imagesy($image);
        }

        if ($width <= $maxWidth) {
            // 縮小は要らなくても向きを直したなら保存し直す
            if ($orientation && $orientation !== 1) {
                $this->saveImage($image, $absolutePath, $type);
            }
            imagedestroy($image);

            return;
        }

        // 縦横の比を保って縮小する
        $newHeight = (int) round($height * ($maxWidth / $width));
        $resized = imagecreatetruecolor($maxWidth, $newHeight);

        // PNGとWebPの透過を保つ
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);

        $this->saveImage($resized, $absolutePath, $type);

        imagedestroy($image);
        imagedestroy($resized);
    }

    /** EXIFの向きに合わせて画像を回す。3は180度、6は右へ90度、8は左へ90度回す。 */
    private function applyExifOrientation($image, int $orientation)
    {
        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    /** 画像を元と同じ種類で保存し直す。JPEGとWebPの画質は85。 */
    private function saveImage($image, string $path, int $type): void
    {
        match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, 85),
            IMAGETYPE_PNG => imagepng($image, $path),
            IMAGETYPE_WEBP => imagewebp($image, $path, 85),
            default => null,
        };
    }

    /** アップロードの欄のhiddenを確かめるルール。コントローラーのrules()に+で足して使う。 */
    public function ajaxUploadRules(): array
    {
        $rules = [];

        foreach ($this->uploadFieldDefinitions() as $def) {
            // エディタの欄にはhiddenが無い。本文のルールはコントローラーのrules()に書く
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
                // 複数の欄は、4本とも配列で要素数がそろっていることを確かめる。
                // 画面は4本を1組で増減させるので、そろっていなければhiddenが書き換えられている。
                // 1本だけ送られてこなかったときも見逃さないよう4本それぞれに付ける
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
     * レコードを保存してidが決まった後に、アップロードしたファイルを確定する。
     * $inputは送信された値で、形はrules()で確かめてある前提。
     *
     * ■ ファイルを消すかはDBの更新前と更新後の違いで決める
     * hiddenで届くファイル名や_delは書き換えられる。1件分のファイルは同じディレクトリにあるので、
     * それを信じて消すと、書き換えで同じ記事の別の欄のファイルを消せてしまう。そこで次のようにする。
     * 1. 更新前に、このレコードが参照しているファイル名を全フィールド分まとめて取る
     * 2. フィールドごとにDBの値だけを更新する。hiddenで届いた今のファイル名はDBの値と
     *    一致するときだけ使い、一致しなければ今のファイルは無いとみなす
     * 3. 更新後にもう一度取り、更新前にあって更新後に無いファイルだけを消す
     * 消すかを決めるのは書き換えられないDBの値だけなので、このレコードが使わなくなった
     * ファイルのほかは消えない。
     *
     * ■ ファイルはトランザクションが確定した後に消す
     * 呼び元はトランザクションの中でこのメソッドを呼ぶ。その場で消すと、後で例外が起きて
     * DBが元に戻ったときに、DBが参照しているファイルが無くなってしまう。そこで3はDB::afterCommit()で
     * 確定の後に消す。トランザクションの外で呼ばれたらその場で消える。
     * 一時ファイルを保存先へ移すのは、DBに書くファイル名を決めるためにその場で行う。
     * 取り消されたときはどこからも使われないファイルが残るだけで、表示は壊れない。
     *
     * エディタの欄は本文をコントローラーが保存済みなので、更新前の本文はgetPrevious()から取る。
     *
     * ■ 入力にキーが無いフィールドは触らない
     * 1つだけの欄は4つのキーのどれも無いとき、複数の欄は{field}が無いとき、ファイルは今のまま。
     * 画面のフォームは4つのhiddenを必ず送るので、画面からの登録には関係しない。
     * CSVにアップロードの列が無いときに今のファイルを消さないため。
     *
     * ■ CSV取り込みから呼ぶとき
     * $fromImportがtrueなら、DBの値と違うファイル名も使う。バッチなどでファイルの名前を変えた後に、
     * 新しい名前をCSVで反映できるようにするため。名前の形と保存先にあることと拡張子は、取り込みの
     * 検証のcheckImportedUploadFilename()で確かめ済みの前提。表示名もDBの値ではなく入力の値を使う。
     * 古いファイルを消すのは、画面からの登録と同じく更新前と更新後の違いで決まる。
     */
    public function commitUploads(Model $model, array $input, bool $fromImport = false): void
    {
        // 1. 更新前のファイル名
        $before = $this->storedFilenames($model, true);

        // 2. フィールドごとにDBを更新する
        foreach ($this->uploadFieldDefinitions() as $def) {
            match ($def['kind']) {
                'single' => $this->commitSingularUploadField($model, $def['field'], $input, $fromImport),
                'repeatable' => $this->commitRepeatableUploadField($model, $def['field'], $input, $fromImport),
                'wysiwyg' => $this->commitWysiwygField($model, $def['field']),
            };
        }

        // 3. 使わなくなったファイルを確定の後に消す
        $after = $this->storedFilenames($model);
        $removed = array_diff_key($before, $after);

        DB::afterCommit(function () use ($model, $removed) {
            foreach ($removed as $filename => $field) {
                $this->deleteUploadedFile($model, $field, $filename);
            }
        });
    }

    /**
     * このレコードが参照しているファイル名の一覧。ファイル名 => フィールド名で、フィールド名は
     * 消すときにディスクを決めるのに使う。1つだけの欄はカラムから、複数の欄は子テーブルから、
     * エディタの欄は本文の<img>から取る。読み込み済みのリレーションは古いかもしれないので、
     * 子テーブルは毎回DBに問い合わせる。
     *
     * $beforeCommitがtrueなら更新前の一覧。エディタの本文はもう新しくなっているので
     * getPrevious()の値を使う。直前の保存で本文が変わっていなければ今の値を使う。
     */
    private function storedFilenames(Model $model, bool $beforeCommit = false): array
    {
        $filenames = [];
        $previous = $beforeCommit ? $model->getPrevious() : [];

        foreach ($this->uploadFieldDefinitions() as $def) {
            $field = $def['field'];

            // 複数の欄は子テーブルの行から
            if ($def['kind'] === 'repeatable') {
                $filenames += array_fill_keys($model->{$field}()->pluck('filename')->all(), $field);

                continue;
            }

            // エディタの欄は本文の<img>から
            if ($def['kind'] === 'wysiwyg') {
                $html = array_key_exists($field, $previous) ? $previous[$field] : $model->{$field};
                $filenames += array_fill_keys($this->wysiwygImageFilenames($model, $field, $html), $field);

                continue;
            }

            // 1つだけの欄はカラムから
            if ($model->{$field}) {
                $filenames[$model->{$field}] = $field;
            }
        }

        return $filenames;
    }

    /**
     * 1レコードに1カラムの欄を確定する。例：t_newsのlist_image。DBの値だけを決めて
     * ファイルは消さない。消すのはcommitUploads()。
     *
     * _tmpがあり、移せた   → 新しいファイルにする
     * _delが1              → 空にする
     * どちらでもない       → 今のファイルのまま
     *
     * 今のファイルは、hiddenで届いた名前がDBの値と一致するときだけ残す。書き換えられたときや
     * 画面を開いた後にほかの人が更新したときは、今のファイルは無いとみなす。今のファイルの
     * 表示名もDBの値を使う。新しいファイルの表示名だけは、hiddenで届いた値しか無いのでそれを使う。
     */
    private function commitSingularUploadField(Model $model, string $field, array $input, bool $fromImport): void
    {
        $originColumn = "{$field}_origin";
        $current = $model->{$field};
        $currentOrigin = $model->{$originColumn};

        // 入力にこの欄のキーが無ければ触らない
        if (! array_intersect_key($input, array_flip([$field, "{$field}_tmp", $originColumn, "{$field}_del"]))) {
            return;
        }

        if ($fromImport) {
            // CSVにファイル名の列があれば、その値にする。空欄ならファイルを外す
            $filename = array_key_exists($field, $input) ? ($input[$field] ?: null) : $current;

            if (array_key_exists($originColumn, $input)) {
                // CSVに表示名の列があれば、その値
                $origin = $input[$originColumn] ?: null;
            } elseif ($filename !== null && $filename === $current) {
                // 列が無くファイルが変わらなければ、今の表示名
                $origin = $currentOrigin;
            } else {
                // 列が無くファイルが変わったなら、表示名は無し
                $origin = null;
            }

            $model->update([$field => $filename, $originColumn => $filename !== null ? $origin : null]);

            return;
        }

        $del = ($input["{$field}_del"] ?? null) == '1';
        $tmp = $input["{$field}_tmp"] ?? null;
        $kept = ($current !== null && ($input[$field] ?? null) === $current) ? $current : null;

        // 新しいファイル
        if ($tmp) {
            $filename = $this->moveTmpToFinal($model, $field, $tmp);

            if ($filename !== null) {
                $model->update([$field => $filename, $originColumn => $input[$originColumn] ?? null]);

                return;
            }

            // 一時ファイルが無ければ、_tmpが無かったときと同じく下へ進む
        }

        // 削除
        if ($del) {
            $model->update([$field => null, $originColumn => null]);

            return;
        }

        // どちらでもなければ今の値を書き戻す。ふつうは何も変わらないが、
        // 複数の欄と同じく送られた内容でDBの値を決め直す形にそろえる
        $model->update([$field => $kept, $originColumn => $kept !== null ? $currentOrigin : null]);
    }

    /**
     * 子テーブルに行を持つ複数の欄を確定する。例：t_news_attachments。DBの行だけを決めて
     * ファイルは消さない。行を1つずつ直すのではなく、今の行を全部消して残す分を作り直す。
     *
     * hiddenで届いた今のファイル名は子テーブルにあるものだけを使い、無いものは空の枠と同じく
     * 飛ばす。同じ名前が2回届いても行は1つだけ作る。今のファイルの表示名はDBの値を使う。
     * 新しいファイルの表示名が届かなければ空のままにする。保存したファイル名はランダムなので、
     * 代わりに入れても意味が無いため。
     */
    private function commitRepeatableUploadField(Model $model, string $field, array $input, bool $fromImport): void
    {
        // 入力にこの欄のキーが無ければ触らない
        if (! array_key_exists($field, $input)) {
            return;
        }

        // 今の行。ファイル名 => 表示名
        $current = $model->{$field}()->pluck('original_name', 'filename')->all();

        if ($fromImport) {
            // CSVの並び順のまま、ファイル名と表示名で行を作り直す。CSVに無いファイルは外れる
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
                // 使われなかった空の枠か、今のファイル名が子テーブルに無い枠
                continue;
            }

            // 新しいファイル
            if ($tmp) {
                $filename = $this->moveTmpToFinal($model, $field, $tmp);

                if ($filename !== null) {
                    $rows[] = [
                        'filename' => $filename,
                        'original_name' => ($origins[$index] ?? null) ?: null,
                    ];

                    continue;
                }

                // 一時ファイルが無ければ、_tmpが無かったときと同じく下へ進む
            }

            // 消さない今のファイルは、DBの表示名で行を作り直す
            if (! $del && $kept && ! isset($used[$kept])) {
                $rows[] = [
                    'filename' => $kept,
                    'original_name' => $current[$kept],
                ];
                $used[$kept] = true;
            }

            // 行を作らないのは、消す枠、一時ファイルも今のファイルも無い枠、同じファイルの2回目
        }

        $model->{$field}()->delete();

        if ($rows !== []) {
            $model->{$field}()->createMany($rows);
        }
    }

    /**
     * エディタの欄を確定する。本文の<img>のうちsrcが一時ファイルのURLの画像を保存先へ移し、
     * srcを書き換えて保存し直す。本文から外した画像を消すのは、ほかの欄と同じくcommitUploads()。
     *
     * 同じ画像を本文で2回以上使っているときは、2回目には一時ファイルがもう無いので
     * 1回目に移した結果を$movedから使う。確認画面に長く置いて一時ファイルが消えた画像は
     * srcをそのままにする。その画像は表示されなくなる。
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

            // 一時ファイルの画像でなければそのまま
            if ($tmp === null) {
                return $m[0];
            }

            $moved[$tmp] ??= $this->moveTmpToFinal($model, $field, $tmp);

            // 移せなければそのまま
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
     * エディタの本文から、このレコードの保存先にある画像のファイル名を集める。対象はsrcがこのレコードの
     * 保存先のURLに完全に一致するものだけで、一時ファイルやほかの記事、外の画像は含めない。
     *
     * DBを直接書き換えた本文もありうるので、先にHtmlSanitizerを通して形をそろえる。
     * ここで取りこぼすと、使っている画像が更新後に無くなったとみなされて消されてしまうため。
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
     * srcが一時ファイルの画像のURLならそのファイル名を返し、そうでなければnullを返す。
     * URLがUploadFilePath::tmpUrl()と完全に一致し、名前が安全な形で拡張子が画像のものだけを認める。
     * 添付ファイルの欄に上げたPDFのURLを本文に書かれても、画像として取り込まないため。
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
     * CSV取り込みで指定されたファイル名を確かめる。CsvImportから呼ぶ。
     * 問題があれば利用者へのメッセージを、無ければnullを返す。
     *
     * - アップロードと同じ安全な形であること。「/」や「..」を含まない
     * - 欄の種類に合った拡張子であること。同じ保存先にある添付ファイルのPDFを画像の欄に
     *   指定させないため
     * - このレコードの保存先にあること。受け取るのはファイル名だけで、必ずこのレコードの保存先で探す
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
     * レコードの保存先のディレクトリ。例：news/000/000012。規則はUploadFilePathが持つ。
     * 1件分のファイルはフィールドに関係なく同じディレクトリに置く。
     */
    private function uploadDirectory(Model $model): string
    {
        return UploadFilePath::directory($model::class, $model->getKey());
    }

    /** ファイルを消す。ディスクが公開か非公開かはフィールドで決まる。 */
    private function deleteUploadedFile(Model $model, string $field, string $filename): void
    {
        Storage::disk(UploadFilePath::disk($model::class, $field))->delete($this->uploadDirectory($model).'/'.$filename);
    }

    /**
     * 一時ファイルを、idが決まったレコードの保存先へ移し、ファイル名を返す。
     *
     * 名前の形はrules()で確かめてあるが、ファイルがあるかもここで確かめる。確認画面の間に
     * 片付けで消えたときや無い名前を送られたときに、移すところで例外になって処理全体が
     * 止まらないようにするため。無ければnullを返し、呼び元は_tmpが無かったときと同じに扱う。
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
            // 同じディスクならそのまま移す
            $tmpDisk->move($tmpPath, $finalPath);
        } else {
            // ディスクが違えば移せないので、書き写してから一時ファイルを消す
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
     * レコードを消す前に呼び、全フィールドのファイルを消す。複数の欄は子テーブルの行も消し、
     * エディタの欄は本文が使っているこのレコードの保存先の画像を消す。
     * レコードそのものは呼び元がdelete()する。
     *
     * 子テーブルの行はその場で消し、ファイルはcommitUploads()と同じくトランザクションが
     * 確定した後に消す。削除が取り消されたときに、残ったレコードのファイルが無くならないようにするため。
     */
    public function deleteAllUploads(Model $model): void
    {
        // ファイル名 => フィールド名。フィールド名はディスクを決めるのに使う
        $filenames = [];

        foreach ($this->uploadFieldDefinitions() as $def) {
            $base = $def['field'];

            // エディタの欄は本文の<img>から
            if ($def['kind'] === 'wysiwyg') {
                $filenames += array_fill_keys($this->wysiwygImageFilenames($model, $base, $model->{$base}), $base);

                continue;
            }

            // 複数の欄は子テーブルの行から集め、行を消す
            if ($def['kind'] === 'repeatable') {
                $filenames += array_fill_keys($model->{$base}()->pluck('filename')->all(), $base);
                $model->{$base}()->delete();

                continue;
            }

            // 1つだけの欄はカラムから
            if ($model->{$base}) {
                $filenames[$model->{$base}] = $base;
            }
        }

        DB::afterCommit(function () use ($model, $filenames) {
            foreach ($filenames as $filename => $field) {
                $this->deleteUploadedFile($model, $field, $filename);
            }

            // 最後にレコードのディレクトリごと消す。空のディレクトリを残さず、DBに記録の無い
            // ファイルも一緒に消える。上のnews/000はほかのレコードと共有なので消さない。
            // 公開と非公開の両方のディスクにありうるので両方で消す
            Storage::disk(UploadFilePath::PUBLIC_DISK)->deleteDirectory($this->uploadDirectory($model));
            Storage::disk(UploadFilePath::PRIVATE_DISK)->deleteDirectory($this->uploadDirectory($model));
        });
    }

    /**
     * 画面へ渡す$inputのうち、アップロードの欄の分。エディタの欄は含めない。
     * {field}・{field}_origin・{field}_tmp・{field}_delの4つを返し、複数の欄では
     * どれも同じ要素数の配列になる。フォームのname="attach[]"などの送られ方と同じ形。
     * プレビューのURLのような表示のためだけの値は含めない。
     *
     * $sourceは入力し直していた値で、そこに無いフィールドは$modelの今の値で補う。
     * - create   old()、$modelはnull
     * - edit     old()、$modelは対象のレコード
     * - confirm  検証済みの値、$modelは新規ならnull、更新なら対象のレコード
     * - show     空の配列、$modelは対象のレコード。いつも今の値になる
     */
    public function ajaxUploadInput(?Model $model, array $source = []): array
    {
        $result = [];

        foreach ($this->uploadFieldDefinitions() as $def) {
            $field = $def['field'];

            // エディタの欄は画像が本文に入っているので、呼び元がほかの項目と同じく入れる
            if ($def['kind'] === 'wysiwyg') {
                continue;
            }

            if ($def['kind'] === 'repeatable') {
                $result += $this->repeatableUploadInput($model, $field, $source);

                continue;
            }

            $originKey = "{$field}_origin";

            // ファイル名と表示名は、入力し直していた値があればそれを、無ければレコードの今の値を使う
            if (array_key_exists($field, $source)) {
                $result[$field] = $source[$field];
            } else {
                $result[$field] = $model->{$field} ?? null;
            }

            if (array_key_exists($originKey, $source)) {
                $result[$originKey] = $source[$originKey];
            } else {
                $result[$originKey] = $model->{$originKey} ?? null;
            }

            $result["{$field}_tmp"] = $source["{$field}_tmp"] ?? null;
            $result["{$field}_del"] = ($source["{$field}_del"] ?? null) == '1' ? '1' : '';
        }

        return $result;
    }

    /**
     * ajaxUploadInput()の複数の欄1つ分。4本の配列を必ず同じ要素数にそろえて返す。
     * $sourceにこの欄のキーが無ければ、$modelのリレーションの今の行を使う。
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

        // ふつうは4本の要素数はそろっているが、old()の値は要素数のずれで差し戻された入力の
        // こともあるので、欠けを埋めておく
        foreach ($names as $index => $name) {
            $result["{$field}_origin"][] = $origins[$index] ?? null;
            $result["{$field}_tmp"][] = $tmps[$index] ?? null;
            $result["{$field}_del"][] = ($dels[$index] ?? null) == '1' ? '1' : '';
        }

        return $result;
    }
}
