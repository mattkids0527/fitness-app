/* ==========================================================================
   M5 健身紀錄 demo
   上半部是可操作的流程，下半部是每個狀態的靜態畫面。
   範例使用者：目前體重 70 kg（11/20 記錄），每日熱量目標 2,000 kcal。
   ========================================================================== */

// 圖示由 icons.js 掛在 window 上
const { ICONS } = window;

const NOW = '19:42';
const TODAY = '2026-11-21';
const DAILY_LIMIT = 10;
const CALORIE_TARGET = 2000;
const PROTEIN_TARGET = 112;
const MEAL_KCAL = 1350;
const MEAL_PROTEIN = 78;
const WATER_TOTAL = 1500;
const ML_PER_KG = 35;
const ML_PER_HOUR = 500;
const MAX_SETS = 20;

// 體重紀錄：計算熱量用「紀錄日當天或之前最新一筆」
const WEIGHTS = [
    ['2026-11-10', 71.2],
    ['2026-11-17', 70.6],
    ['2026-11-20', 70.0],
];

// 記錄方式
const RT = {
    duration: { label: '填時長', icon: 'timer', hint: '輸入運動時間，例如跑步、飛輪' },
    sets: { label: '逐組', icon: 'listOrdered', hint: '每組次數 × 重量，例如重訓' },
};

// 照片辨識失敗原因（規格 §8.2）
const ERRORS = {
    not_exercise: { title: '照片裡看不出器材或動作', message: '換一張清楚一點的照片，或手動新增項目。' },
    busy: { title: 'Gemini 暫時忙碌', message: '免費額度暫時用完，稍等一下再試。' },
    timeout: { title: '辨識太久沒有回應', message: '再試一次，或手動新增項目。' },
    failed: { title: '辨識沒有完成', message: '再試一次，或手動新增項目。' },
};

// 範例照片：史密斯機（SVG 插畫，demo 用）
const SMITH_PHOTO =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">
<defs><linearGradient id="bg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b4350"/><stop offset="1" stop-color="#232830"/></linearGradient></defs>
<rect width="400" height="300" fill="url(#bg)"/>
<rect y="232" width="400" height="68" fill="#1b1e24"/>
<g fill="#4d5664"><rect x="20" y="30" width="70" height="40" rx="4"/><rect x="310" y="30" width="70" height="40" rx="4"/></g>
<g fill="#9aa3ad"><rect x="92" y="34" width="12" height="200" rx="3"/><rect x="296" y="34" width="12" height="200" rx="3"/><rect x="86" y="30" width="228" height="12" rx="4"/></g>
<rect x="60" y="118" width="280" height="8" rx="4" fill="#d7dce2"/>
<g fill="#2a2a2a"><rect x="64" y="94" width="16" height="56" rx="4"/><rect x="320" y="94" width="16" height="56" rx="4"/></g>
<g fill="#e25b2c"><rect x="80" y="102" width="9" height="40" rx="3"/><rect x="311" y="102" width="9" height="40" rx="3"/></g>
<rect x="150" y="186" width="100" height="16" rx="6" fill="#111"/><rect x="190" y="202" width="20" height="30" fill="#555"/><rect x="160" y="228" width="80" height="6" rx="3" fill="#555"/>
<g fill="#7d8792"><rect x="86" y="226" width="24" height="8" rx="2"/><rect x="290" y="226" width="24" height="8" rx="2"/></g>
</svg>`);

// 範例照片：棒式（SVG 插畫，demo 用）
const PLANK_PHOTO =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">
<rect width="400" height="300" fill="#e9e2d6"/>
<rect y="200" width="400" height="100" fill="#b98f62"/>
<g stroke="#a47c52" stroke-opacity=".5"><path d="M0 228h400M0 262h400"/></g>
<rect x="40" y="196" width="320" height="14" rx="4" fill="#5aa38a"/>
<g stroke="#2d3a4a" stroke-width="16" stroke-linecap="round" fill="none"><path d="M112 150 L300 176"/><path d="M300 176 L330 196"/></g>
<g stroke="#2d3a4a" stroke-width="12" stroke-linecap="round" fill="none"><path d="M118 152 L116 194"/><path d="M116 194 L150 194"/></g>
<circle cx="92" cy="140" r="17" fill="#c78d61"/>
<rect x="86" y="120" width="22" height="10" rx="5" fill="#2b2b2b"/>
</svg>`);

const SAMPLES = {
    smith: {
        photo: SMITH_PHOTO,
        equipment: '史密斯機',
        candidates: [
            {
                name: '史密斯機深蹲',
                equipment: '史密斯機',
                category: '重訓',
                type: 'sets',
                met: 5.0,
                sec: 3,
                rest: 120,
                desc: '槓鈴固定在軌道上，扛在上背往下蹲，比自由槓鈴好控制。主要訓練大腿前側、臀部。',
                confidence: 0.88,
            },
            {
                name: '史密斯機臥推',
                equipment: '史密斯機',
                category: '重訓',
                type: 'sets',
                met: 5.0,
                sec: 3,
                rest: 90,
                desc: '把平板椅推到槓下躺著推起。主要訓練胸、三頭肌、肩膀前側。',
                confidence: 0.66,
            },
            {
                name: '槓鈴肩推',
                equipment: '史密斯機',
                category: '重訓',
                type: 'sets',
                met: 5.0,
                sec: 3,
                rest: 90,
                desc: '坐在椅上把槓從肩前推過頭頂。主要訓練肩膀、三頭肌。',
                confidence: 0.45,
            },
        ],
    },
    plank: {
        photo: PLANK_PHOTO,
        equipment: '徒手（瑜伽墊）',
        candidates: [
            {
                name: '棒式',
                equipment: '徒手',
                category: '核心',
                type: 'duration',
                met: 3.8,
                desc: '前臂撐地，身體從頭到腳保持一直線不動。主要訓練腹部、下背的核心肌群。',
                confidence: 0.91,
            },
            {
                name: '側棒式',
                equipment: '徒手',
                category: '核心',
                type: 'duration',
                met: 3.8,
                desc: '側躺以單手前臂撐起身體。主要訓練側腹。',
                confidence: 0.52,
            },
        ],
    },
};

/** 預設資料：使用者完成首次設定時複製的 2 個分類、7 個項目，加上 demo 用的封存項目與紀錄。 */
function seed() {
    const d = {
        categories: [
            { id: 1, name: '有氧', type: 'duration' },
            { id: 2, name: '重訓', type: 'sets' },
        ],
        exercises: [
            { id: 1, cat: 1, name: '室外跑步', met: 9.0, desc: '' },
            { id: 2, cat: 1, name: '室內跑步（跑步機）', met: 9.0, desc: '' },
            { id: 3, cat: 1, name: '滑雪機', met: 6.8, desc: '' },
            { id: 4, cat: 1, name: '踩樓梯', met: 9.3, desc: '' },
            { id: 5, cat: 2, name: '槓鈴臥推', met: 5.0, sec: 3, rest: 90, desc: '' },
            { id: 6, cat: 2, name: '槓鈴肩推', met: 5.0, sec: 3, rest: 90, desc: '' },
            { id: 7, cat: 2, name: '槓鈴划船', met: 5.0, sec: 3, rest: 90, desc: '' },
            { id: 8, cat: 1, name: '飛輪', met: 7.0, desc: '', archived: true },
        ],
        logs: [],
        nextId: 100,
    };
    const add = (date, time, exId, patch) => d.logs.push({ id: d.logs.length + 1, ...snapshot(d, exId, date), date, time, note: '', ...patch });
    add('2026-11-21', '07:10', 1, { min: 32, km: 5.2 });
    add('2026-11-21', '18:30', 5, { sets: toSets([[12, 40], [10, 50], [8, 55], [8, 55]]) });
    add('2026-11-21', '18:45', 7, { sets: toSets([[10, 40], [10, 40], [10, 40]]) });
    add('2026-11-19', '20:05', 8, { min: 40, note: '間歇課' });
    add('2026-11-18', '19:20', 6, { sets: toSets([[10, 30], [10, 30], [8, 35]]) });
    return d;
}

/** [[次數, 重量]] 轉成組。 */
function toSets(rows) {
    return rows.map(([reps, wt]) => ({ reps, wt }));
}

/** 存檔當下的快照：分類名稱、項目名稱、記錄方式、MET、秒數、體重（規格 Q4）。 */
function snapshot(d, exId, date) {
    const ex = d.exercises.find((e) => e.id === exId);
    const cat = d.categories.find((c) => c.id === ex.cat);
    return { exId, catName: cat.name, name: ex.name, type: cat.type, met: ex.met, sec: ex.sec, rest: ex.rest, w: weightOn(date)[1] };
}

let data = seed();
const tool = { theme: 'light', device: 'phone', result: 'smith', quota: 7 };
const state = initialState();
let timer = null;

/** 流程的初始狀態。 */
function initialState() {
    return {
        screen: 'workouts',
        date: TODAY,
        overlay: null,
        query: '',
        toast: '',
        fresh: null,
        log: null,
        form: null,
        catForm: null,
        menuLog: null,
        menuCat: null,
        delEx: null,
        showArchived: false,
        quota: Number(tool.quota),
        idf: { source: 'camera', sample: 'smith', photo: SMITH_PHOTO, phase: 'ready', error: null, hint: '' },
    };
}

/* ---------- 共用工具 ---------- */

/** 輸出一個 SVG 圖示。 */
function icon(name, size = '', cls = '') {
    return `<svg class="icon ${size} ${cls}" viewBox="0 0 24 24" aria-hidden="true">${ICONS[name]}</svg>`;
}

/** 整數加千分位。 */
const int = (n) => Math.round(n).toLocaleString('en-US');

/** 一位小數，整數不帶小數。 */
const one = (n) => {
    const r = Math.round(n * 10) / 10;
    return Number.isInteger(r) ? String(r) : r.toFixed(1);
};

const WEEKDAYS = ['日', '一', '二', '三', '四', '五', '六'];
const parse = (iso) => iso.split('-').map(Number);
const weekday = (iso) => {
    const [y, m, d] = parse(iso);
    return WEEKDAYS[new Date(y, m - 1, d).getDay()];
};
/** 「11月21日 週六」。 */
const longDate = (iso) => `${parse(iso)[1]}月${parse(iso)[2]}日 週${weekday(iso)}`;
/** MET 固定一位小數。 */
const metFmt = (n) => Number(n).toFixed(1);

/** 「11/18」。 */
const md = (iso) => `${parse(iso)[1]}/${parse(iso)[2]}`;
/** 「今天」或「11/18（三）」。 */
const shortDate = (iso) => (iso === TODAY ? '今天' : `${md(iso)}（${weekday(iso)}）`);
/** 加減天數。 */
const addDays = (iso, n) => {
    const [y, m, d] = parse(iso);
    return new Date(Date.UTC(y, m - 1, d + n)).toISOString().slice(0, 10);
};

/** 紀錄日當天或之前最新一筆體重 [日期, kg]。 */
function weightOn(date) {
    return [...WEIGHTS].reverse().find(([d]) => d <= date) ?? WEIGHTS[0];
}

/** 逐組估算秒數：Σ(次數 × 每下秒數) + (組數 − 1) × 組間休息。 */
function setsSeconds(sets, sec, rest) {
    const valid = sets.filter((s) => Number(s.reps) > 0);
    if (!valid.length) return 0;
    const reps = valid.reduce((sum, s) => sum + Number(s.reps), 0);
    return reps * sec + (valid.length - 1) * rest;
}

