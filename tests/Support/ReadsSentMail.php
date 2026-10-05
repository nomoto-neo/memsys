<?php

namespace Tests\Support;

use Illuminate\Support\Collection;
use Symfony\Component\Mime\Email;

/**
 * テストの中で送ったメールを読む。テストではメールを実際には送らず、送ったものを配列に溜める
 * （phpunit.xmlのMAIL_MAILER=array）。その配列から、宛先・件名・本文を取り出す。
 */
trait ReadsSentMail
{
    /**
     * このテストの中で送ったメール。古い順。
     *
     * @return Collection<int, Email>
     */
    protected function sentMails(): Collection
    {
        return app('mailer')->getSymfonyTransport()->messages()
            ->map(fn ($sent) => $sent->getOriginalMessage())
            ->values();
    }

    // 最後に送ったメール。1通も送っていなければテストを失敗させる
    protected function lastMail(): Email
    {
        $mail = $this->sentMails()->last();

        $this->assertNotNull($mail, 'メールが1通も送られていません。');

        return $mail;
    }

    // 最後に送ったメールの本文にある、6桁の確認コード
    protected function lastVerificationCode(): string
    {
        $found = preg_match('/(?<!\d)(\d{6})(?!\d)/', (string) $this->lastMail()->getTextBody(), $matches);

        $this->assertSame(1, $found, '確認コードが本文にありません。');

        return $matches[1];
    }

    // 送ったメールの件名の一覧。古い順
    protected function sentSubjects(): array
    {
        return $this->sentMails()->map(fn (Email $mail) => $mail->getSubject())->all();
    }

    // メールの宛先のアドレス
    protected function recipientsOf(Email $mail): array
    {
        return array_map(fn ($address) => $address->getAddress(), $mail->getTo());
    }
}
