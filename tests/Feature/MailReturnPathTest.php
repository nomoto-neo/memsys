<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 届かなかったときのエラーのメールの戻り先（config/mail.phpのreturn_path。.envのMAIL_RETURN_PATH）。
 * 書いてあれば、すべてのメールのReturn-Pathと、SMTPの封筒の送信元になる。
 */
class MailReturnPathTest extends TestCase
{
    /** 設定を変えてから、メールを1通送り、送ったものを返す */
    private function sendWithReturnPath(?string $address)
    {
        config(['mail.return_path.address' => $address]);

        // 設定は、メールの部品を作るときに読まれるので、作り直す
        app('mail.manager')->forgetMailers();
        Mail::clearResolvedInstances();

        Mail::raw('本文', fn ($message) => $message->to('taro@example.com')->subject('件名'));

        return app('mailer')->getSymfonyTransport()->messages()->last();
    }

    public function test_return_path_is_added_to_every_mail(): void
    {
        $sent = $this->sendWithReturnPath('bounce@example.com');

        $this->assertSame('bounce@example.com', $sent->getOriginalMessage()->getReturnPath()->getAddress());
        $this->assertSame('bounce@example.com', $sent->getEnvelope()->getSender()->getAddress());
    }

    public function test_mail_returns_to_the_sender_without_the_setting(): void
    {
        $sent = $this->sendWithReturnPath(null);

        $this->assertNull($sent->getOriginalMessage()->getReturnPath());
        $this->assertSame(config('mail.from.address'), $sent->getEnvelope()->getSender()->getAddress());
    }
}