/** 訓練量 Σ(次數 × 重量)，沒填重量不計。 */
function setsVolume(sets) {
    return sets.reduce((sum, s) => sum + (Number(s.reps) > 0 && Number(s.wt) > 0 ? Number(s.reps) * Number(s.wt) : 0), 0);
}

/** 一筆紀錄的分鐘數（逐組為估算時間）。 */
function logMinutes(l) {
    return l.type === 'duration' ? Number(l.min) || 0 : setsSeconds(l.sets, l.sec, l.rest) / 60;
}

/** 一筆紀錄的消耗熱量：MET × 體重 × 時數。 */
function logKcal(l) {
    return (l.met * l.w * logMinutes(l)) / 60;
}

/** 某天的紀錄（依時間排序）。 */
function logsOn(date) {
    return data.logs.filter((l) => l.date === date).sort((a, b) => a.time.localeCompare(b.time));
}

/** 某天合計：消耗、時間、訓練量。 */
function dayTotals(date) {
    const logs = logsOn(date);
    return {
        kcal: logs.reduce((s, l) => s + logKcal(l), 0),
        min: logs.reduce((s, l) => s + logMinutes(l), 0),
        vol: logs.reduce((s, l) => s + (l.type === 'sets' ? setsVolume(l.sets) : 0), 0),
        hasSets: logs.some((l) => l.type === 'sets'),
        count: logs.length,
    };
}

/** 運動加到喝水建議量的 ml（運動時數 × 500，四捨五入到 10 ml）。 */
const exerciseWater = (min) => Math.round(((min / 60) * ML_PER_HOUR) / 10) * 10;

/** 逐組摘要：每組相同時「3 組 × 10 下 · 40 kg」，否則「12×40 · 10×50 · 8×55 ×2 kg」。 */
function setsSummary(sets) {
    const valid = sets.filter((s) => Number(s.reps) > 0);
    if (!valid.length) return '';
    const same = valid.every((s) => s.reps == valid[0].reps && s.wt == valid[0].wt);
    if (same) return `${valid.length} 組 × ${valid[0].reps} 下${Number(valid[0].wt) > 0 ? ` · ${one(valid[0].wt)} kg` : ''}`;
    const parts = [];
    valid.forEach((s) => {
        const label = Number(s.wt) > 0 ? `${s.reps}×${one(s.wt)}` : `${s.reps} 下`;
        const last = parts[parts.length - 1];
        if (last && last.label === label) last.n += 1;
        else parts.push({ label, n: 1 });
    });
    const text = parts.map((p) => (p.n > 1 ? `${p.label} ×${p.n}` : p.label)).join(' · ');
    return `${valid.length} 組：${text}${valid.some((s) => Number(s.wt) > 0) ? ' kg' : ''}`;
}

/** 紀錄卡的第二行。 */
function logDetail(l) {
    if (l.type === 'duration') return [`${one(l.min)} 分鐘`, l.km ? `${one(l.km)} km` : ''].filter(Boolean).join(' · ');
    return setsSummary(l.sets);
}

const exById = (id) => data.exercises.find((e) => e.id === id);
const catById = (id) => data.categories.find((c) => c.id === id);
const activeIn = (catId) => data.exercises.filter((e) => e.cat === catId && !e.archived);
const logCount = (exId) => data.logs.filter((l) => l.exId === exId).length;
const catOf = (ex) => catById(ex.cat);

/** 記錄方式標籤。 */
function rtBadge(type) {
    return `<span class="rt">${icon(RT[type].icon)}${RT[type].label}</span>`;
}

/** 項目的參數摘要。 */
function exParams(ex, type) {
    return type === 'sets' ? `MET ${metFmt(ex.met)} · 每下 ${ex.sec} 秒 · 休息 ${ex.rest} 秒` : `MET ${metFmt(ex.met)}`;
}

/** 項目「約消耗」估算文字（規格 Q10）。 */
function exEstimate(met, type, sec, rest) {
    const w = weightOn(TODAY)[1];
    if (type === 'sets') {
        const s = setsSeconds(toSets([[10], [10], [10]]), Number(sec) || 0, Number(rest) || 0);
        return { lead: '3 組 × 10 下', kcal: (met * w * s) / 3600, w };
    }
    return { lead: '每 30 分鐘', kcal: met * w * 0.5, w };
}

/** 信心度分級（沿用 M4）。 */
function confLevel(c) {
    if (c >= 0.75) return { label: '高', bars: 3, cls: '' };
    if (c >= 0.5) return { label: '中', bars: 2, cls: '' };
    return { label: '低', bars: 1, cls: 'low' };
}

/** 信心度標籤：三格訊號＋文字。 */
function confPill(c) {
    const lv = confLevel(c);
    const bars = [1, 2, 3].map((i) => `<i class="${i <= lv.bars ? 'on' : ''}"></i>`).join('');
    return `<span class="conf ${lv.cls}" title="AI 信心度"><span class="bars">${bars}</span>${lv.label}<span class="sr-only">信心度</span></span>`;
}

/** 今天剩餘辨識次數。 */
function quotaChip(quota) {
    if (quota <= 0) return `<span class="quota out">${icon('sparkles', 'xs')}今天的 ${DAILY_LIMIT} 次已用完</span>`;
    return `<span class="quota ${quota <= 3 ? 'low' : ''}">${icon('sparkles', 'xs')}今天還能辨識 ${quota} 次</span>`;
}

/** 頁首（返回＋標題＋右側動作）。 */
function pageHead(title, backAction, actions = '') {
    const back = backAction ? `<button class="back" data-action="${backAction}" aria-label="返回">${icon('chevronLeft')}</button>` : '';
    return `<div class="page-head">${back}<h3>${title}</h3>${actions ? `<div class="actions">${actions}</div>` : ''}</div>`;
}

/* ---------- 版型 ---------- */

/** 手機外框：內容、底部固定列、底部 Tab、覆蓋層。 */
function phone(view, s, extraCls = '') {
    const nav = (key, label, ic) =>
        `<button data-action="nav-${key}" class="${view.nav === key ? 'on' : ''}">${icon(ic, 'sm')}<span>${label}</span></button>`;
    return `<div class="device phone ${extraCls}">
        ${s.toast ? `<div class="toast">${icon('check', 'sm')}${s.toast}</div>` : ''}
        <div class="scroll">${view.body}</div>
        ${view.bar ?? ''}
        ${
            view.hideNav
                ? ''
                : `<nav class="bottom-nav">
            ${nav('today', '今日', 'house')}
            ${nav('meals', '飲食', 'utensils')}
            <button data-action="open-quick" aria-label="快速新增"><span class="plus">${icon('plus')}</span></button>
            ${nav('workouts', '健身', 'dumbbell')}
            ${nav('me', '我的', 'user')}
        </nav>`
        }
        ${overlay(s, 'phone')}
    </div>`;
}

/** 桌機外框：側欄＋內容。 */
function desktop(view, s) {
    const nav = (key, label, ic) => `<button data-action="nav-${key}" class="nav ${view.nav === key ? 'on' : ''}">${icon(ic, 'sm')}${label}</button>`;
    return `<div class="device desktop">
        <aside class="sidebar">
            <div class="brand">飲食健身紀錄</div>
            ${nav('today', '今日', 'house')}
            ${nav('meals', '飲食', 'utensils')}
            ${nav('workouts', '健身', 'dumbbell')}
            ${nav('me', '我的', 'user')}
            <button data-action="open-quick" class="nav add">${icon('plus', 'sm')}快速新增</button>
        </aside>
        <div class="desk-main">
            ${s.toast ? `<div class="toast">${icon('check', 'sm')}${s.toast}</div>` : ''}
            <div class="scroll"><div class="page-wrap ${view.width ?? ''}">${view.body}</div></div>
            ${view.bar ?? ''}
            ${overlay(s, 'desktop')}
        </div>
    </div>`;
}

/** 依裝置包外框。 */
function frame(device, s, extraCls = '') {
    const view = screenView(s, device);
    return device === 'desktop' ? desktop(view, s) : phone(view, s, extraCls);
}

/** 依目前狀態選擇畫面。 */
function screenView(s, device) {
    switch (s.screen) {
        case 'today':
            return todayView(s, device);
        case 'log':
            return logView(s, device);
        case 'manage':
            return manageView(s, device);
        case 'form':
            return formView(s, device);
        case 'identify':
            return identifyView(s, device);
        case 'candidates':
            return candidatesView(s, device);
        case 'meals':
            return placeholderView('meals', '飲食', '這裡是 M3 的飲食頁，demo 不重畫。');
        case 'me':
            return placeholderView('me', '我的', '這裡是 M1 的「我的」頁，demo 不重畫。');
        default:
            return workoutsView(s, device);
    }
}

/** 其他頁的佔位。 */
function placeholderView(nav, title, text) {
    return { nav, body: `${pageHead(title)}<div class="callout">${icon('info')}<div>${text}</div></div>` };
}

/* ---------- 健身頁 ---------- */

/** 健身頁：日期切換、當日合計、分類與項目入口、紀錄列表。 */
function workoutsView(s, device) {
    const logs = logsOn(s.date);
    const t = dayTotals(s.date);
    const cats = data.categories.length;
    const exs = data.exercises.filter((e) => !e.archived).length;

    const head = pageHead('健身', '', `<button class="btn primary" style="height:40px" data-action="open-picker">${icon('plus', 'sm')}新增</button>`);
    const switcher = `<div class="day-switch">
            <button class="arrow" data-action="day-prev" aria-label="前一天">${icon('chevronLeft')}</button>
            <div class="label">${icon('calendarDays', 'sm')}${longDate(s.date)}${s.date === TODAY ? '（今天）' : ''}</div>
            <button class="arrow" data-action="day-next" aria-label="後一天" ${s.date >= TODAY ? 'disabled' : ''}>${icon('chevronRight')}</button>
        </div>
        ${s.date !== TODAY ? '<button class="back-today" data-action="day-today">回到今天</button>' : ''}`;

    const summary = `<div class="card">
            <h4>${icon('dumbbell', 'sm')}${shortDate(s.date)}合計</h4>
            <div class="stats">
                <div class="stat hl"><div class="k">${icon('flame')}消耗</div><div class="v">${int(t.kcal)}<small>kcal</small></div></div>
                <div class="stat"><div class="k">${icon('timer')}時間</div><div class="v">${int(t.min)}<small>分</small></div></div>
                <div class="stat"><div class="k">${icon('dumbbell')}訓練量</div><div class="v">${t.hasSets ? int(t.vol) : '—'}<small>${t.hasSets ? 'kg' : ''}</small></div></div>
            </div>
        </div>
        <button class="link-row" style="margin-top:12px" data-action="nav-manage">${icon('settings2')}<span class="grow">分類與項目</span><span class="muted">${cats} 個分類 · ${exs} 個項目</span>${icon('chevronRight', 'sm', 'muted')}</button>`;

    const list = logs.length
        ? logs.map((l) => logCard(l, s.fresh === l.id)).join('')
        : `<div class="empty"><span class="type-icon">${icon('dumbbell')}</span>${s.date === TODAY ? '今天' : '這天'}還沒有運動紀錄<button class="btn primary" style="height:40px" data-action="open-picker">${icon('plus', 'sm')}新增運動</button></div>`;
    const listBlock = `<div class="sec-title" style="margin-top:4px">紀錄${logs.length ? `<span class="count">${logs.length} 筆</span>` : ''}</div>${list}`;

    if (device === 'desktop') {
        return { nav: 'workouts', body: `${head}<div style="max-width:320px">${switcher}</div><div class="cols side" style="margin-top:8px"><div>${summary}</div><div>${listBlock}</div></div>` };
    }
    return { nav: 'workouts', body: `${head}${switcher}<div class="stack" style="margin-top:8px">${summary}<div>${listBlock}</div></div>` };
}

