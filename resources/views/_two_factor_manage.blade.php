{{--
    ログイン中の本人による、2段階認証の管理。バックアップコードの発行と、認証アプリの登録の解除の
    ボタンと、その確認の画面。スタッフの詳細と、会員の2段階認証の画面から@includeする。
    本人が見ているときだけ@includeする。入口はApp\Support\TwoFactorManagementトレイト。

    ■ バックアップコードの発行
    バックアップコードを使うモデルの人に出す（モデルの定数 BACKUP_CODES）。発行の前に、その人の
    2段階目の方式で本人を確かめる。
    - 認証アプリの方式：確認の画面（モーダル）で、今の認証アプリのコードを入力する
    - メールの方式：確認コードをメールで送り、届いたコードをこの画面で入力する

    ■ 認証アプリの登録の解除
    認証アプリの方式で、登録が済んでいる人に出す。認証アプリのコードか、バックアップコードで確かめる。
    端末を無くした人も、バックアップコードを発行してあれば、自分で解除できる。

    モーダルの中のフォームは、画面を読み直さずに送る（クラス名ajax_submit。resources/js/app.js）。
    コードを間違えても、確認の画面を開いたまま、エラーを出せるようにするため。

    渡す変数：
    - $twoFactorAccount  ログイン中の本人
    - $twoFactorRoutes   ルートの名前。regenerate＝発行、code＝確認コードの送信、reset＝解除、
                          passkeys＝パスキーの一覧。パスキーのルートがあるサイトでは、解除の説明に
                          パスキーも消えることを書く
--}}
@php
    $usesBackupCodes = $twoFactorAccount::usesBackupCodes();
    $confirmByEmail = $twoFactorAccount::twoFactorMethod() === \App\Enums\TwoFactorMethod::Email;
    $totpConfirmed = $twoFactorAccount::usesTotp() && $twoFactorAccount->hasTwoFactorConfirmed();

    // 発行したことがあれば「再発行」、無ければ「発行」
    $issueLabel = $usesBackupCodes && $twoFactorAccount->backupCodes()->exists() ? '再発行' : '発行';

    // メールの方式：確認コードを送って入力を待っているか
    $backupCodesCodeSent = (new \App\Support\TwoFactorChallenge($twoFactorAccount))
        ->hasSent(request(), \App\Support\TwoFactorChallenge::PURPOSE_BACKUP_CODES);

    // 解除で消えるもの
    $resetAlso = array_filter([
        $usesBackupCodes ? 'バックアップコード' : null,
        Route::has($twoFactorRoutes['passkeys']) ? 'パスキー' : null,
    ]);
@endphp

@if ($usesBackupCodes && $confirmByEmail)
    {{-- メールの方式での発行。確認コードを送ってから、届いたコードを入力してもらう --}}
    <h2 class="h6 mt-4">バックアップコードを{{ $issueLabel }}する</h2>

    @if ($issueLabel === '再発行')
        <p>今のバックアップコードは全て無効になり、新しい10個に置き換わります。</p>
    @endif

    @if ($backupCodesCodeSent)
        <p>本人確認のため、メールでお送りした確認コード（6桁）を入力してください。</p>
        <form method="POST" action="{{ route($twoFactorRoutes['regenerate']) }}" class="row g-2 align-items-start">
            @csrf
            <div class="col-auto">
                <input type="text" name="code" class="form-control" inputmode="numeric"
                       autocomplete="one-time-code" aria-label="確認コード" placeholder="000000">
                <div class="invalid-feedback" data-item="code">{{ $errors->regenerateBackupCodes->first('code') }}</div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">{{ $issueLabel }}する</button>
            </div>
        </form>
        <form method="POST" action="{{ route($twoFactorRoutes['code']) }}" class="mt-2">
            @csrf
            <button type="submit" class="btn btn-link btn-sm p-0">確認コードを再送する</button>
        </form>
    @else
        {{-- 確認コードの有効期限が切れた後に入力した場合は、ここにエラーが出る --}}
        @if ($errors->regenerateBackupCodes->has('code'))
            <div class="text-danger small mb-2">{{ $errors->regenerateBackupCodes->first('code') }}</div>
        @endif
        <p>本人確認のため、登録されているメールアドレスに確認コードをお送りします。</p>
        <form method="POST" action="{{ route($twoFactorRoutes['code']) }}">
            @csrf
            <button type="submit" class="btn btn-primary">確認コードを送信する</button>
        </form>
    @endif
@endif

@if ($totpConfirmed)
    <div class="mt-3 d-flex gap-2">
        @if ($usesBackupCodes)
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#regenerateBackupCodesModal">
                バックアップコードを{{ $issueLabel }}する
            </button>
        @endif
        <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#selfResetTwoFactorModal">
            2段階認証の登録を解除する
        </button>
    </div>

    @if ($usesBackupCodes)
        <div class="modal fade" id="regenerateBackupCodesModal" tabindex="-1" aria-labelledby="regenerateBackupCodesModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route($twoFactorRoutes['regenerate']) }}" class="ajax_submit">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="regenerateBackupCodesModalLabel">バックアップコードの{{ $issueLabel }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                        </div>
                        <div class="modal-body">
                            <p>
                                @if ($issueLabel === '再発行')
                                    今のバックアップコードは全て無効になり、新しい10個に置き換わります。
                                @else
                                    スマートフォンが手元に無いときに、1回ずつ使える緊急用のコードを10個発行します。
                                @endif
                                よろしければ、認証アプリに表示されている6桁の数字を入力してください。
                            </p>
                            <div class="mb-3">
                                <label for="regenerate_code" class="form-label">確認コード</label>
                                <input id="regenerate_code" type="text" name="code"
                                       class="form-control" inputmode="numeric" autocomplete="one-time-code" placeholder="000000">
                                <div class="invalid-feedback" data-item="code">{{ $errors->regenerateBackupCodes->first('code') }}</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                            <button type="submit" class="btn btn-primary">{{ $issueLabel }}する</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

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
                            2段階認証の登録を解除します。
                            @if ($resetAlso !== [])
                                {{ implode('・', $resetAlso) }}も全て無効になります。
                            @endif
                            次回ログイン時に、QRコードから登録し直すことになります
                            （スマートフォンの機種変更や、紛失のときなどにお使いください）。
                            よろしければ、認証アプリに表示されている6桁の数字を入力してください。
                        </p>
                        <div class="mb-3">
                            <label for="self_reset_code" class="form-label">確認コード</label>
                            <input id="self_reset_code" type="text" name="code"
                                   class="form-control" inputmode="numeric" autocomplete="one-time-code" placeholder="000000">
                            <div class="invalid-feedback" data-item="code">{{ $errors->selfResetTwoFactor->first('code') }}</div>
                        </div>

                        {{-- 認証アプリの入った端末を無くした人のための入力欄。バックアップコードを発行して
                             ある人にだけ出す。どちらか片方を入れれば通る。両方入っていたら、
                             バックアップコードで確かめる --}}
                        @if ($usesBackupCodes && $twoFactorAccount->unusedBackupCodesCount() > 0)
                            <p class="text-muted small mb-2">
                                スマートフォンが手元に無い場合は、バックアップコードを入力してください。
                            </p>
                            <div class="mb-3">
                                <label for="self_reset_backup_code" class="form-label">バックアップコード</label>
                                <input id="self_reset_backup_code" type="text" name="backup_code"
                                       class="form-control" placeholder="0000-0000">
                                <div class="invalid-feedback" data-item="backup_code">{{ $errors->selfResetTwoFactor->first('backup_code') }}</div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="submit" class="btn btn-warning">解除する</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
