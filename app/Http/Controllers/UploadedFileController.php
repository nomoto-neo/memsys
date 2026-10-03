<?php

namespace App\Http\Controllers;

use App\Support\UploadFilePath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Webサーバーから直接は見えない場所（"local"ディスク）に置いたアップロード
 * ファイルを、見てよい人にだけ返す。保存先とURLの規則は
 * App\Support\UploadFilePathの「非公開」「一時ディレクトリ」を参照。
 *
 * - show()  非公開のフィールド（モデルのPRIVATE_FILE_FIELDS）の保存済みファイル。
 *           そのファイルが本当にそのフィールドのものかをDBで確かめ、ログイン中の
 *           ユーザー（会員・スタッフのどのガードでも）の誰かが、持ち主のモデルの
 *           PolicyのviewFiles($user, $record, $field)で許されたときだけ返す。
 * - tmp()   確認画面を経て保存するまでの一時ファイル。アップロードした
 *           セッション（AjaxFileUploadがセッションに覚えた名前）にだけ返す。
 *
 * 見てはいけない人には、ファイルがあるかどうかも分からないよう、403ではなく
 * 404を返す。ルートにauthのミドルウェアを付けないのは、会員・スタッフの
 * どちらのガードでログインしていても使えるようにするため（管理画面からも
 * マイページからも同じURLで見る）と、tmp()はログインしていない訪問者
 * （お問い合わせの添付ファイル）も使うため。
 */
class UploadedFileController extends Controller
{
    // 非公開のフィールドの保存済みファイル（GET /uploads/{type}/{id}/{field}/{filename}）
    public function show(string $type, int $id, string $field, string $filename): BinaryFileResponse
    {
        // URLの種類の名前（enforceMorphMap()の名前）から、持ち主のモデルを探す
        $ownerClass = Relation::getMorphedModel($type);

        // 非公開のフィールドでなければ、モデルのプロパティを読む前に断る
        abort_unless($ownerClass !== null && UploadFilePath::isPrivate($ownerClass, $field), 404);
        abort_unless(preg_match(UploadFilePath::SAFE_FILENAME, $filename) === 1, 404);

        $owner = $ownerClass::find($id);

        abort_unless($owner !== null && $this->fileBelongsToField($owner, $field, $filename), 404);
        abort_unless($this->canViewFiles($owner, $field), 404);

        $path = UploadFilePath::directory($ownerClass, $id).'/'.$filename;
        $disk = Storage::disk(UploadFilePath::PRIVATE_DISK);

        abort_unless($disk->exists($path), 404);

        return $this->privateFileResponse($disk->path($path));
    }

    // 一時ファイル（GET /uploads/tmp/{filename}）
    public function tmp(Request $request, string $filename): BinaryFileResponse
    {
        abort_unless(preg_match(UploadFilePath::SAFE_FILENAME, $filename) === 1, 404);
        abort_unless(in_array($filename, $request->session()->get(UploadFilePath::TMP_SESSION_KEY, []), true), 404);

        $path = UploadFilePath::TMP_DIR.'/'.$filename;
        $disk = Storage::disk(UploadFilePath::TMP_DISK);

        abort_unless($disk->exists($path), 404);

        return $this->privateFileResponse($disk->path($path));
    }

    /**
     * ファイルを返すレスポンス。ブラウザやプロキシに残させない（ログアウトした後に、
     * 同じ端末の別の人が履歴やキャッシュから見られないようにする）。
     * BinaryFileResponseは既定でCache-Controlをpublicにするので、privateに直す。
     */
    private function privateFileResponse(string $absolutePath): BinaryFileResponse
    {
        $response = response()->file($absolutePath, ['X-Content-Type-Options' => 'nosniff']);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    /**
     * そのファイルが、今DBでそのフィールドに保存されているものか。Policyをフィールドごとに
     * 分けたとき、見てよいフィールドのURLに、別のフィールドのファイル名を入れて見られないようにする。
     * - 複数のフィールド（attach.*）: 同じ名前のHasManyリレーションの行にあるか
     * - 単数のフィールド: カラムの値と同じか
     * - WYSIWYG欄: 本文の中に、このファイルのURLがあるか
     */
    private function fileBelongsToField(Model $owner, string $field, string $filename): bool
    {
        if ($owner->isRelation($field)) {
            return $owner->{$field}()->where('filename', $filename)->exists();
        }

        $value = (string) $owner->{$field};
        $url = UploadFilePath::url($owner::class, $owner->getKey(), $field, $filename);

        return $value === $filename || str_contains($value, '"'.$url.'"');
    }

    /**
     * ログイン中のユーザーの誰かが、このモデルのこのフィールドのファイルを見てよいか。
     * 同じブラウザで会員とスタッフの両方にログインしていることもあるので、
     * config/auth.phpのガードを順に見て、1つでも許されればよい。
     */
    private function canViewFiles(Model $owner, string $field): bool
    {
        foreach (array_keys(config('auth.guards')) as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user !== null && Gate::forUser($user)->allows('viewFiles', [$owner, $field])) {
                return true;
            }
        }

        return false;
    }
}
