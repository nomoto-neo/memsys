<?php

use Neobit\Core\Enums\OperationLogAction;

return [

    /*
    |--------------------------------------------------------------------
    | レイアウト
    |--------------------------------------------------------------------
    |
    | フレームワークの画面（CSV取り込み、2段階認証など）が継承するレイアウト。
    | アプリの config/neobit.php で、アプリのレイアウトに差し替える。
    | レイアウトは、セクション title・content（必須）・content-width と、
    | スタック head-extra・scripts を持つ。
    |
    */

    // admin：管理画面の画面が継承するレイアウト。app：訪問者・会員の画面が継承するレイアウト
    'layouts' => [
        'admin' => 'neobit::layouts.plain',
        'app' => 'neobit::layouts.plain',
    ],

    /*
    |--------------------------------------------------------------------
    | 管理画面のURL
    |--------------------------------------------------------------------
    |
    | 管理画面の頭のURL（先頭と末尾の/は付けない）。/adminはURLを順に叩く攻撃で真っ先に狙われるので、
    | 既定もapp/adminにしている。アプリのroutes/web.phpが管理画面のグループのprefix()に使い、
    | サイトのアクセス制限（RestrictSiteAccess）は、ここの下を制限の外にする。
    | 変えたら、ベーシック認証のVirtualHostの決まり（利用ガイドの25章）も合わせて直す。
    | robots.txtには書かない。誰でも読めるので、隠したURLを教えることになるため。
    |
    */

    'admin_path' => 'app/admin',

    /*
    |--------------------------------------------------------------------
    | コード表
    |--------------------------------------------------------------------
    |
    | コード表の読み込み（Neobit\Core\Code\CodeTable）に渡す、プロジェクトの設定。
    |
    */

    // DBのコード表（code_tables）。ここに載せたコード名は、DBから読み、管理画面の項目見出し一覧で
    // 編集できる。コード名 => ['label' => 項目見出し一覧に出す名前, 'fixed' => コード値を固定するか]。
    // fixedをtrueにすると、並び替えと表示名の変更だけができる。プログラムが特定のコード値を
    // 前提に動くコード表は、trueにする。初期データは、アプリのシーダーで入れる
    'code_table_db' => [],

    // ほかのテーブルなどから、プログラムで作るコード表。コード名 => [クラス, 静的メソッドの名前]。
    // メソッドは「値 => 名称」の配列を返す
    'code_table_method' => [],

    // 列挙型のコード表を探す、アプリの名前空間。書いた順に探す。末尾に \ を付ける
    'code_table_enum_namespaces' => [
        'App\\Enums\\',
    ],

    /*
    |--------------------------------------------------------------------
    | 操作ログ
    |--------------------------------------------------------------------
    |
    | 操作ログ（Neobit\Core\Models\OperationLog）に渡す、プロジェクトの設定。
    |
    */

    // 操作の種類（operation_logs.action）にする列挙型。Neobit\Core\Enums\OperationLogActionTypeを実装する。
    // 操作のコード表（operation_log_action）も、ここに書いた順に合わせて作る
    'operation_log_actions' => [
        OperationLogAction::class,
    ],

    // 操作した人と対象の種類（operation_logs.operator_type・target_type）に、画面の名前を付ける列挙型。
    // 値はAppServiceProviderのenforceMorphMap()の名前、label()が画面の名前。フレームワークが、ここに書いた順に
    // 合わせて、コード表operation_log_modelを作る。書いていない種類は、種類の名前のまま出る
    'operation_log_models' => [],

    // 操作ログの画面のルート名。日々の報告のメールに、この画面のURLを載せる。nullなら載せない
    'operation_log_route' => null,

    /*
    |--------------------------------------------------------------------
    | 管理画面の回数の制限
    |--------------------------------------------------------------------
    |
    | ログイン後の管理画面の全体に掛ける、回数の制限（Neobit\Core\Security\AdminRequestLimit）。
    |
    */

    // 1人のスタッフが1分に送れる回数。超えた回は処理せず、操作ログとwarningのログに残す。
    // 人が普通に使って届かない数にする。0にすると、制限を掛けない
    'admin_requests_per_minute' => 120,

    // 上の回数を超えたときに出す、案内の文。画面と、アップロードのようなJavaScriptからの送信に
    // 返すJSONの両方に、この文をそのまま出す。タグは書けない
    'admin_requests_exceeded_message' => '操作の回数が上限を超えました。1分ほど待ってから、もう一度お試しください。',

    /*
    |--------------------------------------------------------------------
    | IPブロック管理
    |--------------------------------------------------------------------
    |
    | 不正なURLを続けて叩くBOTを、IPアドレスで止める（Neobit\Core\Security\IpBlocker）。
    | 止めたIPアドレスからのアクセスには、どのページも404を返す。
    |
    */

    // 存在しないURLへのアクセスが多いIPアドレスを、自動で止めるか。falseなら、管理画面からの登録だけ
    'ip_block_auto' => true,

    // 数える時間（分）
    'ip_block_watch_minutes' => 10,

    // 上の時間に、存在しないURLへのアクセス（応答が404・405）が何回あったら止めるか。
    // ふつうの人が10分に30回も存在しないページを開くことは無く、BOTは数秒で数十回叩くので、その間に置いている
    'ip_block_watch_count' => 30,

    // 自動で止める時間（時間）。携帯電話や会社の回線は大勢で同じIPアドレスを使うので、長くしすぎない
    'ip_block_hours' => 24,

    /*
    |--------------------------------------------------------------------
    | 操作ログの報告
    |--------------------------------------------------------------------
    |
    | 1日1回の、操作ログの報告のメール（Neobit\Core\Operations\OperationLogReport）。
    |
    */

    // 報告のメールの宛先。カンマ区切りで複数書ける。空なら送らない
    'operation_report_to' => (string) env('OPERATION_REPORT_TO', ''),

    // 報告のメールで、「気になる点」として挙げる目安の数。これ以上なら挙げる。
    // 0にすると、その項目は見ない。アプリが書くと丸ごと置き換わるので、3つとも書く
    'operation_report_thresholds' => [
        // 1人が、1時間に開いた詳細の件数
        'views_per_hour' => 50,
        // 1人が、1日にCSVをダウンロードした回数
        'csv_downloads_per_day' => 5,
        // 1日のログインの失敗の件数
        'login_failures_per_day' => 10,
    ],

    /*
    |--------------------------------------------------------------------
    | サーバーのcron
    |--------------------------------------------------------------------
    |
    | サーバーでcron（毎分のphp artisan schedule:run）が動くか。動かないサーバーでは、cronの代わりに、
    | サイトへのアクセスを契機にroutes/console.phpの予定を動かす（Neobit\Core\Operations\RequestScheduler）。
    | 予定の時刻を過ぎて最初のアクセスで、応答を返した後に動く。キューのワーカーも予定の1つなので、
    | 一斉メールなどもアクセスを契機に送られる。
    |
    */

    // cronが動くならtrue。動かないサーバーだけ、.envにCRON_ENABLED=falseと書く
    'cron_enabled' => env('CRON_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | SEO（ページのタイトル・説明文・OGP）
    |--------------------------------------------------------------------------
    |
    | 訪問者の側のレイアウトの<head>の部品（neobit::_seo_head）と、ヘルパーのseo()が使う、サイトの既定。
    | 記事の詳細の画面では、記事（Neobit\Core\Seo\SeoSubject）の値が優先する。
    | - title_format        タイトルの形。{title}はページのタイトル、{site}はサイト名（app.site_name）
    | - description         記事の無いページの説明文。空なら説明文のタグを出さない
    | - description_length  記事の本文から作る説明文の文字数
    | - image               既定のシェアの画像。publicの下のパス（例：/images/ogp.png）か、URL。nullなら出さない
    | - organization_name   構造化データで出す運営者の名前。空ならサイト名
    | - organization_logo   構造化データで出す運営者のロゴ。publicの下のパスか、URL。nullなら出さない
    | - noindex_routes      検索に出さないルートの名前。*が使える（例：'contact.thanks'）
    | - sitemap_routes      サイトマップ（/sitemap.xml）に載せる、記事を持たないページのルートの名前
    | - sitemap_models      サイトマップに載せる記事のモデル（Neobit\Core\Seo\SitemapSourceを実装したもの）
    | - robots_disallow     robots.txtで巡回させない場所。本番（APP_ENV=production）だけ使い、本番以外は全部を巡回させない。
    |                       管理画面は書かない（URLを教えることになるため。管理画面はX-Robots-Tagで検索に出さない）
    | - page_routes         管理画面の「SEO設定」に出す、記事を持たないページ。ルート名 => 画面に出す名前。
    |                       引数の要らないルートだけ
    | - redirects           管理画面の「リダイレクト」で登録した移し方を当てるか。falseなら登録を見ずに、
    |                       URLの末尾の/を外す301だけをする（Neobit\Core\Http\Middleware\ApplyRedirects）
    |
    | title_format・description・image・organization_name・organization_logoは、管理画面の「SEO設定」で空欄のときの値。
    | 配列のキーなので、アプリのconfig/neobit.phpでseoを書くときは、全部のキーを書く。
    */

    'seo' => [
        'title_format' => '{title}｜{site}',
        'description' => '',
        'description_length' => 120,
        'image' => null,
        'organization_name' => '',
        'organization_logo' => null,
        'noindex_routes' => [],
        'sitemap_routes' => [],
        'sitemap_models' => [],
        'robots_disallow' => [],
        'page_routes' => [],
        'redirects' => false,
    ],

];
