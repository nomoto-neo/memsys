@extends('layouts.app')

{{-- 画面の名前。メールの方式では、できることがバックアップコードの発行だけなので、それに合わせる --}}
@section('title', $twoFactorAccount::usesTotp() ? '2段階認証' : 'バックアップコード')

{{--
    発行と解除の確認の画面（モーダル）に、BootstrapのJavaScriptが要る。訪問者向けのレイアウトは
    読み込んでいないので、この画面でだけ<head>に足す。
--}}
@push('head-extra')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
@endpush

@section('content')
{{--
    本人による、2段階認証の管理。バックアップコードの発行と、認証アプリの登録の解除。
    入口はNeobit\Core\Auth\TwoFactorManagementトレイト。
--}}
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                {{ $twoFactorAccount::usesTotp() ? '2段階認証' : 'バックアップコード' }}
                <a href="{{ route('mypage') }}" class="btn btn-sm btn-outline-secondary">マイページへ戻る</a>
            </div>
            <div class="card-body">
                {{-- 認証アプリの登録の状況。認証アプリの方式のときだけ --}}
                @if ($twoFactorAccount::usesTotp())
                    @if ($twoFactorAccount->hasTwoFactorConfirmed())
                        <p>認証アプリを登録済みです。</p>
                    @else
                        <p>認証アプリは、まだ登録されていません。次回のログインのときに、QRコードから登録します。</p>
                    @endif
                @endif

                {{-- バックアップコードの残り。バックアップコードを使うモデルのときだけ --}}
                @if ($twoFactorAccount::usesBackupCodes())
                    <p>
                        バックアップコードは、2段階認証を通れないときに、1回ずつ使える緊急用のコードです。
                        使っていないコードは、残り {{ $twoFactorAccount->unusedBackupCodesCount() }} 個です。
                    </p>
                @endif

                @include('_two_factor_manage', [
                    'twoFactorRoutes' => [
                        'regenerate' => 'twoFactor.regenerateBackupCodes',
                        'code' => 'twoFactor.backupCodes.code',
                        'reset' => 'twoFactor.selfReset',
                        'passkeys' => 'mypage.passkeys',
                    ],
                ])
            </div>
        </div>
    </div>
</div>
@endsection
