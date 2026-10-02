<?php

namespace App\Http\Controllers;

use App\Mail\TemplatedMail;
use App\Models\Inquiry;
use App\Rules\PhoneNumberRule;
use App\Support\AjaxFileUpload;
use App\Support\FormFlow;
use App\Support\UploadFilePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * お問い合わせフォーム（/contact）。入力 → 確認 → 送信。
 *
 * 管理画面の登録と同じくFormFlowで作っている。違うのは、保存した後にスタッフへ
 * 通知メールを送ることと、確認画面を経由した1回だけの送信を保証する
 * confirm_tokenがあることだけ。
 */
class ContactController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 入力→確認→保存の共通処理はFormFlowトレイトが提供する。
    // クラス側は rules() saveFieldNames() と、必要なら prepareInput() などを用意する。
    use FormFlow;

    // 添付ファイルのAjaxアップロードまわりの共通処理はAjaxFileUploadトレイトが提供する。
    use AjaxFileUpload;

    // ---- アップロード（AjaxFileUpload）の設定 ----

    // AjaxFileUploadが要求する設定。フィールド名 => 横幅(px)。
    // attach_fileは1件のみ（複数展開ではない）添付ファイル欄なので".*"は付けない。
    // 横幅0は「画像ではなく添付ファイル」を意味する。
    private const UPLOAD_FILES = [
        'attach_file' => 0,
    ];

    // ---- 確認画面から送信までの順番の保証（confirm_token）の設定 ----

    // confirm→storeの順番を保証するためのトークンを、セッションのどのキーに入れるか。
    // 値そのものはissueConfirmToken()・hasValidConfirmToken()参照。
    private const CONFIRM_TOKEN_SESSION_KEY = 'contact.confirm_token';

    // ---- このコーナーの項目の定義 ----

    /**
     * 入力バリデーションルール。
     *
     * AjaxFileUpload::ajaxUploadRules()が、attach_file・attach_file_tmp・
     * attach_file_origin・attach_file_delのルールをUPLOAD_FILESの定義から
     * 自動生成して返すので、それをそのまま+で足す（Admin\NewsController
     * と同じ考え方）。
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kana' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', new PhoneNumberRule()],
            // ハイフンの有無どちらでも受け付ける。フォーム側のJavaScript
            // （resources/js/contact_form.js）が7桁になった時点で
            // "123-4567"の形に自動整形するが、JavaScriptが動かない
            // 環境からの送信（ハイフン無しの7桁）も弾かない。
            'zip' => ['nullable', 'regex:/^\d{3}-?\d{4}$/'],
            'prefecture' => ['nullable', 'integer', Rule::in(code_keys('prefectures'))],
            'city' => ['nullable', 'string', 'max:255'],
            'address_other' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            // acceptedは「値が'1'などの"真"を表す文字列であること」に加えて
            // 「未入力（チェックを外したまま送信）」も弾く、必須のチェック
            // ボックス向けのルール。'required'という文字列を含まないので、
            // create()で必須マークを組み立てるときに別途足している。
            'agree' => ['accepted'],
        ] + $this->ajaxUploadRules();
    }

    // 保存する項目（t_inquiriesのカラム）。ここに書いた項目だけを保存する。
    // 添付ファイル（attach_file等）はcommitUploads()で保存するので、ここには書かない。
    // agree（同意のチェック）は確認のためだけの項目なので保存しない。
    private function saveFieldNames(array $validated, Inquiry $inquiry): array
    {
        return ['name', 'kana', 'email', 'phone', 'zip', 'prefecture', 'city', 'address_other', 'body'];
    }

    // 検証の後、確認画面の表示・保存の前に行う整形。
    private function prepareInput(array $validated): array
    {
        // 郵便番号を"123-4567"の形にそろえる（ハイフン無しの7桁が来たときだけ差し込む）
        $zip = $validated['zip'] ?? null;
        $digits = preg_replace('/[^0-9]/', '', (string) $zip);
        $validated['zip'] = match (true) {
            $zip === null || $zip === '' => null,
            strlen($digits) === 7 => substr($digits, 0, 3).'-'.substr($digits, 3),
            default => $zip,
        };

        return $validated;
    }

    // ---- 確認画面から送信までの順番の保証（confirm_token） ----

    /**
     * 確認画面を表示するたびに1個発行する使い捨てトークン。セッションに
     * 保存すると同時に、確認画面のhiddenフィールドにも同じ値を埋める
     * （resources/views/contact/confirm.blade.php）。store()側で両者が
     * 一致するかだけを見る、CSRFトークンとは別枠の仕組み。
     *
     * ■ これはCSRFトークン（@csrf）と何が違うのか
     *
     * CSRFトークンは「別サイトからの偽装リクエストを防ぐ」ためのもので、
     * セッションにひもづいた1個の値を、ページを開いている間ずっと使い回す
     * （確認画面を何回表示しても同じ@csrfトークンのまま）。そのため、
     * 正規のCSRFトークンさえ持っていれば、/contact/confirmを経由せずに
     * 直接/contact/storeへPOSTすることを妨げない。
     *
     * このconfirm_tokenは逆に「confirm→storeの順番で1回だけPOSTされた
     * こと」を保証するためのもの。確認画面を表示するたびに新しい値を
     * 発行してセッションを上書きし、store()で保存できた時点でセッションから
     * 消す（store()内のsession()->forget()呼び出し参照）。これにより、
     *   - confirm画面を経由せずに直接storeへPOSTする（トークンをセッションに
     *     持っていないので必ず不一致になる）
     *   - 同じ確認画面の内容を2回submitする（1回目の成功でセッションから
     *     消えるので、2回目は不一致になる）
     * のどちらも弾けるようになる。
     */
    private function issueConfirmToken(): string
    {
        $token = Str::random(40);

        session([self::CONFIRM_TOKEN_SESSION_KEY => $token]);

        return $token;
    }

    /**
     * $requestのconfirm_tokenが、セッションに保存されている値と一致するか。
     * 単純な===比較は、一致しない文字が現れた時点で比較を打ち切るため、
     * 「正解の値と何文字目まで一致していたか」がごくわずかな処理時間の
     * 差として外部から観測できる余地が（理論上は）生まれる。hash_equals()
     * は常に全体を比較してから結果を返すため、この時間差が生まれない
     * （CSRFトークンなど、秘密の値をユーザー入力と比較する場面での定石）。
     * 両方とも文字列であることをis_string()で先に確認しているのは、
     * hash_equals()に文字列以外を渡すとTypeErrorになるため（sessionに
     * 何も入っていない＝nullのときに、比較そのもので例外が起きないように
     * する）。
     */
    private function hasValidConfirmToken(Request $request): bool
    {
        $expected = session(self::CONFIRM_TOKEN_SESSION_KEY);
        $actual = $request->input('confirm_token');

        return is_string($expected) && is_string($actual) && hash_equals($expected, $actual);
    }

    // ---- 入力・確認・送信 ----

    // フォームの表示
    public function create(): View
    {
        return view('contact.create', [
            // old() があればそちらを優先
            'input' => $this->formInput(null, old()),
            // agreeは'accepted'ルールで'required'を含まないので、必須マークだけ足す
            'required' => $this->requiredFields(null, ['agree']),
        ]);
    }

    // 入力内容のバリデーションと、確認画面の表示
    public function confirmStore(Request $request): View
    {
        return view('contact.confirm', [
            'input' => $this->confirmInput($request),
            // $inputには混ぜない（$inputは「送信される業務項目だけ」という
            // resources/views/_confirm_hiddenの前提を保つため）。確認画面の
            // 「送信する」フォームにだけ、この専用のhiddenとして直接埋める。
            'confirmToken' => $this->issueConfirmToken(),
        ]);
    }

    // 確認画面の「戻る」
    public function back(Request $request): RedirectResponse
    {
        // confirm_tokenは入力画面では使わない制御用の値なので、old()経由で持ち越さない
        return redirect()->route('contact.create')
            ->withInput($request->except(['_token', 'confirm_token']));
    }

    // 送信の実行：t_inquiriesへの保存と、スタッフへの通知メール送信
    public function store(Request $request): RedirectResponse
    {
        // confirm画面を経由せずに直接ここへPOSTされた場合と、確認済みの
        // 内容が2回submitされた場合（詳しくはissueConfirmToken()の
        // コメント参照）は、ここで弾いて入力画面へ差し戻す。バリデーション
        // より前に見ているのは、順序を無視したリクエストに対しては
        // 業務データの中身を見るまでもなく門前払いにしたいため。
        if (! $this->hasValidConfirmToken($request)) {
            return redirect()->route('contact.create')
                ->with('error', '確認画面を経由せずに送信されたか、確認画面の有効期限が切れています。お手数ですが、入力からやり直してください。');
        }

        // 保存（検証・添付ファイルの確定を含む）
        $inquiry = new Inquiry();
        $this->saveData($inquiry, $request);

        // 保存できたら、トークンを使い切る（＝セッションから消す）。同じ確認画面の内容を
        // もう一度送信されても、上のチェックで弾かれる。検証に落ちたときは、saveData()の
        // 中で入力画面へ戻るので、ここまでは来ない。
        session()->forget(self::CONFIRM_TOKEN_SESSION_KEY);

        // メールは保存のトランザクションが確定した後に送る（送信に失敗しても、保存した問い合わせは取り消さない）
        $this->sendStaffNotification($inquiry);

        return redirect()->route('contact.thanks');
    }

    // 送信完了画面の表示
    public function thanks(): View
    {
        return view('contact.thanks');
    }

    // ---- 通知メール ----

    /**
     * スタッフへの通知メール送信。
     *
     * メール送信に失敗しても、問い合わせ自体はすでにt_inquiriesへ
     * 保存済みなので、ここで例外を投げて訪問者にエラー画面を見せる
     * ことはしない（せっかく入力してもらった内容がsubmitのやり直しで
     * 失われるのを避ける）。失敗はログに残し、担当者が後で気づける
     * ようにするだけに留める。
     */
    private function sendStaffNotification(Inquiry $inquiry): void
    {
        $attachments = [];

        if ($inquiry->attach_file) {
            $attachments[] = [
                'path' => Storage::disk('public')->path(
                    UploadFilePath::directory(Inquiry::class, $inquiry->id).'/'.$inquiry->attach_file
                ),
                'name' => $inquiry->attach_file_origin ?: $inquiry->attach_file,
            ];
        }

        try {
            Mail::send(new TemplatedMail('contact_staff', [
                'from_mail' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'staff_mail' => config('contact.staff_email'),
                'name' => $inquiry->name,
                'furigana' => $inquiry->kana,
                'email' => $inquiry->email,
                'phone' => $inquiry->phone ?: '（未入力）',
                'zip' => $inquiry->zip ?: '（未入力）',
                'prefecture' => code_label('prefectures', $inquiry->prefecture),
                'city' => $inquiry->city ?: '',
                'address_other' => $inquiry->address_other ?: '',
                'body' => $inquiry->body ?: '（本文なし）',
            ], $attachments));
        } catch (\Throwable $e) {
            Log::error('ContactController: 問い合わせ通知メールの送信に失敗しました。', [
                'inquiry_id' => $inquiry->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    // Ajaxアップロード処理（実体はApp\Support\AjaxFileUpload::uploadAjaxFile()）。
    // ルーティングからこのメソッド名を直接指定できるよう、あえてここに
    // 明示のuploadAjaxFile()は書かず、トレイト側の実装をそのまま使う。
}
