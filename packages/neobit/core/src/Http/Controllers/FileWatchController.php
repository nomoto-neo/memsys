<?php

namespace Neobit\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Operations\OperationRecorder;
use Neobit\Core\Security\FileWatcher;

/**
 * 管理画面の「改ざん監視」。改ざん監視の今の状況を出し、改ざんの疑いと判断待ちの変化を承認する。
 * 監視の決まりと記録は、Neobit\Core\Security\FileWatcherにある。
 *
 * 画面は、開くたびにその場でチェックし直して出す（FileWatcher::inspect()）。記録はapacheが書けないので、
 * 承認は目印を置くだけにし、毎分のcron（app:watch-files --approve）が拾って基準に取り込む。
 * 承認の後に増えた変化を取り込まないよう、画面に出した一覧のしるし（FileWatcher::signature()）を送り、押したときの
 * 一覧と同じときだけ承認する。違えば、開き直してもらう。一覧そのものを送らないのは、ファイルが多いとき（WordPressの
 * 更新など）に、PHPの受け取れる数を超えて黙って一部だけになるため。
 *
 * ルートはアプリが書く。機能を使うかどうかは、案件ごとに決めるため。管理者だけが入れるグループの中に、
 * 「頭.index」（GET）と「頭.approve」（POST）の組で名前を付ける。頭はアプリが決めてよい。
 * このコントローラーは、呼ばれたルートの名前から、組になるもう一方の名前を作る。
 */
class FileWatchController
{
    // ---- ルート ----

    /** 表示・承認のルートの名前の末尾 */
    private const ACTIONS = ['index', 'approve'];

    // ---- このコーナーの項目の定義 ----

    /** 変化の種類ごとの見出し。画面・操作ログとも、この順に種類ごとにまとめて並べる（承認のメールと同じ） */
    private const KIND_LABELS = [
        'changed' => '変わったファイル',
        'added' => '増えたファイル',
        'deleted' => '消えたファイル',
    ];

    /** 確かめのモーダルと操作ログに並べる件数。多いときは「ほか○件」にする。全部の一覧は承認のメールに載せる */
    private const SHOWN_ITEMS = 50;

    // ---- 表示・承認 ----

    /** 今の状況。監視を使っていないときと、基準がまだ無いときは、その旨だけを出す */
    public function index(Request $request): View
    {
        $watcher = app(FileWatcher::class);
        $enabled = (string) config('neobit.file_watch.release_file') !== '';

        return view('neobit::admin.file_watch.index', [
            'enabled' => $enabled,
            'status' => $enabled ? $watcher->inspect() : null,
            'approval' => $watcher->pendingApproval(),
            'recent' => $enabled ? $watcher->recentlyAccepted() : [],
            'kindLabels' => self::KIND_LABELS,
            'shownItems' => self::SHOWN_ITEMS,
            'routes' => $this->routeNames($request),
        ]);
    }

    /** 承認。押したときの改ざんの疑いと判断待ちが、画面に出した一覧と同じなら、承認の目印に書いて操作ログに残す */
    public function approve(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'signature' => ['required', 'string', 'size:64'],
        ]);
        $watcher = app(FileWatcher::class);
        $routes = $this->routeNames($request);

        // 押したときの一覧を、画面に出した一覧のしるしと照らし合わせる
        $status = $watcher->inspect();
        $items = [...($status['alerts'] ?? []), ...($status['pending'] ?? [])];
        if ($items === []) {
            return redirect()->route($routes['index'])->with('status', '承認する変更内容がありません。');
        }
        if (! hash_equals(FileWatcher::signature($items), $validated['signature'])) {
            return redirect()->route($routes['index'])->with('status', '画面を開いた後に、変更内容の一覧が変わりました。開き直した一覧を確かめてから、承認してください。');
        }

        $watcher->requestApproval($items, (string) $request->user()?->name);

        // 承認した一覧を、種類ごとに見出しを付けて操作ログの補足に残す。「全文」のモーダルで、1項目ずつ行に分かれて出る。
        // 決めた件数まで並べ、多いときは「ほか○件」にする。見出しの件数は、切る前の件数
        $detail = [];
        $shown = 0;
        foreach (self::KIND_LABELS as $kind => $label) {
            $ofKind = array_values(array_filter($items, fn (array $item) => $item['kind'] === $kind));
            if ($ofKind === [] || $shown >= self::SHOWN_ITEMS) {
                continue;
            }
            $detail[$kind] = $label.'（'.count($ofKind).'件）';
            foreach (array_slice($ofKind, 0, self::SHOWN_ITEMS - $shown) as $item) {
                $shown++;
                $detail['file_'.$shown] = $item['label'];
            }
        }
        if (count($items) > self::SHOWN_ITEMS) {
            $detail['more'] = 'ほか'.(count($items) - self::SHOWN_ITEMS).'件';
        }
        OperationRecorder::record(OperationLogAction::FileWatchApproval, detail: $detail);

        // 受け付けたことは、取り込まれるまで画面の知らせ（$approval）で出すので、ここでは出さない
        return redirect()->route($routes['index']);
    }

    // ---- ルート ----

    /** 呼ばれたルートと組になるルートの名前。末尾 => 名前。「頭.index」から「頭.approve」のように、末尾だけを替える */
    private function routeNames(Request $request): array
    {
        $prefix = Str::beforeLast((string) $request->route()?->getName(), '.');

        return array_combine(self::ACTIONS, array_map(fn (string $action) => $prefix.'.'.$action, self::ACTIONS));
    }
}
