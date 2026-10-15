/**
 * IPブロック管理の画面（neobit::admin.ip_blocks.index）の動き。
 *
 * 1. #ip-block-ip（登録するIPアドレスの欄）
 *    入力に合わせて、実際に止まる範囲を #ip-block-range に「192.0.2.0 ～ 192.0.2.255（256個）」の形で出す。
 *    IPv4とIPv6の両方を、192.0.2.0/24のようなまとまりでも計算する。保存するときの形の確かめは
 *    サーバーが行うので、ここは目安の表示だけ。
 * 2. form[data-confirm]（解除）
 *    送信の前に、data-confirmの文言で確認する。
 */

// 1. 止まる範囲の表示
const input = document.getElementById('ip-block-ip');
const output = document.getElementById('ip-block-range');

/** IPv4の文字を、32ビットの数にする。正しくなければnull */
function parseIpv4(text) {
    const parts = text.split('.');
    if (parts.length !== 4 || parts.some((part) => !/^\d{1,3}$/.test(part) || Number(part) > 255)) {
        return null;
    }

    return parts.reduce((value, part) => (value << 8n) | BigInt(part), 0n);
}

/** IPv6の文字を、128ビットの数にする。「::」の省略に対応する。正しくなければnull */
function parseIpv6(text) {
    const halves = text.split('::');
    if (halves.length > 2) {
        return null;
    }

    const head = halves[0] === '' ? [] : halves[0].split(':');
    const tail = halves.length === 2 && halves[1] !== '' ? halves[1].split(':') : [];
    const missing = 8 - head.length - tail.length;
    if ((halves.length === 1 && missing !== 0) || (halves.length === 2 && missing < 1)) {
        return null;
    }

    const groups = [...head, ...Array(halves.length === 2 ? missing : 0).fill('0'), ...tail];
    if (groups.some((group) => !/^[0-9a-fA-F]{1,4}$/.test(group))) {
        return null;
    }

    return groups.reduce((value, group) => (value << 16n) | BigInt(parseInt(group, 16)), 0n);
}

/** 数を、IPv4の文字にする */
function formatIpv4(value) {
    return [24n, 16n, 8n, 0n].map((shift) => String((value >> shift) & 0xFFn)).join('.');
}

/** 数を、IPv6の文字にする。いちばん長い0の並びを「::」にまとめる */
function formatIpv6(value) {
    const groups = [];
    for (let shift = 112n; shift >= 0n; shift -= 16n) {
        groups.push(((value >> shift) & 0xFFFFn).toString(16));
    }

    // いちばん長い0の並び（2つ以上）を探す
    let bestStart = -1;
    let bestLength = 1;
    for (let start = 0; start < 8; start++) {
        let length = 0;
        while (start + length < 8 && groups[start + length] === '0') {
            length++;
        }
        if (length > bestLength) {
            bestStart = start;
            bestLength = length;
        }
    }

    if (bestStart === -1) {
        return groups.join(':');
    }

    return groups.slice(0, bestStart).join(':') + '::' + groups.slice(bestStart + bestLength).join(':');
}

/** 入力から、[最初, 最後, 個数] の文字を作る。計算できなければnull */
function rangeOf(text) {
    const [address, prefixText] = text.trim().split('/');
    const isV4 = address.includes('.') && !address.includes(':');
    const bits = isV4 ? 32n : 128n;
    const value = isV4 ? parseIpv4(address) : parseIpv6(address);
    if (value === null) {
        return null;
    }

    let prefix = bits;
    if (prefixText !== undefined) {
        if (!/^\d+$/.test(prefixText) || BigInt(prefixText) > bits) {
            return null;
        }
        prefix = BigInt(prefixText);
    }

    // 頭からprefixのビットだけを残したのが最初、残りを1で埋めたのが最後
    const hostMask = (1n << (bits - prefix)) - 1n;
    const first = value & ~hostMask & ((1n << bits) - 1n);
    const last = first | hostMask;
    const format = isV4 ? formatIpv4 : formatIpv6;

    return [format(first), format(last), (hostMask + 1n).toLocaleString()];
}

if (input && output) {
    const show = () => {
        if (input.value.trim() === '') {
            output.textContent = '';
            return;
        }

        const range = rangeOf(input.value);
        output.textContent = range === null
            ? '範囲を計算できません。IPアドレスか、192.0.2.0/24のようなまとまりで入力してください。'
            : `止まる範囲：${range[0]} ～ ${range[1]}（${range[2]}個）`;
    };

    input.addEventListener('input', show);
    show();
}

// 2. 解除の確認
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});
