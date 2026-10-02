{{--
    パスキーの一覧・登録・削除の画面の中身（会員：mypage/passkeys、管理：admin/staff/passkeys）。
    コントローラー側はApp\Support\PasskeyManagementトレイトで、変数はそのpasskeyIndex()が渡す。
    - $passkeys：このアカウントのパスキー（新しい順）
    - $passkeyRoute：一覧画面のルート名。登録・削除などのルート名は、この後ろに.confirmなどを付けたもの
    - $confirmBy：登録の前の本人確認の方法（'email'：メールの確認コード、'totp'：認証アプリ）
    - $identityConfirmed：本人確認が済んでいて、今パスキーを登録できるか
    - $codeSent：（email）確認コードを送った後か
    - $twoFactorMissing：（totp）2段階認証が未登録で、本人確認ができないか

    呼び出す側の画面で、<head>にCSRFトークンの<meta>とresources/js/passkeys.jsを
    読み込んでおく（@push('head-extra')）。登録と削除の確認はpasskeys.jsが行う。
--}}
<p>
    パスキーを登録すると、次回から、ログイン画面の「パスキーでログイン」を押し、
    この端末のロック解除（指紋・顔・PINなど）だけでログインできます。
    パスワードでのログインも、今までどおり使えます。
</p>

@if ($passkeys->isEmpty())
    <p class="text-muted">登録されているパスキーはありません。</p>
@else
    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>名前</th>
                    <th>登録日</th>
                    <th>最後に使った日時</th>
                    <th>最後に使った端末</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($passkeys as $passkey)
                    <tr>
                        <td>{{ $passkey->name }}</td>
                        <td>{{ $passkey->created_at->format('Y年n月j日') }}</td>
                        <td>{{ $passkey->last_used_at?->format('Y年n月j日 H:i') ?? '（未使用）' }}</td>
                        <td>{{ $passkey->last_used_device ?? '－' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route($passkeyRoute.'.destroy', $passkey) }}"
                                  data-confirm="「{{ $passkey->name }}」のパスキーを削除します。削除したパスキーではログインできなくなります。よろしいですか？">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">削除</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<h2 class="h6 mt-4">パスキーを追加する</h2>

{{-- このブラウザがパスキーに対応していないときは、passkeys.jsがこの案内を表示し、登録の操作を隠す --}}
<div class="alert alert-warning" data-passkey-unsupported hidden>
    このブラウザはパスキーに対応していません。
</div>

<div data-passkey-supported>
    @if ($identityConfirmed)
        <div data-passkey-register
             data-options-url="{{ route($passkeyRoute.'.options') }}"
             data-submit-url="{{ route($passkeyRoute.'.store') }}">
            <p>
                本人確認ができました。下のボタンを押し、画面の案内に従ってパスキーを作成してください
                （本人確認から10分以内）。
            </p>
            <button type="button" class="btn btn-primary">この端末でパスキーを作成する</button>
            <div class="text-danger small mt-2" data-passkey-message></div>
        </div>
    @elseif ($confirmBy === 'totp')
        @if ($twoFactorMissing)
            <p class="text-muted">
                パスキーを追加するには、2段階認証（認証アプリ）の登録が必要です。
                一度ログアウトし、ログインIDとパスワードでログインして登録してください。
            </p>
        @else
            <p>本人確認のため、認証アプリに表示されている6桁の数字を入力してください。</p>
            <form method="POST" action="{{ route($passkeyRoute.'.confirm') }}" class="row g-2 align-items-start">
                @csrf
                <div class="col-auto">
                    <input type="text" name="code" class="form-control" inputmode="numeric"
                           autocomplete="one-time-code" aria-label="確認コード">
                    <div class="invalid-feedback" data-item="code">{{ $errors->first('code') }}</div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">確認する</button>
                </div>
            </form>
        @endif
    @elseif ($codeSent)
        <p>本人確認のため、メールでお送りした確認コード（6桁）を入力してください。</p>
        <form method="POST" action="{{ route($passkeyRoute.'.confirm') }}" class="row g-2 align-items-start">
            @csrf
            <div class="col-auto">
                <input type="text" name="code" class="form-control" inputmode="numeric"
                       autocomplete="one-time-code" aria-label="確認コード">
                <div class="invalid-feedback" data-item="code">{{ $errors->first('code') }}</div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">確認する</button>
            </div>
        </form>
        <form method="POST" action="{{ route($passkeyRoute.'.code') }}" class="mt-2">
            @csrf
            <button type="submit" class="btn btn-link btn-sm p-0">確認コードを再送する</button>
        </form>
    @else
        {{-- 確認コードの有効期限が切れた後に入力した場合は、ここにエラーが出る --}}
        @error('code')
            <div class="text-danger small mb-2">{{ $message }}</div>
        @enderror
        <p>本人確認のため、登録されているメールアドレスに確認コードをお送りします。</p>
        <form method="POST" action="{{ route($passkeyRoute.'.code') }}">
            @csrf
            <button type="submit" class="btn btn-primary">確認コードを送信する</button>
        </form>
    @endif
</div>
