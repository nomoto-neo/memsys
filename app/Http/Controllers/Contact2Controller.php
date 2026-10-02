<?php

namespace App\Http\Controllers;

use App\Mail\Contact2Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * /contact2（比較用）：私のフレーム基盤の考え方（App\Support\MailTemplate・
 * MailTemplateParser）も、App\Support\AjaxFileUploadの二段階アップロードも
 * 使わない、Laravelとしていちばん素直な書き方の問い合わせフォーム。
 *
 * App\Http\Controllers\ContactController（/contact）との違い：
 * - confirm画面を挟まない一段階の送信（create→store→create（フラッシュ
 *   メッセージ）だけ）。理由はApp\Mail\Contact2Notificationのコメント参照。
 * - 添付ファイルは、フォームの<input type="file">からそのまま届いた
 *   ファイルを、保存せずにメールへ添付するだけ（DBにもファイルにも
 *   痕跡を残さない）。
 * - DBへの保存は行わない（メール送信だけ）。
 * - rules()から必須マークを組み立てる仕組み（required_fields()）も、
 *   比較を単純にするため使わず、ラベルにrequired_mark()を直接書いている。
 */
class Contact2Controller extends Controller
{
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'message' => ['nullable', 'string'],
            // mimesは拡張子だけでなく実際のファイル内容から判定した
            // MIMEタイプも見る、Laravel標準のバリデーション。
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,zip,jpg,jpeg,png'],
        ];
    }

    public function create(): View
    {
        return view('contact2.create', [
            'input' => old(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        // hasFile()は「ファイル欄が送信されたか」だけでなく、
        // アップロード自体が壊れていないか（isValid()）まで見て判定する。
        $attachment = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachment = [
                // getRealPath()はPHPが受け取った一時ファイルの実パス。
                // このリクエストが終わると自動的に削除される
                // （Mail::send()を同期実行している今の作りだから安全に使える。
                // 理由はApp\Mail\Contact2Notificationのコメント参照）。
                'path' => $file->getRealPath(),
                'name' => $file->getClientOriginalName(),
            ];
        }

        Mail::send(new Contact2Notification($validated, $attachment));

        return redirect()->route('contact2.create')
            ->with('status', 'お問合せいただきありがとうございます。1営業日以内に担当者よりご連絡させていただきます。');
    }
}
