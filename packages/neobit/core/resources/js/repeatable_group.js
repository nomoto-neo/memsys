/*
 * 繰り返しの組（Neobit\Core\Crud\RepeatableGroup・_repeatable_group.blade.php）の行の追加と削除。
 *
 * .repeatable_groupが1つの組で、その中の.repeatable_rowsに行（.repeatable_row）が並ぶ。
 * 「行を足す」で<template>のひな型を複製して末尾に足し、行の×で行ごと消す。
 * 行の数は組のdata-max（REPEATABLE_GROUPSの'max'）までで、上限まで行があれば「行を足す」を押せなくする。
 * 行が1つだけのときは×を隠す。最後の1行は残し、空のまま送れば保存のときに除かれるため。
 *
 * 行の中のファイルの欄は、ajax_upload.jsが受け持つ。足した行の中の欄にも同じ動きをさせるため、
 * どちらもdocument全体へのイベント委譲で書いている。
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

    // 行の数に合わせて、「行を足す」を押せるかと、×を出すかを決める
    function updateButtons(group) {
        const count = rowsOf(group).length;

        const addButton = group.querySelector(':scope > .repeatable_add');
        if (addButton) {
            addButton.disabled = count >= maxRows(group);
        }

        rowsOf(group).forEach((row) => {
            const removeButton = row.querySelector(':scope > .repeatable_remove');
            if (removeButton) {
                removeButton.style.display = count >= 2 ? '' : 'none';
            }
        });
    }

    document.addEventListener('click', (event) => {
        // 「行を足す」。上限までなら、ひな型を複製して末尾に足す
        const addButton = event.target.closest('.repeatable_add');
        if (addButton) {
            const group = addButton.closest('.repeatable_group');
            if (rowsOf(group).length < maxRows(group)) {
                const template = group.querySelector(':scope > .repeatable_template');
                group.querySelector(':scope > .repeatable_rows').appendChild(template.content.cloneNode(true));
                updateButtons(group);
            }

            return;
        }

        // 行の×。行が2つ以上あれば、行ごと消す
        const removeButton = event.target.closest('.repeatable_remove');
        if (removeButton) {
            const group = removeButton.closest('.repeatable_group');
            if (rowsOf(group).length >= 2) {
                removeButton.closest('.repeatable_row').remove();
                updateButtons(group);
            }
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

    // 画面を開いたときに、どの組もボタンの出し方を決めておく
    document.querySelectorAll('.repeatable_group').forEach(updateButtons);
})();
