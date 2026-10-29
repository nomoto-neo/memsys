<?php

namespace Neobit\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Neobit\Core\Security\AccessRestriction;

/**
 * アクセス制限の画面。サイト（訪問者画面）と管理画面のそれぞれに、制限を掛けるかと、開けるIPアドレスを、
 * 運用する人が管理画面から変える。決まりと判断は、Neobit\Core\Security\AccessRestrictionにある。
 *
 * ルートはアプリが書く。機能を使うかどうかは、案件ごとに決めるため。管理者だけが入れるグループの中に、
 * 「頭.index」（GET）と「頭.update」（PATCH）の組で名前を付ける。頭はアプリが決めてよい。
 * このコントローラーは、呼ばれたルートの名前から、組になるもう一方の名前を作る。
 *
 * 項目が少なく、締め出しは保存のときに確かめるので、確認画面は挟まない。
 */
class AccessRestrictionController
{
    // ---- ルート ----

    /** 表示・保存のルートの名前の末尾 */
    private const ACTIONS = ['index', 'update'];

    // ---- このコーナーの項目の定義 ----

    /** 入力の検証ルール。IPアドレスの形は、update()でAccessRestriction::parseList()が確かめる */
    private function rules(): array
    {
        return [
            'site_restricted' => ['nullable', 'boolean'],
            'site_ips' => ['nullable', 'string', 'max:5000'],
            'admin_restricted' => ['nullable', 'boolean'],
            'admin_ips' => ['nullable', 'string', 'max:5000'],
        ];
    }

    // ---- 表示・保存 ----

    /** 今の設定。IPアドレスは、入力した文（コメントも）をそのまま出す。設定ファイルで常に許可しているIPアドレスも、それぞれの欄の下に出す */
    public function index(Request $request): View
    {
        $settings = AccessRestriction::settings();

        return view('neobit::admin.access_restriction.index', [
            'siteRestricted' => (bool) old('site_restricted', $settings['site_restricted']),
            'siteIps' => old('site_ips', $settings['site_text']),
            'adminRestricted' => (bool) old('admin_restricted', $settings['admin_restricted']),
            'adminIps' => old('admin_ips', $settings['admin_text']),
            'envSiteIps' => AccessRestriction::envSiteIps(),
            'envAdminIps' => AccessRestriction::envAdminIps(),
            'currentIp' => (string) $request->ip(),
            'routes' => $this->routeNames($request),
        ]);
    }

    /** 保存。今の自分のIPアドレスで管理画面を開けなくなる設定は、保存しない */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        // 入力した文の改行をそろえ、IPアドレスの一覧にして確かめる。正しくない形のものがあれば、それを示して戻す
        $siteText = str_replace(["\r\n", "\r"], "\n", (string) ($validated['site_ips'] ?? ''));
        $adminText = str_replace(["\r\n", "\r"], "\n", (string) ($validated['admin_ips'] ?? ''));
        AccessRestriction::parseList($siteText, $siteInvalid);
        $adminIps = AccessRestriction::parseList($adminText, $adminInvalid);

        $errors = [];
        if ($siteInvalid !== []) {
            $errors['site_ips'] = 'IPアドレスの形になっていないものがあります：'.implode('、', $siteInvalid);
        }
        if ($adminInvalid !== []) {
            $errors['admin_ips'] = 'IPアドレスの形になっていないものがあります：'.implode('、', $adminInvalid);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // 自分を締め出す設定は保存しない
        $siteRestricted = (bool) ($validated['site_restricted'] ?? false);
        $adminRestricted = (bool) ($validated['admin_restricted'] ?? false);
        $lockout = AccessRestriction::lockoutMessage($adminRestricted, $adminIps, (string) $request->ip());
        if ($lockout !== null) {
            throw ValidationException::withMessages(['admin_ips' => $lockout]);
        }

        AccessRestriction::save($siteRestricted, $siteText, $adminRestricted, $adminText);

        return redirect()->route($this->routeNames($request)['index'])
            ->with('status', 'アクセス制限を保存しました。');
    }

    // ---- ルート ----

    /** 呼ばれたルートと組になるルートの名前。末尾 => 名前。「頭.index」から「頭.update」のように、末尾だけを替える */
    private function routeNames(Request $request): array
    {
        $prefix = Str::beforeLast((string) $request->route()?->getName(), '.');

        return array_combine(self::ACTIONS, array_map(fn (string $action) => $prefix.'.'.$action, self::ACTIONS));
    }
}
