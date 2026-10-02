<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// 従来のPHPで「電話番号チェック用の関数」を1つ作って使い回していたのと同じ発想。
// Laravelでは「バリデーションルールをクラスにする」という形でこれを行う。
//
// make:rule で生成すると、Laravel が用意した ValidationRule という
// インターフェース（契約）を実装するクラスの雛形ができる。
// インターフェースなので、validate() という名前・引数のメソッドを
// 必ず持つことだけが決められていて、中身は完全に自由。
class PhoneNumberRule implements ValidationRule
{
    /**
     * 実際のチェック処理。
     *
     * $attribute … チェック対象のフィールド名（例: 'phone'）
     * $value     … チェック対象の値そのもの（例: '090-1234-5678'）
     * $fail      … 「これはNGだった」とLaravelに伝えるための関数（呼び出すとバリデーション失敗になる）
     *
     * 戻り値は無い(void)。真偽値を返すのではなく、
     * ダメだったときにだけ $fail() を呼ぶ、という設計になっている。
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // ハイフンを含むかどうかで、許容する桁数を変える。
        // ハイフンあり: 例 090-1234-5678(13桁) / 03-1234-5678(12桁) / 0120-1-2345(11桁) など → 11〜13文字
        // ハイフンなし: 例 09012345678(11桁) / 0312345678(10桁) など → 9〜11桁
        if (str_contains($value, '-')) {
            $pattern = '/^[0-9\-]{11,13}$/';
        } else {
            $pattern = '/^[0-9]{9,11}$/';
        }

        if (! preg_match($pattern, $value)) {
            $fail('電話番号の形式が正しくありません。');
        }
    }
}