/** 一筆紀錄卡：點了編輯，⋮ 開動作選單。 */
function logCard(l, fresh = false, flat = false) {
    const archived = exById(l.exId)?.archived;
    return `<div class="log-card ${fresh ? 'fresh' : ''} ${flat ? 'flat' : ''}">
        <span class="type-icon">${icon(RT[l.type].icon, 'sm')}</span>
        <button class="grow" style="border:0;background:none;padding:0;text-align:left" data-action="edit-log" data-id="${l.id}">
            <div class="t"><span class="num">${l.time}</span><span>${l.catName}</span>${archived ? '<span class="badge-muted">已封存</span>' : ''}</div>
            <div class="name"><span>${l.name}</span></div>
            <div class="d">${logDetail(l)}</div>
        </button>
        <span class="kcal">${int(logKcal(l))}<small>kcal</small></span>
        ${flat ? '' : `<button class="menu" data-action="log-menu" data-id="${l.id}" aria-label="更多">${icon('ellipsisVertical', 'sm')}</button>`}
    </div>`;
}

/* ---------- 選擇項目面板 ---------- */

/** 選擇項目面板：搜尋、照片辨識、最近做過、依分類分組、管理入口。 */
function pickerSheet(s, device) {
    const photoBtns = `<div class="photo-btns">
            ${device === 'desktop' ? '' : `<button class="photo-btn" data-action="pick-camera">${icon('camera')}<span>拍照辨識<small>不認得的器材、動作</small></span></button>`}
            <button class="photo-btn" data-action="pick-upload">${icon('imageUp')}<span>上傳照片辨識<small>${device === 'desktop' ? '不認得的器材、動作，交給 AI 辨識' : '從相簿選一張'}</small></span></button>
        </div>`;
    return `<div class="overlay" data-action="close-overlay">
        <div class="sheet tall" data-stop>
            <div class="grab"></div>
            <h3>選擇運動項目</h3>
            <label class="search">${icon('search', 'sm')}<input data-input="query" placeholder="搜尋項目" value="${s.query}" /></label>
            ${photoBtns}
            <div class="sheet-scroll" data-region="picker-list">${pickerList(s)}</div>
            <div class="sheet-foot"><button class="btn block" data-action="nav-manage">${icon('settings2', 'sm')}管理分類與項目</button></div>
        </div>
    </div>`;
}

/** 面板裡的清單（搜尋時只重繪這一塊）。 */
function pickerList(s) {
    const q = s.query.trim();
    const row = (ex) => {
        const cat = catOf(ex);
        const name = q ? ex.name.replace(q, `<mark>${q}</mark>`) : ex.name;
        return `<button class="ex-row" data-action="pick-ex" data-id="${ex.id}">
            <div class="grow"><div class="nm">${name}</div><div class="sub">${q ? `${cat.name} · ` : ''}${exParams(ex, cat.type)}</div></div>
            ${q ? rtBadge(cat.type) : ''}${icon('chevronRight', 'sm')}
        </button>`;
    };

    if (q) {
        const found = data.exercises.filter((e) => !e.archived && e.name.includes(q));
        if (found.length) return `<div class="sec-title">搜尋結果<span class="count">${found.length}</span></div><div class="ex-list">${found.map(row).join('')}</div>`;
        return `<div class="empty" style="margin-top:14px">找不到「${q}」<button class="btn primary" style="height:40px" data-action="new-ex-query">${icon('plus', 'sm')}新增項目「${q}」</button></div>`;
    }

    const recentIds = [];
    [...data.logs]
        .sort((a, b) => (b.date + b.time).localeCompare(a.date + a.time))
        .forEach((l) => {
            const ex = exById(l.exId);
            if (ex && !ex.archived && !recentIds.includes(ex.id)) recentIds.push(ex.id);
        });
    const recent = recentIds.length
        ? `<div class="sec-title">${icon('history', 'xs')}最近做過</div><div class="recent">${recentIds
              .slice(0, 5)
              .map((id) => `<button class="chip-btn" data-action="pick-ex" data-id="${id}">${exById(id).name}</button>`)
              .join('')}</div>`
        : '';

    const groups = data.categories
        .map((c) => {
            const list = activeIn(c.id);
            return `<div class="sec-title">${c.name}${rtBadge(c.type)}<span class="count">${list.length} 項</span></div>
            <div class="ex-list">${list.length ? list.map(row).join('') : '<div class="ex-row"><span class="muted small">這個分類還沒有項目</span></div>'}</div>`;
        })
        .join('');
    return recent + groups;
}

/* ---------- 記錄頁 ---------- */

/** 新增紀錄的初始值。 */
function newLog(exId, date = state.date) {
    const ex = exById(exId);
    const cat = catOf(ex);
    return {
        mode: 'new',
        exId,
        date,
        time: date === TODAY ? NOW : '19:00',
        type: cat.type,
        min: '',
        km: '',
        sets: [{ reps: '', wt: '' }],
        note: '',
    };
}

/** 編輯既有紀錄的初始值。 */
function editLog(l) {
    return {
        mode: 'edit',
        id: l.id,
        exId: l.exId,
        date: l.date,
        time: l.time,
        type: l.type,
        min: l.min ?? '',
        km: l.km ?? '',
        sets: (l.sets ?? []).map((x) => ({ ...x })),
        note: l.note ?? '',
    };
}

/** 記錄頁用的參數：新增用項目目前設定，編輯用紀錄快照（體重以紀錄日重算）。 */
function logParams(lg) {
    if (lg.mode === 'edit') {
        const l = data.logs.find((x) => x.id === lg.id);
        return { name: l.name, catName: l.catName, type: l.type, met: l.met, sec: l.sec, rest: l.rest, w: weightOn(lg.date) };
    }
    const ex = exById(lg.exId);
    const cat = catOf(ex);
    return { name: ex.name, catName: cat.name, type: cat.type, met: ex.met, sec: ex.sec, rest: ex.rest, w: weightOn(lg.date) };
}

/** 這個項目上一次的逐組紀錄（帶入上次紀錄用）。 */
function lastLogOf(exId, excludeId) {
    return data.logs
        .filter((l) => l.exId === exId && l.id !== excludeId && l.type === 'sets')
        .sort((a, b) => (b.date + b.time).localeCompare(a.date + a.time))[0];
}

/** 記錄頁目前的估算值。 */
function logLive(lg) {
    const p = logParams(lg);
    const w = p.w[1];
    if (p.type === 'duration') {
        const min = Number(lg.min) || 0;
        return { kcal: (p.met * w * min) / 60, min, vol: 0 };
    }
    const secs = setsSeconds(lg.sets, p.sec, p.rest);
    return { kcal: (p.met * w * secs) / 3600, min: secs / 60, vol: setsVolume(lg.sets) };
}

/** 記錄頁：填時長或逐組，底部即時顯示估算消耗。 */
function logView(s, device) {
    const lg = s.log;
    const p = logParams(lg);
    const ex = exById(lg.exId);
    const live = logLive(lg);
    const isEdit = lg.mode === 'edit';

    const head = pageHead(
        p.name,
        'back-log',
        isEdit ? `<button class="back" style="margin:0 -10px 0 0" data-action="ask-del-log" aria-label="刪除這筆紀錄">${icon('trash2')}</button>` : '',
    );
    const sub = `<div class="ex-head">${rtBadge(p.type)}<span>${p.catName}</span><span>·</span><span>MET ${metFmt(p.met)}</span>${ex?.archived ? '<span class="badge-muted">已封存</span>' : ''}</div>`;

    const when = `<div class="card"><div class="grid2">
            <div class="field"><span class="label">日期</span><div class="unit-input"><span>${lg.date.replaceAll('-', '/')}</span><span>${icon('calendar', 'sm')}</span></div></div>
            <div class="field"><span class="label">時間</span><div class="unit-input"><span>${lg.time}</span><span>${icon('clock', 'sm')}</span></div></div>
        </div></div>`;

    let main;
    if (p.type === 'duration') {
        const chips = [15, 30, 45, 60].map((m) => `<button class="chip-btn ${Number(lg.min) === m ? 'on' : ''}" data-action="set-min" data-v="${m}">${m} 分</button>`).join('');
        main = `<div class="card">
            <div class="field"><label for="min-${device}">時長<span class="req">*</span></label>
                <div class="unit-input"><input id="min-${device}" data-input="min" inputmode="decimal" placeholder="0" value="${lg.min}" /><span class="u">分鐘</span></div>
                <div class="chips">${chips}</div>
            </div>
            <div class="field"><label for="km-${device}">距離</label>
                <div class="unit-input"><input id="km-${device}" data-input="km" inputmode="decimal" placeholder="例如 5.2" value="${lg.km}" /><span class="u">km</span></div>
                <div class="field-help">只記錄，不影響熱量。</div>
            </div>
        </div>`;
    } else {
        const last = isEdit ? null : lastLogOf(lg.exId);
        const lastCard = last
            ? `<div class="last-card">${icon('history')}<div class="grow"><b>上次 ${shortDate(last.date)} ${last.time}</b><span class="d">${setsSummary(last.sets)}</span></div><button class="mini" data-action="prefill">帶入</button></div>`
            : '';
        const rows = lg.sets
            .map(
                (x, i) => `<div class="set-row">
                <span class="set-no">${i + 1}</span>
                <div class="unit-input"><input data-input="reps" data-i="${i}" inputmode="numeric" placeholder="0" value="${x.reps}" aria-label="第 ${i + 1} 組次數" /><span class="u">下</span></div>
                <div class="unit-input"><input data-input="wt" data-i="${i}" inputmode="decimal" placeholder="徒手" value="${x.wt}" aria-label="第 ${i + 1} 組重量" /><span class="u">kg</span></div>
                <button class="del" data-action="del-set" data-i="${i}" aria-label="刪除第 ${i + 1} 組" ${lg.sets.length <= 1 ? 'disabled' : ''}>${icon('x', 'sm')}</button>
            </div>`,
            )
            .join('');
        const full = lg.sets.length >= MAX_SETS;
        main = `${lastCard}<div class="card">
            <h4>${icon('listOrdered', 'sm')}組數 <span class="quota" style="padding:1px 8px">${lg.sets.length}</span></h4>
            <div class="set-head"><span style="text-align:center">組</span><span>次數<span style="color:hsl(var(--brand))">*</span></span><span>重量</span><span></span></div>
            ${rows}
            <div class="set-actions">
                <button class="btn" data-action="add-set" ${full ? 'disabled' : ''}>${icon('plus', 'sm')}新增一組</button>
                <button class="btn" data-action="copy-set" ${full ? 'disabled' : ''}>${icon('copy', 'sm')}複製上一組</button>
            </div>
            ${full ? `<div class="field-help">最多 ${MAX_SETS} 組。</div>` : ''}
        </div>`;
    }

    const note = `<div class="card field"><label for="note-${device}">備註</label><textarea id="note-${device}" class="textarea" data-input="note" rows="2" maxlength="500" placeholder="例如：間歇、今天狀況不錯">${lg.note}</textarea></div>`;
    const [wDate, w] = p.w;
    const calc =
        p.type === 'sets'
            ? `以 ${one(w)} kg（${md(wDate)} 體重）、MET ${metFmt(p.met)} 計算；時間 = 次數 × ${p.sec} 秒 ＋ 組間休息 ${p.rest} 秒。重量只算訓練量，不影響熱量。`
            : `以 ${one(w)} kg（${md(wDate)} 體重）、MET ${metFmt(p.met)} 計算。`;
    const calcNote = `<div class="calc-note">${icon('info', 'xs')}<span>${isEdit ? '編輯舊紀錄以紀錄日的體重重新計算。' : ''}${calc}</span></div>`;

    const sub2 =
        p.type === 'sets'
            ? `<div class="sub">訓練量 <span data-live="vol">${int(live.vol)}</span> kg · 約 <span data-live="min">${one(live.min)}</span> 分鐘</div>`
            : '';
    const bar = `<div class="sticky-bar"><div class="est"><small>預估消耗</small><b>約 <span data-live="kcal">${int(live.kcal)}</span> kcal</b>${sub2}</div><button class="btn primary" data-action="save-log">儲存</button></div>`;

    const body = `${head}${sub}<div class="stack">${when}${main}${note}${calcNote}</div>`;
    return { nav: 'workouts', body, bar, width: 'mid' };
}

