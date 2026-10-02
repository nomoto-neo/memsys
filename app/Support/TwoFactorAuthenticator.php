<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP（Google Authenticator等のアプリで30秒ごとに変わる6桁のコードを
 * 出す方式）の、秘密鍵の発行・QRコード用SVGの生成・コードの検証をまとめた
 * ラッパー。
 *
 * 実体はpragmarx/google2fa（秘密鍵の生成・コード検証・otpauth://URLの
 * 組み立て）とbacon/bacon-qr-code（そのURLをQRコードの画像として描く）の
 * 2つのパッケージ。この2つを直接コントローラーから呼ぶと、パッケージ
 * 固有の詳細（otpauth://のURI形式、SVG描画に必要な3クラスの組み合わせ）が
 * あちこちに散らばってしまうので、このクラスに閉じ込めている。
 *
 * ■ 必要なパッケージ
 * 次の2つをcomposerで入れておくこと。
 *   composer require pragmarx/google2fa bacon/bacon-qr-code
 *
 * ■ QRコードの描画方式について
 * 画像処理系のPHP拡張（GD・Imagick）が入っていなくても動くよう、
 * ラスター画像（PNG等）ではなくSVG（ベクター画像）で描画している。
 * <img src="data:image/svg+xml;base64,...">の形でそのままビューに渡せる。
 */
class TwoFactorAuthenticator
{
    /**
     * 認証アプリ側で「どのサービスの鍵か」を見分けるために表示される発行者名。
     */
    private const ISSUER = 'memsys管理画面';

    /**
     * verifyCode()で許容する前後のステップ数（1ステップ=30秒）。
     * 端末とサーバーの時計に多少のズレがあっても弾かれないよう、
     * 前後1ステップ（実質90秒分）まで許容している。
     */
    private const VERIFY_WINDOW = 1;

    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA();
    }

    /**
     * 新しい秘密鍵（Base32文字列）を生成する。呼ぶたびに毎回違う値になるので、
     * QRコードを表示する前に必ずセッション等へ保存し、同じ画面を再読み込み
     * しても同じ鍵を使い続けるようにすること（呼ぶたびに変えてしまうと、
     * 認証アプリ側の登録と食い違って永久に検証が通らなくなる）。
     */
    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    /**
     * $secret（このスタッフ用に発行済みの秘密鍵）と$holder（メールアドレス
     * など、認証アプリ上でどのアカウントか分かる表示名）から、QRコードの
     * SVG画像をdata URI（<img>のsrcにそのまま渡せる文字列）として作る。
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
     * 認証アプリに表示されている6桁のコードが、$secretから見て正しいかを
     * 検証する。verifyKey()の第1引数が秘密鍵、第2引数が入力されたコード
     * （逆にすると常に不一致になるので注意。GitHub本体のソースで確認済み）。
     */
    public function verifyCode(string $secret, string $code): bool
    {
        return $this->engine->verifyKey($secret, $code, self::VERIFY_WINDOW);
    }
}
