<?php

namespace Neobit\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Neobit\Core\Upload\UploadFilePath;

/**
 * アップロードの欄に、ファイルがあるかを確かめるルール。UPLOAD_FILESの'required' => trueの欄に、
 * AjaxFileUpload・RepeatableGroupが付ける。エラーは通常の項目と同じvalidation.requiredの文。
 *
 * 新しいファイル（_tmpの名前の一時ファイルが今ある）か、今のファイル（欄の値がレコードの今の値と一致し、
 * _delが立っていない）のどちらかがあれば通る。欄の値は書き換えられるので、レコードの今の値と照らす。
 * 新しく選んだだけのときは欄の値が空で届くので、値が空でも呼ばれるルール（implicit）にしている。
 */
class UploadRequiredRule implements DataAwareRule, ValidationRule
{
    /** 値が空でも検証する */
    public bool $implicit = true;

    private array $data = [];

    /**
     * $tmpKeyと$delKeyは、送信データの中の一時ファイルの名前と削除の印の場所（組の中の欄は'photos_image_tmp.0'の形）。
     * $currentFileは、レコードの今のファイル名。新規登録や新しい行ではnull
     */
    public function __construct(private string $tmpKey, private string $delKey, private ?string $currentFile)
    {
    }

    /** 検証の前に、Laravelが送信データ全体を渡すために呼ぶ */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /** 検証。新しいファイルも今のファイルも無ければ$fail()を呼ぶ */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 新しいファイル。名前の形を確かめてから、一時ディレクトリにあるかを見る
        $tmp = Arr::get($this->data, $this->tmpKey);
        if (is_string($tmp) && preg_match(UploadFilePath::SAFE_FILENAME, $tmp)
            && Storage::disk(UploadFilePath::TMP_DISK)->exists(UploadFilePath::TMP_DIR.'/'.$tmp)) {
            return;
        }

        // 今のファイル。消していなければ通る
        $deleted = Arr::get($this->data, $this->delKey) == '1';
        if ($this->currentFile !== null && $value === $this->currentFile && ! $deleted) {
            return;
        }

        $fail('validation.required')->translate();
    }
}
