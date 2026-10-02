<?php

namespace App\Support;

use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 2段階認証のバックアップコード（スマートフォンを紛失した際などに使う、
 * 1回だけ使える緊急用コード）の発行・検証をまとめたクラス。
 *
 * コードそのものはDBに平文で保存せず、パスワードと同じ考え方で
 * Hash::make()（bcrypt）してcode_hashに保存する。発行時にgenerateFor()が
 * 返す平文の配列は、画面に一度表示したらそれきりで、DBのどこにも
 * 残らない（パスワードの再発行はできても、元の値を確認する手段が
 * 無いのと同じ発想）。
 */
class BackupCodeGenerator
{
    /**
     * 1回の発行で作るコードの個数。
     */
    private const CODE_COUNT = 10;

    /**
     * 新しいバックアップコードを発行し、$staffに紐づけて保存する。
     * 既存のコード（未使用・使用済み問わず）は全て削除してから作り直す
     * （リセットや再発行のたびに、古いコードが有効なまま残らないように
     * するため）。
     *
     * 戻り値は画面に一度だけ表示する、ハイフン区切りの平文コード一覧
     * （例: "1234-5678"）。
     */
    public function generateFor(Staff $staff): array
    {
        // 古いコードの削除と新しいコードの作成は、途中で止まって「古いコードも
        // 新しいコードも無い」状態にならないよう、まとめて行う
        return DB::transaction(function () use ($staff) {
            $staff->backupCodes()->delete();

            $plainCodes = [];

            for ($i = 0; $i < self::CODE_COUNT; $i++) {
                // 見間違えやすい英字（O/0、I/1等）を避け、数字だけ8桁にする
                // （手で書き写す・電話口で伝える場面を想定）。
                $digits = (string) random_int(10000000, 99999999);

                $staff->backupCodes()->create([
                    // 照合はハイフンを含まない8桁の数字そのもので行う
                    // （表示用のハイフンは見やすさのためだけに付けている）。
                    'code_hash' => Hash::make($digits),
                ]);

                $plainCodes[] = substr($digits, 0, 4).'-'.substr($digits, 4, 4);
            }

            return $plainCodes;
        });
    }

    /**
     * $codeが、$staffの未使用のバックアップコードのいずれかと一致するかを
     * 確認する。一致すればそのコードをused_atで使用済みにし、以後
     * 再利用できなくする（1回限り）。
     *
     * ハイフンの有無や余分な空白は気にせず、数字だけを取り出して照合する。
     */
    public function verifyAndConsume(Staff $staff, string $code): bool
    {
        $normalized = preg_replace('/\D/', '', $code) ?? '';

        if ($normalized === '') {
            return false;
        }

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
