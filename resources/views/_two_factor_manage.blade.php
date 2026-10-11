{{--
    ログイン中の本人による、2段階認証（認証アプリ）の管理。バックアップコードの再発行と、
    登録の解除のボタンと、その確認の画面。スタッフの詳細と、会員の2段階認証の画面から@includeする。
    入口はApp\Support\TwoFactorManagementトレイト。

    再発行は今の認証アプリのコードで確かめる。解除は、認証アプリのコードか、バックアップコードで
    確かめる。端末を無くした人も、バックアップコードで自分で解除できる。本人が見ていて、
    登録が済んでいるときだけ@includeする。

    2つのフォームは、画面を読み直さずに送る（クラス名ajax_submit。resources/js/app.js）。コードを間違えても、
    確認の画面を開いたまま、エラーを出せるようにするため。

    渡す変数：
    - $twoFactorRoutes  ルートの名前。regenerate＝再発行、reset＝解除、passkeys＝パスキーの一覧。
                         パスキーのルートがあるサイトでは、解除の説明にパスキーも消えることを書く
--}}
<div class="mt-3 d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#regenerateBackupCodesModal">
        バックアップコードを再発行する
    </button>
    <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#selfResetTwoFactorModal">
        2段階認証の登録を解除する
    </button>
</div>

<div class="modal fade" id="regenerateBackupCodesModal" tabindex="-1" aria-labelledby="regenerateBackupCodesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route($twoFactorRoutes['regenerate']) }}" class="ajax_submit">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="regenerateBackupCodesModalLabel">バックアップコードの再発行</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    <p>
                        今のバックアップコード10個は全て無効になり、新しい10個に置き換わります。
                        よろしければ、認証アプリに表示されている6桁の数字を入力してください。
                    </p>
                    <div class="mb-3">
                        <label for="regenerate_code" class="form-label">確認コード</label>
                        <input id="regenerate_code" type="text" name="code"
                               class="form-control" inputmode="numeric" autocomplete="one-time-code">
                        <div class="invalid-feedback" data-item="code">{{ $errors->regenerateBackupCodes->first('code') }}</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" class="btn btn-primary">再発行する</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="selfResetTwoFactorModal" tabindex="-1" aria-labelledby="selfResetTwoFactorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route($twoFactorRoutes['reset']) }}" class="ajax_submit">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title" id="selfResetTwoFactorModalLabel">2段階認証の登録解除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    <p>
                        2段階認証の登録を解除します。バックアップコード{{ Route::has($twoFactorRoutes['passkeys']) ? '・パスキー' : '' }}も全て無効になり、
                        次回ログイン時にQRコードから登録し直すことになります
                        （スマートフォンの機種変更や、紛失のときなどにお使いください）。
                        よろしければ、認証アプリに表示されている6桁の数字を入力してください。
                    </p>
                    <div class="mb-3">
                        <label for="self_reset_code" class="form-label">確認コード</label>
                        <input id="self_reset_code" type="text" name="code"
                               class="form-control" inputmode="numeric" autocomplete="one-time-code">
                        <div class="invalid-feedback" data-item="code">{{ $errors->selfResetTwoFactor->first('code') }}</div>
                    </div>

                    {{-- 認証アプリの入った端末を無くした人のための入力欄。どちらか片方を入れれば通る。
                         両方入っていたら、バックアップコードで確かめる --}}
                    <p class="text-muted small mb-2">
                        スマートフォンが手元に無い場合は、バックアップコードを入力してください。
                    </p>
                    <div class="mb-3">
                        <label for="self_reset_backup_code" class="form-label">バックアップコード</label>
                        <input id="self_reset_backup_code" type="text" name="backup_code"
                               class="form-control" placeholder="0000-0000">
                        <div class="invalid-feedback" data-item="backup_code">{{ $errors->selfResetTwoFactor->first('backup_code') }}</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" class="btn btn-warning">解除する</button>
                </div>
            </form>
        </div>
    </div>
</div>
