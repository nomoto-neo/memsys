<?php

namespace Neobit\Core\Http;

use Illuminate\Http\Response;

/**
 * 公開ディレクトリの古い.phpを動かす、という目印の応答。中身は持たない。
 * Laravelの全体のミドルウェアを通った後、入口のindex.phpがNeobit\Core\Http\PublicFiles::handle()で受け取り、
 * ヘッダーを送ってから、このファイルをグローバルの場所で動かす。
 */
class PublicScriptResponse extends Response
{
    /** 動かす.phpの絶対パス */
    public function __construct(public readonly string $script)
    {
        parent::__construct();
    }
}
