{{--
    会員の経歴（学歴・職歴）の組の1行（neobit::_repeatable_groupから使う）。年・月・内容を横に並べる。
    管理画面の会員の入力欄（admin/members/_fields）と、マイページの編集（mypage/edit）の両方で使う。
    $readonlyが空でなければ（確認・詳細の画面）、「2020年4月　〇〇大学 入学」の1行の文字だけを出す。

    受け取る変数:
    - $input     画面の入力値。careers_year・careers_month・careers_contentの、列ごとの配列が入っている
    - $idx       何行目か
    - $readonly  ' readonly'または''
--}}
@php
    $year = $input['careers_year'][$idx] ?? '';
    $month = $input['careers_month'][$idx] ?? '';
    $content = $input['careers_content'][$idx] ?? '';
@endphp
@if ($readonly)
    <div>{{ $year }}年{{ $month }}月　{{ $content }}</div>
@else
    <div class="d-flex gap-2 align-items-start pe-4">
        <div style="width: 6rem;">
            <input type="number" name="careers_year[]" class="form-control form-control-sm" min="{{ \App\Models\MemberCareer::YEAR_MIN }}" max="{{ now()->year }}"
                   placeholder="年" aria-label="年" value="{{ $year }}">
            <div class="invalid-feedback" data-item="careers_year.{{ $idx }}">{{ $errors->first("careers_year.{$idx}") }}</div>
        </div>
        <div style="width: 5rem;">
            <select name="careers_month[]" class="form-select form-select-sm" aria-label="月">
                <option value="">月</option>
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected((string) $month === (string) $m)>{{ $m }}月</option>
                @endfor
            </select>
            <div class="invalid-feedback" data-item="careers_month.{{ $idx }}">{{ $errors->first("careers_month.{$idx}") }}</div>
        </div>
        <div class="flex-grow-1">
            <input type="text" name="careers_content[]" class="form-control form-control-sm" maxlength="{{ \App\Models\MemberCareer::CONTENT_MAX_LENGTH }}"
                   placeholder="内容（〇〇大学 経済学部 入学など）" aria-label="内容" value="{{ $content }}">
            <div class="invalid-feedback" data-item="careers_content.{{ $idx }}">{{ $errors->first("careers_content.{$idx}") }}</div>
        </div>
    </div>
@endif
