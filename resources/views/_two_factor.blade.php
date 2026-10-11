{{--
    ログインの2段階目の画面の中身。個人会員・企業会員・管理画面で同じものを使う。
    各ログインの2段階目のビュー（auth/login-verify など）が、レイアウトの中で@includeする。
    入口はApp\Support\TwoFactorLoginトレイト。設計は docs/two-factor-spec.md。

    渡される変数：
    - $twoFactorState    今出す画面。email＝メールの確認コードの入力、totp＝認証アプリのコードの入力、
                          setup＝認証アプリの登録（QRコード）
    - $twoFactorLoginId  ログインに使った値。認証アプリの画面で、どのアカウントのコードかを示す
    - $twoFactorBackupCodes  バックアップコードの入力欄を出すか。発行してある人にだけ出す
    - $twoFactorRoutes   ルートの名前。login＝ログイン画面、confirm＝照合、resend＝確認コードの再送
    - $twoFactorSecret・$twoFactorQrCode  （setup）秘密鍵と、QRコードの画像

    @includeする側が渡す変数：
    - $rememberLabel     「この端末を記憶する」のチェックの文言
--}}
<div class="card">
    @if ($twoFactorState === 'email')
        {{-- メールの確認コードの入力 --}}
        <div class="card-header">確認コードの入力</div>
        <div class="card-body">
            <p>
                ご登録のメールアドレス宛に、確認コードを送信しました。
                メールに記載された6桁の数字を入力してください。
            </p>

            <form method="POST" action="{{ route($twoFactorRoutes['confirm']) }}">
                @csrf

                <div class="mb-3">
                    <label for="code" class="form-label">確認コード</label>
                    <input id="code" type="text" name="code"
                           class="form-control"
                           inputmode="numeric" autocomplete="one-time-code" autofocus>
                    <div class="invalid-feedback" data-item="code">{{ $errors->first('code') }}</div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="remember_device" value="1" class="form-check-input" id="remember_device">
                    <label class="form-check-label" for="remember_device">{{ $rememberLabel }}</label>
                </div>

                <button type="submit" class="btn btn-primary">ログインする</button>
            </form>

            <form method="POST" action="{{ route($twoFactorRoutes['resend']) }}" class="mt-3">
                @csrf
                <button type="submit" class="btn btn-link p-0">確認コードを再送する</button>
            </form>

            @if ($twoFactorBackupCodes)
                @include('_two_factor_backup_code_form', ['backupCodeLead' => 'メールが届かない場合は、発行済みのバックアップコードでもログインできます。'])
            @endif
        </div>
    @elseif ($twoFactorState === 'totp')
        {{-- 認証アプリのコードの入力 --}}
        <div class="card-header">2段階認証</div>
        <div class="card-body">
            <p>
                ログインID「<strong>{{ $twoFactorLoginId }}</strong>」の認証アプリに表示されている、
                6桁の数字を入力してください。
            </p>

            <form method="POST" action="{{ route($twoFactorRoutes['confirm']) }}">
                @csrf

                <div class="mb-3">
                    <label for="code" class="form-label">確認コード</label>
                    <input id="code" type="text" name="code"
                           class="form-control"
                           inputmode="numeric" autocomplete="one-time-code" autofocus>
                    <div class="invalid-feedback" data-item="code">{{ $errors->first('code') }}</div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="remember_device" value="1" class="form-check-input" id="remember_device">
                    <label class="form-check-label" for="remember_device">{{ $rememberLabel }}</label>
                </div>

                <button type="submit" class="btn btn-primary">ログインする</button>
            </form>

            @if ($twoFactorBackupCodes)
                @include('_two_factor_backup_code_form', ['backupCodeLead' => 'スマートフォンが手元に無い場合は、発行済みのバックアップコードでもログインできます。'])
            @endif

            <div class="mt-3">
                <a href="{{ route($twoFactorRoutes['login']) }}">ログイン画面に戻る</a>
            </div>
        </div>
    @else
        {{-- 認証アプリの登録。QRコードを読み取ってもらい、出たコードを1回入力してもらって確かめる --}}
        <div class="card-header">2段階認証の登録</div>
        <div class="card-body">
            <p>
                ログインID「<strong>{{ $twoFactorLoginId }}</strong>」用の2段階認証を設定します。
                Google Authenticator・Microsoft Authenticatorなどの認証アプリで、
                下のQRコードを読み取ってください。読み取ると、認証アプリの画面に
                6桁の数字（30秒ごとに変わります）が表示されます。
            </p>

            <div class="text-center my-4">
                <img src="{{ $twoFactorQrCode }}" alt="QRコード" width="240" height="240">
            </div>

            <p class="text-muted small">
                カメラでQRコードを読み取れない場合は、次のキーを認証アプリに手入力してください。<br>
                <code>{{ $twoFactorSecret }}</code>
            </p>

            <form method="POST" action="{{ route($twoFactorRoutes['confirm']) }}">
                @csrf

                <div class="mb-3">
                    <label for="code" class="form-label">
                        認証アプリに表示されている、ログインID「{{ $twoFactorLoginId }}」の6桁の数字
                    </label>
                    <input id="code" type="text" name="code"
                           class="form-control"
                           inputmode="numeric" autocomplete="one-time-code" autofocus>
                    <div class="invalid-feedback" data-item="code">{{ $errors->first('code') }}</div>
                </div>

                <button type="submit" class="btn btn-primary">登録を完了する</button>
            </form>

            <div class="mt-3">
                <a href="{{ route($twoFactorRoutes['login']) }}">ログイン画面に戻る</a>
            </div>
        </div>
    @endif
</div>
