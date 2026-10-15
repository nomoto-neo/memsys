{{--
    IPブロック管理（Neobit\Core\Http\Controllers\IpBlockController）。止めているIPアドレスの一覧と、
    手動の登録・期限の変更・解除。ルートの名前（$routes）は、アプリが付けた名前をコントローラーが渡す。
    止まる範囲は、一覧はコントローラーが計算した$boundsで、登録の欄はip_block.jsが入力に合わせて出す。
--}}
@extends(config('neobit.layouts.admin'))

@push('head-extra')
@vite(['vendor/neobit/core/resources/js/ip_block.js'])
@endpush

@section('content')
<h1 class="h4 mb-3">IPブロック管理</h1>

<div class="card mb-4">
    <div class="card-body small">
        <p class="mb-2">ここに登録したIPアドレスからのアクセスには、どのページも「見つかりません（404）」を返します。</p>
        @if ($auto)
            <p class="mb-2">
                存在しないページへのアクセスが、{{ $watchMinutes }}分に{{ $watchCount }}回を超えたIPアドレスは、
                自動で{{ $hours }}時間ブロックします。
            </p>
        @else
            <p class="mb-2">自動でブロックする設定は、止めてあります。</p>
        @endif
        <p class="mb-0">あなたのIPアドレス：<strong>{{ $currentIp }}</strong>（自分のIPアドレスと、アクセス制限で通しているIPアドレスは登録できません）</p>
    </div>
</div>

{{-- 手動の登録 --}}
<form method="POST" action="{{ route($routes['store']) }}" class="card mb-4">
    @csrf
    <div class="card-body">
        <h2 class="h6 mb-3">登録する</h2>
        <div class="row g-3">
            <div class="col-md-5">
                <label for="ip-block-ip" class="form-label">IPアドレス</label>
                <input type="text" id="ip-block-ip" name="ip" value="{{ old('ip') }}" class="form-control"
                       placeholder="203.0.113.5 または 203.0.113.0/24" autocomplete="off">
                <div class="invalid-feedback" data-item="ip">{{ $errors->first('ip') }}</div>
                <div id="ip-block-range" class="form-text"></div>
            </div>
            <div class="col-md-4">
                <label for="ip-block-reason" class="form-label">理由</label>
                <input type="text" id="ip-block-reason" name="reason" value="{{ old('reason') }}" class="form-control">
                <div class="invalid-feedback" data-item="reason">{{ $errors->first('reason') }}</div>
            </div>
            <div class="col-md-2">
                <label for="ip-block-days" class="form-label">期間</label>
                <select id="ip-block-days" name="days" class="form-select">
                    @foreach ($daysOptions as $days => $label)
                        <option value="{{ $days }}" @selected((string) old('days', 1) === (string) $days)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="invalid-feedback" data-item="days">{{ $errors->first('days') }}</div>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">登録</button>
            </div>
        </div>
    </div>
</form>

{{-- 止めているIPアドレスの一覧 --}}
<h2 class="h6 mb-2">ブロック中のIPアドレス（{{ number_format($blocks->count()) }}件）</h2>
<div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead>
            <tr>
                <th>IPアドレス</th>
                <th>止まる範囲</th>
                <th>登録</th>
                <th>理由</th>
                <th>止めた日時</th>
                <th>期限</th>
                <th style="width: 16rem;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($blocks as $block)
                <tr>
                    <td class="text-break">{{ $block->ip }}</td>
                    <td class="small text-break">
                        @if ($bounds[$block->id][0] === $bounds[$block->id][1])
                            {{ $bounds[$block->id][0] }}
                        @else
                            {{ $bounds[$block->id][0] }} ～ {{ $bounds[$block->id][1] }}
                        @endif
                    </td>
                    <td class="small">
                        @if ($block->auto)
                            自動（{{ number_format($block->hits) }}回）
                        @else
                            {{ $block->registered_by ?? '手動' }}
                        @endif
                    </td>
                    <td class="small">{{ $block->reason }}</td>
                    <td class="small">{{ $block->created_at->format('Y-m-d H:i') }}</td>
                    <td class="small">{{ $block->blocked_until?->format('Y-m-d H:i') ?? '期限なし' }}</td>
                    <td>
                        <div class="d-flex gap-2">
                            {{-- 期限の変更。変えた日から数え直す --}}
                            <form method="POST" action="{{ route($routes['update'], $block) }}" class="d-flex gap-1">
                                @csrf
                                @method('PATCH')
                                <select name="days" class="form-select form-select-sm" aria-label="期間">
                                    @foreach ($daysOptions as $days => $label)
                                        <option value="{{ $days }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">変更</button>
                            </form>
                            <form method="POST" action="{{ route($routes['destroy'], $block) }}"
                                  data-confirm="「{{ $block->ip }}」のブロックを解除します。よろしいですか？">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">解除</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-muted">ブロック中のIPアドレスはありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
