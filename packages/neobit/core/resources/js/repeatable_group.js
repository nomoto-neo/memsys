/*
 * 繰り返しの組（Neobit\Core\Crud\RepeatableGroup・_repeatable_group.blade.php）の行の追加と削除。
 *
 * .repeatable_groupが1つの組で、その中の.repeatable_rowsに行（.repeatable_row）が並ぶ。
 * 「行を足す」で<template>のひな型を複製して末尾に足し、行の×で行ごと消す。
 * 行の数は組のdata-max（REPEATABLE_GROUPSの'max'）までで、上限まで行があれば「行を足す」を押せなくする。
 * 最後の1行の×は、行を消さずに、ひな型の空の行に置き換える。行の入れ物を残したまま、行のid・ファイルの欄の
 * hidden・文字の欄をまとめて空にするため。空の行は保存のときに除かれるので、今の行は削除になる。
 *
 * 行の中のファイルの欄は、ajax_upload.jsが受け持つ。足した行の中の欄にも同じ動きをさせるため、
 * どちらもdocument全体へのイベント委譲で書いている。ファイルをまとめて選んだときに行を足すのと、
 * ファイルだけの組で「ファイルを削除」を押したときに行を消すのは、ajax_upload.jsが出す
 * repeatable:add-row・repeatable:remove-rowのイベントを受けて、ここで行う。
 */
(() => {

    // 組の今の行。ひな型の中の行は数えない
    function rowsOf(group) {
        return group.querySelectorAll(':scope > .repeatable_rows > .repeatable_row');
    }

    // 組の行の数の上限
    function maxRows(group) {
        return Number(group.dataset.max) || Infinity;
    }

    // 行の数に合わせて、「行を足す」を押せるかを決める
    function updateButtons(group) {
        const addButton = group.querySelector(':scope > .repeatable_add');
        if (addButton) {
            addButton.disabled = rowsOf(group).length >= maxRows(group);
        }
    }

    // ひな型を複製した、空の行
    function blankRow(group) {
        return group.querySelector(':scope > .repeatable_template').content.firstElementChild.cloneNode(true);
    }

    // ひな型を複製して、組の末尾に行を足し、足した行を返す。上限まで行があれば、足さずにnullを返す
    function addRow(group) {
        if (rowsOf(group).length >= maxRows(group)) {
            return null;
        }

        const row = blankRow(group);
        group.querySelector(':scope > .repeatable_rows').appendChild(row);
        updateButtons(group);

        return row;
    }

    // 行を消す。最後の1行は消さずに、ひな型の空の行に置き換える
    function removeRow(group, row) {
        if (rowsOf(group).length >= 2) {
            row.remove();
        } else {
            row.replaceWith(blankRow(group));
        }

        updateButtons(group);
    }

    document.addEventListener('click', (event) => {
        // 「行を足す」。上限までなら、ひな型を複製して末尾に足す
        const addButton = event.target.closest('.repeatable_add');
        if (addButton) {
            addRow(addButton.closest('.repeatable_group'));

            return;
        }

        // 行の×。行が2つ以上あれば行ごと消し、最後の1行なら空にする
        const removeButton = event.target.closest('.repeatable_remove');
        if (removeButton) {
            removeRow(removeButton.closest('.repeatable_group'), removeButton.closest('.repeatable_row'));
        }
    });

    // ×にポインターを載せたら、色を濃くする。インラインのハンドラーを書かずに済ませるため、ここで受ける
    document.addEventListener('mouseover', (event) => {
        const removeButton = event.target.closest('.repeatable_remove');
        if (removeButton) {
            removeButton.style.color = '#212529';
        }
    });

    document.addEventListener('mouseout', (event) => {
        const removeButton = event.target.closest('.repeatable_remove');
        if (removeButton) {
            removeButton.style.color = '#6c757d';
        }
    });

    // ほかのスクリプトから、行を足すのを頼まれたとき。ファイルをまとめて選んだときにajax_upload.jsが使う。
    // 足した行（上限で足せなければnull）を、イベントのdetail.rowに入れて返す
    document.addEventListener('repeatable:add-row', (event) => {
        const group = event.target.closest('.repeatable_group');
        if (group) {
            event.detail.row = addRow(group);
        }
    });

    // ほかのスクリプトから、行を消すのを頼まれたとき。ファイルだけの組で「ファイルを削除」を押したときに
    // ajax_upload.jsが、消す行からイベントを出す
    document.addEventListener('repeatable:remove-row', (event) => {
        const group = event.target.closest('.repeatable_group');
        const row = event.target.closest('.repeatable_row');
        if (group && row) {
            removeRow(group, row);
        }
    });

    // 画面を開いたときに、どの組もボタンの出し方を決めておく
    document.querySelectorAll('.repeatable_group').forEach(updateButtons);
})();
