<?php

namespace App\Http\Controllers;

use App\Mail\TemplatedMail;
use App\Models\Member;
use App\Rules\PhoneNumberRule;
use App\Support\AjaxFileUpload;
use App\Support\FormFlow;
use App\Support\MemberActivityLog;
use App\Support\PasskeyManagement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MypageController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // プロフィール編集の検証・保存はFormFlowトレイトが提供する（確認画面は挟まない）。
    // クラス側は rules() saveFieldNames() inputFromModel() を用意する。
    use FormFlow;

    // 顔写真のAjaxアップロード（入口のuploadAjaxFile()もトレイト側）。
    // クラス側は UPLOAD_FILES を用意し、rules()に ajaxUploadRules() を足す。
    use AjaxFileUpload;

    // パスキーの一覧・登録・削除（passkeyIndex()など。App\Support\PasskeyManagement参照）。
    // 使わないサイトでは、このuseとroutes/web.phpのmypage.passkeysのルートを消す。
    use PasskeyManagement;

    // ---- アップロード（AjaxFileUpload）の設定 ----

    // フィールド名 => 横幅(px)。管理画面（Admin\MemberController）と同じ。顔写真は非公開の
    // フィールド（Member::PRIVATE_FILE_FIELDS）なので、本人とスタッフだけが見られる場所に保存される。
    private const UPLOAD_FILES = [
        'photo' => Member::PHOTO_WIDTH,
    ];

    // ---- パスキー（PasskeyManagement）の設定 ----

    // ログイン中の会員を取るガード。
    private const PASSKEY_GUARD = 'web';

    // パスキーの一覧画面のルート名（登録・削除などのルート名は、この後ろに.confirmなどを付ける）。
    private const PASSKEY_ROUTE = 'mypage.passkeys';

    // パスキーの一覧画面のビュー。
    private const PASSKEY_VIEW = 'mypage.passkeys';

    // 登録の前の本人確認（メールの確認コード）の試行制限（LoginThrottle）のカウンターの名前。
    private const PASSKEY_THROTTLE_SCOPE = 'member-passkey-code';

    // ---- プロフィールの項目の定義 ----

    /**
     * プロフィール編集で使うバリデーションルール一式。
     *
     * 会員登録のときと違い、emailの重複チェックは「自分自身」を除外する
     * 必要があるため、Rule::unique()に->ignore($member->id)を付ける。
     * そのために、対象のMemberをこのメソッドの引数として受け取る形にしている。
     */
    private function rules(Member $member): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kana' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(Member::class, 'email')->ignore($member->id)],
            'phone' => ['nullable', 'string', new PhoneNumberRule()],
            'birthdate' => ['nullable', 'date'],
            'prefecture' => ['nullable', 'integer', Rule::in(code_keys('prefectures'))],
        ] + $this->ajaxUploadRules();
    }

    // 保存する項目（t_membersのカラム）。顔写真はcommitUploads()が保存するので書かない。
    // パスワードはこのフォームでは扱わない（変更はAuthPasswordControllerの専用フォーム）。
    private function saveFieldNames(array $validated, Member $member): array
    {
        return ['name', 'kana', 'email', 'phone', 'birthdate', 'prefecture'];
    }

    // モデルの今の値から、編集画面に渡す$inputを組み立てる。
    private function inputFromModel(Member $member): array
    {
        return [
            'name' => $member->name,
            'kana' => $member->kana,
            'email' => $member->email,
            'phone' => $member->phone,
            'birthdate' => optional($member->birthdate)->format('Y-m-d'),
            'prefecture' => $member->prefecture,
        ];
    }

    // ---- マイページ・プロフィール編集 ----

    /**
     * マイページ（プロフィール表示）の表示（GET /mypage）
     *
     * ログイン中の会員情報を取るのに$request->user()ではなくAuth::user()を使う。
     * どちらも最終的には同じAuthManagerに行き着くので結果は同じだが、
     * SessionController側のAuth::login()/Auth::logout()とAuthファサードで
     * 統一し、「フォームから送られてきた値」を扱う$requestと、
     * 「認証状態」を扱うAuthを、名前の上でもはっきり分けている。
     */
    public function index(): View
    {
        return view('mypage.index', [
            'member' => Auth::user(),
        ]);
    }

    /**
     * プロフィール編集フォームの表示（GET /mypage/edit）
     */
    public function edit(): View
    {
        $member = Auth::user();

        // admin側と同じく、表示する値はコントローラーが組み立て、ビューは
        // $inputを見るだけにしている。old()があればそちらを優先し、無ければ
        // $memberの現在値（顔写真のhiddenの値も含む）を使う（FormFlow::formInput()）。
        return view('mypage.edit', [
            'member' => $member,
            'input' => $this->formInput($member, old()),
            'required' => $this->requiredFields($member),
        ]);
    }

    /**
     * プロフィールの更新処理（PATCH /mypage）
     *
     * 会員登録とは違い確認画面を挟まないので、saveData()をそのまま呼ぶ。
     * 検証に失敗すれば、Laravelの標準の動きで直前のページ（この編集フォーム）へ戻る。
     * 「今ログイン中の会員が誰か」はAuth::user()から取る。
     */
    public function update(Request $request): RedirectResponse
    {
        $member = Auth::user();

        try {
            $this->saveData($member, $request);
        } catch (UniqueConstraintViolationException $e) {
            // Rule::unique()での事前チェックと実際のUPDATEの間の、ごく僅かな隙間で
            // 同じメールアドレスが別の会員に使われてしまった場合の最後の砦。
            // 会員登録のstore()と同じ考え方。
            return redirect()->route('mypage.edit')
                ->withInput()
                ->with('error', '入力いただいたメールアドレスは、別の方に登録されたようです。');
        }

        return redirect()->route('mypage')->with('status', 'プロフィールを更新しました。');
    }

    // ---- 退会 ----

    /**
     * 退会の確認画面（GET /mypage/withdraw）。
     */
    public function withdraw(): View
    {
        return view('mypage.withdraw');
    }

    /**
     * 退会の実行（DELETE /mypage/withdraw）。
     *
     * 会員の行を物理削除する。個人情報をサーバーに残さないため、論理削除
     * （退会日時を付けて行を残す）にはしていない。退会の記録は、個人情報を
     * 含まない形でApp\Support\MemberActivityLogがログに残す。
     *
     * 一緒に消すもの：
     * - 「このデバイスを記憶する」の記録（trusted_devicesのうち、この会員の行）と
     *   パスキー（passkeysのうち、この会員の行）。どちらも汎用のテーブルで
     *   外部キー制約が無いので、会員を消しても自動では消えない
     * - ほかの端末に残っているログイン中のセッション（deleteSessionsOf()）
     * - 顔写真のファイル（deleteAllUploads()）
     *
     * 処理の順番に意味がある。Auth::logout()は、「ログイン状態を保持する」を
     * 使ってログインしていた会員について、remember_tokenを新しい値に書き換えて
     * save()する。会員の行を消した後にlogout()を呼ぶと、このsave()が
     * 消したはずの行をもう一度INSERTしてしまう（Eloquentは、削除済みの
     * モデルをsave()すると新規作成として扱う）。そのため、先にlogout()してから
     * 行を消す。
     */
    public function destroy(Request $request): RedirectResponse
    {
        $member = Auth::user();

        // 完了メールの宛先。行を消した後は読めなくなるので先に控えておく。
        $email = $member->email;
        $name = $member->name;

        Auth::logout();

        DB::transaction(function () use ($member) {
            $member->trustedDevices()->delete();
            $member->passkeys()->delete();
            $this->deleteSessionsOf($member);
            // 顔写真のファイルは、トランザクションが確定した後に消える（AjaxFileUpload参照）
            $this->deleteAllUploads($member);
            $member->delete();
        });

        // delete()した後も、$memberのインスタンスが持つ属性（id・created_at）は
        // 読めるので、そのままログに渡せる。
        MemberActivityLog::withdrawn($member, $request);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->sendWithdrawnMail($email, $name);

        return redirect('/')->with('status', '退会手続きが完了しました。ご利用ありがとうございました。');
    }

    /**
     * その会員がログインしているセッションを、DBのsessionsテーブルから消す。
     * 別の端末でログインしたままになっていても、そこから使い続けられない
     * ようにするため（行が消えればログイン状態は自然に解けるが、セッションの
     * 行にはIPアドレスやブラウザの情報が残るので、それも一緒に消す）。
     *
     * sessionsテーブルのuser_idには、既定のガード（config/auth.phpの
     * defaults.guard＝'web'＝会員）でログインしている会員のidが入る。
     * スタッフの管理画面のセッションは'admin'ガードなのでuser_idは入らず、
     * 消されない。ただし、同じブラウザで会員と管理画面の両方にログインして
     * いた場合は、1つのセッションを共有しているので、管理画面の方も
     * ログアウトされる。
     *
     * セッションをDB以外（ファイルなど）に保存する設定の場合は、会員ごとに
     * 探して消す手段が無いので何もしない。
     */
    private function deleteSessionsOf(Member $member): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $member->id)
            ->delete();
    }

    /**
     * 退会完了のお知らせメール。本人以外がログインして退会させた場合に、
     * 本人が気付けるようにするためのもの。
     *
     * 退会の処理自体は済んでいるので、送信に失敗しても画面の結果は変えず、
     * ログにだけ残す（メールアドレスは個人情報なのでログには出さない）。
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
