<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StaffAcl;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Support\FormFlow;
use App\Support\PasskeyManagement;
use App\Support\PasswordChange;
use App\Support\SearchableList;
use App\Support\TrustedDeviceManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// 「誰が何をしてよいか」のチェックは、コントローラーに入る前にroutes/web.phpで行う。
// - 詳細・編集・削除・削除の取り消し・2段階認証の代理解除：canミドルウェア（判断はApp\Policies\StaffPolicy）
// - 一覧・新規登録：acl.manager（管理者だけ）
// 権限（acl）を変えてよいかも、同じStaffPolicyのupdateAcl()で判断する。
class StaffController extends Controller
{
    // ---- 共通処理（トレイト） ----

    // 一覧・検索まわりの共通処理はSearchableListトレイトが提供する。
    // クラス側は必要な定数と srchRules() applyCustomSearch() だけ用意する。
    use SearchableList;

    // 入力→確認→保存の共通処理はFormFlowトレイトが提供する。
    // クラス側は rules() saveFieldNames() inputFromModel() と、必要なら afterSave() などを用意する。
    use FormFlow;

    // ログイン中の本人による、パスキーの一覧・登録・削除（passkeyIndex()など）。
    // App\Support\PasskeyManagement参照。使わないサイトでは、このuseと
    // routes/web.phpのadmin.passkeysのルートを消す。
    use PasskeyManagement;

    // ---- 一覧・検索（SearchableList）の設定 ----

    // 一覧画面のルート名。セッションキー名の識別子としても使用。
    // 登録・更新・削除の後の戻り先（?back付きの一覧）にも使う。
    private const INDEX_ROUTE = 'admin.staff.index';

    // フリーワード検索の検索対象とするカラムの一覧。
    private const FREE_WORD_COLUMNS = ['name'];

    // 1ページに表示する件数。
    private const PER_PAGE = 20;

    // 一覧の並び順の選択肢。
    private const ORDER_OPTIONS = [
        'updated_desc' => [
            'label' => '更新日が新しい順',
            'orderBy' => [
                ['updated_at', 'desc'],
                ['id', 'desc'],
            ],
        ],
        'updated_asc' => [
            'label' => '更新日が古い順',
            'orderBy' => [
                ['updated_at', 'asc'],
                ['id', 'asc'],
            ],
        ],
    ];

    // ---- パスキー（PasskeyManagement）の設定 ----

    // ログイン中のスタッフを取るガード。
    private const PASSKEY_GUARD = 'admin';

    // パスキーの一覧画面のルート名（登録・削除などのルート名は、この後ろに.confirmなどを付ける）。
    private const PASSKEY_ROUTE = 'admin.passkeys';

    // パスキーの一覧画面のビュー。
    private const PASSKEY_VIEW = 'admin.staff.passkeys';

    // 登録の前の本人確認（TOTPコード）の試行制限。ログインの2段階目などと同じ
    // カウンターで数える（TwoFactorChallengeController::THROTTLE_SCOPE参照）。
    private const PASSKEY_THROTTLE_SCOPE = TwoFactorChallengeController::THROTTLE_SCOPE;

    // ---- このコーナーの項目の定義 ----

