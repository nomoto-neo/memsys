{{--
    メンテナンス中の画面。サイト全体のIPアドレスの制限（.envのSITE_ALLOWED_IPS）で、
    入ってよいIPアドレスのほかから開かれたときに出す（App\Http\Middleware\RestrictSiteAccess）。
    公開前のデモの運用のときも、この画面が出る。

    DBを止めている間も出せるよう、レイアウトを継承しない1枚のHTMLにしている。
    DB・セッション・ログイン中の人の情報は使わない。文言は、このファイルを直接書き換える。
--}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>メンテナンス中 - memsys</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card mx-auto" style="max-width: 36rem;">
        <div class="card-body text-center py-5">
            <h1 class="h4 mb-4">ただいまメンテナンス中です</h1>
            <p class="mb-0">ご不便をおかけしますが、しばらくお待ちください。</p>
        </div>
    </div>
</div>
</body>
</html>
