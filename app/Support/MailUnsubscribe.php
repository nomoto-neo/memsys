<?php

namespace App\Support;

use App\Enums\NoticeMail;
use App\Enums\OperationLogAction;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * お知らせメール（一斉メール）の配信停止。メールに載せるURLを作り、そのURLからの停止を受け付ける。
 * 広告のメールには配信停止の方法を載せる決まりがあり、Gmailなども、大量に送る相手に
 * ワンクリックで解除できることを求めているため。
 *
 * ■ URL
 * 宛先のメールアドレスを入れた署名付きのURLにする。署名があるので、アドレスを他人のものに
 * 書き換えたURLでは停止できない。期限は付けない。古いメールからでも停止できるようにするため。
 * 署名はパスとクエリだけで作る。キューのワーカーが作るURLと、訪問者が開くURLとで、
 * ホスト名やhttpsかどうかの見え方が違っても、照合できるようにするため。
 *
 * ■ 停止
 * URLを開くと「配信を停止する」のボタンの画面が出て、押すと停止する。開いただけで停止しないのは、
 * 受信側のウイルス検査やプレビューが、メールの中のURLを自動で開くことがあるため。
 * そのアドレスの会員がいれば「受け取らない」（App\Enums\NoticeMail）に変える。
 * 会員がいなくても、URLが正しくなくても、画面の表示は同じにする。そのアドレスの会員が
 * いるかどうかを、外から確かめられないようにするため。
 * メールソフトの「登録解除」のボタン（List-Unsubscribeのヘッダー）からは、同じURLへPOSTが届く。
 *
 * ■ 操作ログ
 * 停止の操作は、結果に関係なく全部を残す。会員がいたときは、対象を会員の種類とidで持つ。
 * 会員がいないときとURLが正しくないときは、どのアドレスへの操作かが分かるよう、
 * メールアドレスを補足に残す。操作ログには個人情報を残さない決まりの、ここだけの例外。
 * 会員ではない宛先から停止の操作があったことを、次に送るCSVから外すために確かめられるようにする。
 */
final class MailUnsubscribe
{
    /** 停止の画面のルート名 */
    private const ROUTE = 'mail.unsubscribe';

    /** 操作ログに残すメールアドレスの長さの上限。URLに何を書かれても、長い文字を残さない */
    private const LOGGED_EMAIL_MAX_LENGTH = 255;

    /** その宛先の、配信停止のURL */
    public static function url(string $email): string
    {
        return url(URL::signedRoute(self::ROUTE, ['email' => $email], absolute: false));
    }

    /**
     * メールに付けるヘッダー。メールソフトが「登録解除」のボタンを出すのに使う。
     *
     * @return array<string, string>
     */
    public static function headers(string $url): array
    {
        return [
            'List-Unsubscribe' => "<{$url}>",
            // ボタンを押したときに、メールソフトが確認の画面を挟まずにPOSTしてよい印
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];
    }

    /**
     * 停止の操作を受け付ける。結果は返さない。呼ぶ側が、結果で画面を変えないようにするため。
     */
    public static function stop(Request $request): void
    {
        $email = (string) $request->query('email');
        $loggedEmail = mb_substr($email, 0, self::LOGGED_EMAIL_MAX_LENGTH);

        // URLが正しくないとき。アドレスを書き換えたURLなので、何も変えない
        if ($email === '' || ! $request->hasValidRelativeSignature()) {
            OperationRecorder::record(OperationLogAction::MailUnsubscribe, detail: [
                'result' => 'URLが正しくない',
                'email' => $loggedEmail,
            ]);

            return;
        }

        $member = Member::where('email', $email)->first();

        // そのアドレスの会員がいないとき。会員ではない宛先か、退会やアドレスの変更の後
        if ($member === null) {
            OperationRecorder::record(OperationLogAction::MailUnsubscribe, detail: [
                'result' => '会員が見つからない',
                'email' => $loggedEmail,
            ]);

            return;
        }

        // すでに「受け取らない」のとき
        if (! $member->receivesNoticeMail()) {
            OperationRecorder::record(OperationLogAction::MailUnsubscribe, $member, detail: [
                'result' => '停止済み',
            ]);

            return;
        }

        // 「受け取らない」に変える。本人へのお知らせのメールは送らない
        DB::transaction(function () use ($member) {
            $member->update(['notice_mail' => NoticeMail::Stop->value]);

            OperationRecorder::record(OperationLogAction::MailUnsubscribe, $member, changedFields: ['notice_mail']);
        });
    }
}
