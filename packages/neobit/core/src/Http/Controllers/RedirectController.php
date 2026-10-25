<?php

namespace Neobit\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Neobit\Core\Csv\CsvDownload;
use Neobit\Core\Csv\CsvImport;
use Neobit\Core\Csv\CsvImportResult;
use Neobit\Core\Csv\CsvImportSettings;
use Neobit\Core\Enums\CsvEncoding;
use Neobit\Core\Enums\CsvImportMode;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\Redirect;
use Neobit\Core\Operations\OperationRecorder;
use Neobit\Core\Seo\RedirectChecker;
use Neobit\Core\Seo\RedirectResolver;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * リダイレクトの画面。古いURLから新しいURLへの移し方の一覧・追加・編集・削除と、「URLを試す」、CSVの書き出しと取り込み。
 * 追加と編集は一覧の行の中で行い、別の画面は持たない。
 * 当て方はNeobit\Core\Seo\RedirectResolver、登録のときの確かめはNeobit\Core\Seo\RedirectChecker。
 *
 * ルートはアプリが書く。機能を使うかどうかは、案件ごとに決めるため。管理者だけが入れるグループの中に、
 * 「頭.index」（GET）・「頭.store」（POST）・「頭.update」（PATCH）・「頭.destroy」（DELETE）・
 * 「頭.csv」（GET）・「頭.csv-import」（GET）・「頭.csv-import.confirm」（POST）・「頭.csv-import.execute」（POST）の組で
 * 名前を付ける。update・destroyのURLには、{redirect}を入れる。頭はアプリが決めてよい。
 * このコントローラーは、呼ばれたルートの名前から、組になるほかの名前を作る。
 *
 * 項目が少なく、間違えてもすぐ直せるので、追加と編集に確認画面は挟まない。
 * CSVの取り込みは、共通の取り込みの画面を、処理だけのモードで使う。古いURLを鍵に、ある行は更新し、無い行は追加する。
 */
class RedirectController
{
    // CSVダウンロードと取り込みの共通処理。項目の定義はどちらもcsvColumns()
    use CsvDownload;
    use CsvImport;

    // ---- ルート ----

    /** ルートの名前の末尾。呼ばれたルートの名前から頭を求めるので、長いものから比べる */
    private const ACTIONS = [
        'csv-import.confirm',
        'csv-import.execute',
        'csv-import',
        'csv',
        'index',
        'store',
        'update',
        'destroy',
    ];

    // ---- 一覧 ----

    /** 1ページに出す件数 */
    private const PER_PAGE = 50;

    /** 一覧の並び順の選択肢。値 => 名前 */
    private const ORDER_OPTIONS = [
        'old_url' => '古いURLの順',
        'hits' => 'HIT回数の多い順',
        'last_hit_at' => '最終HIT日の古い順',
        'created_at' => '登録の新しい順',
    ];

    // ---- このコーナーの項目の定義 ----

    /** 種類の選択肢。値 => 名前 */
    private const STATUS_OPTIONS = [
        301 => '301（恒久）',
        302 => '302（一時）',
    ];

    /** 項目の名前。CSVの取り込みのエラーの列の名前にも使う */
    private const LABELS = [
        'old_url' => '古いURL',
        'new_url' => '新しいURL',
    ];

    /**
     * 入力の検証ルール。画面とCSVの取り込みで共通。URLの書き方と、ほかの登録との関係は、
     * 保存の前にRedirectChecker::errors()が確かめる
     */
    private function rules(): array
    {
        return [
            'old_url' => ['required', 'string', 'max:500'],
            'new_url' => ['required', 'string', 'max:1000'],
            'status_code' => ['required', 'integer', 'in:301,302'],
            'enabled' => ['required', 'in:0,1'],
        ];
    }

    // ---- 一覧・試す・追加・編集・削除 ----

    /** 一覧（いちばん上が追加の行）と、「URLを試す」の結果 */
    public function index(Request $request): View
    {
        // 古いURLで探し、選んだ順に並べる
        $search = trim((string) $request->query('q'));
        $order = array_key_exists((string) $request->query('order'), self::ORDER_OPTIONS) ? (string) $request->query('order') : 'old_url';

        $query = Redirect::query();
        if ($search !== '') {
            $query->where('old_url', 'like', '%'.addcslashes(RedirectResolver::normalize($search), '%_\\').'%');
        }
        match ($order) {
            'hits' => $query->orderByDesc('hits'),
            // 当たったことの無いものを先に出す（MariaDBの昇順はnullが先）
            'last_hit_at' => $query->orderBy('last_hit_at'),
            'created_at' => $query->orderByDesc('created_at'),
            default => $query->orderBy('old_url'),
        };

        return view('neobit::admin.redirects.index', [
            'redirects' => $query->orderBy('id')->paginate(self::PER_PAGE)->withQueryString(),
            'search' => $search,
            'order' => $order,
            'orderOptions' => self::ORDER_OPTIONS,
            'statusOptions' => self::STATUS_OPTIONS,
            'test' => $this->testResult((string) $request->query('test')),
            'testUrl' => (string) $request->query('test'),
            'enabled' => (bool) config('neobit.seo.redirects'),
            'routes' => $this->routeNames(),
        ]);
    }

