<?php

namespace App\Models;

use App\Enums\BulkMailStatus;
use App\Support\UploadFilePath;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;

/**
 * 一斉メールの送信の記録。送るたびに1件作る。宛先は個人情報なので持たず、件数だけを持つ。
 *
 * ■ 氏名の差し込み
 * 件名と本文には、宛先の氏名を差し込む{{$name}}を書ける。書き方はBladeと同じだが、Bladeでは
 * 展開せず、文字列を置き換えるだけにする。画面から入力された文をBladeで展開すると、@phpなどで
 * サーバーの中の処理を何でも動かせてしまうため。{{ $name }}のように空白が入ってもよい。
 * ほかの{{…}}は書き間違いとして、入力のときにBulkMailPlaceholderRuleでエラーにする。
 */
class BulkMail extends Model
{
    // ログインしたスタッフだけが見られる場所に置くアップロードのフィールド。見てよいかはBulkMailPolicyが決める
    public const PRIVATE_FILE_FIELDS = ['attach'];

    // 入力の全角と半角をそろえない項目（App\Support\InputNormalizer）。
    // メールの件名と本文は、書いたとおりに送る
    public const RAW_INPUT_FIELDS = ['subject', 'body'];

    // 氏名の差し込みの印
    public const NAME_PLACEHOLDER = '/\{\{\s*\$name\s*\}\}/';

    // 差し込みの印に見えるものすべて。氏名の印のほかが残っていれば書き間違い
    public const ANY_PLACEHOLDER = '/\{\{.*?\}\}/s';

    protected $table = 't_bulk_mails';

    protected $fillable = [
        'subject',
        'body',
        'attach',
        'attach_origin',
        'csv_filename',
        'recipient_count',
        'sent_count',
        'failed_count',
        'status',
        'batch_id',
        'operator_id',
        'finished_at',
    ];

    protected $casts = [
        'status' => BulkMailStatus::class,
        'recipient_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'operator_id' => 'integer',
        'finished_at' => 'datetime',
    ];

    // 件名や本文の{{$name}}を、宛先の氏名に置き換える
    public static function fillName(string $text, string $name): string
    {
        return preg_replace_callback(self::NAME_PLACEHOLDER, fn () => $name, $text);
    }

    // 送信中の記録だけに絞る
    #[Scope]
    protected function sending(Builder $query): void
    {
        $query->where('status', BulkMailStatus::Sending);
    }

    // 送信の進み具合を持つジョブのバッチ。まだ積んでいないか、片付けで消えていればnull
    public function batch(): ?Batch
    {
        return $this->batch_id !== null ? Bus::findBatch($this->batch_id) : null;
    }

    // 添付ファイルのURL。添付が無ければnull
    protected function attachUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => UploadFilePath::url(self::class, $this->getKey(), 'attach', $this->attach),
        );
    }

    // 添付ファイルのサーバー上の場所。メールに付けるときに使う。添付が無ければnull
    protected function attachPath(): Attribute
    {
        return Attribute::make(
            get: fn () => UploadFilePath::path(self::class, $this->getKey(), 'attach', $this->attach),
        );
    }
}
