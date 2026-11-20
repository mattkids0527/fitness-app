/**
 * 熱量取整數並加千分位。
 */
export function formatKcal(kcal: number): string {
    return Math.round(kcal).toLocaleString('en-US');
}

/**
 * 克數顯示到小數 1 位；不知道（null）顯示 —。
 */
export function formatGrams(grams: number | null): string {
    return grams === null ? '—' : String(Math.round(grams * 10) / 10);
}
