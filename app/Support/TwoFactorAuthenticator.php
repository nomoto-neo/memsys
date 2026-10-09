<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

/**
 * 2段階認証のTOTPの、秘密鍵の発行・QRコードの画像の生成・コードの照合。
 * TOTPは、認証アプリが30秒ごとに変わる6桁のコードを出す方式。
 *
 * 中身はpragmarx/google2faとbacon/bacon-qr-codeの2つのパッケージで、パッケージの
 * 細かい使い方がコントローラーに散らばらないようこのクラスにまとめている。
 * QRコードは画像処理のPHP拡張が無くても作れるよう、SVGで描く。
 */
class TwoFactorAuthenticator
{
    /** 認証アプリ側で「どのサービスの鍵か」を見分けるために表示される発行者名。 */
    private const ISSUER = 'memsys管理画面';

    /**
     * 照合で前後に許すずれ。1が30秒分。端末とサーバーの時計が少しずれていても
     * 弾かれないよう、前後30秒ずつで合わせて90秒分まで許す。
     */
    private const VERIFY_WINDOW = 1;

    private Google2FA $engine;

    /** パッケージのTOTPの処理を用意する */
    public function __construct()
    {
        $this->engine = new Google2FA();
    }

    /**
     * 新しい秘密鍵を作る。呼ぶたびに違う値になるので、セッションなどに控えて画面を
     * 読み直しても同じ鍵を使い続けること。変わると認証アプリの登録と食い違って通らなくなる。
     */
    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    /**
     * 秘密鍵と認証アプリに出すアカウント名から、QRコードの画像を作る。
     * <img>のsrcにそのまま書けるdata URIの形で返す。
     */
    public function qrCodeSvgDataUri(string $secret, string $holder): string
    {
        $otpAuthUrl = $this->engine->getQRCodeUrl(self::ISSUER, $holder, $secret);

        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle(240),
                new SvgImageBackEnd()
            )
        );

        $svg = $writer->writeString($otpAuthUrl);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * 入力された6桁のコードが、その秘密鍵から見て正しいかを確かめる。
     * verifyKey()は1つ目が秘密鍵、2つ目が入力されたコード。逆にすると必ず不一致になる。
     */
    public function verifyCode(string $secret, string $code): bool
    {
        return $this->engine->verifyKey($secret, $code, self::VERIFY_WINDOW);
    }
}