    // 入力バリデーションルール
    // $staffは既存スタッフの編集ならそのインスタンス、新規登録ならnull。
    private function rules(?Staff $staff): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'login_id' => ['required', 'string', 'max:255',
                // 自idを除外してユニークであること。
                // Rule::unique()はEloquentを通さずテーブルを直接検索するので、
                // SoftDeletesの「削除済みを除く」条件は掛からず、削除済み
                // スタッフのログインIDとも重複チェックされる（削除済みの
                // ログインIDは再利用させない。t_staffsのunique制約と同じ考え方）。
                Rule::unique(Staff::class, 'login_id')
                    ->ignore($staff?->id)],
            // emailはログインには使わない連絡先で、重複を許す（役職用の
            // 共有アドレスを複数人で登録することもできる）。
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'password' => ($staff === null)
                ? ['required', 'string', 'min:8', 'confirmed']
                : ['nullable', 'string', 'min:8', 'confirmed'],
            // 権限を変えられない人（StaffPolicy::updateAcl()）の画面には権限欄が無いので、
            // 'exclude'で検証の対象からも入力値からも外す（保存しないのはsaveFieldNames()側）。
            'acl' => $this->canUpdateAcl($staff)
                ? ['required', 'integer', Rule::in(code_keys('staff_acl'))]
                : ['exclude'],
        ];
    }

    // 保存する項目（t_staffsのカラム）。ここに書いた項目だけを保存する。
    // パスワードは入力値をそのまま保存しないので、additionalFields()で扱う。
    // ここから外した項目は、更新ではDBの今の値がそのまま残る（NULLにするのとは違う）。
    private function saveFieldNames(array $validated, Staff $staff): array
    {
        $fields = ['name', 'login_id', 'email'];

        // 権限を変えられない人が保存したときは、DBの今の値のまま残す
        // （rules()で入力値から外しているので、ここに入れるとNULLで上書きしてしまう）。
        if ($this->canUpdateAcl($staff)) {
            $fields[] = 'acl';
        }

        return $fields;
    }

    // saveFieldNames()に加えて保存する項目（項目名 => 値）。入力値をそのまま使わないものをここに書く。
    private function additionalFields(array $validated, Staff $staff): array
    {
        if (empty($validated['password'])) {
            return [];
        }

        // パスワードは入力があったときだけハッシュ変換して更新
        return ['password' => Hash::make($validated['password'])];
    }

    // モデルの今の値から、_fields.blade.phpに渡す$inputを組み立てる（詳細・編集で使う）。
    private function inputFromModel(Staff $staff): array
    {
        return [
            'name' => $staff->name,
            'login_id' => $staff->login_id,
            'email' => $staff->email,
            'acl' => $staff->acl?->value,
        ];
    }

    // 新規登録フォームの初期値。
    private function defaultInput(): array
    {
        // 新規スタッフは最小権限から
        return ['acl' => StaffAcl::Staff->value];
    }

    // 保存の直後の処理。
    private function afterSave(Staff $staff, array $validated): void
    {
        if ($staff->wasChanged('password')) {
            // パスワードが変わったら、信頼済み端末とパスキーを無効にし、本人へ
            // お知らせのメールを送る（App\Support\PasswordChange参照）。
            PasswordChange::resetAndNotify($staff, changedBy: Auth::guard('admin')->user());

            if ($staff->id === Auth::guard('admin')->id()) {
                // 自分のパスワードを変えたときは、ログイン中のスタッフを更新後のインスタンスに
                // 差し替える。auth.session（routes/web.php）はリクエストの最後に、ログイン中の
                // スタッフのパスワードのハッシュ値をセッションに控え直すので、差し替えないと
                // 古い値が控えられ、次のリクエストで自分までログアウトされてしまう。
                Auth::guard('admin')->setUser($staff);
            }
        }
    }

    // 削除の直前の処理。
    private function beforeDelete(Staff $staff): void
    {
        // 論理削除では行が残るので、信頼済み端末はここで明示的に消しておく
        // （復元したときに、削除前の信頼が一緒に戻らないようにするため）。
        // パスキーは、パスワード・2段階認証の設定と同じく消さずに残す。削除済みの
        // スタッフは、パスキーでもログインできない（App\Models\Passkey::user()参照）。
        TrustedDeviceManager::forStaff()->forgetAll($staff);
    }

    // 操作しているスタッフが、権限（acl）を変えてよいか（判断はStaffPolicy::updateAcl()）。
    // 新規登録（$staffがnull）のときは、対象が無いのでクラス名で問い合わせる。
    private function canUpdateAcl(?Staff $staff): bool
    {
        return Auth::guard('admin')->user()->can('updateAcl', $staff ?? Staff::class);
    }

    // ---- 一覧・検索 ----

    // スタッフ一覧・検索
    // 検索条件の復元、絞り込み、並び替え、ページネーションは SearchableList::buildListData が行う
    public function index(Request $request): View|RedirectResponse
    {
        $result = $this->buildListData($request, Staff::query());

        if ($result instanceof RedirectResponse) {
            // リダイレクトが要求された場合
            return $result;
        }

        // 一覧を表示
        return view('admin.staff.index', [
            'staffList' => $result['paginated'],
            'filters' => $result['filters'],
            'orderOptions' => $result['orderOptions'],
            'selectedOrder' => $result['orderKey'],
        ]);
    }

    // 検索対象項目の検証ルール（SearchableListが要求する）。
    // integer・boolean・Rule::inのどれかがあれば完全一致、無ければ部分一致、配列ならIN()条件
    private function srchRules(): array
    {
        return [
            'email' => ['nullable', 'string', 'max:255'],
            'acl' => ['nullable', 'integer', Rule::in(code_keys('staff_acl'))],
            // 「削除済みも含める」のチェックボックス（カラムではないのでapplyCustomSearch()で処理）
            'with_trashed' => ['nullable', 'boolean'],
        ];
    }

    // イレギュラーな検索条件の追加処理
    // DB項目と単純に比較できないものは先にここでwhere条件を追加し、処理済み(true)を返す。
    private function applyCustomSearch(Builder $query, string $key, mixed $value): bool
    {
        if ($key === 'with_trashed') {
            // チェックが入っていれば、削除済み（論理削除）のスタッフも一覧に含める
            if ((bool) $value) {
                $query->withTrashed();
            }

            return true;
        }

        return false;
    }

    // ---- 登録（管理者のみ。routes/web.phpのacl.managerの内側） ----

    // 新規登録フォームの表示
    public function create(): View
    {
        // 初期値はdefaultInput()。old() があればそちらを優先（パスワードは再表示しないので外す）
        $input = $this->formInput(null, Arr::except(old(), ['password', 'password_confirmation']));

        return view('admin.staff.create', [
            'input' => $input,
            // password_confirmationはrules()に無いが、新規登録では必須
            'required' => $this->requiredFields(null, ['password_confirmation']),
        ]);
    }

    // 新規登録の確認画面を表示
    public function confirmStore(Request $request): View
    {
        // password_confirmationはrules()に無いので、hiddenで持ち回れるように足しておく
        $input = $this->confirmInput($request)
            + ['password_confirmation' => (string) $request->input('password_confirmation')];

        return view('admin.staff.confirm', [
            'isCreate' => true,
            'staff' => null,
            'input' => $input,
            'showAcl' => true,
        ]);
    }

    // 確認画面からの「戻る」
    public function backToCreate(Request $request): RedirectResponse
    {
        return redirect()->route('admin.staff.create')
            ->withInput($request->except('_token'));
    }

    // 新規登録の実行
    public function store(Request $request): RedirectResponse
    {
        $this->saveData(new Staff(), $request);

        return redirect()->route(self::INDEX_ROUTE, ['back'])
            ->with('status', 'スタッフを登録しました。');
    }

    // ---- 詳細・編集（本人または管理者。routes/web.phpのcan:view・can:updateの内側） ----

    // 詳細画面の表示
    public function show(Staff $staff): View
    {
        // 詳細画面にフォームの送信は無いが、_fields.blade.phpに渡す値は$input
        return view('admin.staff.show', [
            'staff' => $staff,
            'actor' => Auth::guard('admin')->user(),
            'input' => $this->formInput($staff),
        ]);
    }

    // 編集フォームの表示
    public function edit(Staff $staff): View
    {
        // old() があればそちらを優先（パスワードは再表示しないので外す）
        $input = $this->formInput($staff, Arr::except(old(), ['password', 'password_confirmation']));

        return view('admin.staff.edit', [
            'staff' => $staff,
            'actor' => Auth::guard('admin')->user(),
            'input' => $input,
            'required' => $this->requiredFields($staff),
        ]);
    }

    // 編集の確認画面を表示
    public function confirmUpdate(Request $request, Staff $staff): View
    {
        // password_confirmationはrules()に無いので、hiddenで持ち回れるように足しておく
        $input = $this->confirmInput($request, $staff)
            + ['password_confirmation' => (string) $request->input('password_confirmation')];

        return view('admin.staff.confirm', [
            'isCreate' => false,
            'staff' => $staff,
            'input' => $input,
            'showAcl' => $this->canUpdateAcl($staff),
        ]);
    }

    // 確認画面からの「戻る」
    public function backToEdit(Request $request, Staff $staff): RedirectResponse
    {
        return redirect()->route('admin.staff.edit', $staff)
            ->withInput($request->except('_token'));
    }

    // 更新の実行
    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $this->saveData($staff, $request);

        if (Auth::guard('admin')->user()->isManager()) {
            // 管理者が編集したときは元の一覧 ?back へ戻る
            return redirect()->route(self::INDEX_ROUTE, ['back'])
                ->with('status', 'スタッフ情報を更新しました。');
        }

        // スタッフが自分自身を編集したときは、自分の編集フォームへ戻す。
        return redirect()->route('admin.staff.edit', $staff)
            ->with('status', 'スタッフ情報を更新しました。');
    }

    // ---- 削除・削除の取り消し・2段階認証の登録解除（管理者のみ・自分自身は対象外。routes/web.phpのcanの内側） ----

    // 削除の実行
    // StaffはSoftDeletesを使っているので、delete()は行を消さずに
    // deleted_atへ削除日時を入れる論理削除になる（詳しくはStaffモデル参照）。
    public function destroy(Staff $staff): RedirectResponse
    {
        $this->deleteData($staff);

        return redirect()->route(self::INDEX_ROUTE, ['back'])
            ->with('status', 'スタッフを削除しました。');
    }

    // 削除の取り消し（PATCH /admin/staff/{staff}/restore）
    // 論理削除したスタッフのdeleted_atを空に戻す。ログインID・パスワード・2段階認証の設定は
    // 削除前のまま戻るので、すぐにログインできる（信頼済み端末だけは削除のときに消している）。
    public function restore(Staff $staff): RedirectResponse
    {
        $staff->restore();

        return redirect()->route('admin.staff.show', $staff)
            ->with('status', 'スタッフの削除を取り消しました。');
    }

    // 2段階認証（TOTP）の登録解除（DELETE /admin/staff/{staff}/two-factor）。
    // スマートフォンの紛失・機種変更などで本人がログインできなくなった際に、
    // 管理者が代わりに実行する。次回そのスタッフがログインすると、QRコードの登録から
    // やり直しになる（バックアップコード・信頼済み端末・パスキーも合わせて失効させる。
    // パスキーを消す理由はTwoFactorChallengeController::selfReset()参照）。
    public function resetTwoFactor(Staff $staff): RedirectResponse
    {
        DB::transaction(function () use ($staff) {
            $staff->totp_secret = null;
            $staff->totp_confirmed_at = null;
            $staff->save();

            $staff->backupCodes()->delete();
            TrustedDeviceManager::forStaff()->forgetAll($staff);
            $staff->passkeys()->delete();
        });

        return redirect()->route('admin.staff.show', $staff)
            ->with('status', '2段階認証の登録を解除しました。次回ログイン時にQRコードから登録し直せます。');
    }
}
