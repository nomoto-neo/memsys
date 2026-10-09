<?php

namespace App\Http\Middleware;

use App\Support\InputNormalizer;
use Closure;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;
use Illuminate\Support\Str;

/**
 * 画面からの入力の、全角と半角の揺らぎをそろえるミドルウェア。画面のルートの全部に掛かる。
 *
 * そろえる中身と、そろえない項目の決め方は、App\Support\InputNormalizerにある。
 * 検証の前に通るので、全角の数字で入力された電話番号も、半角に直ってから検証される。
 * コントローラーと画面に書くものは無い。そろえない項目があるコントローラーだけ、
 * 定数RAW_INPUT_FIELDSを書く。
 *
 * そのリクエストを受け持つコントローラーの定数を見るので、ルートが決まった後に通す。
 * bootstrap/app.phpで、画面のルートのグループ（web）に足している。
 */
class NormalizeInput extends TransformsRequest
{
    /**
     * どの画面でも、そろえない項目。パスワードは、入力されたままを照合する。
     * そろえる中身を後で変えたときに、それまでのパスワードが通らなくなることを防ぐため
     */
    private const EXCEPT = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /** このリクエストで、そろえない項目の名前 */
    private array $rawFields = [];

    public function handle($request, Closure $next)
    {
        // コントローラーが、そろえないと決めている項目を読む
        $this->rawFields = InputNormalizer::rawFieldsOf($request->route()?->getControllerClass());

        return parent::handle($request, $next);
    }

    protected function transform($key, $value)
    {
        if (! is_string($value) || Str::is(self::EXCEPT, $key)) {
            return $value;
        }

        // 配列の中の値は「項目名.番号」の形で届くので、先頭の項目名で比べる
        if (in_array(explode('.', (string) $key)[0], $this->rawFields, true)) {
            return $value;
        }

        return InputNormalizer::normalize($value);
    }
}
