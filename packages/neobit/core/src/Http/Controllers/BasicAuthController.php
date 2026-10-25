<?php

namespace Neobit\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Neobit\Core\Models\BasicAuthUser;
use Neobit\Core\Security\BasicAuth;

/**
 * ベーシック認証の画面。訪問者画面と管理画面のそれぞれに、掛けるかと、ユーザーの追加と削除を、運用する人が管理画面から変える。
 * 掛け方と決まりは、Neobit\Core\Security\BasicAuthにある。働くのは、サーバーのVirtualHostに決まりを書いたときだけ。
 *
 * ルートはアプリが書く。機能を使うかどうかは、案件ごとに決めるため。管理者だけが入れるグループの中に、
 * 「頭.index」（GET）・「頭.update」（PATCH）・「頭.store」（POST）・「頭.destroy」（DELETE）の組で名前を付ける。
 * update・storeのURLには{area}（siteかadmin）を、destroyのURLには{basicAuthUser}を入れる。頭はアプリが決めてよい。
 * このコントローラーは、呼ばれたルートの名前から、組になるほかの名前を作る。
 *
 * 入力のエラーは、範囲の名前のエラーの入れ物（site・admin）に入れる。画面の範囲ごとの枠に、その範囲のエラーだけを出すため。
 * 項目が少なく、間違えてもすぐ直せるので、確認画面は挟まない。
 */
class BasicAuthController
{
    // ---- ルート ----

    /** 表示・切り替え・ユーザーの追加・削除のルートの名前の末尾 */
    private const ACTIONS = ['index', 'update', 'store', 'destroy'];

    // ---- このコーナーの項目の定義 ----

    /** 項目の名前 */
    private const LABELS = [
        'username' => 'ユーザー名',
        'password' => 'パスワード',
    ];

    /** ユーザーの追加の検証ルール。ユーザー名は範囲の中で一意 */
    private function userRules(string $area): array
    {
        return [
            'username' => [
                'required',
                'string',
                'regex:'.BasicAuth::USERNAME_PATTERN,
                Rule::unique(BasicAuthUser::class, 'username')->where('area', $area),
            ],
            // bcryptは72バイトより後ろを読まないので、それより長いものは断る
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ];
    }

    /** 決まりの文言では分かりにくい項目のエラーの文言 */
    private function messages(): array
    {
        return [
            'username.regex' => 'ユーザー名は、半角の英数字と - _ . で入力してください。',
            'username.unique' => 'このユーザー名は、もう登録されています。',
        ];
    }

    // ---- 表示・切り替え ----

    /** 範囲ごとの、掛けているかとユーザーの一覧 */
    public function index(Request $request): View
    {
        $users = BasicAuthUser::query()->orderBy('username')->get()->groupBy('area');

        $areas = [];
        foreach (BasicAuth::AREAS as $area => $label) {
            $areas[$area] = [
                'label' => $label,
                'on' => BasicAuth::isOn($area),
                'users' => $users[$area] ?? collect(),
            ];
        }

        return view('neobit::admin.basic_auth.index', [
            'areas' => $areas,
            'routes' => $this->routeNames($request),
        ]);
    }

    /** 範囲に掛けるかの保存。ユーザーがいなければ掛けられない */
    public function update(Request $request, string $area): RedirectResponse
    {
        $this->ensureArea($area);
        $validated = $request->validateWithBag($area, ['on' => ['nullable', 'boolean']]);

        $error = BasicAuth::save($area, (bool) ($validated['on'] ?? false));
        if ($error !== null) {
            throw ValidationException::withMessages(['on' => $error])->errorBag($area);
        }

        return redirect()->route($this->routeNames($request)['index'])
            ->with('status', BasicAuth::AREAS[$area].'のベーシック認証の設定を保存しました。');
    }

    // ---- ユーザー ----

    /** 範囲へのユーザーの追加 */
    public function store(Request $request, string $area): RedirectResponse
    {
        $this->ensureArea($area);
        $validated = $request->validateWithBag($area, $this->userRules($area), $this->messages(), self::LABELS);

        BasicAuth::addUser($area, $validated['username'], $validated['password']);

        return redirect()->route($this->routeNames($request)['index'])
            ->with('status', BasicAuth::AREAS[$area]."のユーザー「{$validated['username']}」を追加しました。");
    }

    /** ユーザーの削除。掛けている範囲の最後のユーザーは消せない */
    public function destroy(Request $request, BasicAuthUser $basicAuthUser): RedirectResponse
    {
        $error = BasicAuth::deleteUser($basicAuthUser);
        if ($error !== null) {
            return redirect()->route($this->routeNames($request)['index'])->with('error', $error);
        }

        return redirect()->route($this->routeNames($request)['index'])
            ->with('status', BasicAuth::AREAS[$basicAuthUser->area]."のユーザー「{$basicAuthUser->username}」を削除しました。");
    }

    // ---- 範囲とルート ----

    /** URLの範囲が、知っている範囲（site・admin）か。違えば404 */
    private function ensureArea(string $area): void
    {
        abort_unless(array_key_exists($area, BasicAuth::AREAS), 404);
    }

    /** 呼ばれたルートと組になるルートの名前。末尾 => 名前。「頭.index」から「頭.update」のように、末尾だけを替える */
    private function routeNames(Request $request): array
    {
        $prefix = Str::beforeLast((string) $request->route()?->getName(), '.');

        return array_combine(self::ACTIONS, array_map(fn (string $action) => $prefix.'.'.$action, self::ACTIONS));
    }
}
