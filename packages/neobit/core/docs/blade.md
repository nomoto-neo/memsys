# 画面（Blade）の決まり

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

## 画面の書き方

- **レイアウト**：`layouts/` ディレクトリに置かれたテンプレートが、ページ全体の外枠になります。処理結果のメッセージは `->with('status', '...')`（緑）・`->with('error', '...')`（赤）で渡すと、レイアウトが表示します。
- **画面ごとのスクリプト**：その画面だけで使うもの（アップロード、WYSIWYG、並び替えなど）を head タグ内に差し込むときは、`@push('head-extra')` に書きます。body の終わりに差し込むときは、`@push('scripts')` に書きます。全画面で使うものは、フレームワークの `global.js` にあり、3つのレイアウトが読み込んでいます。
- **全体で使う CSS**：フレームワークの `global.css` にあります。3つのレイアウトが読み込んでいるので、どの画面にも効きます。サイトだけの全体の CSS を足すときは、アプリに `resources/css/app.css` を作り、レイアウトで `global.css` の後に読み込みます。画面の CSS は Bootstrap を使い、このファイルには、Bootstrap で足りない分だけを書きます。今あるのは、見本の文字の濃さ、エラー欄の表示、読み取り専用の表示、WYSIWYG の本文の表示、削除済みの行の色です。変えたら、`npm run build` をやり直します。その画面だけで使う CSS は、その画面の `@push('head-extra')` に書きます。
- **入力欄の見本の文字（placeholder）**：濃さは、`global.css` で全体を `opacity: 0.5` にそろえています。入力した値と見分けやすくするためです。確認コード（6桁）の入力欄には、`placeholder="000000"` を付けます。バックアップコードの入力欄は `0000-0000` です。
- **フォームの補助（global.js）**：必須マーク（`.required-mark`）の付いたラベルの `for` の欄に `required` 属性を付ける、エラー欄（`.invalid-feedback[data-item]`）に文言があれば同じ名前の欄を赤くする、ブラウザの入力チェックの結果を同じエラー欄に出す、入力し直したらエラー表示を消す、エラーのあるモーダルを開く、クラス名 `ajax_submit` の付いたフォームを画面を読み直さずに送る、フォームを送っている間は二重の送信を止めて処理中の表示を出す。画面ごとの設定は要りません。ラベルの `for` と入力欄の `id` を必ず対応させてください。
- **フォームの送信中の表示（global.js）**：どのフォームも、送信した瞬間から2回目の送信を止めます。0.3秒経っても画面が切り替わらなければ、画面全体を覆って処理中であることを見せます。目印は要りません。確認コードのメールが2通届く、登録が2件できる、といったことを防ぐためです。すぐ切り替わる画面では、表示は出ません。リンクでの移動には出しません。読み込みを Esc キーで止めたときと、ブラウザの「戻る」で画面が復元されたときは、送信できる状態に戻ります。
- **ファイルを返すものは、フォームにせずリンクにします（global.js）**。フォームにするときは `target` を付けます。ファイルが返ってくると画面が切り替わらないので、処理中の表示が覆ったままになるためです。`target` の付いたフォームには、送信中の表示の仕組みは働きません。CSV のダウンロードと PDF は、どれもリンクにしてあります。
- **モーダルの中のフォーム（global.js）**：入力欄のあるフォームには `<form class="ajax_submit" ...>` と書きます。`global.js` が画面を読み直さずに送り、エラーは同じフォームのエラー欄（`.invalid-feedback[data-item]`）に出します。うまくいったときだけ、コントローラーが返した移動先へ移ります。送っている間は、画面全体を覆って処理中であることを見せます。エラーのたびに画面を読み直すと、モーダルが閉じて開き直すことになるためです。
    - コントローラーは、ふつうの送信と同じくリダイレクトを返します。JSON への読み替えは、ミドルウェア `AjaxFormResponse` が行います。エラーは `redirect()->withErrors([...])` でも `validate()` でもかまいません。
    - 削除の確認のように入力欄の無いモーダルには、付けなくてかまいません。エラーが出ないためです。
    - JavaScript が動かなかったときは、ふつうの送信になります。そのときは、エラーのあるモーダルを `global.js` が自動で開きます。
    - モーダルを使う画面には、Bootstrap の JavaScript が要ります。管理画面のレイアウトは読み込んでいますが、訪問者向けと企業会員のレイアウトは読み込んでいないので、その画面の `@push('head-extra')` で読み込みます（例：`mypage/two-factor.blade.php`）。
