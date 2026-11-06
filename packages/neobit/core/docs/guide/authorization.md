# 権限

<p align="right"><a href="../guide.md">利用ガイドへ戻る</a></p>

**ファイル**：`app/Policies/StaffPolicy.php`・`app/Http/Middleware/EnsureStaffIsManager.php`　**実例**：スタッフ

- **URL 単位で判断するもの**（一覧・新規登録など、管理者だけが使える機能）：ルートを `Route::middleware('acl.manager')` のグループに入れます。
- **1件ごとの判断**（本人か管理者なら編集できる、自分自身は削除できない、削除済みは編集できない、など）：`app/Policies/<モデル名>Policy.php` にメソッドを書くと、名前の対応で自動的に使われます。同じ判断を3か所から使います。
  - ルート：`->middleware('can:update,staff')`（false なら 403）
  - 画面：`@can('update', $staff)`（ボタンやリンクの出し分け）
  - コントローラー：`Auth::guard('admin')->user()->can('updateAcl', $staff ?? Staff::class)`
- 権限によって入力欄を出さない項目は、`rules()` で `'exclude'` にし、`saveValues()` の `$saveFieldNames` からも外します（外さないと NULL で上書きしてしまう。実例：スタッフの権限）。
- 管理者かどうかの判定は `Staff::isManager()` です。
