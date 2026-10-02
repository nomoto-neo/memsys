/*
 * 項目見出し一覧（admin/codes/index）の画面の動き。@push('head-extra')で読み込む。
 *
 * 1. 行のドラッグでの並び替え（SortableJS。⠿の部分をつかんだときだけ動く）
 * 2. 「追加」で空の行を1行足す。コード値の欄には、画面に出ているコード値が
 *    すべて数字なら「いちばん大きい値＋1」を入れる（前ゼロの付いた値があれば、
 *    いちばん長い値の桁数に揃える。例：01・02 → 03）。数字以外の値があれば空欄。
 * 3. 「×」でその行を画面から消す（更新すると、その行は削除される）
 * 4. 送信の直前に、入力欄のnameの番号を画面の並び順で振り直す
 *    （codes[0][code]・codes[0][name]、codes[1][code]…）。サーバーはこの順を表示順にする
 * 5. 保存していない変更があるときにコード表を切り替えようとしたら、
 *    「はい・いいえ」で確かめる。「いいえ」ならプルダウンを元に戻す
 *
 * 変更があるかどうかは、画面を開いた時点の行の内容（並び順を含む）と、今の内容を
 * 比べて判断する。検証エラーで戻ってきた画面（data-unsaved="1"）は、保存して
 * いない内容を表示しているので、最初から変更ありとして扱う。
 */
import Sortable from 'sortablejs';

const form = document.getElementById('code-form');
const rows = document.getElementById('code-rows');
const template = document.getElementById('code-row-template');
const addButton = document.getElementById('code-add');
const typeForm = document.getElementById('code-type-form');
const typeSelect = document.getElementById('code-type');
const modalElement = document.getElementById('code-switch-modal');

// 今の行の内容を1つの文字列にする（変更の有無の判定用）
const snapshot = () => JSON.stringify(
    Array.from(rows.querySelectorAll('tr')).map((tr) => [
        tr.querySelector('[data-field="code"]').value,
        tr.querySelector('[data-field="name"]').value,
    ]),
);

const initial = snapshot();
const isDirty = () => form.dataset.unsaved === '1' || snapshot() !== initial;

// 1. 並び替え
Sortable.create(rows, {
    handle: '.drag-handle',
    animation: 150,
});

// 2. 行の追加
const nextCode = () => {
    const codes = Array.from(rows.querySelectorAll('[data-field="code"]'))
        .map((input) => input.value.trim())
        .filter((value) => value !== '');

    if (codes.some((value) => !/^[0-9]+$/.test(value))) {
        return '';
    }

    if (codes.length === 0) {
        return '1';
    }

    // 桁数の多い値でも正しく比べられるよう、BigIntで計算する
    const max = codes.reduce((a, value) => (BigInt(value) > a ? BigInt(value) : a), 0n);
    const next = (max + 1n).toString();
    const zeroPadded = codes.some((value) => value.length > 1 && value.startsWith('0'));
    const width = Math.max(...codes.map((value) => value.length));

    return zeroPadded ? next.padStart(width, '0') : next;
};

addButton?.addEventListener('click', () => {
    const row = template.content.firstElementChild.cloneNode(true);
    row.querySelector('[data-field="code"]').value = nextCode();
    rows.appendChild(row);
    row.querySelector('[data-field="name"]').focus();
});

// 3. 行の削除（後から追加した行にも効くよう、まとめて受け取る）
rows.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-row]');
    if (button) {
        button.closest('tr').remove();
    }
});

// 4. 送信の直前にnameを振り直す
form.addEventListener('submit', () => {
    rows.querySelectorAll('tr').forEach((tr, index) => {
        tr.querySelector('[data-field="code"]').name = `codes[${index}][code]`;
        tr.querySelector('[data-field="name"]').name = `codes[${index}][name]`;
    });
});

// 5. コード表の切り替え
const modal = window.bootstrap ? new window.bootstrap.Modal(modalElement) : null;
let switching = false;

typeSelect.addEventListener('change', () => {
    if (!isDirty()) {
        typeForm.submit();
        return;
    }

    if (!modal) {
        // Bootstrapが読み込めていないときは、ブラウザ標準の確認で代わりにする
        if (window.confirm('保存していない変更があります。切り替えますか？')) {
            typeForm.submit();
        } else {
            typeSelect.value = typeSelect.dataset.current;
        }
        return;
    }

    switching = false;
    modal.show();
});

modalElement.querySelectorAll('[data-switch-answer]').forEach((button) => {
    button.addEventListener('click', () => {
        switching = button.dataset.switchAnswer === 'yes';
        modal.hide();
    });
});

// 「いいえ」のほか、Escキーや枠の外のクリックで閉じたときも、切り替えずに元に戻す
modalElement.addEventListener('hidden.bs.modal', () => {
    if (switching) {
        typeForm.submit();
    } else {
        typeSelect.value = typeSelect.dataset.current;
    }
});
