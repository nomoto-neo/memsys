{{--
    管理画面のスタッフによる、2段階認証（認証アプリ）の登録の解除。スマートフォンの紛失などで
    本人がログインできなくなったときに、代わりに行う。会員と、企業の担当者の詳細から@includeする。

    その人の方式が認証アプリで、登録が済んでいるときだけ出す。ほかのときは何も出さない。

    渡す変数：
    - $twoFactorAccount   対象の人
    - $twoFactorResetUrl  解除のURL（DELETE）
--}}
@if ($twoFactorAccount::usesTotp() && $twoFactorAccount->hasTwoFactorConfirmed())
    <div class="mt-3 d-flex gap-2">
        <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#resetTwoFactorModal">
            2段階認証の登録を解除する
        </button>
    </div>

    <div class="modal fade" id="resetTwoFactorModal" tabindex="-1" aria-labelledby="resetTwoFactorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetTwoFactorModalLabel">2段階認証の登録解除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    「{{ $twoFactorAccount->displayName() }}」さんの2段階認証の登録を解除します。バックアップコード・パスキーも全て無効になり、
                    次回ログイン時にQRコードから登録し直すことになります。よろしいですか？
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <form method="POST" action="{{ $twoFactorResetUrl }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-warning">解除する</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
