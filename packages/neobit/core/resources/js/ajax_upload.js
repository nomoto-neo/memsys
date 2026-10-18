/*
 * 画像・添付ファイルのAjaxアップロードUI用のスクリプト。
 * Neobit\Core\Upload\AjaxFileUpload・resources/views/_ajax_upload_block.blade.php
 * ・_ajax_upload_group.blade.phpと対応している。
 *
 * .ajax_upload_blockという単位が1ファイル分のアップロード枠で、画面内に
 * 何個あってもよい(idではなくclassでまとめているのはこのため)。動的に
 * 追加される枠(.ajax_upload_group内の「ファイルを追加」で複製されたもの)
 * にも同じ挙動をさせたいので、個々の要素へ直接addEventListener()するのではなく、
 * document全体へのイベント委譲(delegation)で統一的に処理している。
 *
 * いくつでも足せる欄(.ajax_upload_group)では、ファイルをまとめて選べる。1つ目は選んだ枠に、
 * 2つ目から先は空いている枠に入れ、1つずつ順に送る。空いている枠が無ければ、受け付けられてから枠を足す。
 * 枠の数はグループのdata-max(UPLOAD_FILESの'max')までで、上限まで枠があれば「ファイルを追加」も押せなくする。
 * ファイルを削除したときは、枠が2つ以上なら枠ごと消し、最後の1枠は空にして残す。
 * 空いている枠は、右上の×でも消せる。枠が1つだけのときは×を隠す。
 *
 * サーバーへの送信そのものは、WYSIWYGエディタの画像アップロードと共通の
 * upload_request.jsのpostUploadFile()で行う。
 */
