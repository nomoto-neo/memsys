<?php

namespace App\Models;

use App\Enums\NoticeMail;
use Illuminate\Database\Eloquent\Model;

/**
 * 会員ではない人の、お知らせメール（メルマガ）を受け取るかどうか。メールアドレスごとに1行。
 * 会員の「受け取るか」は会員のテーブルで持つ。行の作り方と、会員の登録・退会とのつながりは、
 * App\Services\NewsletterSubscriptionにまとめてある。
 * 受け取るかはnotice_mailで、会員と同じ列挙型（App\Enums\NoticeMail）の値を持つ。
 */
class NewsletterSubscriber extends Model
{
    protected $table = 't_newsletter_subscribers';

    protected $fillable = [
        'name',
        'email',
        'notice_mail',
        'source',
        'subscribed_at',
        'unsubscribed_at',
        'ip',
    ];

    protected $casts = [
        // コード表の値と===で比べるので、intにそろえる
        'notice_mail' => 'integer',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    /** お知らせメールを受け取るか */
    public function receivesNoticeMail(): bool
    {
        return $this->notice_mail === NoticeMail::Receive->value;
    }
}
