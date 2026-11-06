/**
 * 管理画面のヘッダーの動き。案件のlayouts/admin.blade.phpが読み込む。
 *
 * 1. [data-nb-sidebar-toggle]（ヘッダーの☰。広い画面だけ）
 *    メニューの固定表示とアイコン表示を切り替え、.nb-adminのdata-nb-sidebarに入れる。
 *    アイコン表示で、マウスを乗せたときに開く動きはCSS（admin.css）が行う。
 * 2. [data-nb-theme-select]・[data-nb-font-size-select]（色の組み合わせと文字の大きさのプルダウン。置いた画面だけ）
 *    選んだものを<html>のdata-theme・data-font-sizeに入れる。値はadmin.cssにある。
 *
 * どちらも選んだ値をブラウザーに覚え、次の画面では、レイアウトの中の短いスクリプトが画面を出す前に当てる。
 * このファイルは画面を出した後に読まれるので、ここで当てると一瞬だけ前の表示が見えるため。
 */

/** 選んだ表示（fixed・rail）を覚えるキー。レイアウトの中のスクリプトも同じキーを読む */
const SIDEBAR_KEY = 'neobit.admin.sidebar';

/**
 * プルダウンの印 => [<html>のdata-の名前, 選んだ値を覚えるキー]。
 * キーは、レイアウトの中のスクリプトも同じものを読む。
 */
const HTML_SETTINGS = {
    'data-nb-theme-select': ['theme', 'neobit.admin.theme'],
    'data-nb-font-size-select': ['fontSize', 'neobit.admin.fontSize'],
};

/** ブラウザーに覚える。保存できないとき（保存が禁じられた設定など）は、覚えずにそのまま動く */
function save(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // 覚えられなくても、今の画面の動きには関係しない
    }
}

// 1. 固定表示とアイコン表示の切り替え
const admin = document.querySelector('.nb-admin');
const toggle = admin?.querySelector('[data-nb-sidebar-toggle]');

if (toggle) {
    toggle.setAttribute('aria-pressed', String(admin.dataset.nbSidebar === 'rail'));
    toggle.addEventListener('click', () => {
        const mode = admin.dataset.nbSidebar === 'rail' ? 'fixed' : 'rail';
        admin.dataset.nbSidebar = mode;
        toggle.setAttribute('aria-pressed', String(mode === 'rail'));
        save(SIDEBAR_KEY, mode);
    });
}

// 2. 色の組み合わせと文字の大きさの切り替え。プルダウンは、今当たっているものを選んだ状態で出す
for (const [attribute, [name, key]] of Object.entries(HTML_SETTINGS)) {
    const select = document.querySelector(`[${attribute}]`);
    if (!select) {
        continue;
    }

    select.value = document.documentElement.dataset[name];
    select.addEventListener('change', () => {
        document.documentElement.dataset[name] = select.value;
        save(key, select.value);
    });
}
