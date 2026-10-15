<?php

namespace Neobit\Core\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Neobit\Core\Security\AccessRestriction;

/**
 * アクセス制限で締め出されたときの非常口のコマンド。php artisan app:access-denied-offで動く。
 *
 * サイトと管理画面の制限を、両方外す。IPアドレスの一覧は残す
 * （Neobit\Core\Security\AccessRestriction::turnOff()）。回線が変わってIPアドレスが変わったときなど、
 * 管理画面を開けなくなったときに、サーバーで実行する。
 */
#[Signature('app:access-denied-off')]
#[Description('アクセス制限を外す（サイトと管理画面の制限を、両方外す）')]
class AccessDeniedOff extends Command
{
    public function handle(): int
    {
        AccessRestriction::turnOff();

        Log::warning('AccessDeniedOff: コマンドで、アクセス制限を外しました。');

        $this->info('サイトと管理画面の制限を、両方外しました。IPアドレスの一覧は残してあります。');

        return self::SUCCESS;
    }
}
