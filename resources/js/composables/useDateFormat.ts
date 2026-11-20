const WEEKDAYS = ['日', '一', '二', '三', '四', '五', '六'];

/**
 * 取得裝置本地時區的今天（YYYY-MM-DD）。
 */
export function localToday(): string {
    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/**
 * 星期幾（日、一…六）。
 */
function weekday(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);

    return WEEKDAYS[new Date(y, m - 1, d).getDay()];
}

/**
 * 日期轉「11月19日 週四」。
 */
export function longDate(iso: string): string {
    const [, m, d] = iso.split('-').map(Number);

    return `${m}月${d}日 週${weekday(iso)}`;
}

/**
 * 日期轉「11/19（四）」；等於 today 時顯示「今天」。
 */
export function shortDate(iso: string, today?: string): string {
    if (today && iso === today) {
        return '今天';
    }

    const [, m, d] = iso.split('-').map(Number);

    return `${m}/${d}（${weekday(iso)}）`;
}

/**
 * 日期（YYYY-MM-DD）加減天數。
 */
export function addDays(iso: string, days: number): string {
    const [y, m, d] = iso.split('-').map(Number);

    return new Date(Date.UTC(y, m - 1, d + days)).toISOString().slice(0, 10);
}
