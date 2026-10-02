@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">
        @if ($actor->id === $staff->id)
            自分の情報
        @else
            スタッフ詳細
        @endif
        @if ($staff->trashed())
            <span class="badge text-bg-danger align-middle">削除済み</span>
        @endif
    </h1>
    <div class="d-flex gap-2">
        @if ($actor->isManager())
            <a href="{{ route('admin.staff.index', ['back']) }}" class="btn btn-sm btn-outline-secondary">一覧へ戻る</a>
        @else
            <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">管理画面TOPへ</a>
        @endif
        {{-- 削除済みのスタッフは編集できない（StaffPolicy::update()）ので、代わりに削除を取り消すボタンが出る --}}
        @can('update', $staff)
            <a href="{{ route('admin.staff.edit', $staff) }}" class="btn btn-sm btn-primary">編集する</a>
        @endcan
        @can('restore', $staff)
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#restoreStaffModal">
                削除を取り消す
            </button>
        @endcan
    </div>
</div>

<div class="card">
    <div class="card-body">
        {{--
            create/edit/confirmと同じ_fields.blade.phpを、readonly/disabled
            付きで呼び出しているだけ。4画面が同じ1つのパーツを見ているので、
            項目を1つ増やすときも_fields.blade.phpだけを直せばよい。

            表示する値（$input）はStaffController::show()が組み立てて渡す。
        --}}
        @include('admin.staff._fields', [
            'isCreate' => false,
            'input' => $input,
            'readonly' => ' readonly',
            'disabled' => ' disabled',
            'required' => [],
            'showAcl' => true,
            'showPassword' => false,
        ])

        <div class="row">
            <div class="col-sm-3 text-muted">登録日時</div>
            <div class="col-sm-9">{{ $staff->created_at->format('Y年n月j日 H:i') }}</div>
        </div>

        @if ($staff->trashed())
            <div class="row mt-2">
                <div class="col-sm-3 text-muted">削除日時</div>
                <div class="col-sm-9 text-danger">{{ $staff->deleted_at->format('Y年n月j日 H:i') }}</div>
            </div>
        @endif

        {{--
            2段階認証（TOTP）の登録状況。本人だけでなく管理者が代理で
            見られるようにするため、詳細画面（本人・管理者どちらも通る画面）に
            出している。バックアップコードの残数は、無くなりかけていることに
            気づく手がかりとして表示するだけで、ここから直接は再発行できない
            （本人が自分の画面から再発行する入口は、この下の
            「バックアップコードを再発行する」ボタン）。
        --}}
        <div class="row mt-2">
            <div class="col-sm-3 text-muted">2段階認証</div>
            <div class="col-sm-9">
                @if ($staff->hasTwoFactorConfirmed())
                    登録済み（{{ $staff->totp_confirmed_at->format('Y年n月j日 H:i') }}確認）
                    <span class="text-muted">／バックアップコード残り{{ $staff->unusedBackupCodesCount() }}件</span>
                @else
                    未登録
                @endif
            </div>
        </div>

        {{-- パスキーの登録件数。routes/web.phpにadmin.passkeysのルートがあるときだけ出す。
             登録・削除は本人だけができる（本人の画面にだけ「管理する」の入口を出す）。 --}}
        @if (Route::has('admin.passkeys'))
            <div class="row mt-2">
                <div class="col-sm-3 text-muted">パスキー</div>
                <div class="col-sm-9">
                    {{ $staff->passkeys()->count() }}件登録
                    @if ($actor->id === $staff->id && ! $staff->trashed())
                        <a href="{{ route('admin.passkeys') }}" class="btn btn-sm btn-outline-primary ms-2">管理する</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

