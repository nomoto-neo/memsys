{{--
    企業の状態のバッジ。一覧と詳細の両方から呼ぶ。色は状態ごとに列挙型（App\Enums\CompanyStatus）が決める。
    呼び出し側が用意する変数：
    - $company  対象の企業
--}}
<span class="badge {{ $company->status->badgeClass() }}">{{ code_label('company_status', $company->status) }}</span>
