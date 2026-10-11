@extends('layouts.app')

@section('title', '2段階認証')

{{--
    再発行と解除の確認の画面（モーダル）に、BootstrapのJavaScriptが要る。訪問者向けのレイアウトは
    読み込んでいないので、この画面でだけ<head>に足す。
--}}
@push('head-extra')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
@endpush

@section('content')
{{--
    本人による、2段階認証（認証アプリ）の管理。入口はApp\Support\TwoFactorManagementトレイト。
    ログインの2段階目の方式が認証アプリのサイトでだけ使う。
--}}
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                2段階認証
                <a href="{{ route('mypage') }}" class="btn btn-sm btn-outline-secondary">マイページへ戻る</a>
            </div>
            <div class="card-body">
                @if ($twoFactorAccount->hasTwoFactorConfirmed())
                    <p>
                        認証アプリを登録済みです。使っていないバックアップコードは、
                        残り {{ $twoFactorAccount->unusedBackupCodesCount() }} 個です。
                    </p>

                    @include('_two_factor_manage', [
                        'twoFactorRoutes' => [
                            'regenerate' => 'twoFactor.regenerateBackupCodes',
                            'reset' => 'twoFactor.selfReset',
                            'passkeys' => 'mypage.passkeys',
                        ],
                    ])
                @else
                    <p class="mb-0">認証アプリは、まだ登録されていません。次回のログインのときに、QRコードから登録します。</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
