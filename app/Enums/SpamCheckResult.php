<?php

namespace App\Enums;

/**
 * 訪問者向けフォームのスパム対策の判定結果（App\Support\SpamGuard::check()が返す）。
 */
enum SpamCheckResult
{
    /**
     * 人からの送信として先へ進めてよい。Cloudflare Turnstileに問い合わせできなかった
     * （障害など）ときも、送信を止めないためにこれになる。
     */
    case Passed;

    /**
     * 機械からの送信。ハニーポットの欄に入力がある、または表示してから送信までが
     * 速すぎる。送れたように見せて、何も保存しない（気付かれて対策されないように）。
     */
    case Bot;

    /**
     * Turnstileの判定に通らなかった（期限切れを含む）、または判定に要る値が無い。
     * 人がたまたま失敗することもあるので、入力画面に戻してもう一度試してもらう。
     */
    case Failed;
}
