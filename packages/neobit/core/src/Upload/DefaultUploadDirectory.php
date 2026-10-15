<?php

namespace Neobit\Core\Upload;

use Illuminate\Support\Str;

/**
 * 保存先のディレクトリの既定の規則。
 * 「モデルのクラス名 / idの下3桁を除いた部分 / 前ゼロ付き6桁のid」。
 *
 *   ニュース id=12       → news/000/000012
 *   ニュース id=1234567  → news/1234/1234567
 *
 * - idを6桁にそろえるのは、ディレクトリを並べたときに番号の順になるようにするため
 * - 下3桁を除いたディレクトリを挟むのは、1つのディレクトリに入るのを1000件までにするため。
 *   idが7桁を超えてもこの部分が伸びるだけで、1000件ずつにまとまる
 * - フィールドごとのディレクトリは作らず、1件分のファイルは1つのディレクトリに置く。
 *   ファイル名はランダムなので重ならず、レコードを消すときはディレクトリごと消せる
 * - クラス名を含めるので、会員とニュースで同じidがあっても保存先は分かれる
 * - idは整数が前提。文字列のidには対応しない
 */
final class DefaultUploadDirectory implements UploadDirectoryRule
{
    public static function directory(string $ownerClass, int|string $ownerKey): string
    {
        // 前ゼロ付き6桁。sprintfの%06dは最低6桁という意味で、6桁を超えるidは切り詰めずにそのままの桁数になる
        $padded = sprintf('%06d', (int) $ownerKey);

        // 下3桁を除いた部分。6桁なら上の3桁
        $group = substr($padded, 0, -3);

        return Str::snake(class_basename($ownerClass)).'/'.$group.'/'.$padded;
    }
}
