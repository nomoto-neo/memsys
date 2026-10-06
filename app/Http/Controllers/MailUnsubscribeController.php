<?php

namespace App\Http\Controllers;

use App\Support\MailUnsubscribe;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * お知らせメールの配信停止の画面。メールの中のURLから開くので、ログインは要らない。
 *
 * どちらの画面も、URLが正しいかや、そのアドレスの会員がいるかに関係なく、同じ表示にする。
 * 停止の中身と、そうする理由は、App\Support\MailUnsubscribeにある。
 */
class MailUnsubscribeController extends Controller
{
    // 「配信を停止する」のボタンの画面（GET /mail/unsubscribe）。開いただけでは何も変えない
    public function show(): View
    {
        return view('mail_unsubscribe.show');
    }

    // 停止の実行（POST /mail/unsubscribe）。メールソフトの「登録解除」のボタンからも、ここに届く。
    // メールソフトは移動先を追わないことがあるので、リダイレクトせずに完了の画面をそのまま返す
    public function store(Request $request): View
    {
        MailUnsubscribe::stop($request);

        return view('mail_unsubscribe.done');
    }
}
