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

## 権限の段階を足す

スタッフと管理者の間に、部門長のような段階を足すときの手順です。直すのは案件の側だけで、フレームワークは変わりません。

1. **段階**：`app/Enums/StaffAcl.php` に case を足し、`LABELS` に名前を足します。
   - 値は、今ある値と重ならない数にします（例：`SectionLeader = 2`）。今のデータを書き換えずに済むためです。
   - 選択肢・入力の検証・CSV はコード表から作るので、これで部門長が出ます。選択肢は `LABELS` に書いた順に並びます。
2. **判定**：`Staff` の `isManager()` の隣に、判定のメソッドを足します。値の大小は権限の上下と合わないので、値を比べずに、当てはまる段階を書き並べます。

   ```php
   /** 部門長以上かどうか */
   public function isSectionLeaderOrAbove(): bool
   {
       return in_array($this->acl, [StaffAcl::SectionLeader, StaffAcl::Manager], true);
   }
   ```

3. **URL 単位の判断**：ミドルウェアを用意して、部門長にも使わせる画面をそのグループに入れます。
   - `EnsureStaffIsManager` を写してミドルウェアを作ります。
   - `bootstrap/app.php` に短い名前で登録します（例：`acl.section_leader`）。
   - 部門長にも使わせる画面は、`routes/admin.php` でそのグループに入れます。
4. **1件ごとの判断**：相手によって変わる判断（例：自分の部門のスタッフだけ編集できる）は、Policy に書きます。部門で分けるなら、スタッフに部門の列も要ります。
5. **画面**：`isManager()` でボタンやリンクを出し分けているところのうち、部門長にも出すものは、新しい判定に替えます。

- 権限を変えてよいか（`StaffPolicy::updateAcl()`）は、管理者だけのままにします。部門長が自分の権限を上げられないようにするためです。