- **クラス名と `data-` の使い分け**：JavaScript に「この要素はこの機能で動かす」と伝えるのは、クラス名です（`ajax_upload_block`・`wysiwyg`・`ajax_submit`）。その機能に値を渡すときに、`data-` の属性を使います（`data-upload-url`・`data-item`・`data-confirm`）。
- **読み取り専用の表示**：管理画面の確認・詳細画面は、入力画面と同じ `_fields` を共有して表示します。`$readonly = ' readonly'`・`$disabled = ' disabled'` を入力欄に展開して、入力できない表示にします。項目が増減したときに、それぞれの画面を書き直さずに済みます。見た目は、全体で使う CSS（フレームワークの `global.css`）で整えています。訪問者向けや企業会員の画面でも、同じ仕組みをそのまま使えます。
- **一覧**：削除済みの行は `row-deleted`、ページ送りは `pagination::bootstrap-5`（Bootstrap v5 のデザインで表示）。
- ページ送りで、今のページの左右に展開するページの数は、3つずつです。左右4つずつに増やしたいときは、`onEachSide()` を付けます。

    ```blade
    {{ $records->onEachSide(4)->links('pagination::bootstrap-5') }}
    ```
- **戻り先**：一覧へ戻るリンクは `route('admin.xxx.index', ['back'])`。
- **共通の部分ビュー**（`_confirm_hidden`・`_ajax_upload_block` など）は、フレームワークの `packages/neobit/core/resources/views/` に置き、モデル名やコントローラー名を書きません。コーナー専用のテンプレート（`admin/news/_fields` など）は、アプリの `resources/views/` に置き、そのコーナーのモデルの定数を参照してかまいません。フレームワークの画面は、`neobit::` を付けた名前で `@include` します（例：`@include('neobit::_confirm_hidden')`）。見た目や文言を変えたいときは、アプリの `resources/views/vendor/neobit/` に同じ名前（例：`_confirm_hidden.blade.php`）で置くと、そちらが使われます。
- **フレームワークの画面が継承するレイアウト**は、`config/neobit.php` の `layouts`（`admin`・`app`）で、アプリのレイアウトに差し替えます。既定は、Bootstrap だけの `neobit::layouts.plain` です。レイアウトには、セクション `title`・`content`（必須）・`content-width` と、スタック `head-extra`・`scripts` を用意します。

## スクリプトと CSS のビルド（vite.config.js）

フレームワークのスクリプトと CSS は、`packages/neobit/core/resources/js/`・`css/` にあります。アプリからは、Composer が置いた `vendor/neobit/core/` の下のパスで読み込みます。

```js
// vite.config.js
const NEOBIT = 'vendor/neobit/core/resources';

input: [`${NEOBIT}/css/global.css`, `${NEOBIT}/js/global.js`, `${NEOBIT}/js/passkeys.js`, ...],
resolve: {
    preserveSymlinks: true,
    alias: { '@neobit': fileURLToPath(new URL(`./${NEOBIT}/js`, import.meta.url)) },
},
```

```blade
@vite(['vendor/neobit/core/resources/js/passkeys.js'])
```

- **`preserveSymlinks: true`**：`vendor/neobit/core` がリンクのとき（memsys の手元とサーバー）に要ります。無いと、リンクの先の実体のパスでマニフェストに載り、画面の `@vite()` が見つけられずにエラーになります。リンクでない案件に書いておいても、害はありません。
- **`@neobit`**：フレームワークのスクリプトの場所の短い名前です。エディタのスクリプトをアプリへコピーしても読み込みが切れないよう、フレームワークのスクリプトは `@neobit/upload_request.js` のように読み込みます（[upload.md](upload.md)）。
- **ビルドした URL にフレームワークの名前は出ません**。URL は `/build/assets/global-<ハッシュ>.js` のように、ファイルの名前から作られます。`npm run dev` を使っている間だけは、手元の画面で元のパスのまま出ます。
- **フレームワークを更新したら、`npm run build` をやり直します**。スクリプトや CSS が変わっていることがあるためです。

フレームワークのスクリプトは、npm のパッケージを読み込みます。Composer は npm のパッケージを入れないので、アプリの `package.json` に書きます。

| npm のパッケージ | 読み込むスクリプト | 要るとき |
|---|---|---|
| `@laravel/passkeys` | `passkeys.js` | パスキーを使うとき |
| `suneditor` | `wysiwyg_suneditor.js`・`wysiwyg_suneditor_content.css` | SunEditor を使うとき |
