<?php

namespace App\Support;

use ReflectionClass;

/**
 * 入力された文字の、全角と半角の揺らぎをそろえる。
 *
 * 住所や電話番号のような個人情報が、入力した人によって全角だったり半角だったりするのを防ぐ。
 * 同じ人や同じ住所を、書き方の違いで別のものとして扱わないようにするため。
 * 画面からの入力（App\Http\Middleware\NormalizeInput）と、CSV取り込みのセル（CsvReader）の
 * 両方から呼ぶので、そろえる中身をここ1か所に持つ。どちらも、検証の前に通す。
 *
 * ■ そろえる内容
 * - 全角の英字と数字を、半角にする
 * - 半角カナを、全角カナにする。濁点と半濁点は、前の文字と1文字にまとめる
 * - メールアドレスやURLに使う記号（＠－．：／）を、半角にする
 * ひらがなとカタカナは変換しない。フリガナにひらがなを入れたときは、検証でエラーにする
 * （App\Rules\KatakanaRule）。空白も変換しない。
 *
 * ■ そろえない項目
 * 改行を含む値も長い値も、区別せずにそろえる。どの項目がそろえられるかを、値の中身で
 * 変わらないようにするため。そろえないのは、次の2つだけ。
 * - パスワード。入力されたままを照合する（ミドルウェアの側で外している）
 * - モデルの定数RAW_INPUT_FIELDSに書いた項目。本文のように、書いたとおりに残す文章
 *
 * ■ そろえない項目の書き方
 * データの仕様なので、モデルに持つ。コントローラーは、同じ名前の定数でそれを引く。
 *     // モデル
 *     public const RAW_INPUT_FIELDS = ['body'];
 *     // コントローラー
 *     private const RAW_INPUT_FIELDS = News::RAW_INPUT_FIELDS;
 * 入力をそろえる時点では、どのモデルの入力かがまだ分からないので、そのリクエストを受け持つ
 * コントローラーの定数を見る。定数を書いていないコントローラーでは、全部の項目をそろえる。
 */
final class InputNormalizer
{
    /** そろえない項目を書く、コントローラーの定数の名前 */
    private const CONTROLLER_CONSTANT = 'RAW_INPUT_FIELDS';

    /** 半角にする記号。全角 => 半角 */
    private const SYMBOLS = [
        '＠' => '@',
        '－' => '-',
        '．' => '.',
        '：' => ':',
        '／' => '/',
    ];

    /** 1つの値をそろえる */
    public static function normalize(string $value): string
    {
        // K：半角カナを全角カナに　V：濁点を前の文字とまとめる　r：全角の英字を半角に　n：全角の数字を半角に
        $value = mb_convert_kana($value, 'KVrn', 'UTF-8');

        return strtr($value, self::SYMBOLS);
    }

    /**
     * そのコントローラーが、そろえないと決めている項目の名前。定数を書いていなければ空。
     * 定数はほかの設定と同じくprivateで書けるよう、見える範囲に関係なく読む。
     *
     * @param  class-string|null  $controllerClass  コントローラーの無いルートではnull
     * @return string[]
     */
    public static function rawFieldsOf(?string $controllerClass): array
    {
        if ($controllerClass === null || ! class_exists($controllerClass)) {
            return [];
        }

        $class = new ReflectionClass($controllerClass);

        return $class->hasConstant(self::CONTROLLER_CONSTANT) ? (array) $class->getConstant(self::CONTROLLER_CONSTANT) : [];
    }
}