/* ---------- 分類與項目管理 ---------- */

/** 管理頁：分類分組、項目、封存區。 */
function manageView(s) {
    const head = pageHead('分類與項目', 'nav-workouts', `<button class="btn primary" style="height:40px" data-action="new-ex">${icon('plus', 'sm')}新增項目</button>`);
    const row = (ex, type) => `<button class="ex-row" data-action="edit-ex" data-id="${ex.id}">
            <div class="grow"><div class="nm">${ex.name}</div><div class="sub">${exParams(ex, type)}</div></div>${icon('chevronRight', 'sm')}
        </button>`;
    const groups = data.categories
        .map((c) => {
            const list = activeIn(c.id);
            return `<div class="cat-head"><b>${c.name}</b>${rtBadge(c.type)}<span class="count">${list.length} 項</span>
                <button class="menu" data-action="cat-menu" data-id="${c.id}" aria-label="${c.name}的選項">${icon('ellipsisVertical', 'sm')}</button></div>
            <div class="ex-list">${list.map((ex) => row(ex, c.type)).join('')}
                <button class="add-in" data-action="new-ex" data-cat="${c.id}">${icon('plus', 'sm')}新增到「${c.name}」</button></div>`;
        })
        .join('');

    const archived = data.exercises.filter((e) => e.archived);
    const archivedBlock = archived.length
        ? `<button class="archived-toggle" data-action="toggle-archived">${icon('archive', 'sm')}已封存（${archived.length}）${icon(s.showArchived ? 'chevronUp' : 'chevronDown', 'sm')}</button>
           ${
               s.showArchived
                   ? `<div class="field-help" style="margin:0 4px 6px">封存的項目不會出現在選擇清單，歷史紀錄照常顯示。</div>
           <div class="ex-list">${archived
               .map((ex) => {
                   const cat = catOf(ex);
                   return `<div class="ex-row archived"><div class="grow"><div class="nm">${ex.name}</div><div class="sub">${cat ? cat.name : '沒有分類'} · ${logCount(ex.id)} 筆紀錄</div></div>
                    <button class="mini" data-action="restore-ex" data-id="${ex.id}">${icon('archiveRestore', 'xs')}還原</button></div>`;
               })
               .join('')}</div>`
                   : ''
           }`
        : '';

    return {
        nav: 'workouts',
        width: 'mid',
        body: `${head}${groups}<button class="dashed-btn" data-action="cat-new">${icon('folderPlus', 'sm')}新增分類</button>${archivedBlock}`,
    };
}

/** 新增／改名分類面板。 */
function catSheet(s) {
    const f = s.catForm;
    const isRename = f.mode === 'rename';
    const card = (type) => {
        const on = f.type === type;
        const locked = isRename && !on;
        return `<button class="radio-card ${on ? 'on' : ''} ${locked ? 'locked' : ''}" data-action="cat-type" data-type="${type}" ${isRename ? 'disabled' : ''}>
            <span class="top">${icon(RT[type].icon, 'sm')}${RT[type].label}${isRename && on ? icon('lock', 'xs', 'muted') : ''}</span>
            <small>${RT[type].hint}</small>
        </button>`;
    };
    return `<div class="overlay" data-action="close-overlay">
        <div class="sheet" data-stop>
            <div class="grab"></div>
            <h3>${isRename ? '分類改名' : '新增分類'}</h3>
            <div class="field"><label for="cat-name">名稱<span class="req">*</span></label>
                <div class="unit-input"><input id="cat-name" data-input="cat-name" maxlength="30" placeholder="例如：核心、伸展" value="${f.name}" /></div></div>
            <div class="field"><span class="label">記錄方式<span class="req" style="color:hsl(var(--brand))">*</span></span>
                <div class="radio-cards">${card('duration')}${card('sets')}</div>
                <div class="field-help">${isRename ? '記錄方式建立後不能改。' : '建立後不能改。分類底下的項目都用這種方式記錄。'}</div>
            </div>
            <button class="btn primary block" style="margin-top:16px" data-action="cat-save">${isRename ? '儲存' : '建立分類'}</button>
        </div>
    </div>`;
}

/** 分類的動作選單。 */
function catMenu(s) {
    const c = catById(s.menuCat);
    const i = data.categories.indexOf(c);
    return `<div class="overlay" data-action="close-overlay">
        <div class="sheet" data-stop>
            <div class="grab"></div>
            <h3>${c.name}</h3>
            <p class="desc">${RT[c.type].label} · ${activeIn(c.id).length} 個項目</p>
            <div class="menu-list">
                <button data-action="cat-rename">${icon('pencil')}改名</button>
                <button data-action="cat-up" ${i === 0 ? 'disabled' : ''}>${icon('arrowUp')}往上移</button>
                <button data-action="cat-down" ${i === data.categories.length - 1 ? 'disabled' : ''}>${icon('arrowDown')}往下移</button>
                <button class="danger" data-action="cat-delete">${icon('trash2')}刪除分類</button>
            </div>
        </div>
    </div>`;
}

/* ---------- 新增／編輯項目 ---------- */

/** 新增項目的初始值。 */
function newForm(patch = {}) {
    const cat = catById(patch.cat ?? data.categories[0].id);
    return { mode: 'new', name: '', cat: cat.id, met: cat.type === 'sets' ? '5.0' : '', sec: 3, rest: 90, desc: '', next: 'manage', ...patch };
}

/** 表單目前選的分類記錄方式。 */
function formType(f) {
    return f.cat === 'new' ? f.newCatType : catById(f.cat).type;
}

/** 項目表單：名稱、分類、MET、（逐組）秒數、說明，即時估算。 */
function formView(s, device) {
    const f = s.form;
    const type = formType(f);
    const isEdit = f.mode === 'edit';
    const fromId = f.mode === 'identify';
    const title = isEdit ? '編輯項目' : fromId ? '建立項目' : '新增項目';
    const head = pageHead(title, 'back-form');

    const summary = fromId
        ? `<div class="card"><div class="summary">
            <div class="thumb"><img src="${s.idf.photo}" alt="辨識的照片" /></div>
            <div><b>${icon('sparkles', 'sm')}來自照片辨識</b><p>器材：${f.equipment} · ${confPill(f.confidence)}</p><p>內容都可以修改，建立後直接開始記錄。</p></div>
        </div></div>`
        : '';

    // 分類選項：編輯時只能選同記錄方式；照片辨識建議的新分類排在最前面
    const cats = isEdit ? data.categories.filter((c) => c.type === type) : data.categories;
    const newOpt = fromId && f.newCatName ? `<option value="new" ${f.cat === 'new' ? 'selected' : ''}>＋ 新建分類「${f.newCatName}」（${RT[f.newCatType].label}）</option>` : '';
    const options =
        newOpt +
        cats
            .filter((c) => !fromId || c.type === f.newCatType)
            .map((c) => `<option value="${c.id}" ${f.cat === c.id ? 'selected' : ''}>${c.name}（${RT[c.type].label}）</option>`)
            .join('');
    const catHelp = isEdit
        ? `只能移到同為「${RT[type].label}」的分類。`
        : fromId && f.newCatName
          ? `存檔時一併建立分類；也可以改放進現有的「${RT[f.newCatType].label}」分類。`
          : `記錄方式跟著分類：${catById(f.cat)?.name ?? ''}是「${RT[type].label}」。`;

    const est = exEstimate(Number(f.met) || 0, type, f.sec, f.rest);
    const setsFields =
        type === 'sets'
            ? `<div class="grid2">
                <div class="field"><label for="sec-${device}">每下秒數<span class="req">*</span></label><div class="unit-input"><input id="sec-${device}" data-input="f-sec" inputmode="numeric" value="${f.sec}" /><span class="u">秒</span></div></div>
                <div class="field"><label for="rest-${device}">組間休息<span class="req">*</span></label><div class="unit-input"><input id="rest-${device}" data-input="f-rest" inputmode="numeric" value="${f.rest}" /><span class="u">秒</span></div></div>
            </div><div class="field-help">用來估算重訓時間：次數 × 每下秒數 ＋ 組間休息。</div>`
            : '';

    const fields = `<div class="card">
        <div class="field"><label for="name-${device}">名稱<span class="req">*</span></label><div class="unit-input"><input id="name-${device}" data-input="f-name" maxlength="50" placeholder="例如：啞鈴臥推" value="${f.name}" /></div></div>
        <div class="field"><label for="cat-${device}">分類<span class="req">*</span></label>
            <div class="select"><select id="cat-${device}" data-input="f-cat">${options}</select>${icon('chevronDown', 'sm')}</div>
            <div class="field-help">${catHelp}</div>
        </div>
        <div class="field"><label for="met-${device}">MET<span class="req">*</span></label><div class="unit-input"><input id="met-${device}" data-input="f-met" inputmode="decimal" placeholder="例如 7.0" value="${f.met}" /></div>
            <div class="field-help">運動強度係數。參考：快走 4.3、慢跑 8～10、飛輪 7、重訓 3.5～6。</div></div>
        ${setsFields}
        <div class="field"><label for="desc-${device}">說明</label><textarea id="desc-${device}" class="textarea" data-input="f-desc" maxlength="300" rows="2" placeholder="器材怎麼用、練哪裡">${f.desc}</textarea>
            <div class="counter"><span data-counter>${f.desc.length}</span>/300</div></div>
    </div>`;

    const estimate = `<div class="estimate">${icon('flame')}<div>以你目前體重 ${one(est.w)} kg，<span data-live="est-lead">${est.lead}</span>約 <b data-live="est">${int(est.kcal)}</b> kcal<small>MET × 體重 × 時數；實際以每筆紀錄計算。</small></div></div>`;

    let danger = '';
    if (isEdit) {
        const n = logCount(f.id);
        danger = `<button class="link-btn" style="color:hsl(var(--destructive));margin:4px 4px 0" data-action="ask-del-ex">${icon(n ? 'archive' : 'trash2', 'sm')}${n ? '封存項目' : '刪除項目'}</button>`;
    }

    const label = f.next === 'log' ? (fromId ? '建立並開始記錄' : '儲存並開始記錄') : '儲存';
    const bar = `<div class="sticky-bar"><button class="btn primary block" data-action="save-form">${label}</button></div>`;
    return { nav: 'workouts', width: 'mid', body: `${head}<div class="stack">${summary}${fields}${estimate}${danger}</div>`, bar };
}

