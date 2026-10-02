<?php

namespace App\Mail;

use App\Support\MailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * resources/mail-templates/配下のテンプレートファイル1つから、送信元・
 * 宛先・件名・本文をすべて決めて送るMailable。
 *
 * 以前のフレームワークでは「テンプレートファイルの中身（FROM_MAIL:・
 * TO_MAIL:等の見出し＋本文）がメールの中身のすべてを決めていて、
 * 呼び出し側は変数と添付ファイルを渡すだけ」という作りだった。このクラスは
 * それをそのまま踏襲していて、他の多くのLaravelのMailable解説にあるような
 * 「envelope()の中でto()・subject()を固定で書く」形にはなっていない
 * （そこはテンプレートファイル任せ）。
 *
 * 呼び出し側は次のように使う（App\Http\Controllers\ContactController::store()参照）。
 *
 *   Mail::send(new TemplatedMail('contact_staff', [
 *       'name' => $inquiry->name,
 *       ...
 *   ], [
 *       ['path' => $absolutePath, 'name' => $originalFileName],
 *   ]));
 *
 * Mail::to(...)->send(...)ではなくMail::send(...)を直接使うのは、
 * 宛先をMailable自身（テンプレートファイル）が知っているため。
 * Mail::to()->send()を使うと、Mail::to()で指定した宛先と、envelope()が
 * 返す宛先の両方が足し合わされてしまい紛らわしい。
 */
class TemplatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array{from_mail: ?string, from_name: ?string, to: string[], cc: string[], bcc: string[], reply_to: string[], subject: ?string, body: string} */
    private array $parsed;

    /**
     * @param  string  $templateName  resources/mail-templates/{$templateName}.blade.phpを使う
     *         （HTML版は{$templateName}_html.blade.php。App\Support\MailTemplate参照）。
     * @param  array<string, scalar>  $vars  テンプレートの中で$変数名として使える値。
     * @param  array<int, array{path: string, name: string}>  $attachmentFiles  添付ファイル。
     *         path=サーバー上の実ファイルパス、name=メールに乗せる元のファイル名
     *         （以前のフレームワークの「添付ファイルの名前と実ファイルパスを渡す」
     *         にそのまま対応する）。
     *
     *         引数名を単純に$attachmentsにしなかったのは、親クラス
     *         Illuminate\Mail\Mailableが、旧来のbuild()スタイルの
     *         attach()が使うpublic array $attachments = [];という
     *         プロパティをすでに持っているため。コンストラクタ
     *         プロパティ昇格でここに同名のプロパティを宣言すると、
     *         「継承済みの（readonlyでない）プロパティを、readonlyとして
     *         再宣言している」とPHPに拒否される
     *         （Cannot redeclare non-readonly property ... as readonly ...）。
     *         Mailableを継承するクラスでプロパティ昇格を使うときは、
     *         このように親クラスの既存プロパティ名（$attachments・$from・
     *         $to・$subject等）と衝突しないか、あらかじめ確認すること。
     */
    public function __construct(
        string $templateName,
        array $vars,
        private readonly array $attachmentFiles = [],
    ) {
        $this->parsed = MailTemplate::render($templateName, $vars);
    }

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

    public function content(): Content
    {
        // 本文はプレーンテキストなので、Bladeの{{ }}によるHTMLエスケープを
        // 経由させないよう、{!! !!}だけの最小限のビュー（mail._raw_text）を
        // 通す（理由はApp\Support\MailTemplateのコメント参照）。
        //
        // {テンプレート名}_html.blade.phpが用意されていれば（App\Support\MailTemplate::
        // render()の'html'キーがnullでなければ）、view（HTML版）とtext
        // （プレーンテキスト版）の両方を同時に指定する。Laravelはこの
        // 組み合わせを「両対応のマルチパートメール」として送る
        // （Content::view()とContent::text()の両方を指定した場合の標準の
        // 動き。片方だけの指定なら、そのままシングルパートで送られる）。
        // HTML側もmail._raw_htmlという最小限のビューを経由させる理由・
        // エスケープ済みである理由はApp\Support\MailTemplateのコメント参照。
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
