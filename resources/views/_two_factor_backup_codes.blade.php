{{--
    発行した直後のバックアップコードを、1回だけ見せる画面の中身。認証アプリの登録が済んだ直後と、
    本人が作り直した直後に出す。入口はApp\Support\TwoFactorManagementトレイト。

    渡される変数：
    - $codes             バックアップコードの一覧
    - $twoFactorNextUrl  見た後の移動先

    @includeする側が渡す変数：
    - $nextLabel         移動先へのボタンの文言
--}}
<div class="card">
    <div class="card-header">バックアップコード</div>
    <div class="card-body">
        <p>
            スマートフォンを紛失した場合などに、1回だけ使える緊急用のコードです。
            <strong>この画面はこの1回しか表示されません。</strong>
            印刷するかパスワード管理アプリなどに保存し、他の人に見られない場所に保管してください。
        </p>

        <div class="row row-cols-2 g-2 my-3">
            @foreach ($codes as $code)
                <div class="col"><code class="fs-5">{{ $code }}</code></div>
            @endforeach
        </div>

        <a href="{{ $twoFactorNextUrl }}" class="btn btn-primary">{{ $nextLabel }}</a>
    </div>
</div>
