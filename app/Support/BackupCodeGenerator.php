<?php

namespace App\Support;

use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 2段階認証のバックアップコードの発行と照合。バックアップコードは、スマートフォンを
 * 無くしたときなどに使う1回だけ使える緊急用のコード。
 *
 * コードはパスワードと同じくハッシュ値にして保存し、元の値はDBに残さない。
 * 発行したときに画面に一度だけ見せたら、後からは見られない。
 */
class BackupCodeGenerator
{
    // 1回の発行で作るコードの個数。
    private const CODE_COUNT = 10;

    /**
     * 新しいバックアップコードを発行して保存し、画面に一度だけ見せる「1234-5678」の形の
     * 一覧を返す。再発行のたびに古いコードが有効なまま残らないよう、今あるコードは
     * 使用済みかどうかに関係なくすべて消してから作り直す。
     */
    public function generateFor(Staff $staff): array
    {
        // 途中で止まって古いコードも新しいコードも無い状態にならないよう、
        // 古いコードの削除と新しいコードの作成はまとめて行う
        return DB::transaction(function () use ($staff) {
            $staff->backupCodes()->delete();

            $plainCodes = [];

            for ($i = 0; $i < self::CODE_COUNT; $i++) {
                // 手で書き写したり口で伝えたりしやすいよう、見間違えやすい英字は使わず、数字だけ8桁にする
                $digits = (string) random_int(10000000, 99999999);

                $staff->backupCodes()->create([
                    // 照合はハイフンの無い8桁の数字で行う。ハイフンは見やすさのためだけ
                    'code_hash' => Hash::make($digits),
                ]);

                $plainCodes[] = substr($digits, 0, 4).'-'.substr($digits, 4, 4);
            }

            return $plainCodes;
        });
    }

    /**
     * $codeが、そのスタッフの使っていないバックアップコードのどれかと一致するかを確かめる。
     * 一致したコードは使用済みにして二度と使えなくする。
     * ハイフンや空白は気にせず、数字だけを取り出して照合する。
     */
    public function verifyAndConsume(Staff $staff, string $code): bool
    {
        // 数字だけにする
        $normalized = preg_replace('/\D/', '', $code) ?? '';

        if ($normalized === '') {
            return false;
        }

        // 使っていないコードと1つずつ照合し、一致したら使用済みにする
        $candidates = $staff->backupCodes()->whereNull('used_at')->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($normalized, $candidate->code_hash)) {
                $candidate->used_at = now();
                $candidate->save();

                return true;
            }
        }

        return false;
    }
}
