<?php

namespace App\Support;

/**
 * 既存のシステムの、古い方式のパスワードの計算。1つの方式につき、1つのクラスが実装する。
 *
 * 入力されたパスワードを、既存のシステムと同じ方式でハッシュ値にして返す。照合と、今の方式への
 * 置き換えは、LegacyPasswordUserProviderが受け持つ。
 *
 * よくある方式（ソルトの無いMD5・SHA-1・SHA-256）は、App\Support\Legacyに用意してある。
 * ソルトを付ける、何度も繰り返す、といったそのサイトだけの方式は、サイトごとにクラスを書いて、
 * config/members.phpのlegacy_passwordsに足す。会員を受け取るので、会員ごとのソルトの列も読める。
 */
interface LegacyPassword
{
    /** 入力されたパスワードの、古い方式でのハッシュ値。既存のシステムのDBにあった値と同じ形で返す */
    public function hash(string $password, MemberAccount $member): string;
}