    /** 追加 */
    public function store(Request $request): RedirectResponse
    {
        $values = $this->validatedValues($request, null);

        $redirect = Redirect::create($values);

        OperationRecorder::record(OperationLogAction::Create, $redirect);

        return redirect()->to($this->indexUrl())
            ->with('status', 'リダイレクトを登録しました。');
    }

    /** 更新。一覧の行から送る。操作ログには、変わった列の名前を残す */
    public function update(Request $request, Redirect $redirect): RedirectResponse
    {
        $values = $this->validatedValues($request, $redirect);

        $redirect->fill($values)->save();

        OperationRecorder::record(OperationLogAction::Update, $redirect, OperationRecorder::loggableFields($redirect, array_keys($redirect->getChanges())));

        return redirect()->to($this->indexUrl())
            ->with('status', 'リダイレクトを更新しました。');
    }

    /** 削除 */
    public function destroy(Redirect $redirect): RedirectResponse
    {
        $redirect->delete();

        OperationRecorder::record(OperationLogAction::Delete, $redirect);

        return redirect()->to($this->indexUrl())
            ->with('status', "「{$redirect->old_url}」のリダイレクトを削除しました。");
    }

    /** 保存と削除の後に戻る一覧。直前に見ていた一覧なら、そのページ・検索・並び順のまま戻る */
    private function indexUrl(): string
    {
        $index = route($this->routeNames()['index']);
        $previous = url()->previous();

        return str_starts_with($previous, $index) ? $previous : $index;
    }