/* ---------- 照片辨識 ---------- */

/** 辨識頁：照片、補充說明、剩餘次數與動作（沿用 M4 分析頁）。 */
function identifyView(s, device) {
    const f = s.idf;
    const busy = f.phase === 'identifying';
    const out = s.quota <= 0 && !busy;
    const isCamera = f.source === 'camera';
    const swapLabel = isCamera ? '重拍' : '換一張';
    const swap = busy ? '' : `<button class="swap" data-action="swap">${icon(isCamera ? 'camera' : 'imageUp', 'xs')}${swapLabel}</button>`;

    let status = '';
    if (busy) {
        status = `<div class="card analyzing-text">${icon('loaderCircle', 'lg', 'spin')}<div><b>辨識中，大約 5～20 秒</b><div class="xsmall muted">請停留在這個畫面，離開會中斷辨識。</div></div></div>`;
    } else if (f.phase === 'error') {
        const e = ERRORS[f.error];
        status = `<div class="callout error">${icon('circleAlert')}<div><b>${e.title}</b><p>${e.message}</p></div></div>`;
    } else if (out) {
        status = `<div class="callout error">${icon('circleAlert')}<div><b>今天的 ${DAILY_LIMIT} 次已用完</b><p>明天再試，或手動新增項目。</p></div></div>`;
    }

    const photo = `<div class="photo ${busy ? 'scanning' : ''}"><img src="${f.photo}" alt="器材或動作的照片" />${busy ? '<div class="beam"></div>' : ''}${swap}</div>`;
    const hint = `<div class="field">
            <label for="hint-${device}">補充說明</label>
            <textarea id="hint-${device}" class="textarea" data-input="hint" maxlength="200" rows="2" placeholder="例如：腿部的機器、想練背" ${busy ? 'disabled' : ''}>${f.hint}</textarea>
            <div class="counter"><span data-counter>${f.hint.length}</span>/200</div>
        </div>`;

    let actions;
    if (busy) {
        actions = `<button class="btn primary block" disabled>${icon('loaderCircle', 'sm', 'spin')}辨識中…</button><button class="btn ghost" data-action="cancel">取消</button>`;
    } else if (f.phase === 'error') {
        const first =
            f.error === 'not_exercise'
                ? `<button class="btn primary" data-action="swap">${icon(isCamera ? 'camera' : 'imageUp', 'sm')}${swapLabel}</button>`
                : `<button class="btn primary" data-action="start" ${s.quota <= 0 ? 'disabled' : ''}>${icon('refreshCw', 'sm')}重新辨識</button>`;
        actions = `<div class="btn-row" style="flex:1">${first}<button class="btn" data-action="manual-ex">${icon('pencil', 'sm')}手動新增</button></div>`;
    } else if (out) {
        actions = `<button class="btn block" data-action="manual-ex">${icon('pencil', 'sm')}手動新增項目</button>`;
    } else {
        actions = `<button class="btn primary block" data-action="start">${icon('scanSearch', 'sm')}開始辨識</button>`;
    }

    const head = pageHead('辨識器材／動作', 'back-identify');
    const intro = `<div class="xsmall muted" style="margin:-6px 0 12px">AI 會告訴你這是什麼、可以做哪些動作，選一個建立項目。照片不會儲存。</div>`;

    if (device === 'desktop') {
        return {
            nav: 'workouts',
            body: `${head}${intro}<div class="cols"><div>${photo}</div><div class="stack">${status}<div class="card stack">${hint}<div>${quotaChip(s.quota)}</div></div>
                <div class="btn-row">${actions.replace('btn primary block', 'btn primary').replace('btn block', 'btn')}</div></div></div>`,
        };
    }
    return {
        nav: 'workouts',
        body: `${head}${intro}<div class="stack">${photo}${status}<div class="card stack">${hint}<div>${quotaChip(s.quota)}</div></div></div>`,
        bar: `<div class="sticky-bar">${actions}</div>`,
    };
}

/** 候選是否已是使用者的項目（名稱相同、未封存）。 */
const ownedOf = (c) => data.exercises.find((e) => !e.archived && e.name === c.name);

/** 建議分類對應：同名且同記錄方式的現有分類，沒有就新建。 */
const catMatch = (c) => data.categories.find((x) => x.name === c.category && x.type === c.type);

/** 辨識結果：候選卡片（最多 3 個）。 */
function candidatesView(s) {
    const sample = SAMPLES[s.idf.sample];
    const head = pageHead('辨識結果', 'back-cand');
    const summary = `<div class="card"><div class="summary">
            <div class="thumb"><img src="${s.idf.photo}" alt="辨識的照片" /></div>
            <div><b>${icon('sparkles', 'sm')}看起來是：${sample.equipment}</b><p>找到 ${sample.candidates.length} 個可以做的動作，選一個建立項目。</p></div>
        </div>${s.idf.hint ? `<div class="quote">補充說明：${s.idf.hint}</div>` : ''}</div>`;

    const cards = sample.candidates
        .map((c, i) => {
            const owned = ownedOf(c);
            const cat = catMatch(c);
            const facts = [rtBadge(c.type), `<span class="badge-muted">MET ${metFmt(c.met)}</span>`];
            if (c.type === 'sets') facts.push(`<span class="badge-muted">每下 ${c.sec} 秒 · 休息 ${c.rest} 秒</span>`);
            const where = owned
                ? `${icon('circleCheck', 'xs')}在「${catOf(owned).name}」`
                : cat
                  ? `${icon('folder', 'xs')}放進「${cat.name}」`
                  : `${icon('folderPlus', 'xs')}新建分類「${c.category}」`;
            return `<button class="cand ${owned ? 'owned' : ''}" data-action="pick-cand" data-i="${i}">
                <div class="top"><b>${c.name}</b>${owned ? '<span class="badge-soft">已有這個項目</span>' : ''}${confPill(c.confidence)}</div>
                <div class="eq">器材：${c.equipment}</div>
                <p>${c.desc}</p>
                <div class="facts">${facts.join('')}</div>
                <div class="foot"><span class="where">${where}</span><span class="go">${owned ? '直接記錄' : '選這個'}${icon('chevronRight', 'sm')}</span></div>
            </button>`;
        })
        .join('');

    const manual = `<button class="btn block" style="margin-top:12px" data-action="manual-ex">${icon('pencil', 'sm')}都不對，手動新增項目</button>`;
    return { nav: 'workouts', width: 'mid', body: `${head}<div class="stack">${summary}<div>${cards}${manual}</div></div>` };
}

/* ---------- 今日頁 ---------- */

/** 今日頁：熱量（含運動）、餐點、喝水（體重＋運動）、體重、運動。 */
function todayView(s, device) {
    const t = dayTotals(TODAY);
    const exKcal = Math.round(t.kcal);
    const target = CALORIE_TARGET + exKcal;
    const remaining = target - MEAL_KCAL;
    const r = 52;
    const c = 2 * Math.PI * r;
    const progress = Math.min(1, MEAL_KCAL / target);

    const targets = `<div class="card">
        <div class="ring-wrap">
            <div class="ring"><svg viewBox="0 0 120 120" aria-hidden="true">
                <circle cx="60" cy="60" r="${r}" fill="none" stroke="hsl(var(--muted))" stroke-width="12"/>
                <circle cx="60" cy="60" r="${r}" fill="none" stroke="hsl(var(--brand))" stroke-width="12" stroke-linecap="round" stroke-dasharray="${c}" stroke-dashoffset="${c * (1 - progress)}"/>
            </svg><div class="c"><b>${int(remaining)}</b><span>剩餘 kcal</span></div></div>
            <div class="t-rows">
                <div class="t-row"><span class="ic">${icon('target', 'sm')}</span><span class="sr-only">熱量目標</span><b>${int(CALORIE_TARGET)}</b><small>kcal</small></div>
                ${exKcal ? `<div class="t-row ex"><span class="ic brand">${icon('flame', 'sm')}</span><span class="sr-only">運動消耗，已加回目標</span><b>+${int(exKcal)}</b><small>kcal</small></div>` : ''}
                <div class="t-row"><span class="ic brand">${icon('utensils', 'sm')}</span><span class="sr-only">已攝取</span><b>${int(MEAL_KCAL)}</b><small>kcal</small></div>
            </div>
        </div>
        ${exKcal ? `<div class="plus-note">${icon('flame', 'xs')}含運動 +${int(exKcal)} kcal</div>` : ''}
        <div style="margin-top:14px">
            <div class="t-row small"><span class="inline-n n-protein">${icon('drumstick', 'xs')}</span><span class="muted xsmall">蛋白質</span><span style="margin-left:auto"><b class="small">${MEAL_PROTEIN}</b><span class="muted"> / ${PROTEIN_TARGET} g</span></span></div>
            <div class="bar"><i style="width:${(MEAL_PROTEIN / PROTEIN_TARGET) * 100}%;background:hsl(var(--protein))"></i></div>
        </div>
    </div>`;

    const meals = `<div class="card"><h4>${icon('utensils', 'sm')}今日餐點<button class="go" aria-label="飲食頁">${icon('chevronRight', 'sm')}</button></h4>
        <div class="log-card flat"><span class="type-icon">${icon('sun', 'sm')}</span><div class="grow"><div class="t">12:31</div><div class="name"><span>滷雞腿、白飯、炒高麗菜</span></div></div><span class="kcal">708<small>kcal</small></span></div>
        <div class="log-card flat"><span class="type-icon">${icon('moon', 'sm')}</span><div class="grow"><div class="t">18:05</div><div class="name"><span>鮭魚飯糰、無糖豆漿</span></div></div><span class="kcal">431<small>kcal</small></span></div>
    </div>`;

    const water = waterBlock(t, true);
    const weight = `<div class="card"><h4>${icon('weight', 'sm')}體重</h4><div class="water-big">70.0<small> kg</small></div><div class="xsmall muted" style="margin-top:4px">11/20 記錄 · 今天還沒量</div></div>`;

    const logs = logsOn(TODAY);
    const exercise = `<div class="card">
        <h4>${icon('dumbbell', 'sm')}今日運動<button class="go" data-action="nav-workouts" aria-label="健身頁">${icon('chevronRight', 'sm')}</button></h4>
        ${
            logs.length
                ? `<div class="stats" style="margin-bottom:4px">
                <div class="stat hl"><div class="k">${icon('flame')}消耗</div><div class="v">${int(t.kcal)}<small>kcal</small></div></div>
                <div class="stat"><div class="k">${icon('timer')}時間</div><div class="v">${int(t.min)}<small>分</small></div></div>
                <div class="stat"><div class="k">${icon('dumbbell')}訓練量</div><div class="v">${t.hasSets ? int(t.vol) : '—'}<small>${t.hasSets ? 'kg' : ''}</small></div></div>
            </div>${logs.map((l) => logCard(l, false, true)).join('')}`
                : '<p class="small muted" style="margin:0">今天還沒有運動紀錄。</p>'
        }
        <button class="btn block" style="margin-top:12px" data-action="open-picker">${icon('plus', 'sm')}記錄運動</button>
    </div>`;

    const head = `<div class="page-head" style="display:block"><h3>今日</h3><div class="small muted">${longDate(TODAY)}</div></div>`;
    if (device === 'desktop') {
        return { nav: 'today', body: `${head}<div class="cols"><div class="stack">${targets}${meals}${exercise}</div><div class="stack">${water}${weight}</div></div>` };
    }
    return { nav: 'today', body: `${head}<div class="stack">${targets}${meals}${exercise}${water}${weight}</div>` };
}

