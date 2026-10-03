<?php

use App\Models\News;
use App\Support\UploadFilePath;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * ニュース記事のファイル（一覧用画像・添付ファイル・本文の画像）を、公開のディスク
 * （"public"）から非公開のディスク（"local"、storage/app/private）へ移す。
 *
 * 記事は一般公開と会員限定を切り替えられるので、News::PRIVATE_FILE_FIELDSに
 * すべてのフィールドを載せ、見せるかどうかは記事の今の状態で決めるようにした
 * （App\Policies\NewsPolicy）。すでに保存されているファイルも同じ場所へ移す。
 *
 * - 記事1件分のディレクトリ（news/000/000012）の中身をまるごと移す
 * - 本文（body）の<img>のsrcに書いてある公開のURL（/storage/news/000/000012/xxx.jpg）を、
 *   非公開のURL（/uploads/news/12/body/xxx.jpg）に書き換える。書き換えるのは
 *   その記事自身のディレクトリを指すsrcだけで、それ以外の部分は変えない
 * - updated_atは変えない（CSV取り込みで、画面から変更された行を見分けるのに使っているため）
 *
 * 移し先にすでにあるファイルは移さないので、途中で止まっても、もう一度流せば続きから移る。
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->moveAll(UploadFilePath::PUBLIC_DISK, UploadFilePath::PRIVATE_DISK, toPrivate: true);
    }

    public function down(): void
    {
        $this->moveAll(UploadFilePath::PRIVATE_DISK, UploadFilePath::PUBLIC_DISK, toPrivate: false);
    }

    private function moveAll(string $fromDiskName, string $toDiskName, bool $toPrivate): void
    {
        $fromDisk = Storage::disk($fromDiskName);
        $toDisk = Storage::disk($toDiskName);

        DB::table('t_news')->select('id', 'body')->orderBy('id')
            ->each(function (object $news) use ($fromDisk, $toDisk, $toPrivate) {
                $directory = UploadFilePath::directory(News::class, $news->id);

                // ディスクをまたいだmoveはできないので、書き写してから元を消す
                foreach ($fromDisk->files($directory) as $path) {
                    if (! $toDisk->exists($path)) {
                        $stream = $fromDisk->readStream($path);
                        $toDisk->writeStream($path, $stream);
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                    $fromDisk->delete($path);
                }
                $fromDisk->deleteDirectory($directory);

                if ($news->body === null || $news->body === '') {
                    return;
                }

                $publicPrefix = Storage::disk(UploadFilePath::PUBLIC_DISK)->url($directory.'/');
                $privatePrefix = "/uploads/news/{$news->id}/body/";
                [$from, $to] = $toPrivate ? [$publicPrefix, $privatePrefix] : [$privatePrefix, $publicPrefix];

                $body = preg_replace('#(\ssrc=")'.preg_quote($from, '#').'(\w+\.\w+")#i', '$1'.$to.'$2', $news->body);

                if ($body !== $news->body) {
                    DB::table('t_news')->where('id', $news->id)->update(['body' => $body]);
                }
            });
    }
};
