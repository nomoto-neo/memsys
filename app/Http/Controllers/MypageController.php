<?php

namespace App\Http\Controllers;

use App\Enums\OperationLogAction;
use App\Mail\TemplatedMail;
use App\Models\Member;
use App\Rules\KatakanaRule;
use App\Rules\PhoneNumberRule;
use App\Support\AjaxFileUpload;
use App\Support\EmailChange;
use App\Support\FormFlow;
use App\Support\LoginSession;
use App\Support\MemberActivityLog;
use App\Support\MemberProfileNotice;
use App\Support\OperationRecorder;
use App\Support\PasskeyManagement;
use App\Support\PdfDownload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * マイページ。ログイン中の会員本人が、プロフィールの表示と編集、履歴書のPDF、退会、
 * パスキーの管理を行う。
 *
 * プロフィールの編集は、確認画面を挟まずに保存する。メールアドレスが変わるときだけ、
 * 新しいアドレスに送った確認コードの入力を挟む。対象は常にログイン中の本人で、
 * パスワードの変更はAuthPasswordControllerが受け持つ。
 */
class MypageController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // プロフィール編集の検証・保存はFormFlowトレイトが提供する（確認画面は挟まない）。
    // クラス側は rules() saveFieldNames() inputFromModel() を用意する。
    use FormFlow;

    // 顔写真のAjaxアップロード（入口のuploadAjaxFile()もトレイト側）。
    // クラス側は UPLOAD_FILES を用意し、rules()に ajaxUploadRules() を足す。
    use AjaxFileUpload;

    // 履歴書のPDF。クラス側は入口でdownloadPdf()を呼ぶだけ。
    use PdfDownload;

    // パスキーの一覧・登録・削除（passkeyIndex()など。App\Support\PasskeyManagement参照）。
    // 使わないサイトでは、このuseとroutes/web.phpのmypage.passkeysのルートを消す。
    use PasskeyManagement;

    // メールアドレスが変わる保存に、確認コードの入力を挟む（emailChangeForm()など。App\Support\EmailChange参照）。
    // クラス側は EMAIL_CHANGE_ の定数を用意し、update()でholdForEmailChange()を呼ぶ。
    use EmailChange;

    // ---- アップロード（AjaxFileUpload）の設定 ----

    /**
     * フィールド名 => 横幅(px)。管理画面（Admin\MemberController）と同じ。顔写真は非公開の
     * フィールド（Member::PRIVATE_FILE_FIELDS）なので、本人とスタッフだけが見られる場所に保存される。
     */
    private const UPLOAD_FILES = [
        'photo' => Member::PHOTO_WIDTH,
    ];

    // ---- パスキー（PasskeyManagement）の設定 ----

    /** ログイン中の会員を取るガード。 */
    private const PASSKEY_GUARD = 'web';

    /** パスキーの一覧画面のルート名（登録・削除などのルート名は、この後ろに.confirmなどを付ける）。 */
    private const PASSKEY_ROUTE = 'mypage.passkeys';

    /** パスキーの一覧画面のビュー。 */
    private const PASSKEY_VIEW = 'mypage.passkeys';

    /** 登録の前の本人確認（メールの確認コード）の試行制限（LoginThrottle）のカウンターの名前。 */
    private const PASSKEY_THROTTLE_SCOPE = 'member-passkey-code';

    // ---- メールアドレスの変更の確認（EmailChange）の設定 ----

    /** ログイン中の会員を取るガード。 */
    private const EMAIL_CHANGE_GUARD = 'web';

    /** 確認コードの入力画面のルート名（照合・再送などのルート名は、この後ろに.confirmなどを付ける）。 */
    private const EMAIL_CHANGE_ROUTE = 'mypage.email';

    /** 確認コードの入力画面のビュー。 */
    private const EMAIL_CHANGE_VIEW = 'mypage.email-verify';

    /** 入力画面のルート名。 */
    private const EMAIL_CHANGE_EDIT_ROUTE = 'mypage.edit';

    /** 保存の後の移動先のルート名と、そこに出すメッセージ。 */
    private const EMAIL_CHANGE_DONE_ROUTE = 'mypage';

    private const EMAIL_CHANGE_DONE_MESSAGE = 'プロフィールを更新しました。';

    /** 確認コードの試行制限（LoginThrottle）と、送信の回数の制限のカウンターの名前。 */
    private const EMAIL_CHANGE_THROTTLE_SCOPE = 'member-email-change-code';

    // ---- プロフィールの項目の定義 ----

    /**
     * プロフィール編集の検証ルール。$memberはログイン中の会員
     * （メールアドレスの重複チェックで、自分自身を除くのに使う）。
     */
    private function rules(Member $member): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kana' => ['nullable', 'string', 'max:255', new KatakanaRule()],
            'email' => [
                'required', 'string', 'email', 'max:255',
                // 自分以外の会員と重ならないこと
                Rule::unique(Member::class, 'email')->ignore($member->id),
            ],
            'phone' => ['nullable', 'string', new PhoneNumberRule()],
            'birthdate' => ['nullable', 'date'],
            'prefecture' => [
                'nullable', 'integer',
                // コードテーブルとの一致を確認
                Rule::in(code_keys('prefectures')),
            ],
            // お知らせメールを受け取るかどうか
            'notice_mail' => [
                'required', 'integer',
                // コードテーブルとの一致を確認
                Rule::in(code_keys('notice_mail')),
            ],
        ] + $this->ajaxUploadRules();
    }

    /**
     * 保存する項目（t_membersのカラム）。顔写真はcommitUploads()が保存するので書かない。
     * パスワードはこのフォームでは扱わない（変更はAuthPasswordControllerの専用フォーム）。
     */
    private function saveFieldNames(array $validated, Member $member): array
    {
        return ['name', 'kana', 'email', 'phone', 'birthdate', 'prefecture', 'notice_mail'];
    }

    /** モデルの今の値から、編集画面に渡す$inputを組み立てる。 */
    private function inputFromModel(Member $member): array
    {
        return [
            'name' => $member->name,
            'kana' => $member->kana,
            'email' => $member->email,
            'phone' => $member->phone,
            'birthdate' => $member->birthdate?->format('Y-m-d'),
            'prefecture' => $member->prefecture,
            'notice_mail' => $member->notice_mail,
        ];
    }

    /**
     * 保存の直後の処理。会員情報が変わったことを、本人へメールで知らせる
     * （App\Support\MemberProfileNotice参照）。
     */
    private function afterSave(Member $member, array $validated, array $changedFields): void
    {
        MemberProfileNotice::send($member, $changedFields);
    }

    // ---- マイページ・プロフィール編集 ----

    /**
     * マイページの表示（GET /mypage）。
     * ログイン中の会員は、このコントローラーではいつもAuth::user()で取る。
     */
    public function index(): View
    {
        return view('mypage.index', [
            'member' => Auth::user(),
        ]);
    }

    /** プロフィール編集フォームの表示（GET /mypage/edit） */
    public function edit(): View
    {
        $member = Auth::user();

        // 入力欄の値。old()があればそちらを優先し、無ければ会員の今の値
        // （顔写真のhiddenの値も含む）を使う（FormFlow::formInput()）
        return view('mypage.edit', [
            'member' => $member,
            'input' => $this->formInput($member, old()),
            'required' => $this->requiredFields($member),
        ]);
    }

    /**
     * プロフィールの更新（PATCH /mypage）。
     * 確認画面を挟まないので、saveData()をそのまま呼ぶ。検証に失敗すれば、編集画面へ戻る。
     */
    public function update(Request $request): RedirectResponse
    {
        $member = Auth::user();

        // メールアドレスが変わるときは、保存せずに確認コードの入力画面へ進む。
        // 保存は、コードが入力できた時点で行う（App\Support\EmailChange）
        $toVerify = $this->holdForEmailChange($request, $member);

        if ($toVerify !== null) {
            return $toVerify;
        }

        $this->saveData($member, $request);

        return redirect()->route('mypage')->with('status', self::EMAIL_CHANGE_DONE_MESSAGE);
    }

    /**
     * 履歴書のPDFをブラウザの中で開く（GET /mypage/resume）。
     * 管理画面のAdmin\MemberController::resume()と同じPDF。
     */
    public function resume(): Response
    {
        $member = Auth::user();

        OperationRecorder::record(OperationLogAction::Pdf, $member, detail: ['name' => '履歴書']);

        return $this->downloadPdf(
            view: 'pdf.resume',
            data: ['member' => $member],
            name: '履歴書_'.$member->name,
            images: [
                // 顔写真は、履歴書の写真の大きさ（横3:縦4）に切り抜いて貼る
                'photo' => [
                    'path' => $member->photo_path,
                    'aspect' => Member::PHOTO_ASPECT,
                ],
            ],
            paper: 'A4',
            orientation: 'P',
            inline: true,
        );
    }

    // ---- 退会 ----

    /** 退会の確認画面（GET /mypage/withdraw）。 */
    public function withdraw(): View
    {
        return view('mypage.withdraw');
    }

    /**
     * 退会の実行（DELETE /mypage/withdraw）。
     *
     * 個人情報を残さないため、会員の行は物理削除する。退会したことは、個人情報を含めずに
     * ログに残す。会員に付いている「このデバイスを記憶する」の記録、パスキー、ほかの端末の
     * ログイン、顔写真のファイルも、一緒に消す。
     *
     * ログアウトは、行を消す前に行う。行を消した後にログアウトすると、ログイン状態を保持する
     * ための値を書き換える保存が走り、消した行がもう一度作られてしまうため。
     */
    public function destroy(Request $request): RedirectResponse
    {
        $member = Auth::user();

        // 完了メールの宛先。行を消した後は読めなくなるので、先に控えておく
        $email = $member->email;
        $name = $member->name;

        // ログアウト（行を消す前に。理由は上のコメント）。会員のログインだけを終わらせ、
        // このブラウザのセッションも作り直す（App\Support\LoginSession）
        LoginSession::logout($request, Member::memberGuard());

        // 会員と、会員に付いている記録・ファイルをまとめて消す
        DB::transaction(function () use ($member) {
            $member->trustedDevices()->delete();
            $member->passkeys()->delete();
            $this->deleteSessionsOf($member);
            // 顔写真のファイルは、トランザクションが確定した後に消える（AjaxFileUpload参照）
            $this->deleteAllUploads($member);
            $member->delete();

            // 操作ログ。もうログアウトしているので、操作した人を渡す
            OperationRecorder::record(OperationLogAction::Delete, $member, operator: $member);
        });

        // 退会の記録をログに残す（delete()した後も、インスタンスのidなどは読める）
        MemberActivityLog::withdrawn($member, $request);

        // 退会完了のお知らせメール
        $this->sendWithdrawnMail($email, $name);

        return redirect('/')->with('status', '退会手続きが完了しました。ご利用ありがとうございました。');
    }

    /**
     * その会員がログインしているセッションを、sessionsテーブルから消す。
     * ほかの端末でログインしたままになっていても、そこから使い続けられないようにするため。
     *
     * sessionsテーブルに会員のidが入るのは、会員のガードでログインしているときだけなので、
     * 管理画面のログインは消えない。このブラウザのセッションは、先にログアウトで作り直して
     * あるので、ここでは消えない。同じブラウザの管理画面のログインも残る。
     */
    private function deleteSessionsOf(Member $member): void
    {
        // セッションをDBに置いていなければ、何もしない
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $member->id)
            ->delete();
    }

    /**
     * 退会完了のお知らせメール。本人以外が退会させた場合に、本人が気付けるようにするため。
     * 退会はもう済んでいるので、送れなくても画面の結果は変えず、ログにだけ残す。
     */
    private function sendWithdrawnMail(string $email, string $name): void
    {
        try {
            Mail::send(new TemplatedMail('member_withdrawn', [
                'from_mail' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'to_mail' => $email,
                'member_name' => $name,
                'contact_url' => route('contact.create'),
            ]));
        } catch (\Throwable $e) {
            Log::error('MypageController: 退会完了メールの送信に失敗しました。', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
