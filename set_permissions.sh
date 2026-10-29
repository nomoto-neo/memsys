#!/bin/bash
# サーバーのファイルの持ち主・グループ・権限・setgid・ACL を、決まった形にそろえる。
# サーバーへ最初に置いたときと、権限が崩れたときに、Laravel プロジェクトのルート（artisan のあるディレクトリ）で root で実行する。
#   sudo bash set_permissions.sh            … 実行するコマンドと、今の確かめの結果を並べるだけ
#   sudo bash set_permissions.sh --apply    … 実際に変える
#   --owner 持ち主（既定 devapp）  --group 作業のグループ（既定 dev）  --web-user Web の利用者（既定 apache）
#
# そろえる形。何度流しても同じ状態になる。
# - 全体：持ち主:作業のグループ。作業のグループは読み書き、Web の利用者は ACL で読むだけ、その他の人はなし
# - bootstrap/cache：全体と同じ。Web の利用者が書けないので、設定のキャッシュと改ざん監視の記録を Web の側から書き換えられない
# - storage の下：持ち主は Web の利用者。Web の利用者も ACL で読み書きできる
# - .env と .env. で始まる控え（.env.example・.env.unused を除く）：Web の利用者は読めない
# - ディレクトリは全部 setgid と既定の ACL を付け、中に新しく作ったファイルも同じ形になるようにする

owner=devapp
group=dev
web=apache
apply=0

# 引数
while [ $# -gt 0 ]; do
    case "$1" in
        --apply) apply=1 ;;
        --owner) owner="$2"; shift ;;
        --group) group="$2"; shift ;;
        --web-user) web="$2"; shift ;;
        *) echo "知らない引数です：$1"; exit 1 ;;
    esac
    shift
done

dir=$(pwd -P)

stop() {  # stop 理由 … 何も変えずに止める
    echo "NG  $1"
    echo '何も変えずに止めました。'
    exit 1
}

# ---- 動かせるかの確かめ。どれかが欠けたら止める ----

[ "$(id -u)" -eq 0 ] || stop 'root で実行してください（sudo bash set_permissions.sh）。'
[ -f "$dir/artisan" ] || stop "artisan がありません。Laravel プロジェクトのルートで実行してください（今の場所：$dir）。"
id "$owner" > /dev/null 2>&1 || stop "利用者 $owner がいません。"
id "$web" > /dev/null 2>&1 || stop "利用者 $web がいません。"
getent group "$group" > /dev/null || stop "グループ $group がありません。"
command -v setfacl > /dev/null || stop 'setfacl がありません。acl のパッケージを入れてください。'

# アプリケーション名は .env の APP_NAME。書いていなければディレクトリの名前
app_name=$(grep -m1 '^APP_NAME=' "$dir/.env" 2> /dev/null | cut -d= -f2- | tr -d '"'"'")
[ -n "$app_name" ] || app_name=$(basename "$dir")

echo "${app_name}（.env の APP_NAME）の権限を確かめます：$dir"
echo "持ち主：$owner　作業のグループ：$group　Web の利用者：$web"
echo
echo '---- 確かめ ----'

# SELinux は最初に Disabled にしている。違えば設定の漏れなので止める
if command -v getenforce > /dev/null; then
    selinux=$(getenforce)
    if [ "$selinux" != 'Disabled' ]; then
        stop "SELinux が $selinux です。このサーバーでは最初に Disabled にしています。設定の漏れなので、/etc/selinux/config を確かめてください。"
    fi
    echo 'OK  SELinux は Disabled です'
else
    echo 'OK  SELinux は入っていません'
fi

# Web の利用者が作業のグループに入っていると、グループで書けるコードを Web の側から書き換えられる。知らせるだけ
if id -nG "$web" | tr ' ' '\n' | grep -qx "$group"; then
    echo "警告  $web が $group グループに入っています。コードを Web の側から書き換えられるので、外してください（gpasswd -d $web $group）。"
else
    echo "OK  $web は $group グループに入っていません"
fi

