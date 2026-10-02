/*
 * 画像・添付ファイルのAjaxアップロードUI用のスクリプト。
 * app/Support/AjaxFileUpload.php・resources/views/admin/_ajax_upload_block.blade.php
 * ・_ajax_upload_group.blade.phpと対応している。
 *
 * .ajax_upload_blockという単位が1ファイル分のアップロード枠で、画面内に
 * 何個あってもよい(idではなくclassでまとめているのはこのため)。動的に
 * 追加される枠(.ajax_upload_group内の「ファイルを追加」で複製されたもの)
 * にも同じ挙動をさせたいので、個々の要素へ直接addEventListener()するのではなく、
 * document全体へのイベント委譲(delegation)で統一的に処理している。
 *
 * サーバーへの送信そのものは、WYSIWYGエディタの画像アップロードと共通の
 * upload_request.jsのpostUploadFile()で行う。
 */
import { postUploadFile } from './upload_request.js';

(() => {

    function findBlock(el) {
        return el.closest('.ajax_upload_block');
    }

    // 表示/非表示の切り替えは、d-noneクラスの付け外しと、インライン
    // styleのdisplay指定の両方を併用する。理由は
    // _ajax_upload_block.blade.php側の同じ処理へのコメント参照
    // （Bootstrap環境ではd-none/d-flexの競合をd-none側が正しく
    // 制するが、Bootstrapが無い環境ではd-noneクラスに何の効果も
    // 無いため、インラインstyleの方で最低限の表示切り替えを担保する）。
    function showImageBlock(block) {
        const imageBlock = block.querySelector('.ajax_image_block');
        const noImageBlock = block.querySelector('.ajax_noimage_block');

        imageBlock.classList.remove('d-none');
        imageBlock.style.display = '';
        noImageBlock.classList.add('d-none');
        noImageBlock.style.display = 'none';
    }

    function showNoImageBlock(block) {
        const imageBlock = block.querySelector('.ajax_image_block');
        const noImageBlock = block.querySelector('.ajax_noimage_block');

        imageBlock.classList.add('d-none');
        imageBlock.style.display = 'none';
        noImageBlock.classList.remove('d-none');
        noImageBlock.style.display = '';
    }

    // 実際のアップロード処理。file入力やドロップで得られたFileオブジェクトを
    // 受け取り、そのブロックのdata-upload-url宛にAjaxで送信する。
    function uploadFile(block, file) {
        const isImage = block.dataset.isImage === '1';
        const field = block.dataset.field;

        postUploadFile(block.dataset.uploadUrl, field, file)
            .then(({ ok, data }) => {
                if (! ok) {
                    alert(data.message || 'アップロードに失敗しました。');
                    return;
                }

                // アップロード成功時の状態遷移（詳しくはApp\Support\AjaxFileUploadのコメント）。
                // 既存画像の差し替えの場合に備えて_delも1にしておく
                // (保存のときに古いファイルが外れるようにするため)。
                block.querySelector('.ajax_tmp').value = data.tmp_name;
                block.querySelector('.ajax_origin').value = data.origin_name;
                block.querySelector('.ajax_del').value = '1';

                const imageBlock = block.querySelector('.ajax_image_block');

                if (isImage) {
                    const img = imageBlock.querySelector('img');
                    if (img) {
                        img.src = data.url;
                    }
                } else {
                    const link = imageBlock.querySelector('.ajax_file_link');
                    if (link) {
                        link.href = data.url;
                        link.download = data.origin_name;
                        link.textContent = data.origin_name;
                    }
                }

                showImageBlock(block);
            })
            .catch(() => {
                alert('アップロードに失敗しました。');
            });
    }

    function handleFiles(block, files) {
        if (! files || files.length === 0) {
            return;
        }

        // 1ファイルのみ受け付ける(複数ドロップされても先頭の1件だけ使う)。
        uploadFile(block, files[0]);
    }

    // ドロップエリアのクリックで、隠しているfile inputのクリックを呼び出す。
    document.addEventListener('click', (event) => {
        const noImageBlock = event.target.closest('.ajax_noimage_block');
        if (noImageBlock) {
            const block = findBlock(noImageBlock);
            block.querySelector('.ajax_file_input').click();
            return;
        }

        // 「ファイルを削除する」リンク。サーバーへの通信は行わず、
        // クライアント側の状態だけを「未選択」に戻す。実際にサーバー上の
        // 既存ファイルを消すかどうかは、確定(登録/更新)時に_delを見て
        // AjaxFileUpload::commitUploads()側で判断する。
        const cancelLink = event.target.closest('.ajax_cancel');
        if (cancelLink) {
            event.preventDefault();
            const block = findBlock(cancelLink);

            block.querySelector('.ajax_origin').value = '';
            block.querySelector('.ajax_tmp').value = '';
            block.querySelector('.ajax_del').value = '1';

            const imageBlock = block.querySelector('.ajax_image_block');
            const img = imageBlock.querySelector('img');
            if (img) {
                img.src = '';
            }

            showNoImageBlock(block);
            return;
        }

        // 「ファイルを追加」ボタン。テンプレートを複製してグループ末尾に
        // 追加する(ボタンの直前に挿入する)。
        const addButton = event.target.closest('.ajax_add_block');
        if (addButton) {
            const group = addButton.closest('.ajax_upload_group');
            const template = group.querySelector('.ajax_block_template');
            const clone = template.content.cloneNode(true);
            group.insertBefore(clone, addButton);
        }
    });

    // file inputの値が変化した(ファイルが選択された)とき。
    document.addEventListener('change', (event) => {
        if (! event.target.classList.contains('ajax_file_input')) {
            return;
        }

        const block = findBlock(event.target);
        handleFiles(block, event.target.files);
    });

    // ドラッグ&ドロップ。dragoverでpreventDefault()しておかないと、
    // ブラウザの既定動作(ファイルを新しいタブで開こうとする)が先に
    // 発生してdropイベント自体が届かない。
    document.addEventListener('dragover', (event) => {
        if (event.target.closest('.ajax_noimage_block')) {
            event.preventDefault();
        }
    });

    document.addEventListener('drop', (event) => {
        const noImageBlock = event.target.closest('.ajax_noimage_block');
        if (! noImageBlock) {
            return;
        }

        event.preventDefault();
        const block = findBlock(noImageBlock);
        handleFiles(block, event.dataTransfer.files);
    });
})();