/** 喝水進度與建議量拆解（今日頁卡片與喝水面板共用）。 */
function waterBlock(t, asCard) {
    const base = Math.round(weightOn(TODAY)[1] * ML_PER_KG);
    const extra = exerciseWater(t.min);
    const target = base + extra;
    const pct = Math.min(100, (WATER_TOTAL / target) * 100);
    const inner = `<div style="display:flex;align-items:baseline;justify-content:space-between;gap:8px">
            <div class="water-big">${int(WATER_TOTAL)} <small>/ ${int(target)} ml</small></div><span class="small muted">還差 ${int(target - WATER_TOTAL)} ml</span>
        </div>
        <div class="bar"><i style="width:${pct}%;background:hsl(199 89% 48%)"></i></div>
        <div class="water-split">建議量 = <span class="pill">${icon('weight')}體重 ${int(base)}</span>${extra ? `＋<span class="pill ex">${icon('dumbbell')}運動 ${int(extra)}</span>` : ''} ml</div>
        <div class="quick-row"><span>+250</span><span>+350</span><span>+500</span></div>`;
    if (!asCard) return inner;
    return `<div class="card"><h4>${icon('droplet', 'sm', '')}喝水<button class="go" data-action="open-water" aria-label="喝水紀錄">${icon('chevronRight', 'sm')}</button></h4>${inner}</div>`;
}

/* ---------- 覆蓋層 ---------- */

/** 覆蓋層分派。 */
function overlay(s, device) {
    switch (s.overlay) {
        case 'quick':
            return quickAdd(device);
        case 'picker':
            return pickerSheet(s, device);
        case 'log-menu':
            return logMenu(s);
        case 'log-delete':
            return logDelete(s);
        case 'cat-form':
            return catSheet(s);
        case 'cat-menu':
            return catMenu(s);
        case 'cat-blocked':
            return catBlocked(s);
        case 'cat-delete':
            return catDelete(s);
        case 'ex-delete':
            return exDelete(s);
        case 'leave':
            return dialog('放棄這次辨識？', '辨識結果不會建立項目，照片也不會留下。這次辨識仍算在今天的次數內。', 'leave-cancel', '繼續選擇', 'leave-confirm', '放棄');
        case 'water':
            return waterSheet();
        default:
            return '';
    }
}

/** 確認對話框。 */
function dialog(title, text, cancelAction, cancelLabel, okAction, okLabel, danger = false) {
    return `<div class="overlay center"><div class="dialog">
        <h3>${title}</h3><p>${text}</p>
        <div class="btn-row">${cancelAction ? `<button class="btn" data-action="${cancelAction}">${cancelLabel}</button>` : ''}<button class="btn ${danger ? 'danger' : 'primary'}" data-action="${okAction}">${okLabel}</button></div>
    </div></div>`;
}

/** ＋選單：「健身」開放（原本是即將推出）。 */
function quickAdd(device) {
    const tile = (action, ic, label, cls = '') =>
        `<button data-action="${action}" class="qa-item ${cls}"><span class="ic">${icon(ic, 'lg')}</span>${label}</button>`;
    return `<div class="overlay" data-action="close-overlay">
        <div class="sheet" data-stop>
            <div class="grab"></div>
            <h3>快速新增</h3>
            <div class="qa">
                ${tile('noop', 'pencil', '記錄餐點')}
                ${device === 'desktop' ? '' : tile('noop', 'camera', '拍照')}
                ${tile('noop', 'imageUp', '上傳照片')}
                ${tile('open-water', 'droplet', '喝水')}
                ${tile('noop', 'weight', '體重')}
                ${tile('open-picker', 'dumbbell', '健身', 'new')}
            </div>
        </div>
    </div>`;
}

/** 紀錄的動作選單。 */
function logMenu(s) {
    const l = data.logs.find((x) => x.id === s.menuLog);
    return `<div class="overlay" data-action="close-overlay">
        <div class="sheet" data-stop>
            <div class="grab"></div>
            <h3>${l.name} · ${l.time}</h3>
            <p class="desc">${logDetail(l)} · 約 ${int(logKcal(l))} kcal</p>
            <div class="menu-list">
                <button data-action="edit-log" data-id="${l.id}">${icon('pencil')}編輯</button>
                ${exById(l.exId)?.archived ? '' : `<button data-action="again-log" data-id="${l.id}">${icon('repeat')}再做一次<span class="end">帶入今天</span></button>`}
                <button class="danger" data-action="ask-del-log">${icon('trash2')}刪除</button>
            </div>
        </div>
    </div>`;
}

/** 刪除紀錄確認。 */
function logDelete(s) {
    const l = data.logs.find((x) => x.id === s.menuLog);
    return dialog('刪除這筆紀錄？', `${shortDate(l.date)} ${l.time} ${l.name}，${logDetail(l)}，約 ${int(logKcal(l))} kcal。刪除後無法復原。`, 'close-overlay-btn', '取消', 'del-log', '刪除', true);
}

/** 分類還有項目，不能刪。 */
function catBlocked(s) {
    const c = catById(s.menuCat);
    const n = activeIn(c.id).length;
    return dialog(
        `還不能刪除「${c.name}」`,
        `這個分類還有 ${n} 個項目。先刪除項目，或把項目移到其他「${RT[c.type].label}」分類，再刪除分類。`,
        '',
        '',
        'close-overlay-btn',
        '知道了',
    );
}

/** 刪除空分類確認（封存項目會變成沒有分類，紀錄不受影響）。 */
function catDelete(s) {
    const c = catById(s.menuCat);
    const archived = data.exercises.filter((e) => e.cat === c.id && e.archived).length;
    return dialog(
        `刪除「${c.name}」？`,
        archived ? `分類裡只剩 ${archived} 個封存項目，刪除後它們會變成沒有分類；歷史紀錄不受影響。` : '這個分類沒有項目，刪除後無法復原。',
        'close-overlay-btn',
        '取消',
        'cat-delete-confirm',
        '刪除',
        true,
    );
}

/** 刪除或封存項目確認。 */
function exDelete(s) {
    const ex = exById(s.delEx);
    const n = logCount(ex.id);
    if (n) {
        return dialog(
            `封存「${ex.name}」？`,
            `這個項目有 ${n} 筆紀錄，所以改成封存：不會出現在選擇清單，歷史紀錄照常顯示，之後可以在「已封存」還原。`,
            'close-overlay-btn',
            '取消',
            'del-ex-confirm',
            '封存',
        );
    }
    return dialog(`刪除「${ex.name}」？`, '這個項目還沒有紀錄，刪除後無法復原。', 'close-overlay-btn', '取消', 'del-ex-confirm', '刪除', true);
}

/** 喝水面板：建議量拆成體重＋運動。 */
function waterSheet() {
    const t = dayTotals(TODAY);
    const w = weightOn(TODAY)[1];
    return `<div class="overlay" data-action="close-overlay">
        <div class="sheet" data-stop>
            <div class="grab"></div>
            <h3>喝水</h3>
            <p class="desc">今天 · 建議量 = 體重 ${one(w)} kg × ${ML_PER_KG} ml${t.min ? ` ＋ 運動 ${int(t.min)} 分鐘 × ${ML_PER_HOUR} ml／小時` : ''}</p>
            ${waterBlock(t, false)}
            <div class="sec-title" style="margin-top:18px">今天的紀錄</div>
            <div class="ex-list"><div class="ex-row"><span class="grow muted num">08:30</span><b class="num">500 ml</b></div><div class="ex-row"><span class="grow muted num">13:10</span><b class="num">1,000 ml</b></div></div>
        </div>
    </div>`;
}

/* ---------- 流程操作 ---------- */

/** 重新渲染流程區。 */
function renderFlow() {
    document.getElementById('flow').innerHTML = frame(tool.device, state);
}

/** 顯示 toast，2.5 秒後消失。 */
function toast(text) {
    state.toast = text;
    setTimeout(() => {
        state.toast = '';
        renderFlow();
    }, 2500);
}

/** 進入記錄頁。 */
function openLog(exId) {
    Object.assign(state, { screen: 'log', overlay: null, query: '', log: newLog(exId) });
}

/** 儲存紀錄：快照目前項目設定與紀錄日體重。 */
function saveLog() {
    const lg = state.log;
    const sets = lg.sets.filter((x) => Number(x.reps) > 0).map((x) => ({ reps: Number(x.reps), wt: x.wt === '' ? '' : Number(x.wt) }));
    if (lg.type === 'duration' && !(Number(lg.min) > 0)) return alertField('min');
    if (lg.type === 'sets' && !sets.length) return alertField('reps');

    const fields = { min: lg.type === 'duration' ? Number(lg.min) : undefined, km: lg.km === '' ? '' : Number(lg.km), sets: lg.type === 'sets' ? sets : undefined, note: lg.note };
    let id;
    if (lg.mode === 'edit') {
        const l = data.logs.find((x) => x.id === lg.id);
        Object.assign(l, fields, { w: weightOn(l.date)[1] });
        id = l.id;
        toast('已更新紀錄');
    } else {
        id = ++data.nextId;
        data.logs.push({ id, ...snapshot(data, lg.exId, lg.date), date: lg.date, time: lg.time, ...fields });
        toast(`已記錄 ${exById(lg.exId).name}`);
    }
    Object.assign(state, { screen: 'workouts', date: lg.date, fresh: id, log: null });
}

/** 必填欄位沒填：標紅並聚焦。 */
function alertField(name) {
    const input = document.querySelector(`#flow [data-input="${name}"]`);
    if (input) {
        input.closest('.unit-input').classList.add('invalid');
        input.focus();
    }
    return false;
}

/** 更新記錄頁與項目表單的即時數字（不重繪，保留游標）。 */
function updateLive() {
    const root = document.getElementById('flow');
    const set = (key, text) => root.querySelectorAll(`[data-live="${key}"]`).forEach((el) => (el.textContent = text));
    if (state.screen === 'log' && state.log) {
        const live = logLive(state.log);
        set('kcal', int(live.kcal));
        set('vol', int(live.vol));
        set('min', one(live.min));
    }
    if (state.screen === 'form' && state.form) {
        const f = state.form;
        const est = exEstimate(Number(f.met) || 0, formType(f), f.sec, f.rest);
        set('est', int(est.kcal));
    }
}

/** 儲存項目（必要時一併建立辨識建議的分類）。 */
function saveForm() {
    const f = state.form;
    if (!f.name.trim()) return alertField('f-name');
    if (!(Number(f.met) > 0)) return alertField('f-met');
    let catId = f.cat;
    if (catId === 'new') {
        catId = ++data.nextId;
        data.categories.push({ id: catId, name: f.newCatName, type: f.newCatType });
    }
    const type = catById(catId).type;
    const values = {
        cat: catId,
        name: f.name.trim(),
        met: Number(f.met),
        sec: type === 'sets' ? Number(f.sec) : undefined,
        rest: type === 'sets' ? Number(f.rest) : undefined,
        desc: f.desc,
    };
    let id = f.id;
    if (f.mode === 'edit') {
        Object.assign(exById(id), values);
    } else {
        id = ++data.nextId;
        data.exercises.push({ id, ...values });
    }
    if (f.next === 'log') {
        openLog(id);
        toast(f.mode === 'identify' ? `已建立「${values.name}」` : '已新增項目');
    } else {
        Object.assign(state, { screen: 'manage', form: null });
        toast(f.mode === 'edit' ? '已更新項目' : '已新增項目');
    }
}