    /**
     * 入力を検証し、URLをそろえて、ほかの登録との関係を確かめた値。$redirectは編集するもの。追加ならnull
     */
    private function validatedValues(Request $request, ?Redirect $redirect): array
    {
        $validated = $request->validate($this->rules(), [], self::LABELS);

        $old = RedirectResolver::normalize($validated['old_url']);
        $new = RedirectResolver::normalize($validated['new_url']);

        // 自分以外の登録と比べる
        $others = Redirect::query()
            ->when($redirect !== null, fn ($query) => $query->whereKeyNot($redirect->id))
            ->get(['old_url', 'new_url'])
            ->map(fn (Redirect $other) => ['old_url' => $other->old_url, 'new_url' => $other->new_url])
            ->all();

        $errors = RedirectChecker::errors($old, $new, $others);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'old_url' => $old,
            'new_url' => $new,
            'status_code' => (int) $validated['status_code'],
            'enabled' => $validated['enabled'] == '1',
        ];
    }

    // ---- URLを試す ----

    /**
     * 「URLを試す」の結果。入れたURL（パスか、http(s)://から始まるURL）が、どのリダイレクトに当たり、最後にどこへ着くか。
     * 何も入れていなければnull
     *
     * @return array{redirect: ?array{id: int, old_url: string, location: string, status_code: int, wildcard: bool}, message: string}|null
     */
    private function testResult(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // URLを丸ごと入れたときは、パスと?の後ろを取り出す
        $parts = parse_url(str_starts_with($url, '/') ? 'http://localhost'.$url : $url);
        $path = rawurldecode($parts['path'] ?? '/');
        $query = $parts['query'] ?? '';

        // 登録に当たるもの
        $redirect = RedirectResolver::find($path, $query);
        if ($redirect !== null) {
            return [
                'redirect' => $redirect,
                'message' => "{$redirect['status_code']} で {$redirect['location']} へ移ります。",
            ];
        }

        // 当たらないとき。末尾の/を外すか、画面が開くか、開ける画面が無いか
        if ($path !== '/' && str_ends_with($path, '/')) {
            return ['redirect' => null, 'message' => '末尾の / を外して '.rtrim($path, '/').' へ移ります。'];
        }
        if (RedirectChecker::opensPage($path)) {
            return ['redirect' => null, 'message' => 'リダイレクトしません。サイトの画面が開きます。'];
        }

        return ['redirect' => null, 'message' => 'リダイレクトしません。開ける画面がありません（404）。'];
    }

    // ---- CSV ----

    /** CSVダウンロード。古いURLの順 */
    public function csv(): StreamedResponse
    {
        return $this->downloadCsv(
            query: Redirect::query()->orderBy('old_url'),
            name: 'リダイレクト',
            encoding: CsvEncoding::Utf8Bom,
            header: true,
            escapeFormula: true,
        );
    }

    /** CSVの項目。見出し => 値の場所（書き方はNeobit\Core\Csv\CsvColumnSet参照）。取り込みも同じ定義を使う */
    private function csvColumns(): array
    {
        return [
            '古いURL' => 'old_url',
            '新しいURL' => 'new_url',
            '種類' => 'status_code',
            '有効' => ['enabled', [1 => '有効', 0 => '無効']],
        ];
    }

    /** CSV取り込みの設定。処理だけのモードで、保存はprocessCsvRows()が行う */
    private function csvImportSettings(): CsvImportSettings
    {
        return new CsvImportSettings(
            query: null,
            name: 'リダイレクト',
            route: $this->routeNames()['csv-import'],
            labelColumn: '古いURL',
            mode: CsvImportMode::Process,
            encoding: null,
            header: true,
            escapeFormula: true,
            allowInsert: true,
            maxRows: null,
        );
    }

    /**
     * 行をまたいだ確かめ。CSVの中の古いURLの重なりと、画面と同じRedirectChecker::errors()の確かめ。
     * 比べる相手は、登録済みのうちCSVで上書きしないものと、CSVのほかの行
     */
    private function validateCsvRows(CsvImportResult $result): void
    {
        // CSVの行ごとの、そろえた古いURLと新しいURL
        $rows = [];
        $firstLines = [];
        foreach ($result->rows as $index => $row) {
            if ($row->hasErrors()) {
                continue;
            }

            $old = RedirectResolver::normalize($row->validated['old_url']);
            if (isset($firstLines[$old])) {
                $row->addError("同じ古いURLが{$firstLines[$old]}行目にもあります。", self::LABELS['old_url']);

                continue;
            }
            $firstLines[$old] = $row->line;
            $rows[$index] = ['old_url' => $old, 'new_url' => RedirectResolver::normalize($row->validated['new_url'])];
        }

        // 登録済みのうち、CSVで上書きしないもの
        $registered = Redirect::query()
            ->whereNotIn('old_url', array_keys($firstLines))
            ->get(['old_url', 'new_url'])
            ->map(fn (Redirect $redirect) => ['old_url' => $redirect->old_url, 'new_url' => $redirect->new_url])
            ->all();

        foreach ($rows as $index => $values) {
            $others = [...$registered, ...array_values(array_diff_key($rows, [$index => true]))];

            foreach (RedirectChecker::errors($values['old_url'], $values['new_url'], $others) as $field => $message) {
                $result->rows[$index]->addError($message, self::LABELS[$field]);
            }
        }
    }

    /** 検証済みの全行を保存する。古いURLが登録済みなら更新し、無ければ追加する */
    private function processCsvRows(CsvImportResult $result): string
    {
        $inserted = 0;
        $updated = 0;

        foreach ($result->rowsOf('process') as $row) {
            $redirect = Redirect::query()->firstOrNew(['old_url' => RedirectResolver::normalize($row->validated['old_url'])]);
            $redirect->fill([
                'new_url' => RedirectResolver::normalize($row->validated['new_url']),
                'status_code' => (int) $row->validated['status_code'],
                'enabled' => $row->validated['enabled'] == '1',
            ]);

            // 変わらない行は保存しない
            if (! $redirect->exists) {
                $inserted++;
            } elseif ($redirect->isDirty()) {
                $updated++;
            }
            $redirect->save();
        }

        return sprintf('リダイレクト：追加 %d件・更新 %d件を取り込みました（変更なし %d件）。', $inserted, $updated, count($result->rowsOf('process')) - $inserted - $updated);
    }

    /** 取り込みの画面の「一覧へ戻る」の行き先 */
    private function csvImportBackUrl(): ?string
    {
        return route($this->routeNames()['index']);
    }

    // ---- ルート ----

    /**
     * 呼ばれたルートと組になるルートの名前。末尾 => 名前。「頭.index」から「頭.store」のように、末尾だけを替える。
     * 末尾に「.」を含むもの（csv-import.confirm）があるので、知っている末尾を長いものから外して頭を求める
     */
    private function routeNames(): array
    {
        $name = (string) request()->route()?->getName();
        $prefix = Str::beforeLast($name, '.');
        foreach (self::ACTIONS as $action) {
            if (str_ends_with($name, '.'.$action)) {
                $prefix = substr($name, 0, -strlen('.'.$action));

                break;
            }
        }

        return array_combine(self::ACTIONS, array_map(fn (string $action) => $prefix.'.'.$action, self::ACTIONS));
    }
}
