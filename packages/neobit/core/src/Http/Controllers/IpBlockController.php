<?php

namespace Neobit\Core\Http\Controllers;

use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Neobit\Core\Auth\LoginAccount;
use Neobit\Core\Models\IpBlock;
use Neobit\Core\Security\IpBlocker;

/**
 * IPブロック管理の画面。止めているIPアドレスの一覧と、手動の登録・期限の変更・解除。
 * 止める仕組みと自動で止める動きは、Neobit\Core\Security\IpBlockerにある。
 *
 * ルートはアプリが書く。機能を使うかどうかは、案件ごとに決めるため。管理者だけが入れる
 * グループの中に、「頭.index」（GET）・「頭.store」（POST）・「頭.update」（PATCH）・
 * 「頭.destroy」（DELETE）の組で名前を付ける。update・destroyのURLには、{ipBlock}を入れる。
 * 頭はアプリが決めてよい。このコントローラーは、呼ばれたルートの名前から、組になるほかの名前を作る。
 *
 * 項目が少なく、間違えてもすぐ解除できるので、確認画面は挟まない。
 */
class IpBlockController
{
    // ---- ルート ----

    /** 一覧・登録・期限の変更・解除のルートの名前の末尾 */
    private const ACTIONS = ['index', 'store', 'update', 'destroy'];

    // ---- このコーナーの項目の定義 ----

    /** 止める期間の選択肢。日数 => 名前。0は期限なし */
    private const DAYS_OPTIONS = [
        1 => '1日',
        7 => '7日',
        30 => '30日',
        0 => '期限なし',
    ];

    /** 登録の検証ルール。IPアドレスの形は、store()でIpBlocker::normalizeRange()が確かめる */
    private function rules(): array
    {
        return [
            'ip' => ['required', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:255'],
            'days' => ['required', 'integer', Rule::in(array_keys(self::DAYS_OPTIONS))],
        ];
    }

    // ---- 一覧・登録・期限の変更・解除 ----

    /** 止めているIPアドレスの一覧と、登録の欄 */
    public function index(Request $request): View
    {
        $blocks = IpBlock::query()->active()->orderByDesc('created_at')->get();

        return view('neobit::admin.ip_blocks.index', [
            'blocks' => $blocks,
            // 一覧に出す、実際に止まる範囲。id => [最初, 最後]
            'bounds' => $blocks->mapWithKeys(fn (IpBlock $block) => [$block->id => IpBlocker::rangeBounds($block->ip)])->all(),
            'daysOptions' => self::DAYS_OPTIONS,
            'currentIp' => (string) $request->ip(),
            'auto' => (bool) config('neobit.ip_block_auto'),
            'watchMinutes' => (int) config('neobit.ip_block_watch_minutes'),
            'watchCount' => (int) config('neobit.ip_block_watch_count'),
            'hours' => (int) config('neobit.ip_block_hours'),
            'routes' => $this->routeNames($request),
        ]);
    }

    /** 手動の登録 */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        // IPアドレスかまとまりの形と、広すぎないことを確かめる
        $range = IpBlocker::normalizeRange($validated['ip']);
        if ($range === null) {
            throw ValidationException::withMessages([
                'ip' => 'IPアドレスか、192.0.2.0/24のようなまとまりで入力してください。まとまりは、IPv4は/16、IPv6は/32より狭くしてください。',
            ]);
        }

        // 自分のIPアドレスと、入ってよいIPアドレスは止めない
        if (IpBlocker::coversProtected($range, (string) $request->ip())) {
            throw ValidationException::withMessages([
                'ip' => 'あなたのIPアドレスか、入ってよいIPアドレス（SITE_ALLOWED_IPS・ADMIN_ALLOWED_IPS）を含むので、登録できません。',
            ]);
        }

        // 登録したスタッフの名前は、登録した時点のものを残す
        $user = $request->user();
        $registeredBy = $user instanceof LoginAccount ? $user->displayName() : null;

        IpBlocker::block($range, $this->until((int) $validated['days']), $validated['reason'] ?? null, registeredBy: $registeredBy);

        return redirect()->route($this->routeNames($request)['index'])
            ->with('status', "「{$range}」をブロックしました。");
    }

    /** 期限の変更。変えた日から数え直す */
    public function update(Request $request, IpBlock $ipBlock): RedirectResponse
    {
        $validated = $request->validate(['days' => $this->rules()['days']]);

        IpBlocker::changeUntil($ipBlock, $this->until((int) $validated['days']));

        return redirect()->route($this->routeNames($request)['index'])
            ->with('status', "「{$ipBlock->ip}」の期限を変えました。");
    }

    /** 解除 */
    public function destroy(Request $request, IpBlock $ipBlock): RedirectResponse
    {
        IpBlocker::release($ipBlock);

        return redirect()->route($this->routeNames($request)['index'])
            ->with('status', "「{$ipBlock->ip}」のブロックを解除しました。");
    }

    // ---- 期限とルート ----

    /** 選んだ日数から、期限の日時。0なら期限なしでnull */
    private function until(int $days): ?CarbonInterface
    {
        return $days > 0 ? now()->addDays($days) : null;
    }

    /** 呼ばれたルートと組になるルートの名前。末尾 => 名前。「頭.index」から「頭.store」のように、末尾だけを替える */
    private function routeNames(Request $request): array
    {
        $prefix = Str::beforeLast((string) $request->route()?->getName(), '.');

        return array_combine(self::ACTIONS, array_map(fn (string $action) => $prefix.'.'.$action, self::ACTIONS));
    }
}