/** 進入照片辨識頁。 */
function openIdentify(source) {
    const sample = tool.result === 'plank' ? 'plank' : 'smith';
    Object.assign(state, {
        screen: 'identify',
        overlay: null,
        idf: { source, sample, photo: SAMPLES[sample].photo, phase: 'ready', error: null, hint: '' },
    });
}

/** 模擬送出辨識：先扣次數，2 秒後依工具列回傳結果。 */
function startIdentify() {
    if (state.quota <= 0) return;
    state.quota -= 1;
    Object.assign(state.idf, { phase: 'identifying', error: null });
    timer = setTimeout(() => {
        if (tool.result === 'smith' || tool.result === 'plank') {
            Object.assign(state, { screen: 'candidates' });
            state.idf.phase = 'ready';
        } else {
            Object.assign(state.idf, { phase: 'error', error: tool.result });
        }
        renderFlow();
    }, 2200);
}

/** 選了候選：已有的直接記錄，否則帶入新增項目表單。 */
function pickCandidate(i) {
    const c = SAMPLES[state.idf.sample].candidates[i];
    const owned = ownedOf(c);
    if (owned) {
        openLog(owned.id);
        return;
    }
    const cat = catMatch(c);
    state.form = {
        mode: 'identify',
        name: c.name,
        cat: cat ? cat.id : 'new',
        newCatName: cat ? '' : c.category,
        newCatType: c.type,
        met: metFmt(c.met),
        sec: c.sec ?? 3,
        rest: c.rest ?? 90,
        desc: c.desc,
        equipment: c.equipment,
        confidence: c.confidence,
        next: 'log',
    };
    state.screen = 'form';
}

const ACTIONS = {
    'open-quick': () => (state.overlay = 'quick'),
    'close-overlay': () => (state.overlay = null),
    'close-overlay-btn': () => (state.overlay = null),
    noop: () => (state.overlay = null),
    'nav-today': () => Object.assign(state, { screen: 'today', overlay: null }),
    'nav-meals': () => Object.assign(state, { screen: 'meals', overlay: null }),
    'nav-me': () => Object.assign(state, { screen: 'me', overlay: null }),
    'nav-workouts': () => Object.assign(state, { screen: 'workouts', overlay: null, fresh: null }),
    'nav-manage': () => Object.assign(state, { screen: 'manage', overlay: null, query: '' }),
    'day-prev': () => Object.assign(state, { date: addDays(state.date, -1), fresh: null }),
    'day-next': () => state.date < TODAY && Object.assign(state, { date: addDays(state.date, 1), fresh: null }),
    'day-today': () => Object.assign(state, { date: TODAY, fresh: null }),
    'open-picker': () => Object.assign(state, { overlay: 'picker', query: '' }),
    'open-water': () => (state.overlay = 'water'),
    'pick-ex': (el) => openLog(Number(el.dataset.id)),
    'pick-camera': () => openIdentify('camera'),
    'pick-upload': () => openIdentify('upload'),
    'new-ex-query': () => Object.assign(state, { screen: 'form', overlay: null, form: newForm({ name: state.query.trim(), next: 'log' }), query: '' }),

    // 記錄頁
    'back-log': () => Object.assign(state, { screen: 'workouts', log: null }),
    'set-min': (el) => (state.log.min = Number(el.dataset.v)),
    'add-set': () => state.log.sets.length < MAX_SETS && state.log.sets.push({ reps: '', wt: '' }),
    'copy-set': () => {
        const last = state.log.sets[state.log.sets.length - 1];
        if (state.log.sets.length < MAX_SETS) state.log.sets.push({ ...last });
    },
    'del-set': (el) => state.log.sets.length > 1 && state.log.sets.splice(Number(el.dataset.i), 1),
    prefill: () => {
        const last = lastLogOf(state.log.exId);
        state.log.sets = last.sets.map((x) => ({ ...x }));
        toast(`已帶入 ${shortDate(last.date)}的 ${last.sets.length} 組`);
    },
    'save-log': saveLog,
    'edit-log': (el) => {
        const l = data.logs.find((x) => x.id === Number(el.dataset.id));
        Object.assign(state, { screen: 'log', overlay: null, log: editLog(l) });
    },
    'again-log': (el) => {
        const l = data.logs.find((x) => x.id === Number(el.dataset.id));
        const lg = newLog(l.exId, TODAY);
        if (l.type === 'sets') lg.sets = l.sets.map((x) => ({ ...x }));
        else Object.assign(lg, { min: l.min, km: l.km });
        Object.assign(state, { screen: 'log', overlay: null, log: lg });
    },
    'log-menu': (el) => Object.assign(state, { overlay: 'log-menu', menuLog: Number(el.dataset.id) }),
    'ask-del-log': () => {
        if (state.screen === 'log') state.menuLog = state.log.id;
        state.overlay = 'log-delete';
    },
    'del-log': () => {
        data.logs = data.logs.filter((l) => l.id !== state.menuLog);
        Object.assign(state, { screen: 'workouts', overlay: null, log: null });
        toast('已刪除紀錄');
    },

    // 管理頁
    'toggle-archived': () => (state.showArchived = !state.showArchived),
    'restore-ex': (el) => {
        const ex = exById(Number(el.dataset.id));
        ex.archived = false;
        if (!catById(ex.cat)) ex.cat = data.categories.find((c) => c.type === 'duration')?.id ?? data.categories[0].id;
        toast(`已還原「${ex.name}」`);
    },
    'cat-new': () => Object.assign(state, { overlay: 'cat-form', catForm: { mode: 'new', name: '', type: 'duration' } }),
    'cat-type': (el) => (state.catForm.type = el.dataset.type),
    'cat-save': () => {
        const f = state.catForm;
        if (!f.name.trim()) return alertField('cat-name');
        if (f.mode === 'rename') catById(state.menuCat).name = f.name.trim();
        else data.categories.push({ id: ++data.nextId, name: f.name.trim(), type: f.type });
        state.overlay = null;
        toast(f.mode === 'rename' ? '已改名' : `已新增分類「${f.name.trim()}」`);
    },
    'cat-menu': (el) => Object.assign(state, { overlay: 'cat-menu', menuCat: Number(el.dataset.id) }),
    'cat-rename': () => {
        const c = catById(state.menuCat);
        Object.assign(state, { overlay: 'cat-form', catForm: { mode: 'rename', name: c.name, type: c.type } });
    },
    'cat-up': () => moveCat(-1),
    'cat-down': () => moveCat(1),
    'cat-delete': () => (state.overlay = activeIn(state.menuCat).length ? 'cat-blocked' : 'cat-delete'),
    'cat-delete-confirm': () => {
        data.exercises.filter((e) => e.cat === state.menuCat).forEach((e) => (e.cat = null));
        data.categories = data.categories.filter((c) => c.id !== state.menuCat);
        state.overlay = null;
        toast('已刪除分類');
    },
    'new-ex': (el) => Object.assign(state, { screen: 'form', form: newForm(el.dataset.cat ? { cat: Number(el.dataset.cat) } : {}) }),
    'edit-ex': (el) => {
        const ex = exById(Number(el.dataset.id));
        Object.assign(state, { screen: 'form', form: { mode: 'edit', id: ex.id, name: ex.name, cat: ex.cat, met: metFmt(ex.met), sec: ex.sec ?? 3, rest: ex.rest ?? 90, desc: ex.desc ?? '', next: 'manage' } });
    },
    'back-form': () => {
        if (state.form.mode === 'identify') return (state.screen = 'candidates');
        Object.assign(state, { screen: state.form.next === 'log' ? 'workouts' : 'manage', form: null });
    },
    'save-form': saveForm,
    'ask-del-ex': () => Object.assign(state, { overlay: 'ex-delete', delEx: state.form.id }),
    'del-ex-confirm': () => {
        const ex = exById(state.delEx);
        if (logCount(ex.id)) {
            ex.archived = true;
            toast(`已封存「${ex.name}」`);
        } else {
            data.exercises = data.exercises.filter((e) => e.id !== ex.id);
            toast(`已刪除「${ex.name}」`);
        }
        Object.assign(state, { screen: 'manage', overlay: null, form: null });
    },

    // 照片辨識
    swap: () => document.getElementById(state.idf.source === 'camera' ? 'file-camera' : 'file-upload').click(),
    start: startIdentify,
    cancel: () => {
        clearTimeout(timer);
        state.idf.phase = 'ready';
    },
    'back-identify': () => {
        clearTimeout(timer);
        Object.assign(state, { screen: 'workouts', overlay: 'picker' });
    },
    'manual-ex': () => Object.assign(state, { screen: 'form', overlay: null, form: newForm({ next: 'log' }) }),
    'pick-cand': (el) => pickCandidate(Number(el.dataset.i)),
    'back-cand': () => (state.overlay = 'leave'),
    'leave-cancel': () => (state.overlay = null),
    'leave-confirm': () => Object.assign(state, { screen: 'workouts', overlay: null }),
};

/** 分類上下移。 */
function moveCat(dir) {
    const i = data.categories.findIndex((c) => c.id === state.menuCat);
    const j = i + dir;
    if (j < 0 || j >= data.categories.length) return;
    [data.categories[i], data.categories[j]] = [data.categories[j], data.categories[i]];
}

/** 流程區的點擊事件（以 data-action 分派）。 */
function onFlowClick(e) {
    const el = e.target.closest('[data-action]');
    if (!el || el.disabled) return;
    // 點面板本身不關閉
    if (el.dataset.action === 'close-overlay' && e.target.closest('[data-stop]')) return;
    const fn = ACTIONS[el.dataset.action];
    if (!fn) return;
    // 按鈕觸發檔案選擇時不重繪
    if (el.dataset.action === 'swap') return fn(el);
    if (fn(el) === false) return;
    renderFlow();
}

/** 輸入：只更新狀態與即時數字，不重繪（保留游標）。 */
function onFlowInput(e) {
    const el = e.target;
    const key = el.dataset.input;
    if (!key) return;
    el.closest('.unit-input')?.classList.remove('invalid');
    const v = el.value;
    const counter = el.closest('.field')?.querySelector('[data-counter]');
    if (counter) counter.textContent = v.length;

    if (key === 'query') {
        state.query = v;
        document.querySelector('#flow [data-region="picker-list"]').innerHTML = pickerList(state);
        return;
    }
    if (key === 'hint') return (state.idf.hint = v);
    if (key === 'cat-name') return (state.catForm.name = v);
    if (state.log) {
        if (key === 'min' || key === 'km' || key === 'note') state.log[key] = v;
        if (key === 'reps' || key === 'wt') state.log.sets[Number(el.dataset.i)][key] = v;
        if (key === 'min') document.querySelectorAll('#flow [data-action="set-min"]').forEach((b) => b.classList.toggle('on', Number(b.dataset.v) === Number(v)));
    }
    if (state.form && key.startsWith('f-')) {
        const k = key.slice(2);
        if (k === 'cat') return;
        state.form[k] = v;
    }
    updateLive();
}

/** 下拉選單變更（分類會改變記錄方式，需要重繪）。 */
function onFlowChange(e) {
    if (e.target.dataset.input !== 'f-cat') return;
    const v = e.target.value;
    state.form.cat = v === 'new' ? 'new' : Number(v);
    renderFlow();
}

