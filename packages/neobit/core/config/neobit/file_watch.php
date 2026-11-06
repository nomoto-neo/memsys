<?php

/**
 * 改ざん監視（neobit:file-watch。Neobit\Core\Security\FileWatcher）の既定の値。
 * アプリのconfig/neobit.phpのfile_watchの節に書いたキーが、これに優先する。
 * コードを更新する利用者（webapp）のcrontabから、5分ごとと1日1回の--fullで動かす。使い方は利用ガイドの vendor/neobit/core/docs/file-watch.md。
 */
return [

    // 鍵のファイル（プロジェクトの直下からのパス）。このファイルの更新の15分後より前に変わったファイルを、
    // 通常のサイトの更新とみなす。送るたびに中身（日時など）を変えて送る。空なら監視しない
    'release_file' => '.version',

    // 通知のメールの宛先（カンマ区切り）。nullなら、エラーの通知と同じ宛先（ERROR_NOTIFY_TO）
    'to' => null,

    // 公開のアップロードの置き場所（storage/app/public）に、運用で置いてよい拡張子。
    // アップロードの設定（neobit.upload.image_types・attach_types）のほかは、置かれていたら知らせる
    'public_upload_extra' => [],

];