import { postUploadFile } from '@neobit/upload_request.js';

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
    // 受け付けられればサーバーの応答を、断られればnullを返すPromiseにする。
    // 入れる枠を、受け付けられてから決められるようにするため（まとめて選んだとき）。
    function sendFile(block, file) {
        return postUploadFile(block.dataset.uploadUrl, block.dataset.field, file)
            .then(({ ok, data }) => {
                if (! ok) {
                    alert(data.message || 'アップロードに失敗しました。');
                    return null;
                }

                return data;
            })
            .catch(() => {
                alert('アップロードに失敗しました。');
                return null;
            });
    }

    // 受け付けられたファイルを、枠に入れて見せる。
    function fillBlock(block, data) {
        const isImage = block.dataset.isImage === '1';

        // アップロード成功時の状態遷移（詳しくはNeobit\Core\Upload\AjaxFileUploadのコメント）。
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
    }

    // グループの枠の数の上限（UPLOAD_FILESの'max'。_ajax_upload_groupのdata-max）。
    function maxBlocks(group) {
        return Number(group.dataset.max) || Infinity;
    }

    // グループの今の枠の数。<template>の中のひな型は数えない。
    function countBlocks(group) {
        return group.querySelectorAll('.ajax_upload_block').length;
    }

    // 枠の数に合わせて、ボタンの出し方を決める。上限まで枠があれば「ファイルを追加」を押せなくし、
    // 枠が1つだけなら×を隠す。最後の1枠は残すため。
    function updateGroupButtons(group) {
        const count = countBlocks(group);

        const addButton = group.querySelector('.ajax_add_block');
        if (addButton) {
            addButton.disabled = count >= maxBlocks(group);
        }

        group.querySelectorAll('.ajax_upload_block .ajax_remove_block').forEach((removeButton) => {
            removeButton.style.display = count >= 2 ? '' : 'none';
        });
    }

    // 枠を消して、ボタンの出し方を決め直す。
    function removeBlock(group, block) {
        block.remove();
        updateGroupButtons(group);
    }

    // ひな型を複製して、枠を並べる入れ物（.ajax_upload_blocks）の末尾に空の枠を足し、足した枠を返す。
    // 上限まで枠があれば、足さずにnullを返す。
    function addBlock(group) {
        if (countBlocks(group) >= maxBlocks(group)) {
            return null;
        }

        const template = group.querySelector('.ajax_block_template');
        const clone = template.content.cloneNode(true);
        const block = clone.querySelector('.ajax_upload_block');
        group.querySelector('.ajax_upload_blocks').appendChild(clone);
        updateGroupButtons(group);

        return block;
    }

    // ファイルの入っていない枠か。今のファイルも新しいファイルも無いか、削除する印の立った枠。
    function isEmptyBlock(block) {
        const hasValue = block.querySelector('.ajax_value').value !== '';
        const hasTmp = block.querySelector('.ajax_tmp').value !== '';
        const deleted = block.querySelector('.ajax_del').value === '1';

        return ! hasTmp && (! hasValue || deleted);
    }

    // ファイルを入れられる空いている枠を返す。選んだ枠を先に使い、無ければnullを返す。
    function findEmptyBlock(group, preferred) {
        // 選んだ枠が、送っている間に削除されていなければ使う
        if (preferred.isConnected && isEmptyBlock(preferred)) {
            return preferred;
        }

        return Array.from(group.querySelectorAll('.ajax_upload_block')).find(isEmptyBlock) ?? null;
    }

    function handleFiles(block, files) {
        if (! files || files.length === 0) {
            return;
        }

        // 1つだけの欄（グループの外）は、先頭の1件だけを使う
        const group = block.closest('.ajax_upload_group');
        if (! group || files.length === 1) {
            sendFile(block, files[0]).then((data) => {
                if (data !== null) {
                    fillBlock(block, data);
                }
            });
            return;
        }

        // いくつでも足せる欄で、まとめて選んだとき。サーバーに負荷を掛けないよう、1つずつ順に送る。
        // 断られたファイルがあっても、続きは送る
        let skipped = 0;
        Array.from(files).reduce(
            (previous, file) => previous.then(() => {
                // 空いている枠も無く、上限まで枠があれば、送らずに飛ばす。上限を超える一時ファイルを作らないため
                if (findEmptyBlock(group, block) === null && countBlocks(group) >= maxBlocks(group)) {
                    skipped++;
                    return;
                }

                // 受け付けられてから入れる枠を決める。選んだ枠、ほかの空いている枠の順で、無ければ枠を足す。
                // 断られたファイルのために、空の枠を増やさないため
                return sendFile(block, file).then((data) => {
                    if (data === null) {
                        return;
                    }

                    const target = findEmptyBlock(group, block) ?? addBlock(group);
                    if (target === null) {
                        // 送っている間に「ファイルを追加」で上限まで枠が足されたとき
                        skipped++;
                        return;
                    }

                    fillBlock(target, data);
                });
            }),
            Promise.resolve(),
        ).then(() => {
            if (skipped > 0) {
                alert(`ファイルは${maxBlocks(group)}件までです。${skipped}件は追加しませんでした。`);
            }
        });
    }

    // ドロップエリアのクリックで、隠しているfile inputのクリックを呼び出す。
    document.addEventListener('click', (event) => {
        // 空いている枠の右上の×。ドロップエリアの中にあるので、ファイルを選ぶ画面を開くより先に見る
        const removeButton = event.target.closest('.ajax_remove_block');
        if (removeButton) {
            const group = removeButton.closest('.ajax_upload_group');
            if (countBlocks(group) >= 2) {
                removeBlock(group, findBlock(removeButton));
            }
            return;
        }

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

            // いくつでも足せる欄で枠が2つ以上あれば、枠ごと消す。保存のときは届いた枠だけで行を作り直すので、
            // 消した枠のファイルは外れる。空の枠が残って上限に数えられないようにするため
            const group = block.closest('.ajax_upload_group');
            if (group && countBlocks(group) >= 2) {
                removeBlock(group, block);
                return;
            }

            // 1つだけの欄か、グループの最後の1枠は、空にして残す
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

        // 「ファイルを追加」ボタン。上限までなら、空の枠を1つ足す。
        const addButton = event.target.closest('.ajax_add_block');
        if (addButton) {
            addBlock(addButton.closest('.ajax_upload_group'));
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

    // ×にポインターを載せたら、色を濃くする。インラインのハンドラーを書かずに済ませるため、ここで受ける
    document.addEventListener('mouseover', (event) => {
        const removeButton = event.target.closest('.ajax_remove_block');
        if (removeButton) {
            removeButton.style.color = '#212529';
        }
    });

    document.addEventListener('mouseout', (event) => {
        const removeButton = event.target.closest('.ajax_remove_block');
        if (removeButton) {
            removeButton.style.color = '#6c757d';
        }
    });

    // 画面を開いたときに、どのグループもボタンの出し方を決めておく
    document.querySelectorAll('.ajax_upload_group').forEach(updateGroupButtons);
})();
