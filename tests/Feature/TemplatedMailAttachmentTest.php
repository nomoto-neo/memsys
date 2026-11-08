<?php

namespace Tests\Feature;

use LogicException;
use Neobit\Core\Mail\TemplatedMail;
use Tests\TestCase;

/**
 * テンプレートのメール（Neobit\Core\Mail\TemplatedMail）の添付ファイル。ファイルのパスのほかに、
 * 中身を直接渡して付けられること。作ったPDFを、ファイルに書かずにメールに付けるため。
 */
class TemplatedMailAttachmentTest extends TestCase
{
    /** お問い合わせのテンプレートに渡す値 */
    private const VARS = [
        'from_mail' => 'from@example.com',
        'from_name' => 'サイト',
        'staff_mail' => 'staff@example.com',
        'email' => 'visitor@example.com',
        'name' => '山田 太郎',
        'furigana' => 'ヤマダ タロウ',
        'phone' => '',
        'zip' => '',
        'prefecture' => '',
        'city' => '',
        'address_other' => '',
        'body' => '本文',
    ];

    public function test_data_is_attached_with_the_type_from_the_name(): void
    {
        $mail = new TemplatedMail('contact_staff', self::VARS, [
            ['data' => '%PDF-1.4 テスト', 'name' => '履歴書_山田.pdf'],
        ]);

        $mail->assertHasAttachedData('%PDF-1.4 テスト', '履歴書_山田.pdf', ['mime' => 'application/pdf']);
    }

    public function test_path_is_attached_as_before(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($path, 'テスト');

        try {
            $mail = new TemplatedMail('contact_staff', self::VARS, [
                ['path' => $path, 'name' => 'メモ.txt'],
            ]);

            $mail->assertHasAttachment($path, ['as' => 'メモ.txt']);
        } finally {
            unlink($path);
        }
    }

    public function test_both_path_and_data_are_rejected(): void
    {
        $this->expectException(LogicException::class);

        new TemplatedMail('contact_staff', self::VARS, [
            ['path' => '/tmp/a.pdf', 'data' => '%PDF', 'name' => 'a.pdf'],
        ]);
    }

    public function test_neither_path_nor_data_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        new TemplatedMail('contact_staff', self::VARS, [
            ['name' => 'a.pdf'],
        ]);
    }

    public function test_missing_name_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        new TemplatedMail('contact_staff', self::VARS, [
            ['data' => '%PDF'],
        ]);
    }
}
