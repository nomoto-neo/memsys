<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 操作ログの補足（operation_logs.detail）の日本語を、そのままの表記に書き直す。
 *
 * 補足はJSONで、日本語は「\u4f1a\u54e1」のような表記で入っている。この表記だと、
 * 一覧の検索で、日本語の語を部分一致で探せない。モデルのキャスト（json:unicode）と同じく、
 * 日本語をそのまま持つ表記にそろえる。表記が変わるだけで、値は変わらない。
 */
return new class extends Migration
{
    /** 1回の問い合わせで読む件数 */
    private const CHUNK = 1000;

    public function up(): void
    {
        DB::table('operation_logs')
            ->whereNotNull('detail')
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($logs) {
                foreach ($logs as $log) {
                    $detail = json_decode($log->detail, true);

                    // JSONとして読めない行は、そのままにする
                    if (! is_array($detail)) {
                        continue;
                    }

                    $unicode = json_encode($detail, JSON_UNESCAPED_UNICODE);

                    // 英数字だけの行は表記が変わらないので、書き直さない
                    if ($unicode !== $log->detail) {
                        DB::table('operation_logs')->where('id', $log->id)->update(['detail' => $unicode]);
                    }
                }
            });
    }

    /** 表記が違うだけで値は同じなので、元の表記には戻さない */
    public function down(): void
    {
    }
};
