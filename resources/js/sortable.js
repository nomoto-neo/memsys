// ドラッグでの並び替え（SortableJS）。使う画面（admin/categories/index）だけが
// @push('head-extra')で読み込む。SortableJSは、CDNではなくnpmでローカルにインストールしたものを使う。
import Sortable from 'sortablejs';

// importした変数はこのファイルの中でしか使えないので、画面側の<script>から
// Sortable.create()を呼べるよう、グローバル変数（window.Sortable）にする。
window.Sortable = Sortable;
