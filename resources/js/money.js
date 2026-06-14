// Persian Toman formatting helper.

export function toman(value) {
    const n = Number(value) || 0;
    return new Intl.NumberFormat('fa-IR').format(n) + ' تومان';
}

export function groupDigits(value) {
    const n = Number(value) || 0;
    return new Intl.NumberFormat('fa-IR').format(n);
}
