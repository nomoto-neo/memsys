/*
 * リダイレクトの画面（neobit::admin.redirects.index）の動き。@push('head-extra')で読み込む。
 *
 * 1. 行の「編集」を押すと、その行だけ、文字（[data-view]）を隠して入力欄（[data-edit]）を出す。
 *    ほかの行の「編集」「削除」（[data-redirect-button]）と、追加の行（[data-redirect-add]）の入力欄とボタンを
 *    まるごと押せなくする。2行を同時に編集したり、編集の途中で別の保存をしたりしないようにするため。
 *    追加の行に入れかけた文字は、消さずに残す。
 * 2. 「キャンセル」で、入力欄を開いたときの値（data-original）に戻し、エラーの表示を消して、文字の行に戻す。
 *    ほかのボタンと追加の行も、また使えるようにする。
 * 3. 入力エラーで戻ってきた行（data-editing="1"）は、開いた状態で始める。
 */

const rows = [...document.querySelectorAll('[data-redirect-row]')];
const addRow = document.querySelector('[data-redirect-add]');

/** 行の、文字と入力欄を切り替える */
function showEditing(row, editing) {
    row.querySelectorAll('[data-view]').forEach((el) => el.classList.toggle('d-none', editing));
    row.querySelectorAll('[data-edit]').forEach((el) => el.classList.toggle('d-none', !editing));
    row.dataset.editing = editing ? '1' : '0';
}

/** 編集中の行の外のボタンと、追加の行の入力欄を、押せなくするか戻す */
function lockOtherButtons(editingRow) {
    const locked = editingRow !== null;
    document.querySelectorAll('[data-redirect-button]').forEach((button) => {
        button.disabled = locked && !editingRow.contains(button);
    });
    addRow?.querySelectorAll('input, select, button').forEach((el) => {
        el.disabled = locked;
    });
}

/** 入力欄を、開いたときの値に戻し、エラーの表示を消す */
function restoreOriginal(row) {
    row.querySelectorAll('[data-original]').forEach((input) => {
        if (input.type === 'checkbox') {
            input.checked = input.dataset.original === '1';
        } else {
            input.value = input.dataset.original;
        }
    });
    row.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    row.querySelectorAll('.invalid-feedback[data-item]').forEach((feedback) => {
        feedback.textContent = '';
    });
}

rows.forEach((row) => {
    // 1. 編集
    row.querySelector('[data-redirect-edit]')?.addEventListener('click', () => {
        showEditing(row, true);
        lockOtherButtons(row);
        row.querySelector('[data-edit] input[type="text"]')?.focus();
    });

    // 2. キャンセル
    row.querySelector('[data-redirect-cancel]')?.addEventListener('click', () => {
        restoreOriginal(row);
        showEditing(row, false);
        lockOtherButtons(null);
    });
});

// 3. 入力エラーで戻ってきた行
const editingRow = rows.find((row) => row.dataset.editing === '1');
if (editingRow) {
    showEditing(editingRow, true);
    lockOtherButtons(editingRow);
}
