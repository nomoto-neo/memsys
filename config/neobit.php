<?php

use App\Enums\AppOperationLogAction;
use App\Enums\AppOperationLogModel;
use App\Models\News;
use App\Models\Page;
use App\Models\Staff;
use App\Services\BulkMailCleanup;
use App\Services\InquiryCleanup;
use Neobit\Core\Enums\OperationLogAction;

/**
 * ネオビットフレームワークの設定のうち、このサイトで変える値だけを、機能ごとの節で書く。
 * 既定の値と各項目の説明は、vendor/neobit/core/config/neobit/ の同じ名前のファイルにある。書いていないキーは、既定の値になる。
 * 節の中の配列は丸ごと置き換わるので、変えない要素も一緒に書く。
 */
return [

    'layouts' => [
        'admin' => 'layouts.admin',
        'app' => 'layouts.app',
    ],

    'code_table' => [
        'db' => [
            'gender' => ['label' => '性別', 'fixed' => true],
            'contact' => ['label' => '連絡方法', 'fixed' => false],
        ],
        'method' => [
            // スタッフの氏名。過去のデータの名前を出す用で、削除したスタッフも含む
            'staff' => [Staff::class, 'nameOptions'],
            // 担当者を選ぶプルダウンの用。今いるスタッフだけ
            'active_staff' => [Staff::class, 'activeNameOptions'],
        ],
    ],

    'operation_log' => [
        'actions' => [
            OperationLogAction::class,
            AppOperationLogAction::class,
        ],
        'models' => [
            AppOperationLogModel::class,
        ],
        'route' => 'admin.operation-logs.index',
    ],

    'seo' => [
        // お問い合わせの完了の画面は、検索に出さない
        'noindex_routes' => ['contact.thanks'],
        // トップ・お知らせの一覧・お問い合わせ
        'sitemap_routes' => ['top', 'news.index', 'contact.create'],
        'sitemap_models' => [
            News::class,
            Page::class,
        ],
        // 企業会員の画面・マイページは巡回させない。管理画面はURLを教えないよう書かない
        'robots_disallow' => ['/company', '/mypage'],
        // 管理画面の「SEO設定」で、タイトル・説明文などを入れられるページ
        'page_routes' => [
            'top' => 'トップページ',
            'news.index' => 'お知らせの一覧',
            'contact.create' => 'お問い合わせ',
            'newsletter.create' => 'メルマガの登録',
            'regist.create' => '会員登録',
        ],
        // 管理画面の「リダイレクト」で登録した移し方を当てる
        'redirects' => true,
    ],

    'cleanup' => [
        'tasks' => [
            '保存期間を過ぎたお問い合わせ' => [
                'run' => [InquiryCleanup::class, 'deleteOld'],
                // nullなら、消さずに残し続ける。日数は、サイトの運用で決める
                'days' => null,
            ],
            '保存期間を過ぎた一斉メールの記録' => [
                'run' => [BulkMailCleanup::class, 'deleteOld'],
                // 送り終えてから数える。nullなら、消さずに残し続ける。日数は、サイトの運用で決める。送信の履歴の画面にも出る
                'days' => null,
            ],
        ],
    ],

];
