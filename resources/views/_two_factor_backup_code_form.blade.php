{{--
    ログインの2段階目の画面の、バックアップコードでログインするフォーム。_two_factorが、
    バックアップコードを発行してある人にだけ@includeする。メールの方式でも認証アプリの方式でも同じ。

    渡す変数：
    - $backupCodeLead  どんなときに使うかの案内
    _two_factorの $twoFactorRoutes と、そこで決めた $rememberLabel も使う。
--}}
<hr>

<p class="text-muted small mb-2">{{ $backupCodeLead }}</p>

<form method="POST" action="{{ route($twoFactorRoutes['confirm']) }}">
    @csrf

    <div class="mb-3">
        <label for="backup_code" class="form-label">バックアップコード</label>
        <input id="backup_code" type="text" name="backup_code"
               class="form-control" placeholder="0000-0000">
        <div class="invalid-feedback" data-item="backup_code">{{ $errors->first('backup_code') }}</div>
    </div>

    <div class="mb-3 form-check">
        <input type="checkbox" name="remember_device" value="1" class="form-check-input" id="remember_device_backup">
        <label class="form-check-label" for="remember_device_backup">{{ $rememberLabel }}</label>
    </div>

    <button type="submit" class="btn btn-outline-secondary">バックアップコードでログインする</button>
</form>
