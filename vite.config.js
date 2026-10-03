import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/sortable.js',
                'resources/js/ajax_upload.js',
                'resources/js/wysiwyg_ckeditor.js',
                'resources/js/wysiwyg_summernote.js',
                'resources/js/contact_form.js',
                'resources/js/passkeys.js',
                'resources/js/code_editor.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
