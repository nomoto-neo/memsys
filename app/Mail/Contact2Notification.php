<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 比較用の問い合わせフォーム（/contact2）の通知メール。テンプレートファイルを使わず、
 * 宛先・件名・本文をこのクラスで組み立てる、Laravelでいちばん素直な書き方の例。
 *
 * 送信元はenvelope()に書かず、config/mail.phpの値に任せる。宛先ごとに送信元を
 * 変えないなら、これがLaravelの普通の書き方。
 *
 * 添付ファイルは、PHPが受け取った一時ファイルのパスをそのまま使う。一時ファイルは
 * リクエストが終わると消えるので、この作りはメールを同じリクエストの中で送り終える
 * ことが前提になる。キューで後から送るように変えるなら、/contactのように、
 * アップロードしたファイルを先に保存しておく作りにする必要がある。
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
            // 実際に使うときは、担当者の受信アドレスに書き換える
            to: ['nomoto@neobit.jp'],
            // 返信すれば、問い合わせた人に届くように
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
