<?php

use App\Models\Inquiry;
use App\Support\UploadFilePath;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * お問い合わせの添付ファイル（t_inquiries.attach_file）を、公開のディスク（"public"）から
 * 非公開のディスク（"local"、storage/app/private）へ移す。
 *
 * Inquiry::PRIVATE_FILE_FIELDSに'attach_file'を載せたので、これからの添付ファイルは
 * 非公開の場所に保存される。すでに保存されているファイルも同じ場所へ移し、
 * URLで直接見られないようにする。DBの値（ファイル名）は変わらない。
 *
 * 移し先にすでにあるファイルは移さないので、途中で止まっても、もう一度流せば続きから移る。
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->moveFiles(UploadFilePath::PUBLIC_DISK, UploadFilePath::PRIVATE_DISK);
    }

    public function down(): void
    {
        $this->moveFiles(UploadFilePath::PRIVATE_DISK, UploadFilePath::PUBLIC_DISK);
    }

    private function moveFiles(string $fromDiskName, string $toDiskName): void
    {
        $fromDisk = Storage::disk($fromDiskName);
        $toDisk = Storage::disk($toDiskName);

        DB::table('t_inquiries')->whereNotNull('attach_file')->orderBy('id')
            ->each(function (object $inquiry) use ($fromDisk, $toDisk) {
                $path = UploadFilePath::directory(Inquiry::class, $inquiry->id).'/'.$inquiry->attach_file;

                if (! $fromDisk->exists($path) || $toDisk->exists($path)) {
                    return;
                }

                // ディスクをまたいだmoveはできないので、書き写してから元を消す
                $stream = $fromDisk->readStream($path);
                $toDisk->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                $fromDisk->delete($path);
            });
    }
};
