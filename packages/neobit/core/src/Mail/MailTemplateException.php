<?php

namespace Neobit\Core\Mail;

use RuntimeException;

/**
 * メールのテンプレートの読み込みに失敗したときの例外。テンプレートが無いときと、
 * 見出しの行の書き方が誤っているときに投げる。
 * 原因がテンプレートだとひと目で分かるよう、専用の例外にしている。
 *
 * メールを送る所は、送信の失敗を受け止めてログに残し、処理を続ける。この例外だけは、
 * 受け止めずにそのまま投げ直す。開発中の誤りで、待っても直らないので、ログに紛れさせずに
 * その場で止めて気付けるようにするため。
 * エラーの通知（Neobit\Core\Operations\ErrorNotifyHandler）だけは、投げ直さない。ログを書く
 * 仕組みの中で動くので、例外を外へ出すと、元のエラーのログが書けなくなる。
 */
class MailTemplateException extends RuntimeException
{
}
