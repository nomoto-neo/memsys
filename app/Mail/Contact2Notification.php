<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * /contact2（比較用）の通知メール。App\Mail\TemplatedMailとは違い、
 * テンプレートファイルは無く、宛先・件名・本文の組み立てをすべて
 * このクラス自身（PHP）が担う、Laravelでいちばん素直な書き方のMailable。
 *
 * ■ fromを指定していない理由
 *
 * envelope()に`from`を書かなければ、config/mail.phpのMAIL_FROM_ADDRESS・
 * MAIL_FROM_NAMEがそのまま使われる。宛先ごとに送信元を変える必要が
 * 無ければ、これで十分（App\Mail\TemplatedMailがFROM_MAILをテンプレート
 * 側で持っているのは「以前の仕組みの再現」のためであって、Laravelの
 * 標準的なMailableでは、こちらの「envelope()には何も書かず、
 * config任せにする」方が普通の書き方）。
 *
 * ■ 添付ファイルをアップロード直後の一時パスからそのまま使っている理由
 *
 * /contact2はconfirm画面を挟まない一段階の送信フォームなので、
 * アップロードされたファイルを画面をまたいで持ち越す必要が無い。
 * そのため、PHPが受け取った時点の一時ファイル（$_FILES的な、
 * リクエストが終わると自動で消える場所）のパスを、そのままこの
 * Mailableへ渡して添付している。
 *
 * ★ここが/contactのAjaxFileUpload方式と根本的に違う点：この作りは
 * 「メール送信が今のリクエストの中で同期的に(Mail::send())完了する」
 * ことが前提。もしこのMailableをキュー送信（ShouldQueueを実装する、
 * またはMail::to(...)->queue(...)を使う）に変えると、キューワーカーが
 * 実際に処理する時点ではリクエストがとっくに終わっていて一時ファイルは
 * 消えており、添付に失敗する。confirm画面をはさむ・キュー送信にする、
 * のどちらかをやる場合は、/contactのように「アップロード直後に確定の
 * 保存先へ移す」設計が必要になる（App\Support\AjaxFileUploadの
 * コメント参照）。
 */
class Contact2Notification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{name: string, email: string, message: ?string}  $data
     * @param  array{path: string, name: string}|null  $attachment
     */
    public function __construct(
        private readonly array $data,
        private readonly ?array $attachment = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            // 実際に使う場合は、担当者の受信アドレスに書き換えること。
            to: ['nomoto@neobit.jp'],
            // 「返信」すれば申込者に届くように。
            replyTo: [$this->data['email']],
            subject: "【お問い合わせ（比較用）】{$this->data['name']} 様より",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contact2',
            text: 'mail.contact2_text',
            with: ['data' => $this->data],
        );
    }

    /**
     * @return Attachment[]
     */
    public function attachments(): array
    {
        if ($this->attachment === null) {
            return [];
        }

        return [
            Attachment::fromPath($this->attachment['path'])->as($this->attachment['name']),
        ];
    }
}
