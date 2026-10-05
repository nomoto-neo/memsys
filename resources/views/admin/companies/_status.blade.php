{{--
    企業の状態のバッジ。一覧と詳細の両方から呼ぶ。申請中は目立つ色、停止は赤にしている。
    呼び出し側が用意する変数：
    - $company  対象の企業
--}}
@php
    $statusColors = [
        'pending' => 'text-bg-warning',
        'approved' => 'text-bg-success',
        'suspended' => 'text-bg-danger',
    ];
@endphp
<span class="badge {{ $statusColors[$company->status->value] }}">{{ code_label('company_status', $company->status) }}</span>
