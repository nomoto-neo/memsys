<?php

namespace App\Mail;

use App\Support\MailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * テンプレートファイル1つで、送信元・宛先・件名・本文のすべてを決めて送るメール。
 * テンプレートはresources/mail-templates/に置き、呼び出し側は変数と添付ファイルを渡すだけ。
 *
 *   Mail::send(new TemplatedMail('contact_staff', [
 *       'name' => $inquiry->name,
 *       ...
 *   ], [
 *       ['path' => $absolutePath, 'name' => $originalFileName],
 *   ]));
 *
 * 宛先はテンプレートが持っているので、Mail::to()は使わずにMail::send()で送る。
 * Mail::to()を使うと、そこで指定した宛先とテンプレートの宛先が足し合わされてしまうため。
 */
class TemplatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array{from_mail: ?string, from_name: ?string, to: string[], cc: string[], bcc: string[], reply_to: string[], subject: ?string, body: string} */
    private array $parsed;

    /**
     * @param  string  $templateName  使うテンプレート。resources/mail-templates/{$templateName}.blade.php。
     *         HTML版は{$templateName}_html.blade.phpに置く（App\Support\MailTemplate参照）。
     * @param  array<string, scalar>  $vars  テンプレートの中で$変数名として使える値。
     * @param  array<int, array{path: string, name: string}>  $attachmentFiles  添付ファイル。
     *         pathはサーバー上のファイルの場所、nameはメールに付けるときのファイル名。
     *         $attachmentsという名前は、親クラスのMailableがすでに持っているので使えない。
     * @param  array<string, string>  $headerLines  メールに足すヘッダー。ヘッダーの名前 => 値。
     *         一斉メールの配信停止のヘッダー（App\Support\MailUnsubscribe）のように、
     *         テンプレートの見出しの行では書けないものに使う。
     */
    public function __construct(
        string $templateName,
        array $vars,
        private readonly array $attachmentFiles = [],
        private readonly array $headerLines = [],
    ) {
        $this->parsed = MailTemplate::render($templateName, $vars);
    }

    /** 送信元・宛先・件名。テンプレートに送信元が無ければ、config/mail.phpの値を使う */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                $this->parsed['from_mail'] ?? config('mail.from.address'),
                $this->parsed['from_name'] ?? config('mail.from.name'),
            ),
            to: $this->parsed['to'],
            cc: $this->parsed['cc'],
            bcc: $this->parsed['bcc'],
            replyTo: $this->parsed['reply_to'],
            subject: $this->parsed['subject'] ?? '',
        );
    }

    /** 呼び出し側が足したヘッダー */
    public function headers(): Headers
    {
        return new Headers(text: $this->headerLines);
    }

    /**
     * 本文。テンプレートで組み立て済みの本文を、そのまま出すだけの最小限のビューに通す
     * （エスケープを重ねないため。理由はApp\Support\MailTemplate参照）
     */
    public function content(): Content
    {
        // HTML版のテンプレートがあれば、HTMLとテキストの両方を付けたマルチパートにする
        if ($this->parsed['html'] !== null) {
            return new Content(
                view: 'mail._raw_html',
                text: 'mail._raw_text',
                with: [
                    'body' => $this->parsed['body'],
                    'htmlBody' => $this->parsed['html'],
                ],
            );
        }

        // それ以外は、テキストだけのメール
        return new Content(
            text: 'mail._raw_text',
            with: ['body' => $this->parsed['body']],
        );
    }

    /**
     * @return Attachment[]
     */
    public function attachments(): array
    {
        return array_map(
            fn (array $a) => Attachment::fromPath($a['path'])->as($a['name']),
            $this->attachmentFiles,
        );
    }
}
