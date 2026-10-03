<?php

namespace App\Http\Controllers;

use App\Mail\Contact2Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * 比較用の問い合わせフォーム。ネオビットフレームワークの部品を使わない、
 * Laravelとしていちばん素直な書き方の例。
 *
 * 確認画面は無く、DBにも保存せず、メールを送るだけ。添付ファイルも、届いたものを
 * そのままメールに付け、どこにも残さない。必須マークも、ラベルに直接書いている。
 */
class Contact2Controller extends Controller
{
    // 入力の検証ルール
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'message' => ['nullable', 'string'],
            // mimesは拡張子だけでなく、実際のファイル内容から判定したMIMEタイプも見る
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,zip,jpg,jpeg,png'],
        ];
    }

    // フォームの表示
    public function create(): View
    {
        return view('contact2.create', [
            'input' => old(),
        ]);
    }

    // 送信：検証してメールを送り、フォームへ戻す
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        // 添付ファイルがあれば、メールに付ける。hasFile()は、ファイル欄が送信されたかに
        // 加えて、アップロード自体が壊れていないか（isValid()）まで見る
        $attachment = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachment = [
                // PHPが受け取った一時ファイルの実パス。リクエストが終わると消えるが、
                // メールはこの中で送り終える（同期送信）ので、そのまま使える
                // （理由はApp\Mail\Contact2Notificationのコメント参照）
                'path' => $file->getRealPath(),
                'name' => $file->getClientOriginalName(),
            ];
        }

        Mail::send(new Contact2Notification($validated, $attachment));

        return redirect()->route('contact2.create')
            ->with('status', 'お問合せいただきありがとうございます。1営業日以内に担当者よりご連絡させていただきます。');
    }
}