{{--
    本人による、バックアップコードの再発行・2段階認証の登録解除。
    どちらも「今の認証アプリでもう一度コードを入力できる」ことが前提の
    自己サービス機能なので、本人が自分自身の画面を見ていて、かつ
    2段階認証が登録済みのときだけ出す。管理者による代理リセット
    （このすぐ下のブロック）とは別の入口で、対象読者が重ならない
    （自分の画面には管理者用ブロックが出ず、他人の画面にはこのブロックが
    出ない）ので、両方のボタンが同時に並ぶことは無い。
--}}
@if ($actor->id === $staff->id && $staff->hasTwoFactorConfirmed())
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
                <form method="POST" action="{{ route('admin.twoFactor.regenerateBackupCodes') }}">
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
                <form method="POST" action="{{ route('admin.twoFactor.selfReset') }}">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h5 class="modal-title" id="selfResetTwoFactorModalLabel">2段階認証の登録解除</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <div class="modal-body">
                        <p>
                            2段階認証の登録を解除します。バックアップコード{{ Route::has('admin.passkeys') ? '・パスキー' : '' }}も全て無効になり、
                            次回ログイン時にQRコードから登録し直すことになります
                            （スマートフォンの機種変更前に、古い端末側の紐づけを外しておきたい場合などにお使いください）。
                            よろしければ、認証アプリに表示されている6桁の数字を入力してください。
                        </p>
                        <div class="mb-3">
                            <label for="self_reset_code" class="form-label">確認コード</label>
                            <input id="self_reset_code" type="text" name="code"
                                   class="form-control" inputmode="numeric" autocomplete="one-time-code">
                            <div class="invalid-feedback" data-item="code">{{ $errors->selfResetTwoFactor->first('code') }}</div>
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
@endif

{{-- 削除・2段階認証の代理解除は、管理者だけ、かつ自分自身は対象外。
     判断はApp\Policies\StaffPolicyのdelete()・resetTwoFactor()で、routes/web.phpの
     canミドルウェアと同じものを使って、使えないボタンは最初から出さない。
     確認は専用の確認画面ではなく、その場で完結するBootstrapのモーダルに
     している（どちらも取り消せる項目が無い単純な1操作なので、確認画面を
     わざわざ1枚作るほどの複雑さが無いと判断）。 --}}
@canany(['delete', 'resetTwoFactor'], $staff)
    <div class="mt-3 d-flex gap-2">
        @can('delete', $staff)
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteStaffModal">
                このスタッフを削除する
            </button>
        @endcan
        @can('resetTwoFactor', $staff)
            @if ($staff->hasTwoFactorConfirmed())
                <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#resetTwoFactorModal">
                    2段階認証の登録を解除する
                </button>
            @endif
        @endcan
    </div>
@endcanany

@can('delete', $staff)
    <div class="modal fade" id="deleteStaffModal" tabindex="-1" aria-labelledby="deleteStaffModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteStaffModalLabel">スタッフの削除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    「{{ $staff->name }}」さんを削除します。削除したスタッフはログインできなくなります。
                    （一覧で「削除済みも含める」にチェックを入れて検索すると表示でき、この画面から削除を取り消せます。）
                    よろしいですか？
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <form method="POST" action="{{ route('admin.staff.destroy', $staff) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">削除する</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endcan

@can('restore', $staff)
    <div class="modal fade" id="restoreStaffModal" tabindex="-1" aria-labelledby="restoreStaffModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="restoreStaffModalLabel">スタッフの削除の取り消し</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    「{{ $staff->name }}」さんの削除を取り消し、ログインできる状態に戻します。
                    ログインID・パスワード・権限・2段階認証の設定は、削除する前のままです。よろしいですか？
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <form method="POST" action="{{ route('admin.staff.restore', $staff) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-primary">削除を取り消す</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endcan

@can('resetTwoFactor', $staff)
    @if ($staff->hasTwoFactorConfirmed())
        <div class="modal fade" id="resetTwoFactorModal" tabindex="-1" aria-labelledby="resetTwoFactorModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="resetTwoFactorModalLabel">2段階認証の登録解除</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <div class="modal-body">
                        「{{ $staff->name }}」さんの2段階認証の登録を解除します。バックアップコード{{ Route::has('admin.passkeys') ? '・パスキー' : '' }}も全て無効になり、
                        次回ログイン時にQRコードから登録し直すことになります。よろしいですか？
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <form method="POST" action="{{ route('admin.staff.twoFactor.reset', $staff) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-warning">解除する</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endcan
@endsection
