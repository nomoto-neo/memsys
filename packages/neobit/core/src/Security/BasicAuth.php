<?php

namespace Neobit\Core\Security;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Neobit\Core\Enums\OperationLogAction;
use Neobit\Core\Models\BasicAuthUser;
use Neobit\Core\Operations\OperationRecorder;

/**
 * ベーシック認証。訪問者画面と管理画面のそれぞれに、Apacheのベーシック認証を掛けるかと、そのユーザーを別々に持つ。
 * 管理画面の「ベーシック認証」の画面（Neobit\Core\Http\Controllers\BasicAuthController）が使う。
 *
 * ■ 掛け方
 * 認証するのはApacheで、Laravelを通らないpublicの下のHTMLや画像にも掛かる。サーバーのVirtualHostに、
 * 範囲ごとに、目印のファイル（storage/app/basic-auth/site.on・admin.on）があるときだけ、その範囲の
 * .htpasswd（site.htpasswd・admin.htpasswd）で認証する決まりを書いておく。ここは目印のファイルを作る・消すことと、
 * ユーザーから.htpasswdを書き直すことだけをする。public/.htaccessは変えない。決まりの書き方は利用ガイドにある。
 *
 * ■ 締め出さない
 * 範囲ごとに、ユーザーが0人のときは掛けられない。掛けている間は、最後のユーザーを削除できない。
 * パスワードを忘れて入れなくなったときは、サーバーで目印のファイルを消す（rm storage/app/basic-auth/admin.on）。
 */
final class BasicAuth
{
    // ---- ファイルの置き場所 ----

    /** 目印のファイルと.htpasswdを置くディレクトリ（storageの下） */
    private const DIRECTORY = 'app/basic-auth';

    // ---- 掛ける範囲 ----

    /** 掛ける範囲 => 名前。目印のファイルは「範囲.on」、ユーザーのファイルは「範囲.htpasswd」 */
    public const AREAS = [
        'site' => '訪問者画面',
        'admin' => '管理画面',
    ];

    // ---- 入力の決まり ----

    /** ユーザー名の形。:はユーザー名とパスワードの区切りなので使えない。末尾は\zにして、改行を通さない */
    public const USERNAME_PATTERN = '/^[A-Za-z0-9._-]{1,50}\z/';

    // ---- 今の状態 ----

    /** 範囲（site・admin）に掛けているか。目印のファイルがあるか */
    public static function isOn(string $area): bool
    {
        return is_file(self::path($area.'.on'));
    }

    // ---- 切り替え ----

    /** 範囲に掛けるかを保存する。保存できないときは、その理由を返す。保存したらnull。変わったら操作ログに残す */
    public static function save(string $area, bool $on): ?string
    {
        // ユーザーがいなければ掛けられない
        if ($on && ! BasicAuthUser::query()->where('area', $area)->exists()) {
            return 'ユーザーがいないので、ベーシック認証を掛けられません。先にユーザーを追加してください。';
        }

        // 掛ける前に、今のユーザーで.htpasswdを書き直しておく
        self::writeHtpasswd($area);

        $before = self::isOn($area);
        $flag = self::path($area.'.on');
        if ($on) {
            touch($flag);
        } elseif (is_file($flag)) {
            unlink($flag);
        }

        if ($before !== $on) {
            OperationRecorder::record(OperationLogAction::BasicAuthChange, detail: [
                'area' => self::AREAS[$area].'：'.($on ? '掛ける' : '掛けない'),
            ]);
        }

        return null;
    }

    // ---- ユーザー ----

    /**
     * 範囲にユーザーを追加して、その範囲の.htpasswdを書き直す。パスワードはbcrypt（Apacheが読める$2y$）にする。
     * 画面の外から検証を通さずに呼ばれても.htpasswdを崩さないよう、ここでもユーザー名の形を確かめる
     */
    public static function addUser(string $area, string $username, string $password): BasicAuthUser
    {
        // 改行や:が入ると、.htpasswdに別の行を書き足せてしまうので断る
        $username = trim($username);
        if (preg_match(self::USERNAME_PATTERN, $username) !== 1) {
            throw new InvalidArgumentException('ベーシック認証のユーザー名の形が正しくありません：'.json_encode($username));
        }

        $user = DB::transaction(function () use ($area, $username, $password) {
            $user = BasicAuthUser::create([
                'area' => $area,
                'username' => $username,
                'password' => password_hash($password, PASSWORD_BCRYPT),
            ]);
            self::writeHtpasswd($area);

            return $user;
        });

        OperationRecorder::record(OperationLogAction::BasicAuthUserAdd, detail: ['user' => self::AREAS[$area].'：'.$username]);

        return $user;
    }

    /** ユーザーを削除して、その範囲の.htpasswdを書き直す。掛けている範囲の最後のユーザーを消すときは、消さずに理由を返す */
    public static function deleteUser(BasicAuthUser $user): ?string
    {
        if (self::isOn($user->area) && BasicAuthUser::query()->where('area', $user->area)->count() <= 1) {
            return 'ベーシック認証を掛けている間は、最後のユーザーを削除できません。';
        }

        DB::transaction(function () use ($user) {
            $user->delete();
            self::writeHtpasswd($user->area);
        });

        OperationRecorder::record(OperationLogAction::BasicAuthUserDelete, detail: ['user' => self::AREAS[$user->area].'：'.$user->username]);

        return null;
    }

    // ---- ファイル ----

    /**
     * 範囲の今のユーザーで、その範囲の.htpasswdを書き直す。「ユーザー名:ハッシュ」を1行に1人。
     * 読んでいる途中のApacheが半端なファイルを読まないよう、隣に書いてから置き換える
     */
    private static function writeHtpasswd(string $area): void
    {
        $lines = BasicAuthUser::query()->where('area', $area)->orderBy('username')->get()
            ->map(fn (BasicAuthUser $user) => $user->username.':'.$user->password."\n")
            ->implode('');

        $path = self::path($area.'.htpasswd');
        file_put_contents($path.'.tmp', $lines, LOCK_EX);
        rename($path.'.tmp', $path);
    }

    /** ディレクトリの中のファイルのパス。ディレクトリが無ければ作る */
    private static function path(string $file): string
    {
        $directory = storage_path(self::DIRECTORY);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.DIRECTORY_SEPARATOR.$file;
    }
}