/** 選了自己的照片：只在瀏覽器預覽，不上傳。 */
function onFilePicked(e) {
    const file = e.target.files[0];
    if (!file) return;
    Object.assign(state.idf, { photo: URL.createObjectURL(file), phase: 'ready', error: null });
    e.target.value = '';
    renderFlow();
}

/* ---------- 畫面一覽 ---------- */

/** 一張靜態畫面。 */
function shot(tag, title, desc, html, opts = {}) {
    return `<div class="${opts.wide ? 'wide' : ''}">
        <div class="shot-label"><span class="tag ${opts.alt ? 'alt' : ''}">${tag}</span>${title}<small>${desc}</small></div>
        ${html}
    </div>`;
}

/** 以指定狀態渲染一張畫面。 */
function still(device, patch, extraCls = '') {
    return frame(device, { ...initialState(), quota: 7, ...patch }, extraCls);
}

/** 辨識頁狀態。 */
const idf = (patch) => ({ source: 'camera', sample: 'smith', photo: SMITH_PHOTO, phase: 'ready', error: null, hint: '', ...patch });

/** 渲染畫面一覽（使用預設資料，不受流程試玩影響）。 */
function renderGallery() {
    const benchFilled = { ...newLog(6, TODAY), sets: toSets([[10, 30], [10, 30], [8, 35], [8, 35]]) };
    const runLog = { ...newLog(1, TODAY), min: 30, km: 5.2 };
    const oldEdit = editLog(data.logs.find((l) => l.exId === 8));
    const plankForm = (() => {
        const saved = state.form;
        state.idf = idf({ sample: 'plank', photo: PLANK_PHOTO });
        pickCandidate(0);
        const f = state.form;
        state.form = saved;
        return f;
    })();
    Object.assign(state, initialState());

    const shots = [
        shot('m5-01', '健身頁', '日期切換；當日消耗／時間／訓練量；「分類與項目」入口；紀錄依時間排序，填時長顯示時長與距離，逐組顯示組數摘要。', still('phone', {})),
        shot(
            'm5-02',
            '健身頁：空狀態',
            '過去日期可補記；沒有紀錄時顯示「新增運動」。11/19 的飛輪是封存項目，紀錄照常顯示並標「已封存」（流程試玩切到 11/19 可看）。',
            still('phone', { date: '2026-11-20' }),
        ),
        shot('m5-03', '＋選單：健身開放', '「健身」從即將推出改成可用，點了開選擇項目面板。', still('phone', { screen: 'today', overlay: 'quick' })),
        shot(
            'm5-04',
            '選擇項目面板',
            '搜尋、拍照辨識／上傳照片、最近做過（最多 5 個）、依分類分組（顯示記錄方式）、管理入口。',
            still('phone', { overlay: 'picker' }),
        ),
        shot('m5-05', '選擇項目：搜尋不到', '直接用搜尋字新增項目，存檔後進記錄頁。', still('phone', { overlay: 'picker', query: '划船機' })),
        shot(
            'm5-06',
            '記錄：填時長',
            '時長必填，有 15／30／45／60 分快選；距離選填只記錄；底部即時顯示預估消耗；計算依據寫在下方。',
            still('phone', { screen: 'log', log: runLog }),
        ),
        shot(
            'm5-07',
            '記錄：逐組（剛進來）',
            '上方「上次」卡可一鍵帶入上次這個動作的組數；預設一組空白列，重量留空＝徒手。',
            still('phone', { screen: 'log', log: newLog(6, TODAY) }),
        ),
        shot(
            'm5-08',
            '記錄：逐組（填好）',
            '「新增一組」「複製上一組」；底部即時顯示預估消耗、訓練量與估算時間；最多 20 組。',
            still('phone', { screen: 'log', log: benchFilled }),
        ),
        shot(
            'm5-09',
            '編輯舊紀錄',
            '右上刪除；以紀錄快照（名稱、MET）與紀錄日體重重算；項目已封存仍可編輯。',
            still('phone', { screen: 'log', date: '2026-11-19', log: oldEdit }),
        ),
        shot('m5-10', '紀錄選單', '⋮：編輯、再做一次（帶入今天）、刪除。', still('phone', { overlay: 'log-menu', menuLog: 2 })),
        shot('m5-11', '刪除紀錄確認', '列出時間、內容與消耗，刪除後無法復原。', still('phone', { overlay: 'log-delete', menuLog: 2 })),
        shot(
            'm5-12',
            '分類與項目管理',
            '分類各自顯示記錄方式與項目數；項目列出 MET（逐組另列秒數）；底部新增分類；已封存收合在最下面，可還原。',
            still('phone', { screen: 'manage', showArchived: true }),
        ),
        shot('m5-13', '新增分類', '名稱＋記錄方式（填時長／逐組），提醒建立後不能改。', still('phone', { screen: 'manage', overlay: 'cat-form', catForm: { mode: 'new', name: '核心', type: 'duration' } })),
        shot('m5-14', '分類選單', '改名、往上移、往下移、刪除。改名時記錄方式鎖住。', still('phone', { screen: 'manage', overlay: 'cat-menu', menuCat: 2 })),
        shot('m5-15', '分類還有項目', '不能刪除，說明要先刪除或移走項目。', still('phone', { screen: 'manage', overlay: 'cat-blocked', menuCat: 2 })),
        shot(
            'm5-16',
            '新增項目（填時長）',
            '名稱、分類（記錄方式跟著分類）、MET（附參考值）、說明；即時顯示「以你目前體重，每 30 分鐘約 X kcal」。',
            still('phone', { screen: 'form', form: newForm({ cat: 1, name: '划船機', met: '7.0' }) }),
        ),
        shot(
            'm5-17',
            '編輯項目（逐組）',
            '只能移到同記錄方式的分類；多每下秒數、組間休息；估算「3 組 × 10 下約 X kcal」；有紀錄的項目底部是「封存項目」。',
            still('phone', { screen: 'form', form: { mode: 'edit', id: 6, name: '槓鈴肩推', cat: 2, met: '5.0', sec: 3, rest: 90, desc: '', next: 'manage' } }),
        ),
        shot('m5-18', '封存項目確認', '有紀錄的項目改成封存；沒有紀錄的直接刪除（確認框改紅色「刪除」）。', still('phone', { screen: 'manage', overlay: 'ex-delete', delEx: 6 })),
        shot('m5-19', '照片辨識：拍照', '沿用 M4 分析頁版型；補充說明選填；顯示今天剩餘次數（每天 10 次，與餐點分開計）。', still('phone', { screen: 'identify', idf: idf({ hint: '' }) })),
        shot(
            'm5-20',
            '辨識中',
            '掃描動畫；補充說明鎖定；可取消（已送出仍算一次）。',
            still('phone', { screen: 'identify', quota: 6, idf: idf({ phase: 'identifying', hint: '腿部的機器？' }) }),
        ),
        shot(
            'm5-21',
            '辨識失敗：看不出器材或動作',
            '主按鈕改「重拍／換一張」；Gemini 忙碌、逾時、其他錯誤則是「重新辨識」（同 M4）。',
            still('phone', { screen: 'identify', quota: 6, idf: idf({ phase: 'error', error: 'not_exercise' }) }),
        ),
        shot('m5-22', '今天次數用完', '只留「手動新增項目」。', still('phone', { screen: 'identify', quota: 0, idf: idf({ source: 'upload' }) })),
        shot(
            'm5-23',
            '辨識結果：候選卡片',
            '最多 3 個；每張有器材、說明、記錄方式、MET、秒數、信心度與放進哪個分類；和現有項目同名的標「已有這個項目」，點了直接記錄。',
            still('phone', { screen: 'candidates', idf: idf({ hint: '腿部的機器？' }) }),
        ),
        shot(
            'm5-24',
            '辨識結果：要新建分類',
            '建議的分類不存在（棒式 → 核心／填時長），卡片標「新建分類」。',
            still('phone', { screen: 'candidates', idf: idf({ sample: 'plank', photo: PLANK_PHOTO, source: 'upload' }) }),
        ),
        shot(
            'm5-25',
            '帶入新增項目表單',
            '上方標示來自照片辨識；分類預設「新建分類『核心』」，也能改選現有的同記錄方式分類；存檔後直接進記錄頁。',
            still('phone', { screen: 'form', form: plankForm, idf: idf({ sample: 'plank', photo: PLANK_PHOTO }) }),
        ),
        shot(
            'm5-26',
            '今日頁',
            '熱量剩餘加回運動消耗並標「含運動 +N kcal」；新增「今日運動」卡；喝水建議量拆成體重＋運動。原本的「即將推出」拿掉。',
            still('phone', { screen: 'today' }, 'short'),
        ),
        shot('m5-27', '喝水面板', '說明列加上運動時數；建議量拆解同今日頁。', still('phone', { screen: 'today', overlay: 'water' })),
        shot('m5-28', '桌機：健身頁', '左邊合計與入口，右邊紀錄列表。', still('desktop', {}), { wide: true }),
        shot('m5-29', '桌機：選擇項目', '置中對話框；桌機只有「上傳照片辨識」。', still('desktop', { overlay: 'picker' }), { wide: true }),
        shot('m5-30', '桌機：記錄逐組', '內容限寬置中，底部固定列。', still('desktop', { screen: 'log', log: benchFilled }), { wide: true }),
        shot('m5-31', '桌機：分類與項目', '同手機版，內容限寬。', still('desktop', { screen: 'manage' }), { wide: true }),
        shot('m5-32', '桌機：今日頁', '左欄熱量、運動、餐點；右欄喝水、體重。', still('desktop', { screen: 'today' }), { wide: true }),
    ];
    document.getElementById('gallery').innerHTML = shots.join('');
}

/* ---------- 工具列 ---------- */

/** 工具列：主題、裝置、重置。 */
function onToolbar(e) {
    const el = e.target.closest('[data-tool]');
    if (!el || el.tagName === 'SELECT') return;
    const { tool: key, value } = el.dataset;
    if (key === 'reset') {
        clearTimeout(timer);
        data = seed();
        Object.assign(state, initialState());
    } else {
        tool[key] = value;
        document.querySelectorAll(`[data-tool="${key}"]`).forEach((b) => b.classList.toggle('on', b === el));
        if (key === 'theme') document.body.dataset.theme = value;
    }
    renderFlow();
}

/** 工具列下拉選單。 */
function onToolbarSelect(e) {
    const key = e.target.dataset.tool;
    tool[key] = e.target.value;
    if (key === 'quota') state.quota = Number(e.target.value);
    if (key === 'result' && state.screen === 'identify' && state.idf.phase !== 'identifying') {
        const sample = tool.result === 'plank' ? 'plank' : 'smith';
        Object.assign(state.idf, { sample, photo: SAMPLES[sample].photo });
    }
    renderFlow();
}

document.querySelector('.toolbar').addEventListener('click', onToolbar);
document.querySelector('.toolbar').addEventListener('change', onToolbarSelect);
document.getElementById('flow').addEventListener('click', onFlowClick);
document.getElementById('flow').addEventListener('input', onFlowInput);
document.getElementById('flow').addEventListener('change', onFlowChange);
document.getElementById('file-camera').addEventListener('change', onFilePicked);
document.getElementById('file-upload').addEventListener('change', onFilePicked);

// iOS Safari 忽略 user-scalable=no，另外擋兩指縮放
document.addEventListener('gesturestart', (e) => e.preventDefault());

document.body.dataset.theme = tool.theme;
renderGallery();
renderFlow();