# PHP-FPM のプールが Web の利用者で動いていないと、storage の下のファイルの持ち主がずれる。知らせるだけ
# pm = ondemand では暇な間ワーカーが止まっているので、プロセスではなくプールの設定の user を読む
site=$(basename "$dir")
pool_dir=/etc/php-fpm.d
# 設定のファイルの名前・プールの名前・user を、タブ区切りで1プール1行に並べる
pools=$(awk -v OFS='\t' '
    FNR == 1 { pool = "" }
    /^[[:space:]]*\[[^]]+\][[:space:]]*$/ { pool = $0; gsub(/[][[:space:]]/, "", pool); next }
    /^[[:space:]]*user[[:space:]]*=/ {
        user = $0; sub(/^[^=]*=[[:space:]]*/, "", user); sub(/[[:space:];].*$/, "", user)
        file = FILENAME; sub(/.*\//, "", file)
        print file, pool, user
    }
' "$pool_dir"/*.conf 2> /dev/null)
# このサイトのプールは、プールの名前か設定のファイルの名前が、ディレクトリの名前と同じもの
site_pool=$(echo "$pools" | awk -F'\t' -v site="$site" '$1 == site ".conf" || $2 == site' | head -n1)

if [ -n "$site_pool" ]; then
    # このサイトのプールが見つかったとき：その user だけで判断する
    IFS=$'\t' read -r pool_file pool_name pool_user <<< "$site_pool"
    if [ "$pool_user" = "$web" ]; then
        echo "OK  PHP-FPM のプール $pool_name は $web で動きます（$pool_dir/$pool_file）"
    else
        echo "警告  PHP-FPM のプール $pool_name が $web 以外（$pool_user）で動きます。$pool_dir/$pool_file の user・group を確かめてください。"
    fi
elif [ -n "$pools" ]; then
    # 同じ名前のプールが無いとき：全部のプールを並べて、どれがこのサイトのものかを確かめてもらう
    pool_list=$(echo "$pools" | awk -F'\t' '{ printf "%s%s：%s", sep, $1, $3; sep = "、" }')
    echo "警告  ディレクトリの名前 $site と同じ名前の PHP-FPM のプールがありません（$pool_list）。このサイトのプールの user を確かめてください。"
else
    # プールの設定が読めないとき：動いているワーカーの利用者で判断する
    pool_users=$(ps -eo user:32,cmd | grep '[p]hp-fpm: pool' | awk '{print $1}' | sort -u)
    if [ -z "$pool_users" ]; then
        echo "警告  PHP-FPM のプールの設定（$pool_dir/*.conf）も、動いているワーカーも見つかりません。PHP-FPM の設定の場所と、動いているかを確かめてください。"
    elif [ "$pool_users" != "$web" ]; then
        echo "警告  PHP-FPM のプールが $web 以外（$(echo "$pool_users" | tr '\n' ' ')）で動いています。プールの user・group を確かめてください。"
    else
        echo "OK  PHP-FPM のプールは $web で動いています"
    fi
fi

echo
if [ $apply -eq 1 ]; then
    echo '---- 実行するコマンド ----'
else
    echo '---- 実行するコマンド（--apply を付けると実行します） ----'
fi

run() {  # run コマンド … 並べる。--apply のときは実行し、失敗したら止める
    echo "$*"
    if [ $apply -eq 1 ] && ! "$@"; then
        echo 'NG  上のコマンドが失敗しました。途中で止めました。'
        exit 1
    fi
}

# 持ち主。シンボリックリンク（public/storage）は辿らず、リンクそのものを変える
run chown -hR "$owner:$group" "$dir"
run chown -hR "$web:$group" "$dir/storage"

# 権限。X はディレクトリと、もともと実行できるファイルにだけ付く。vendor/bin などの実行の権限を消さないため
run chmod -R u+rwX,g+rwX,o-rwx "$dir"
run find "$dir" -type d -exec chmod g+s {} +

# Web の利用者は読むだけ。既定の ACL はディレクトリにしか付けられないので、ディレクトリだけに付ける
run setfacl -R -m "u:$web:rX" "$dir"
run find "$dir" -type d -exec setfacl -m "d:u::rwx,d:u:$web:rx,d:g::rwx,d:o::---" {} +

# storage の下は、Web の利用者も読み書き
run setfacl -R -m "u:$web:rwX" "$dir/storage"
run find "$dir/storage" -type d -exec setfacl -m "d:u:$web:rwx" {} +

# .env と、秘密が入り得る控えは、Web の利用者から読めなくする。設定は config:cache で読ませる
for env_file in "$dir"/.env "$dir"/.env.*; do
    case "$(basename "$env_file")" in
        .env.example | .env.unused) continue ;;
    esac
    [ -f "$env_file" ] || continue
    run setfacl -x "u:$web" "$env_file"
done

echo
echo "---- $web で試した結果 ----"
ng=0

can() {  # can 期待（yes・no） 説明 test の引数 … Web の利用者の権限で試し、期待どおりかを並べる
    local expect=$1 label=$2
    shift 2
    if sudo -u "$web" test "$@"; then actual=yes; else actual=no; fi
    if [ "$actual" = "$expect" ]; then
        echo "OK  $label"
    else
        echo "NG  $label"; ng=1
    fi
}

can no  '.env を読めない'              -r "$dir/.env"
can no  'bootstrap/cache に書けない'   -w "$dir/bootstrap/cache"
can yes 'storage/logs に書ける'        -w "$dir/storage/logs"
can yes 'public/index.php を読める'    -r "$dir/public/index.php"
can no  'app/ に書けない'              -w "$dir/app"

echo
if [ $ng -eq 0 ]; then
    echo 'すべて決まった形です。'
elif [ $apply -eq 1 ]; then
    echo 'NG が残りました。上のコマンドの結果と、ディレクトリの上の階層の権限を確かめてください。'
    exit 1
else
    echo 'NG があります。--apply を付けて実行すると、決まった形にそろえます。'
fi
