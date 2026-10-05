<?php

namespace App\Http\Controllers;

use App\Enums\SpamCheckResult;
use App\Mail\TemplatedMail;
use App\Models\Inquiry;
use App\Rules\KatakanaRule;
use App\Rules\PhoneNumberRule;
use App\Support\AjaxFileUpload;
use App\Support\FormFlow;
use App\Support\SpamGuard;
use App\Support\UploadFilePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * お問い合わせフォーム。入力、確認、送信の順に進む。
 *
 * 管理画面の登録と同じくFormFlowで作り、送信の後にスタッフへ通知メールを送る。
 * 確認画面を通った1回だけの送信を合言葉で保証し、確認画面へ進むときにスパム対策を行う。
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

    // ---- スパム対策（SpamGuard）の設定 ----

    // 入力画面を表示してから「確認画面へ進む」までの、いちばん短い秒数。これより速い送信は
    // 機械からとみなす（名前・メール・本文の入力と同意のチェックに、人ならこれ以上かかる）。
    private const SPAM_GUARD_MIN_SECONDS = 3;

    // ---- 入力をそろえる処理（InputNormalizer）の設定 ----

    // 全角と半角をそろえない項目。データの仕様なので、モデルの指定を引く
    private const RAW_INPUT_FIELDS = Inquiry::RAW_INPUT_FIELDS;

    // ---- このコーナーの項目の定義 ----

    // 入力バリデーションルール。添付ファイルのhiddenのルールは、ajaxUploadRules()が
    // UPLOAD_FILESから作るので、それを足す。
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kana' => ['required', 'string', 'max:255', new KatakanaRule()],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', new PhoneNumberRule()],
            // ハイフンの有無どちらでも受け付ける（画面のJavaScriptが"123-4567"の形に整えるが、
            // JavaScriptが動かない環境からのハイフン無しの7桁も弾かない。prepareInput()で整える）
            'zip' => ['nullable', 'regex:/^\d{3}-?\d{4}$/'],
            'prefecture' => ['nullable', 'integer', Rule::in(code_keys('prefectures'))],
            'city' => ['nullable', 'string', 'max:255'],
            'address_other' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            // 同意のチェック。acceptedは、チェックが無い（未送信）ときも弾く。'required'を
            // 含まないので、必須マークはcreate()で別に足している
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
     * 確認画面を表示するたびに、使い捨ての合言葉を発行する。セッションと確認画面のhiddenに
     * 同じ値を置き、store()で一致するかを確かめる。
     *
     * CSRFトークンは、別のサイトからの偽の送信を防ぐもので、ページを開いている間ずっと同じ値を
     * 使う。そのため、確認画面を通らずに送信の処理へ直接送ることは防げない。
     * この合言葉は、確認画面を表示するたびに新しい値にし、保存できたら消す。確認画面を通らない
     * 送信と、同じ確認画面からの2回目の送信を、どちらも弾ける。
     */
    private function issueConfirmToken(): string
    {
        $token = Str::random(40);

        session([self::CONFIRM_TOKEN_SESSION_KEY => $token]);

        return $token;
    }

    /**
     * 送られてきた合言葉が、セッションに置いた値と一致するか。
     *
     * ===ではなくhash_equals()で比べる。===は食い違った所で比べるのをやめるので、何文字目まで
     * 合っていたかが、処理時間の差から理論上は分かってしまう。hash_equals()は文字列しか
     * 受け付けないので、先に両方が文字列かを確かめる。
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
    public function confirmStore(Request $request): View|RedirectResponse
    {
        // スパム対策（App\Support\SpamGuard）。確認画面から先は、ここを通った人にだけ発行する
        // confirm_tokenで守るので、送信（store()）では確かめない
        $spam = SpamGuard::check($request, minSeconds: self::SPAM_GUARD_MIN_SECONDS);

        if ($spam === SpamCheckResult::Bot) {
            // 機械からの送信。送れたように見せて、何も保存しない（気付かれて対策されないように）
            return redirect()->route('contact.thanks');
        }

        if ($spam === SpamCheckResult::Failed) {
            // 人がたまたま失敗することもあるので、入力を残したまま入力画面に戻す
            return redirect()->route('contact.create')
                ->withInput($request->except([
                    '_token',
                    // スパム対策の値は持ち越さない（入力画面を出し直すと新しい値になる）
                    SpamGuard::HONEYPOT_FIELD,
                    SpamGuard::STARTED_FIELD,
                    SpamGuard::TURNSTILE_FIELD,
                ]))
                ->with('error', 'ロボットによる送信ではないことを確認できませんでした。お手数ですが、もう一度「確認画面へ進む」を押してください。');
        }

        // 入力を検証して、確認画面を表示する
        return view('contact.confirm', [
            'input' => $this->confirmInput($request),
            // 確認画面の「送信する」フォームにだけ埋める合言葉。$inputには混ぜない
            // （$inputは送信される業務の項目だけ、という_confirm_hiddenの前提を保つため）
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
        // 確認画面を通っていない送信と、同じ内容の2回目の送信は、中身を検証する前に
        // 入力画面へ戻す（issueConfirmToken()のコメント参照）
        if (! $this->hasValidConfirmToken($request)) {
            return redirect()->route('contact.create')
                ->with('error', '確認画面を経由せずに送信されたか、確認画面の有効期限が切れています。お手数ですが、入力からやり直してください。');
        }

        // 保存（検証・添付ファイルの確定を含む）
        $inquiry = new Inquiry();
        $this->saveData($inquiry, $request);

        // 保存できたら合言葉を使い切る（同じ内容をもう一度送られても、上で弾かれる）。
        // 検証に落ちたときは、saveData()の中で入力画面へ戻るので、ここまでは来ない
        session()->forget(self::CONFIRM_TOKEN_SESSION_KEY);

        // スタッフへの通知メール。保存のトランザクションが確定した後に送る
        // （送信に失敗しても、保存した問い合わせは取り消さない）
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
     * スタッフへの通知メールの送信。
     *
     * 送れなくても、問い合わせはもう保存してあるので、訪問者にエラー画面は見せない。
     * 失敗はログに残し、担当者が後で気付けるようにする。
     */
    private function sendStaffNotification(Inquiry $inquiry): void
    {
        // 添付ファイルがあれば、メールに付ける（非公開の場所に置いたファイル）
        $attachments = [];

        if ($inquiry->attach_file) {
            $attachments[] = [
                'path' => UploadFilePath::path(Inquiry::class, $inquiry->id, 'attach_file', $inquiry->attach_file),
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

    // 添付ファイルのAjaxアップロード（contact.ajaxUploadのルート）は、トレイトの
    // App\Support\AjaxFileUpload::uploadAjaxFile()をそのまま使う。
}
