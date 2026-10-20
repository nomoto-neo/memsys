/*
 * 画像・添付ファイルのAjaxアップロードUI用のスクリプト。
 * Neobit\Core\Upload\AjaxFileUpload・resources/views/_ajax_upload_block.blade.phpと対応している。
 *
 * .ajax_upload_blockという単位が1ファイル分のアップロード枠で、画面内に
 * 何個あってもよい(idではなくclassでまとめているのはこのため)。繰り返しの組で
 * 足された行の中の枠にも同じ挙動をさせたいので、個々の要素へ直接addEventListener()するのではなく、
 * document全体へのイベント委譲(delegation)で統一的に処理している。
 *
 * ファイルの項目が1つだけの繰り返しの組（_repeatable_group）の枠では、ファイルをまとめて選べる
 * （file inputにmultipleが付いている）。1つ目は選んだ枠に、2つ目から先はほかの行の同じ欄で空いているものに入れ、
 * 1つずつ順に送る。空いている枠が無ければ、受け付けられてから行を足す（repeatable_group.jsに頼む）。
 * 行の数は組のdata-max（REPEATABLE_GROUPSの'max'）までで、超える分は送らずに最後にまとめて知らせる。
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

    // まとめて選べる枠なら、その枠のある繰り返しの組を返す。そうでなければnull
    function multipleGroupOf(block) {
        const input = block.querySelector('.ajax_file_input');

        return input && input.multiple ? block.closest('.repeatable_group') : null;
    }

    // 組の行の数の上限（REPEATABLE_GROUPSの'max'。_repeatable_groupのdata-max）
    function maxRows(group) {
        return Number(group.dataset.max) || Infinity;
    }

    // 組の今の行。ひな型の中の行は数えない
    function rowsOf(group) {
        return group.querySelectorAll(':scope > .repeatable_rows > .repeatable_row');
    }

    // 組の行の中の、同じ欄の枠
    function blocksOf(group, field) {
        return Array.from(rowsOf(group))
            .map((row) => row.querySelector(`.ajax_upload_block[data-field="${field}"]`))
            .filter((block) => block !== null);
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
        // 選んだ枠が、送っている間に行ごと消されていなければ使う
        if (preferred.isConnected && isEmptyBlock(preferred)) {
            return preferred;
        }

        return blocksOf(group, preferred.dataset.field).find(isEmptyBlock) ?? null;
    }

    // 組に行を1つ足し、その行の同じ欄の枠を返す。行を足すのはrepeatable_group.jsで、イベントで頼む。
    // 上限まで行があって足せなかったときはnull
    function addRowBlock(group, field) {
        const request = { row: null };
        group.dispatchEvent(new CustomEvent('repeatable:add-row', { bubbles: true, detail: request }));

        return request.row?.querySelector(`.ajax_upload_block[data-field="${field}"]`) ?? null;
    }

    function handleFiles(block, files) {
        if (! files || files.length === 0) {
            return;
        }

        // まとめて選べない枠は、先頭の1件だけを使う
        const group = multipleGroupOf(block);
        if (! group || files.length === 1) {
            sendFile(block, files[0]).then((data) => {
                if (data !== null) {
                    fillBlock(block, data);
                }
            });
            return;
        }

        // まとめて選んだとき。サーバーに負荷を掛けないよう、1つずつ順に送る。断られたファイルがあっても、続きは送る
        const field = block.dataset.field;
        let skipped = 0;
        Array.from(files).reduce(
            (previous, file) => previous.then(() => {
                // 空いている枠も無く、上限まで行があれば、送らずに飛ばす。上限を超える一時ファイルを作らないため
                if (findEmptyBlock(group, block) === null && rowsOf(group).length >= maxRows(group)) {
                    skipped++;
                    return;
                }

                // 受け付けられてから入れる枠を決める。選んだ枠、ほかの行の空いている枠の順で、無ければ行を足す。
                // 断られたファイルのために、空の行を増やさないため
                return sendFile(block, file).then((data) => {
                    if (data === null) {
                        return;
                    }

                    const target = findEmptyBlock(group, block) ?? addRowBlock(group, field);
                    if (target === null) {
                        // 送っている間に「行を足す」で上限まで行が足されたとき
                        skipped++;
                        return;
                    }

                    fillBlock(target, data);
                });
            }),
            Promise.resolve(),
        ).then(() => {
            if (skipped > 0) {
                alert(`ファイルは${maxRows(group)}件までです。${skipped}件は追加しませんでした。`);
            }
        });
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
        // AjaxFileUpload::commitUploads()か繰り返しの組の保存の側で判断する。
        // ファイルだけの組で行が2つ以上あれば、行ごと消す（repeatable_group.jsに頼む）。
        // それ以外の組の行は消さない。同じ行のほかの項目の入力を残すため。ファイルの無くなった空の行は保存のときに除かれる
        const cancelLink = event.target.closest('.ajax_cancel');
        if (cancelLink) {
            event.preventDefault();
            const block = findBlock(cancelLink);

            const filesOnlyGroup = block.closest('.repeatable_group[data-files-only]');
            if (filesOnlyGroup && rowsOf(filesOnlyGroup).length >= 2) {
                block.dispatchEvent(new CustomEvent('repeatable:remove-row', { bubbles: true }));
                return;
            }

            block.querySelector('.ajax_origin').value = '';
            block.querySelector('.ajax_tmp').value = '';
            block.querySelector('.ajax_del').value = '1';

            const imageBlock = block.querySelector('.ajax_image_block');
            const img = imageBlock.querySelector('img');
            if (img) {
                img.src = '';
            }

            showNoImageBlock(block);
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
