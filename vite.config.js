import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath } from 'node:url';

// フレームワークのスクリプトとCSSの場所。Composerがvendor/の下に置く
const NEOBIT = 'vendor/neobit/core/resources';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // フレームワークのもの。使わないエディタの行は消してよい
                `${NEOBIT}/css/global.css`,
                `${NEOBIT}/js/global.js`,
                `${NEOBIT}/js/ajax_upload.js`,
                `${NEOBIT}/js/passkeys.js`,
                `${NEOBIT}/js/wysiwyg_suneditor.js`,
                `${NEOBIT}/css/wysiwyg_suneditor_content.css`,
                `${NEOBIT}/js/wysiwyg_summernote.js`,
                // このサイトのもの
                'resources/js/sortable.js',
                'resources/js/contact_form.js',
                'resources/js/code_editor.js',
            ],
            refresh: true,
        }),
    ],
    resolve: {
        // vendor/neobit/coreがリンクのとき（memsysの手元とサーバー）も、マニフェストにvendor/の
        // パスのまま載せる。リンクをたどると実体のパスで載り、画面の@vite()が見つけられない
        preserveSymlinks: true,
        alias: {
            // フレームワークのスクリプトの場所。エディタのスクリプトをアプリへコピーしても、
            // @neobit/upload_request.jsで読み込めるようにする
            '@neobit': fileURLToPath(new URL(`./${NEOBIT}/js`, import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
