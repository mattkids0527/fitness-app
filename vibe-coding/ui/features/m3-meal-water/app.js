/* ==========================================================================
   M3 飲食紀錄＋喝水 demo 互動
   以單一 state 物件驅動畫面：點擊類操作整頁重繪，輸入類操作只更新 data-live 區塊（避免失去焦點）。
   計算規則依 vibe-coding/specs/2026-11-20-m3-meal-water.md §5。
   ========================================================================== */

// demo 的「現在」與範例使用者（沿用 M1 demo：男、34 歲、175 cm、78.5 kg、輕度活動、每週減 0.5 kg）
const TODAY = '2026-11-20';
const NOW = '12:40';

// 與之後 meal / water 模組設定檔一致的數值
const CONFIG = {
    calorieTarget: 1678,
    proteinTarget: 141,
    weightKg: 78.5,
    waterMlPerKg: 35,
    waterRound: 50,
    waterMin: 10,
    waterMax: 2000,
    servings: [0.5, 1, 1.5, 2],
    recentFoodsDays: 30,
    recentFoodsLimit: 20,
    copyMealDays: 14,
    maxItems: 30,
};

const MEAL_TYPES = {
    breakfast: { label: '早餐', icon: 'sunrise', time: '08:00' },
    lunch: { label: '午餐', icon: 'sun', time: '12:30' },
    dinner: { label: '晚餐', icon: 'moon', time: '18:30' },
    snack: { label: '點心', icon: 'cookie', time: '15:00' },
};

const NUTRIENTS = [
    ['protein_g', '蛋白質'],
    ['carbs_g', '碳水'],
    ['fat_g', '脂肪'],
    ['fiber_g', '纖維'],
];

// lucide 風格圖示（與 lucide-vue-next 對應）
const ICONS = {
    home: '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
    utensils: '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
    plus: '<path d="M5 12h14"/><path d="M12 5v14"/>',
    dumbbell: '<path d="m6.5 6.5 11 11"/><path d="m21 21-1-1"/><path d="m3 3 1 1"/><path d="m18 22 4-4"/><path d="m2 6 4-4"/><path d="m3 10 7-7"/><path d="m14 21 7-7"/>',
    user: '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    chevronRight: '<path d="m9 18 6-6-6-6"/>',
    chevronLeft: '<path d="m15 18-6-6 6-6"/>',
    chevronDown: '<path d="m6 9 6 6 6-6"/>',
    chevronUp: '<path d="m18 15-6-6-6 6"/>',
    camera: '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
    pencil: '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
    droplet: '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
    weight: '<circle cx="12" cy="5" r="3"/><path d="M6.5 8a2 2 0 0 0-1.905 1.46L2.1 18.5A2 2 0 0 0 4 21h16a2 2 0 0 0 1.925-2.54L19.4 9.5A2 2 0 0 0 17.48 8Z"/>',
    trash: '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
    alert: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    calculator: '<rect width="16" height="20" x="4" y="2" rx="2"/><path d="M8 6h8M16 14v4M16 10h.01M12 10h.01M8 10h.01M12 14h.01M8 14h.01M12 18h.01M8 18h.01"/>',
    activity: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    more: '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
    copy: '<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
    repeat: '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
    calendar: '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    sunrise: '<path d="M12 2v8"/><path d="m4.93 10.93 1.41 1.41"/><path d="M2 18h2"/><path d="M20 18h2"/><path d="m19.07 10.93-1.41 1.41"/><path d="M22 22H2"/><path d="m8 6 4-4 4 4"/><path d="M16 18a4 4 0 0 0-8 0"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
    moon: '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
    cookie: '<path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/>',
};

/** 輸出一個 SVG 圖示。 */
function icon(name, size = '') {
    return `<svg class="icon ${size}" viewBox="0 0 24 24">${ICONS[name]}</svg>`;
}

/** 跳脫 HTML，避免使用者輸入破壞畫面。 */
function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

/* ---------- 範例資料 ---------- */

/** 建立一個食物項目（營養素傳 null 表示不知道）。 */
function food(name, portion, grams, kcal, protein, carbs, fat, fiber) {
    return { name, portion, grams, kcal, protein_g: protein, carbs_g: carbs, fat_g: fat, fiber_g: fiber };
}

const FOODS = {
    tunaEggPancake: () => food('鮪魚蛋餅', '1 份', null, 380, 18, 42, 15, 1.5),
    soyMilk: () => food('無糖豆漿', '1 杯', 400, 132, 13, 4, 7, 3),
    chickenBento: () => food('雞胸肉便當', '1 個', null, 650, 45, 78, 16, 5),
    beefNoodle: () => food('牛肉麵', '1 碗', null, 620, 35, 70, 20, null),
    bubbleTea: () => food('珍珠奶茶', '中杯 無糖', 500, 330, null, null, null, null),
    braisedPork: () => food('滷肉飯', '小碗', null, 360, 10, 50, 13, null),
    greens: () => food('燙青菜', '1 盤', 150, 80, 3, 8, 4, 4),
    teaEgg: () => food('茶葉蛋', '1 顆', 55, 75, 7, 1, 5, 0),
    banana: () => food('香蕉', '1 根', 120, 105, 1.3, 27, 0.4, 3.1),
    drumstickBento: () => food('雞腿便當', '1 個', null, 820, 42, 95, 30, 4),
    salmonSalad: () => food('鮭魚沙拉', '1 盒', 320, 420, 28, 18, 26, 6),
};

/** 建立 demo 初始資料；scenario 決定今天的紀錄長什麼樣子。 */
function seedData(scenario) {
    let meals = [
        { id: 1, date: TODAY, time: '07:50', type: 'breakfast', note: '', items: [FOODS.tunaEggPancake(), FOODS.soyMilk()] },
        { id: 2, date: TODAY, time: '12:20', type: 'lunch', note: '', items: [FOODS.chickenBento()] },
        { id: 3, date: '2026-11-19', time: '08:05', type: 'breakfast', note: '', items: [FOODS.tunaEggPancake(), FOODS.soyMilk()] },
        { id: 4, date: '2026-11-19', time: '12:30', type: 'lunch', note: '', items: [FOODS.beefNoodle()] },
        { id: 5, date: '2026-11-19', time: '15:30', type: 'snack', note: '', items: [FOODS.bubbleTea()] },
        { id: 6, date: '2026-11-19', time: '18:40', type: 'dinner', note: '', items: [FOODS.braisedPork(), FOODS.greens(), FOODS.teaEgg()] },
        { id: 7, date: '2026-11-18', time: '07:40', type: 'breakfast', note: '', items: [FOODS.banana(), food('茶葉蛋', '1 顆 ×2', 110, 150, 14, 2, 10, 0)] },
        { id: 8, date: '2026-11-18', time: '12:10', type: 'lunch', note: '', items: [FOODS.drumstickBento()] },
        { id: 9, date: '2026-11-18', time: '19:00', type: 'dinner', note: '健身後', items: [FOODS.salmonSalad()] },
        { id: 10, date: '2026-11-17', time: '12:00', type: 'lunch', note: '', items: [FOODS.chickenBento()] },
    ];
    let waterLogs = [
        { id: 1, time: '08:10', ml: 500 },
        { id: 2, time: '10:30', ml: 250 },
        { id: 3, time: '12:15', ml: 250 },
    ];

    if (scenario === 'empty') {
        meals = meals.filter((m) => m.date !== TODAY);
        waterLogs = [];
    }
    if (scenario === 'over') {
        meals.find((m) => m.id === 2).items.push(food('炸雞排', '1 片', null, 640, null, null, null, null));
        meals.push({ id: 11, date: TODAY, time: '10:30', type: 'snack', note: '', items: [food('珍珠奶茶', '大杯 半糖', 700, 450, null, null, null, null)] });
    }

    return {
        meals,
        waterLogs,
        savedFoods: [
            { id: 1, ...FOODS.tunaEggPancake(), last_used: `${TODAY} 07:50` },
            { id: 2, ...FOODS.soyMilk(), last_used: `${TODAY} 07:50` },
            { id: 3, ...FOODS.teaEgg(), last_used: '2026-11-19 18:40' },
            { id: 4, ...FOODS.banana(), last_used: '2026-11-18 07:40' },
            { id: 5, ...food('雞胸肉', '1 份', 100, 165, 31, 0, 3.6, 0), last_used: null },
        ],
        nextId: 100,
    };
}

/** 讀取 demo 外觀偏好（裝置、主題），只是方便檢視，讀不到就用預設。 */
function loadPrefs() {
    try {
        return JSON.parse(localStorage.getItem('m3-demo-prefs')) || {};
    } catch {
        return {};
    }
}

const prefs = loadPrefs();

const state = {
    screen: 'today',
    viewport: prefs.viewport || 'mobile',
    theme: prefs.theme || 'light',
    scenario: 'normal',
    ...seedData('normal'),
    mealsDate: TODAY,
    form: null,
    sheet: null,
    sheetData: {},
    waterCustom: '',
    errors: {},
    toast: null,
};

let itemSeq = 0;

/* ---------- 格式化 ---------- */

const WEEKDAYS = ['日', '一', '二', '三', '四', '五', '六'];

/** 四捨五入成整數並加千分位。 */
function num(n) {
    return Math.round(Number(n)).toLocaleString('en-US');
}

/** 克數顯示到小數 1 位，不知道（null）顯示 —。 */
function g1(v) {
    if (val(v) === null) return '—';
    return String(Math.round(Number(v) * 10) / 10);
}

/** 加減天數，回傳 YYYY-MM-DD。 */
function addDays(iso, n) {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(Date.UTC(y, m - 1, d + n)).toISOString().slice(0, 10);
}

/** 兩個日期相差幾天（b − a）。 */
function daysBetween(a, b) {
    return Math.round((Date.parse(b) - Date.parse(a)) / 86400000);
}

/** 日期轉「11月20日 週四」。 */
function longDate(iso) {
    const [y, m, d] = iso.split('-').map(Number);
    return `${m}月${d}日 週${WEEKDAYS[new Date(y, m - 1, d).getDay()]}`;
}

/** 日期轉「11/19（三）」，今天、昨天顯示文字。 */
function shortDate(iso) {
    if (iso === TODAY) return '今天';
    if (iso === addDays(TODAY, -1)) return '昨天';
    const [y, m, d] = iso.split('-').map(Number);
    return `${m}/${d}（${WEEKDAYS[new Date(y, m - 1, d).getDay()]}）`;
}

/* ---------- 計算 ---------- */

/** 表單字串或 null 轉成數字；空值回傳 null（表示不知道）。 */
function val(v) {
    return v === null || v === undefined || v === '' ? null : Number(v);
}

/** 加總熱量與營養素：null 當 0；蛋白質／碳水／脂肪任一為 null 時標記 missing（纖維不列入）。 */
function totalsOf(items) {
    const t = { kcal: 0, protein_g: 0, carbs_g: 0, fat_g: 0, fiber_g: 0, missing: false };
    items.forEach((i) => {
        ['kcal', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g'].forEach((k) => (t[k] += val(i[k]) ?? 0));
        if (['protein_g', 'carbs_g', 'fat_g'].some((k) => val(i[k]) === null)) t.missing = true;
    });
    return t;
}

/** 某一天的餐點，依時間排序。 */
function mealsOn(date) {
    return state.meals.filter((m) => m.date === date).sort((a, b) => a.time.localeCompare(b.time));
}

/** 依時間推測餐別：5–10 早餐、10–14 午餐、17–21 晚餐，其餘點心。 */
function typeByTime(time) {
    const h = Number(time.split(':')[0]);
    if (h >= 5 && h < 10) return 'breakfast';
    if (h >= 10 && h < 14) return 'lunch';
    if (h >= 17 && h < 21) return 'dinner';
    return 'snack';
}

/** 依份數換算重量與營養素，四捨五入到小數 1 位；份數不是 1 時在份量描述加上倍數。 */
function scaleFood(f, k) {
    const s = (v) => (val(v) === null ? null : Math.round(val(v) * k * 10) / 10);
    return {
        name: f.name,
        portion: k === 1 ? f.portion : `${f.portion || '1 份'} ×${k}`,
        grams: s(f.grams),
        kcal: s(f.kcal),
        protein_g: s(f.protein_g),
        carbs_g: s(f.carbs_g),
        fat_g: s(f.fat_g),
        fiber_g: s(f.fiber_g),
    };
}

/** 最近吃過：近 30 天的項目依名稱（不分大小寫）取最近一次，最多 20 筆。 */
function recentFoods() {
    const seen = new Set();
    const out = [];
    [...state.meals]
        .filter((m) => daysBetween(m.date, TODAY) < CONFIG.recentFoodsDays)
        .sort((a, b) => (b.date + b.time).localeCompare(a.date + a.time))
        .forEach((m) =>
            m.items.forEach((i) => {
                const key = i.name.trim().toLowerCase();
                if (seen.has(key) || out.length >= CONFIG.recentFoodsLimit) return;
                seen.add(key);
                out.push({ ...i, lastDate: m.date });
            }),
        );
    return out;
}

/** 複製整餐的來源：近 14 天的餐點（編輯中的那一餐除外），新到舊。 */
function recentMeals() {
    return state.meals
        .filter((m) => m.id !== state.form?.id && daysBetween(m.date, TODAY) < CONFIG.copyMealDays)
        .sort((a, b) => (b.date + b.time).localeCompare(a.date + a.time));
}

/** 常用食物依最近使用時間排序，沒用過的排最後。 */
function savedFoodsSorted() {
    return [...state.savedFoods].sort((a, b) => {
        if (a.last_used === b.last_used) return a.name.localeCompare(b.name, 'zh-Hant');
        if (!a.last_used) return 1;
        if (!b.last_used) return -1;
        return b.last_used.localeCompare(a.last_used);
    });
}

/** 以名稱（不分大小寫）找常用食物。 */
function findSaved(name, exceptId = null) {
    const key = name.trim().toLowerCase();
    return state.savedFoods.find((f) => f.id !== exceptId && f.name.trim().toLowerCase() === key);
}

/** 熱量沒填、但蛋白質／碳水／脂肪有填時，回傳估算熱量；否則 null。 */
function estimateKcal(i) {
    const [p, c, f] = [val(i.protein_g), val(i.carbs_g), val(i.fat_g)];
    if (val(i.kcal) !== null || (p === null && c === null && f === null)) return null;
    return Math.round((p ?? 0) * 4 + (c ?? 0) * 4 + (f ?? 0) * 9);
}

/** 每日建議飲水量：體重 × 35 ml，四捨五入到 50 ml。 */
function waterTarget() {
    return Math.round((CONFIG.weightKg * CONFIG.waterMlPerKg) / CONFIG.waterRound) * CONFIG.waterRound;
}

/** 今天已喝的總量。 */
function waterTotal() {
    return state.waterLogs.reduce((s, l) => s + l.ml, 0);
}

/** 蛋白質／碳水／脂肪的一行摘要。 */
function macroLine(i) {
    return `蛋白質 ${g1(i.protein_g)} · 碳水 ${g1(i.carbs_g)} · 脂肪 ${g1(i.fat_g)}`;
}

/** 一餐的項目名稱摘要。 */
function itemNames(meal) {
    return meal.items.map((i) => i.name).join('、');
}

/* ---------- 表單資料 ---------- */

/** 空白的手動項目（新增時直接展開）。 */
function emptyItem(open = true) {
    return { key: ++itemSeq, name: '', portion: '', grams: '', kcal: '', protein_g: '', carbs_g: '', fat_g: '', fiber_g: '', open };
}

/** 資料轉成表單用的字串欄位。 */
function toFormItem(f, open = false) {
    const s = (v) => (val(v) === null ? '' : String(v));
    return {
        key: ++itemSeq,
        name: f.name,
        portion: f.portion ?? '',
        grams: s(f.grams),
        kcal: s(f.kcal),
        protein_g: s(f.protein_g),
        carbs_g: s(f.carbs_g),
        fat_g: s(f.fat_g),
        fiber_g: s(f.fiber_g),
        open,
    };
}

/** 表單字串欄位轉回資料（空值存 null）。 */
function fromFormItem(i) {
    return food(i.name.trim(), i.portion.trim() || null, val(i.grams), val(i.kcal), val(i.protein_g), val(i.carbs_g), val(i.fat_g), val(i.fiber_g));
}

/** 新增餐點的表單：今天預設現在時間，其他日期預設餐別的代表時間。 */
function newForm({ date, type = null }) {
    const t = type ?? typeByTime(NOW);
    return {
        id: null,
        date,
        time: date === TODAY ? NOW : MEAL_TYPES[t].time,
        type: t,
        typeTouched: !!type,
        timeTouched: false,
        note: '',
        items: [],
        pickTab: 'saved',
        copiedFrom: null,
        returnTo: state.screen,
    };
}

/** 編輯餐點的表單。 */
function editForm(meal) {
    return {
        id: meal.id,
        date: meal.date,
        time: meal.time,
        type: meal.type,
        typeTouched: true,
        timeTouched: true,
        note: meal.note,
        items: meal.items.map((i) => toFormItem(i)),
        pickTab: 'saved',
        copiedFrom: null,
        returnTo: state.screen,
    };
}

/** 再吃一次：日期為今天、餐別沿用，內容預填，儲存才建立。 */
function againForm(meal) {
    return {
        ...newForm({ date: TODAY, type: meal.type }),
        items: meal.items.map((i) => toFormItem(i)),
        copiedFrom: `${shortDate(meal.date)}的${MEAL_TYPES[meal.type].label}`,
    };
}

/* ---------- 共用片段 ---------- */

/** 欄位錯誤訊息。 */
function errorOf(key) {
    return state.errors[key] ? `<div class="error">${state.errors[key]}</div>` : '';
}

/** 文字輸入框。 */
function textInput(bind, value, placeholder, maxlength) {
    const invalid = state.errors[bind] ? ' invalid' : '';
    return `<div class="input-wrap sm${invalid}"><input type="text" data-bind="${bind}" value="${esc(value)}" placeholder="${placeholder}" maxlength="${maxlength}" /></div>`;
}

/** 帶單位的數字輸入框。 */
function numberInput(bind, value, unit, placeholder = '') {
    const invalid = state.errors[bind] ? ' invalid' : '';
    return `<div class="input-wrap sm${invalid}">
        <input type="number" inputmode="decimal" min="0" step="any" data-bind="${bind}" value="${esc(value)}" placeholder="${placeholder}" />
        <span class="unit">${unit}</span>
    </div>`;
}

/** 分段按鈕（點擊即設定 path 的值）。 */
function segmented(path, value, options, cls = '') {
    return `<div class="segmented ${cls}">${Object.entries(options)
        .map(([k, label]) => `<button data-action="set" data-path="${path}" data-value="${k}" class="${value === k ? 'on' : ''}">${label}</button>`)
        .join('')}</div>`;
}

/** 餐別小圖示。 */
function typeIcon(type) {
    return `<span class="type-icon">${icon(MEAL_TYPES[type].icon)}</span>`;
}

/** 蛋白質、碳水、脂肪、纖維四格。 */
function macroGrid(t) {
    return `<div class="macro-grid">${NUTRIENTS.map(([k, label]) => `<div class="macro"><div class="k">${label}</div><div class="v">${g1(t[k])}<small>g</small></div></div>`).join('')}</div>`;
}

/** 缺營養素提示。 */
function missingCallout(t) {
    return t.missing
        ? `<div class="callout warning">${icon('alert', 'sm')}<div>部分項目沒有填蛋白質、碳水或脂肪，加總時以 0 計算，實際攝取可能更多。</div></div>`
        : '';
}

/* ---------- 只需局部更新的區塊 ---------- */

/** 依 data-live 名稱產生區塊內容。 */
function renderLive(name) {
    const [kind, arg] = name.split(':');
    switch (kind) {
        case 'item-head':
            return itemHead(Number(arg));
        case 'estimate': {
            const it = state.form?.items[Number(arg)];
            const e = it ? estimateKcal(it) : null;
            return e === null ? '' : `<button class="estimate" data-action="estimate" data-i="${arg}">${icon('calculator', 'sm')} 依營養素估算 ${num(e)} kcal</button>`;
        }
        case 'form-total':
            return `${num(totalsOf(state.form.items).kcal)} kcal`;
        default:
            return '';
    }
}

/** 重新計算所有 data-live 區塊。 */
function refreshLive() {
    document.querySelectorAll('[data-live]').forEach((el) => (el.innerHTML = renderLive(el.dataset.live)));
}

/* ---------- 畫面：今日 ---------- */

/** 今日頁：熱量卡（M1 環形圖接上實際攝取）、今日餐點、喝水、體重、運動即將推出。 */
function screenToday() {
    const t = totalsOf(mealsOn(TODAY).flatMap((m) => m.items));
    const target = CONFIG.calorieTarget;
    const remaining = target - t.kcal;
    const over = remaining < 0;
    const circ = 2 * Math.PI * 52;
    const progress = Math.min(1, t.kcal / target);
    const proteinLeft = Math.max(0, CONFIG.proteinTarget - t.protein_g);
    const proteinPct = Math.min(100, (t.protein_g / CONFIG.proteinTarget) * 100);

    const calorieCard = `<div class="card">
        <div class="ring-wrap">
            <div class="ring"><svg viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="52" fill="none" stroke="hsl(var(--muted))" stroke-width="12"/>
                <circle cx="60" cy="60" r="52" fill="none" stroke="${over ? 'hsl(346 77% 50%)' : 'hsl(var(--brand))'}" stroke-width="12" stroke-linecap="round" stroke-dasharray="${circ}" stroke-dashoffset="${circ * (1 - progress)}"/>
            </svg><div class="center"><b class="${over ? 'over' : ''}">${num(Math.abs(remaining))}</b><span>${over ? '超過 kcal' : '剩餘 kcal'}</span></div></div>
            <div style="flex:1">
                <div class="muted small">熱量目標</div><div style="font-size:20px;font-weight:700" class="tnum">${num(target)} kcal</div>
                <div class="muted small mt-8">已攝取</div><div style="font-weight:600" class="tnum ${over ? 'over' : ''}">${num(t.kcal)} kcal</div>
            </div>
        </div>
        <div class="mt-12">
            <div class="row between small"><span>蛋白質</span><span class="muted tnum">${num(t.protein_g)} / ${CONFIG.proteinTarget} g</span></div>
            <div class="bar mt-8"><i style="width:${proteinPct}%"></i></div>
            <div class="small mt-8">${proteinLeft > 0 ? `還差 <b>${num(proteinLeft)} g</b>` : '<b>已達成蛋白質目標</b>'}</div>
        </div>
        ${missingCallout(t)}
    </div>`;

    const meals = mealsOn(TODAY);
    const mealsCard = `<div class="card">
        <div class="card-title">${icon('utensils', 'sm')} 今日餐點<button class="link" data-action="go" data-screen="meals">飲食頁${icon('chevronRight', 'sm')}</button></div>
        ${
            meals.length
                ? meals
                      .map(
                          (m) => `<button class="today-meal" data-action="edit-meal" data-id="${m.id}">${typeIcon(m.type)}
                    <div class="body"><div class="t">${MEAL_TYPES[m.type].label} · ${m.time}</div><div class="names">${esc(itemNames(m))}</div></div>
                    <span class="kcal">${num(totalsOf(m.items).kcal)} <small class="muted">kcal</small></span></button>`,
                      )
                      .join('')
                : '<div class="muted small">今天還沒有記錄餐點。</div>'
        }
        <button class="btn outline block mt-12" data-action="new-meal" data-today="1">${icon('plus', 'sm')} 記錄餐點</button>
    </div>`;

    const total = waterTotal();
    const wTarget = waterTarget();
    const waterCard = `<div class="card">
        <div class="card-title"><span class="water-icon">${icon('droplet', 'sm')}</span> 喝水<button class="link" data-action="open-sheet" data-sheet="water">紀錄${icon('chevronRight', 'sm')}</button></div>
        <div class="row between"><div class="water-amount">${num(total)}<small>/ ${num(wTarget)} ml</small></div>
            <span class="small ${total >= wTarget ? '' : 'muted'}">${total >= wTarget ? '<b>已達成</b>' : `還差 ${num(wTarget - total)} ml`}</span></div>
        <div class="bar water mt-8"><i style="width:${Math.min(100, (total / wTarget) * 100)}%"></i></div>
        <div class="water-buttons"><button class="btn outline" data-action="water-add" data-ml="250">+250 ml</button><button class="btn outline" data-action="water-add" data-ml="500">+500 ml</button></div>
    </div>`;

    const weightCard = `<div class="card">
        <div class="card-title">${icon('weight', 'sm')} 體重</div>
        <div class="hero-number">78.5<small>kg</small></div>
        <div class="muted small">今天已記錄</div>
        <button class="btn outline block mt-12" data-action="m1-skip">${icon('plus', 'sm')} 記錄體重</button>
    </div>`;

    return `<div class="page">
        <div class="page-header"><div><h2>今日</h2><div class="sub">${longDate(TODAY)}</div></div></div>
        <div class="grid-2 today-grid">
            <div>${calorieCard}${mealsCard}</div>
            <div>${waterCard}${weightCard}
                <div class="section-label">即將推出</div>
                <div class="coming" style="margin-top:0"><span class="icon-box">${icon('dumbbell')}</span><div><b>運動</b><div>有氧與重訓紀錄、消耗熱量</div></div></div>
            </div>
        </div>
    </div>`;
}

/* ---------- 畫面：飲食 ---------- */

/** 飲食頁：日期切換、當日合計、依餐別分組的餐點。 */
function screenMeals() {
    const d = state.mealsDate;
    const meals = mealsOn(d);
    const t = totalsOf(meals.flatMap((m) => m.items));
    const target = CONFIG.calorieTarget;
    const over = t.kcal > target;

    const totals = `<div class="card">
        <div class="card-title">${icon('utensils', 'sm')} ${d === TODAY ? '今天' : shortDate(d)}合計</div>
        <div class="hero-number">${num(t.kcal)}<small>/ ${num(target)} kcal</small></div>
        <div class="bar mt-8"><i class="${over ? 'over' : ''}" style="width:${Math.min(100, (t.kcal / target) * 100)}%"></i></div>
        <div class="small mt-8 ${over ? 'over' : 'muted'}">${over ? `超過 ${num(t.kcal - target)} kcal` : `還可以吃 ${num(target - t.kcal)} kcal`}</div>
        ${macroGrid(t)}
        ${missingCallout(t)}
    </div>
    <div class="list mt-12"><button class="list-row" data-action="go" data-screen="saved-foods">${icon('star')}<span class="label">常用食物</span><span class="value">${state.savedFoods.length} 項</span><span class="chev">${icon('chevronRight', 'sm')}</span></button></div>`;

    const groups = Object.entries(MEAL_TYPES)
        .map(([type, mt]) => {
            const ms = meals.filter((m) => m.type === type);
            const kcal = totalsOf(ms.flatMap((m) => m.items)).kcal;
            return `<div class="meal-group">
                <div class="meal-group-head">${typeIcon(type)}${mt.label}${ms.length ? `<span class="muted tnum">${num(kcal)} kcal</span>` : ''}
                    ${ms.length ? `<button class="add" data-action="new-meal" data-type="${type}">${icon('plus', 'sm')}新增</button>` : ''}</div>
                ${
                    ms.length
                        ? ms
                              .map(
                                  (m) => `<div class="meal-card" data-action="edit-meal" data-id="${m.id}" role="button">
                            <div class="body"><div class="names">${esc(itemNames(m))}</div><div class="meta">${m.time} · ${m.items.length} 個項目${m.note ? ` · ${esc(m.note)}` : ''}</div></div>
                            <span class="kcal">${num(totalsOf(m.items).kcal)}<small>kcal</small></span>
                            <button class="icon-btn plain" data-action="meal-menu" data-id="${m.id}" aria-label="更多">${icon('more')}</button>
                        </div>`,
                              )
                              .join('')
                        : `<button class="meal-empty" data-action="new-meal" data-type="${type}">${icon('plus', 'sm')} 新增${mt.label}</button>`
                }
            </div>`;
        })
        .join('');

    return `<div class="page">
        <div class="page-header"><div><h2>飲食</h2></div><span class="spacer"></span>
            <button class="btn primary sm" data-action="new-meal">${icon('plus', 'sm')} 新增餐點</button></div>
        <div class="date-switch">
            <button class="icon-btn" data-action="date-prev" aria-label="前一天">${icon('chevronLeft')}</button>
            <label class="date-label">${icon('calendar', 'sm')} ${longDate(d)}${d === TODAY ? '（今天）' : ''}
                <input type="date" data-bind="mealsDate" data-change="render" value="${d}" max="${TODAY}" /></label>
            <button class="icon-btn" data-action="date-next" aria-label="後一天" ${d === TODAY ? 'disabled' : ''}>${icon('chevronRight')}</button>
        </div>
        ${d === TODAY ? '' : `<div style="text-align:center;margin-bottom:4px"><button class="chip" data-action="date-today">回到今天</button></div>`}
        <div class="meals-layout"><div>${totals}</div><div>${groups}</div></div>
    </div>`;
}

/* ---------- 畫面：新增／編輯餐點 ---------- */

/** 食物項目卡片的標頭（名稱、份量、營養素摘要、熱量、星號、展開）。 */
function itemHead(i) {
    const it = state.form?.items[i];
    if (!it) return '';
    const saved = it.name.trim() && findSaved(it.name);
    const parts = [it.portion, it.grams !== '' ? `${g1(it.grams)} g` : ''].filter(Boolean);
    const macro = [it.protein_g, it.carbs_g, it.fat_g].some((v) => v !== '') ? macroLine(it) : '營養素未填';
    return `<div class="body" data-action="item-toggle" data-i="${i}">
            <div class="name ${it.name.trim() ? '' : 'placeholder'}">${esc(it.name.trim()) || '未命名項目'}</div>
            <div class="meta">${[...parts.map(esc), macro].join(' · ')}</div>
        </div>
        <span class="kcal">${it.kcal !== '' ? num(it.kcal) : '—'}<small>kcal</small></span>
        <button class="star-btn ${saved ? 'on' : ''}" data-action="item-star" data-i="${i}" aria-label="${saved ? '已在常用食物' : '加入常用食物'}">${icon('star', 'sm')}</button>
        <button class="icon-btn plain" data-action="item-toggle" data-i="${i}" aria-label="${it.open ? '收合' : '展開'}">${icon(it.open ? 'chevronUp' : 'chevronDown', 'sm')}</button>`;
}

/** 展開後的項目欄位。 */
function itemBody(it, i) {
    const p = `form.items.${i}`;
    return `<div class="item-body">
        <div class="field compact"><label>名稱</label>${textInput(`${p}.name`, it.name, '例如：雞胸肉便當', 100)}${errorOf(`${p}.name`)}</div>
        <div class="form-grid">
            <div class="field compact"><label>份量描述（選填）</label>${textInput(`${p}.portion`, it.portion, '例如：1 碗', 50)}</div>
            <div class="field compact"><label>重量（選填）</label>${numberInput(`${p}.grams`, it.grams, 'g')}</div>
        </div>
        <div class="field compact"><label>熱量</label>${numberInput(`${p}.kcal`, it.kcal, 'kcal')}${errorOf(`${p}.kcal`)}<div data-live="estimate:${i}"></div></div>
        <div class="form-grid">
            ${NUTRIENTS.map(([k, label]) => `<div class="field compact"><label>${label}（選填）</label>${numberInput(`${p}.${k}`, it[k], 'g')}</div>`).join('')}
        </div>
        <div class="hint">不知道的營養素可以留空，加總時以 0 計算。</div>
        <button class="remove" data-action="item-remove" data-i="${i}">${icon('trash', 'sm')} 刪除這個項目</button>
    </div>`;
}

/** 快速帶入的清單（常用食物／最近吃過／複製整餐）。 */
function pickList() {
    const tab = state.form.pickTab;
    const row = (attrs, name, meta, iconName = 'plus') =>
        `<button class="pick-row" ${attrs}><div class="body"><div class="name">${esc(name)}</div><div class="meta">${meta}</div></div><span class="plus">${icon(iconName, 'sm')}</span></button>`;

    if (tab === 'saved') {
        const list = savedFoodsSorted();
        if (!list.length) return `<div class="pick-list"><div class="pick-empty">還沒有常用食物，在下方項目按 ☆ 就能加入。</div></div>`;
        return `<div class="pick-list">${list
            .map((f) => row(`data-action="pick-food" data-source="saved" data-id="${f.id}"`, f.name, `${esc(f.portion || '1 份')} · ${num(f.kcal)} kcal · ${macroLine(f)}`))
            .join('')}</div><div class="hint">點選後選份數加入。</div>`;
    }
    if (tab === 'recent') {
        const list = recentFoods();
        if (!list.length) return `<div class="pick-list"><div class="pick-empty">近 30 天沒有紀錄。</div></div>`;
        return `<div class="pick-list">${list
            .map((f, idx) => row(`data-action="pick-food" data-source="recent" data-idx="${idx}"`, f.name, `${esc(f.portion || '1 份')} · ${num(f.kcal)} kcal · ${shortDate(f.lastDate)}`))
            .join('')}</div><div class="hint">近 30 天吃過的項目，點選後選份數加入。</div>`;
    }
    const meals = recentMeals();
    if (!meals.length) return `<div class="pick-list"><div class="pick-empty">近 14 天沒有餐點。</div></div>`;
    return `<div class="pick-list">${meals
        .map((m) => row(`data-action="copy-meal" data-id="${m.id}"`, itemNames(m), `${shortDate(m.date)} ${MEAL_TYPES[m.type].label} · ${m.items.length} 個項目 · ${num(totalsOf(m.items).kcal)} kcal`, 'copy'))
        .join('')}</div><div class="hint">近 14 天的餐點，點選後把整餐的項目加入。</div>`;
}

/** 新增／編輯餐點頁。 */
function screenForm() {
    const f = state.form;
    const isEdit = !!f.id;

    const basic = `<div class="card">
        <div class="form-grid">
            <div class="field compact" style="margin-top:0"><label>日期</label>
                <div class="input-wrap sm${state.errors['form.date'] ? ' invalid' : ''}"><input type="date" data-bind="form.date" data-change="render" value="${f.date}" max="${TODAY}" /></div>${errorOf('form.date')}</div>
            <div class="field compact" style="margin-top:0"><label>時間</label>
                <div class="input-wrap sm"><input type="time" data-bind="form.time" data-change="render" value="${f.time}" /></div></div>
        </div>
        <div class="field compact"><label>餐別</label>${segmented('form.type', f.type, Object.fromEntries(Object.entries(MEAL_TYPES).map(([k, v]) => [k, v.label])), 'sm')}
            ${f.typeTouched ? '' : '<div class="hint">依時間自動選擇，可以手動改。</div>'}</div>
    </div>`;

    const pick = `<div class="card">
        <div class="card-title">快速帶入</div>
        ${segmented('form.pickTab', f.pickTab, { saved: '常用食物', recent: '最近吃過', copy: '複製整餐' }, 'sm')}
        ${pickList()}
    </div>`;

    const items = `<div class="card">
        <div class="card-title">這一餐的項目<span class="badge">${f.items.length}</span></div>
        ${errorOf('form.items')}
        ${f.items.length ? '' : '<div class="muted small">還沒有項目。從「快速帶入」選擇，或手動新增。</div>'}
        ${f.items.map((it, i) => `<div class="item-card ${it.open ? 'open' : ''}"><div class="item-head" data-live="item-head:${i}"></div>${it.open ? itemBody(it, i) : ''}</div>`).join('')}
        <button class="btn outline block mt-12" data-action="item-add" ${f.items.length >= CONFIG.maxItems ? 'disabled' : ''}>${icon('plus', 'sm')} 手動新增項目</button>
    </div>
    <div class="card"><div class="field compact" style="margin-top:0"><label>備註（選填）</label>
        <div class="input-wrap area"><textarea data-bind="form.note" maxlength="500" placeholder="例如：外食、聚餐">${esc(f.note)}</textarea></div></div></div>`;

    return `<div class="page">
        <div class="page-header">
            <button class="back" data-action="form-back" aria-label="返回">${icon('chevronLeft')}</button>
            <h2>${isEdit ? '編輯餐點' : '新增餐點'}</h2><span class="spacer"></span>
            ${isEdit ? `<button class="header-action danger" data-action="form-delete" aria-label="刪除這一餐">${icon('trash')}</button>` : ''}
        </div>
        ${f.copiedFrom ? `<div class="callout" style="margin-top:0;margin-bottom:4px">${icon('repeat', 'sm')}<div>已帶入${esc(f.copiedFrom)}的內容，可以修改，按「儲存」才會建立。</div></div>` : ''}
        <div class="form-layout"><div>${basic}${pick}</div><div>${items}</div></div>
        <div class="sticky-actions">
            <div class="sticky-total"><span class="k">本餐合計</span><span class="v" data-live="form-total"></span></div>
            <button class="btn primary" data-action="form-save">儲存</button>
        </div>
    </div>`;
}

/* ---------- 畫面：常用食物 ---------- */

/** 常用食物管理頁：依最近使用排序，點選編輯。 */
function screenSavedFoods() {
    const list = savedFoodsSorted();
    const lastUsed = (f) => (f.last_used ? `最近使用：${shortDate(f.last_used.slice(0, 10))}` : '還沒用過');
    return `<div class="page narrow">
        <div class="page-header">
            <button class="back" data-action="go" data-screen="meals" aria-label="返回">${icon('chevronLeft')}</button>
            <div><h2>常用食物</h2><div class="sub">${list.length} 項 · 依最近使用排序</div></div><span class="spacer"></span>
            <button class="header-action" data-action="food-new" aria-label="新增常用食物">${icon('plus')}</button>
        </div>
        <div class="callout" style="margin-top:0">${icon('info', 'sm')}<div>記錄餐點時按項目旁的 ☆ 也能加入。數值以「一份」為準，帶入時可以選份數。</div></div>
        ${
            list.length
                ? `<div class="list mt-12">${list
                      .map(
                          (f) => `<button class="food-row" data-action="food-edit" data-id="${f.id}">
                    <div class="body"><div class="name">${esc(f.name)}</div>
                        <div class="meta">${esc(f.portion || '1 份')}${val(f.grams) !== null ? ` · ${g1(f.grams)} g` : ''} · ${macroLine(f)}</div>
                        <div class="meta">${lastUsed(f)}</div></div>
                    <span class="kcal">${num(f.kcal)}<small>kcal / 份</small></span></button>`,
                      )
                      .join('')}</div>`
                : `<div class="empty"><span class="icon-box">${icon('star', 'lg')}</span><h3>還沒有常用食物</h3><div>按右上角 ＋ 新增，或在記錄餐點時按 ☆。</div></div>`
        }
    </div>`;
}

/* ---------- 底部面板 ---------- */

/** 依 state.sheet 產生底部面板（桌機為置中對話框）。 */
function renderSheet() {
    if (!state.sheet) return '';
    const sd = state.sheetData;
    let body = '';

    if (state.sheet === 'plus') {
        body = `<h3>快速新增</h3><p class="sheet-desc">選擇要記錄的項目</p>
            <div class="quick-grid">
                <button class="quick" disabled><span class="badge">即將推出</span><span class="icon-box">${icon('camera')}</span><div>拍照分析<div class="muted small" style="font-weight:400">AI 估算營養素</div></div></button>
                <button class="quick" data-action="new-meal" data-today="1"><span class="icon-box">${icon('pencil')}</span><div>手動記錄<div class="muted small" style="font-weight:400">輸入餐點內容</div></div></button>
                <button class="quick" disabled><span class="badge">即將推出</span><span class="icon-box">${icon('dumbbell')}</span><div>健身<div class="muted small" style="font-weight:400">有氧、重訓</div></div></button>
                <button class="quick" data-action="open-sheet" data-sheet="water"><span class="icon-box">${icon('droplet')}</span><div>喝水<div class="muted small" style="font-weight:400">+250 / +500 ml</div></div></button>
                <button class="quick wide" data-action="m1-skip"><span class="icon-box">${icon('weight')}</span><div style="flex:1">記錄體重<div class="muted small" style="font-weight:400">建議每天固定時間量</div></div>${icon('chevronRight', 'sm')}</button>
            </div>`;
    }

    if (state.sheet === 'water') {
        const total = waterTotal();
        const target = waterTarget();
        const logs = [...state.waterLogs].sort((a, b) => b.time.localeCompare(a.time));
        body = `<h3 class="row"><span class="water-icon">${icon('droplet')}</span>喝水</h3>
            <p class="sheet-desc">今天 · 建議量 = 體重 ${CONFIG.weightKg} kg × ${CONFIG.waterMlPerKg} ml</p>
            <div class="row between"><div class="water-amount">${num(total)}<small>/ ${num(target)} ml</small></div>
                <span class="badge ${total >= target ? 'brand' : ''}">${Math.round((total / target) * 100)}%</span></div>
            <div class="bar water mt-8"><i style="width:${Math.min(100, (total / target) * 100)}%"></i></div>
            <div class="water-buttons"><button class="btn outline" data-action="water-add" data-ml="250">+250 ml</button><button class="btn outline" data-action="water-add" data-ml="500">+500 ml</button></div>
            <div class="field"><label>自訂</label>
                <div class="row"><div class="input-wrap sm${state.errors.waterCustom ? ' invalid' : ''}" style="flex:1"><input type="number" inputmode="numeric" min="${CONFIG.waterMin}" max="${CONFIG.waterMax}" step="1" data-bind="waterCustom" value="${esc(state.waterCustom)}" placeholder="例如 330" /><span class="unit">ml</span></div>
                <button class="btn primary" style="height:42px" data-action="water-custom-add">記錄</button></div>${errorOf('waterCustom')}</div>
            <div class="section-label">今天的紀錄</div>
            ${
                logs.length
                    ? `<div class="list">${logs
                          .map((l) => `<div class="log-row"><span class="time">${l.time}</span><span class="ml">${num(l.ml)} ml</span><button class="icon-btn" data-action="water-delete" data-id="${l.id}" aria-label="刪除">${icon('trash', 'sm')}</button></div>`)
                          .join('')}</div>`
                    : '<div class="muted small" style="margin:0 4px">今天還沒有紀錄。</div>'
            }`;
    }

    if (state.sheet === 'meal-actions') {
        const m = state.meals.find((x) => x.id === sd.mealId);
        body = `<h3>${MEAL_TYPES[m.type].label} · ${m.time}</h3><p class="sheet-desc">${esc(itemNames(m))} · ${num(totalsOf(m.items).kcal)} kcal</p>
            <div class="action-list">
                <button data-action="edit-meal" data-id="${m.id}">${icon('pencil')} 編輯</button>
                <button data-action="meal-again" data-id="${m.id}">${icon('repeat')} 再吃一次<span class="muted small" style="margin-left:auto">帶入今天</span></button>
                <button class="danger" data-action="meal-delete-ask" data-id="${m.id}">${icon('trash')} 刪除</button>
            </div>`;
    }

    if (state.sheet === 'meal-delete') {
        const m = state.meals.find((x) => x.id === sd.mealId);
        body = `<h3>刪除這筆${MEAL_TYPES[m.type].label}？</h3>
            <p class="sheet-desc">${shortDate(m.date)} ${m.time}，包含 ${m.items.length} 個項目，共 ${num(totalsOf(m.items).kcal)} kcal。刪除後無法復原。</p>
            <div class="sheet-actions"><button class="btn outline" data-action="dismiss">取消</button><button class="btn danger" data-action="meal-delete-confirm" data-id="${m.id}">刪除</button></div>`;
    }

    if (state.sheet === 'servings') {
        const scaled = scaleFood(sd.food, sd.k);
        body = `<h3>${esc(sd.food.name)}</h3><p class="sheet-desc">一份：${esc(sd.food.portion || '1 份')} · ${num(sd.food.kcal)} kcal</p>
            <span class="field-label">份數</span>
            <div class="servings">${CONFIG.servings.map((k) => `<button class="${sd.k === k ? 'on' : ''}" data-action="servings-set" data-k="${k}">×${k}</button>`).join('')}</div>
            <div class="card">
                <div class="muted small">${esc(scaled.portion || '1 份')}${val(scaled.grams) !== null ? ` · ${g1(scaled.grams)} g` : ''}</div>
                <div class="hero-number" style="font-size:32px">${num(scaled.kcal)}<small>kcal</small></div>
                ${macroGrid(scaled)}
            </div>
            <div class="sheet-actions"><button class="btn outline" data-action="dismiss">取消</button><button class="btn primary" data-action="servings-add">加入</button></div>`;
    }

    if (state.sheet === 'food-edit') {
        const f = sd.food;
        const p = 'sheetData.food';
        body = `<h3>${f.id ? '編輯常用食物' : '新增常用食物'}</h3><p class="sheet-desc">數值以「一份」為準。</p>
            <div class="field compact" style="margin-top:0"><label>名稱</label>${textInput(`${p}.name`, f.name, '例如：茶葉蛋', 100)}${errorOf(`${p}.name`)}</div>
            <div class="form-grid">
                <div class="field compact"><label>份量描述（選填）</label>${textInput(`${p}.portion`, f.portion, '例如：1 顆', 50)}</div>
                <div class="field compact"><label>重量（選填）</label>${numberInput(`${p}.grams`, f.grams, 'g')}</div>
            </div>
            <div class="field compact"><label>熱量</label>${numberInput(`${p}.kcal`, f.kcal, 'kcal')}${errorOf(`${p}.kcal`)}</div>
            <div class="form-grid">${NUTRIENTS.map(([k, label]) => `<div class="field compact"><label>${label}（選填）</label>${numberInput(`${p}.${k}`, f[k], 'g')}</div>`).join('')}</div>
            <div class="sheet-actions">
                ${f.id ? `<button class="btn outline" style="flex:none;color:hsl(var(--destructive))" data-action="food-delete-ask" aria-label="刪除">${icon('trash', 'sm')}</button>` : ''}
                <button class="btn outline" data-action="dismiss">取消</button><button class="btn primary" data-action="food-save">儲存</button>
            </div>`;
    }

    if (state.sheet === 'food-delete') {
        body = `<h3>刪除「${esc(sd.food.name)}」？</h3><p class="sheet-desc">只會從常用食物移除，已經記錄的餐點不受影響。</p>
            <div class="sheet-actions"><button class="btn outline" data-action="food-edit" data-id="${sd.food.id}">取消</button><button class="btn danger" data-action="food-delete-confirm">刪除</button></div>`;
    }

    return `<div class="overlay" data-action="close-sheet"><div class="sheet"><div class="grabber"></div>${body}</div></div>`;
}

/* ---------- 版型 ---------- */

const TABS = [
    { key: 'today', label: '今日', icon: 'home' },
    { key: 'meals', label: '飲食', icon: 'utensils' },
    { key: 'plus' },
    { key: 'workout', label: '健身', icon: 'dumbbell', soon: true },
    { key: 'me', label: '我的', icon: 'user' },
];

/** 目前畫面對應的 Tab。 */
function activeTab() {
    return ['form', 'saved-foods'].includes(state.screen) ? 'meals' : state.screen;
}

/** 手機底部 Tab。 */
function renderTabbar() {
    return `<nav class="tabbar">${TABS.map((t) =>
        t.key === 'plus'
            ? `<button class="tab" data-action="open-sheet" data-sheet="plus" aria-label="快速新增"><span class="tab-plus">${icon('plus', 'lg')}</span></button>`
            : `<button class="tab ${activeTab() === t.key ? 'on' : ''}" data-action="go" data-screen="${t.key}">${icon(t.icon)}<span>${t.label}</span></button>`,
    ).join('')}</nav>`;
}

/** 桌機側欄。 */
function renderSidebar() {
    return `<aside class="sidebar">
        <div class="brand"><span class="brand-mark">${icon('activity', 'sm')}</span>Fitness</div>
        <button class="btn primary block side-quick" data-action="open-sheet" data-sheet="plus">${icon('plus', 'sm')} 快速新增</button>
        ${TABS.filter((t) => t.key !== 'plus')
            .map((t) => `<button class="side-item ${activeTab() === t.key ? 'on' : ''}" data-action="go" data-screen="${t.key}">${icon(t.icon)}${t.label}${t.soon ? '<span class="badge">即將推出</span>' : ''}</button>`)
            .join('')}
        <div class="side-user"><span class="avatar sm">D</span><div><div style="font-weight:600">Demo 使用者</div><div class="muted">demo@example.com</div></div></div>
    </aside>`;
}

let lastScreen = null;
let lastSheet = null;

/** 重繪整個 App；同一個畫面重繪時保留捲動位置。 */
function render() {
    const app = document.getElementById('app');
    const device = document.getElementById('device');
    const mainScroll = lastScreen === state.screen ? (app.querySelector('.main')?.scrollTop ?? 0) : 0;
    const sheetScroll = lastSheet === state.sheet ? (app.querySelector('.sheet')?.scrollTop ?? 0) : 0;
    const withSticky = state.screen === 'form';

    device.className = `device ${state.viewport}`;
    device.dataset.theme = state.theme;

    const screens = { today: screenToday, meals: screenMeals, form: screenForm, 'saved-foods': screenSavedFoods };

    app.innerHTML = `
        ${renderSidebar()}
        <div class="main ${withSticky ? 'no-tabbar' : ''}">
            <div class="statusbar"><span>12:40</span><span>●●● 100%</span></div>
            ${screens[state.screen]()}
        </div>
        ${withSticky ? '' : renderTabbar()}
        ${renderSheet()}
        ${state.toast ? `<div class="toast">${icon('check', 'sm')}${esc(state.toast)}</div>` : ''}`;

    app.querySelector('.main').scrollTop = mainScroll;
    if (app.querySelector('.sheet')) app.querySelector('.sheet').scrollTop = sheetScroll;
    lastScreen = state.screen;
    lastSheet = state.sheet;

    refreshLive();
    renderToolbar();
}

/* ---------- demo 工具列 ---------- */

const TOOLBAR_SCREENS = [
    ['today', '今日'],
    ['meals', '飲食'],
    ['form-new', '新增餐點'],
    ['form-edit', '編輯餐點'],
    ['form-again', '再吃一次'],
    ['saved-foods', '常用食物'],
    ['plus', '＋選單'],
    ['water', '喝水面板'],
    ['meal-actions', '餐點選單'],
    ['servings', '選份數'],
];

/** 工具列目前對應的畫面鍵。 */
function toolbarKey() {
    if (['plus', 'water', 'meal-actions', 'servings'].includes(state.sheet)) return state.sheet;
    if (state.screen === 'form') return state.form.id ? 'form-edit' : state.form.copiedFrom ? 'form-again' : 'form-new';
    return state.screen;
}

/** 更新工具列按鈕的選取狀態並保存偏好。 */
function renderToolbar() {
    const current = toolbarKey();
    document.getElementById('tb-screens').innerHTML =
        '<span class="group-label">畫面</span>' + TOOLBAR_SCREENS.map(([k, label]) => `<button data-tb-screen="${k}" class="${current === k ? 'on' : ''}">${label}</button>`).join('');
    document.querySelectorAll('[data-scenario]').forEach((b) => b.classList.toggle('on', b.dataset.scenario === state.scenario));
    document.querySelectorAll('[data-viewport]').forEach((b) => b.classList.toggle('on', b.dataset.viewport === state.viewport));
    document.querySelectorAll('[data-theme-btn]').forEach((b) => b.classList.toggle('on', b.dataset.themeBtn === state.theme));
    try {
        localStorage.setItem('m3-demo-prefs', JSON.stringify({ viewport: state.viewport, theme: state.theme }));
    } catch {
        // 無法存偏好時不影響 demo
    }
}

/** 工具列切換畫面：直接把 state 設成該畫面的情境。 */
const TOOLBAR_SETUP = {
    today: () => go('today'),
    meals: () => go('meals'),
    'form-new': () => openForm(newForm({ date: TODAY })),
    'form-edit': () => openForm(editForm(state.meals.find((m) => m.date === TODAY) || state.meals[0])),
    'form-again': () => openForm(againForm(state.meals.find((m) => m.date === addDays(TODAY, -1)) || state.meals[0])),
    'saved-foods': () => go('saved-foods'),
    plus: () => openSheet('plus'),
    water: () => openSheet('water'),
    'meal-actions': () => {
        state.screen = 'meals';
        openSheet('meal-actions', { mealId: mealsOn(state.mealsDate)[0]?.id ?? state.meals[0].id });
    },
    servings: () => {
        if (state.screen !== 'form') openForm(newForm({ date: TODAY }), false);
        state.form.pickTab = 'saved';
        openSheet('servings', { source: 'saved', foodId: savedFoodsSorted()[0].id, food: savedFoodsSorted()[0], k: 1.5 });
    },
};

/* ---------- 狀態操作 ---------- */

/** 依路徑（例如 form.items.0.kcal）設定 state 的值。 */
function setPath(path, value) {
    const keys = path.split('.');
    let obj = state;
    keys.slice(0, -1).forEach((k) => (obj = obj[k]));
    obj[keys.at(-1)] = value;
}

let toastTimer = null;

/** 顯示短暫提示，並重繪畫面。 */
function toast(text) {
    state.toast = text;
    render();
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        state.toast = null;
        document.querySelector('#app .toast')?.remove();
    }, 1800);
}

/** 切換頁面並關閉面板。 */
function go(screen) {
    state.screen = screen;
    state.sheet = null;
    state.errors = {};
    if (screen !== 'form') state.form = null;
    render();
}

/** 打開新增／編輯餐點頁。 */
function openForm(form, doRender = true) {
    state.form = form;
    state.screen = 'form';
    state.sheet = null;
    state.errors = {};
    if (doRender) render();
}

/** 打開底部面板。 */
function openSheet(sheet, data = {}) {
    state.sheet = sheet;
    state.sheetData = data;
    state.errors = {};
    render();
}

/** 關閉底部面板。 */
function closeSheet() {
    state.sheet = null;
    state.sheetData = {};
    state.errors = {};
    render();
}

/** 驗證餐點表單，回傳錯誤物件（key 為 data-bind 路徑）。 */
function validateForm() {
    const e = {};
    const f = state.form;
    if (!f.date) e['form.date'] = '請選擇日期。';
    else if (f.date > TODAY) e['form.date'] = '不能記錄未來的日期。';
    if (!f.items.length) e['form.items'] = '至少要有一個項目。';
    f.items.forEach((it, i) => {
        if (!it.name.trim()) e[`form.items.${i}.name`] = '請填寫名稱。';
        if (it.kcal === '') e[`form.items.${i}.kcal`] = '請填寫熱量。';
        else if (Number(it.kcal) < 0 || Number(it.kcal) > 5000) e[`form.items.${i}.kcal`] = '熱量需介於 0～5000 kcal。';
    });
    return e;
}

/** 驗證常用食物表單（含同名檢查）。 */
function validateFood(f) {
    const e = {};
    if (!f.name.trim()) e['sheetData.food.name'] = '請填寫名稱。';
    else if (findSaved(f.name, f.id)) e['sheetData.food.name'] = '常用食物裡已經有同名的項目。';
    if (f.kcal === '') e['sheetData.food.kcal'] = '請填寫熱量。';
    return e;
}

/** 記錄一筆喝水。 */
function addWater(ml) {
    state.waterLogs.push({ id: state.nextId++, time: NOW, ml });
    toast(`已記錄 ${num(ml)} ml`);
}

/** 表單還能再加幾個項目；超過上限時提示。 */
function roomFor(n) {
    if (state.form.items.length + n <= CONFIG.maxItems) return true;
    toast(`一餐最多 ${CONFIG.maxItems} 個項目`);
    return false;
}

const actions = {
    go: ({ screen }) => {
        if (screen === 'me') return toast('「我的」是 M1 已完成的畫面，demo 略過');
        if (screen === 'workout') return toast('健身在 M5 推出');
        go(screen);
    },
    'open-sheet': ({ sheet }) => openSheet(sheet),
    'close-sheet': closeSheet,
    dismiss: closeSheet,
    'm1-skip': () => toast('記錄體重是 M1 已完成的功能，demo 略過'),

    set: ({ path, value }) => {
        setPath(path, value);
        if (path === 'form.type') state.form.typeTouched = true;
        render();
    },

    // 飲食頁
    'date-prev': () => {
        state.mealsDate = addDays(state.mealsDate, -1);
        render();
    },
    'date-next': () => {
        state.mealsDate = addDays(state.mealsDate, 1) > TODAY ? TODAY : addDays(state.mealsDate, 1);
        render();
    },
    'date-today': () => {
        state.mealsDate = TODAY;
        render();
    },
    'new-meal': ({ type, today }) => openForm(newForm({ date: today || state.screen !== 'meals' ? TODAY : state.mealsDate, type: type || null })),
    'edit-meal': ({ id }) => openForm(editForm(state.meals.find((m) => m.id === Number(id)))),
    'meal-menu': ({ id }) => openSheet('meal-actions', { mealId: Number(id) }),
    'meal-again': ({ id }) => openForm(againForm(state.meals.find((m) => m.id === Number(id)))),
    'meal-delete-ask': ({ id }) => openSheet('meal-delete', { mealId: Number(id) }),
    'meal-delete-confirm': ({ id }) => {
        const m = state.meals.find((x) => x.id === Number(id));
        state.meals = state.meals.filter((x) => x.id !== m.id);
        state.sheet = null;
        if (state.screen === 'form') {
            state.mealsDate = m.date;
            state.screen = state.form.returnTo === 'today' ? 'today' : 'meals';
            state.form = null;
        }
        toast(`已刪除${MEAL_TYPES[m.type].label}`);
    },

    // 新增／編輯餐點
    'form-back': () => go(state.form.returnTo === 'today' ? 'today' : state.form.returnTo === 'saved-foods' ? 'saved-foods' : 'meals'),
    'form-delete': () => openSheet('meal-delete', { mealId: state.form.id }),
    'pick-food': ({ source, id, idx }) => {
        const f = source === 'saved' ? state.savedFoods.find((x) => x.id === Number(id)) : recentFoods()[Number(idx)];
        openSheet('servings', { source, foodId: f.id, food: f, k: 1 });
    },
    'servings-set': ({ k }) => {
        state.sheetData.k = Number(k);
        render();
    },
    'servings-add': () => {
        const { food: f, k, source, foodId } = state.sheetData;
        if (!roomFor(1)) return;
        state.form.items.push(toFormItem(scaleFood(f, k)));
        if (source === 'saved') state.savedFoods.find((x) => x.id === foodId).last_used = `${TODAY} ${NOW}`;
        state.sheet = null;
        toast(`已加入${f.name}${k === 1 ? '' : ` ×${k}`}`);
    },
    'copy-meal': ({ id }) => {
        const m = state.meals.find((x) => x.id === Number(id));
        if (!roomFor(m.items.length)) return;
        m.items.forEach((i) => state.form.items.push(toFormItem(i)));
        delete state.errors['form.items'];
        toast(`已加入 ${m.items.length} 個項目`);
    },
    'item-toggle': ({ i }) => {
        const it = state.form.items[Number(i)];
        it.open = !it.open;
        render();
    },
    'item-add': () => {
        if (!roomFor(1)) return;
        state.form.items.forEach((it) => (it.open = false));
        state.form.items.push(emptyItem(true));
        delete state.errors['form.items'];
        render();
        document.querySelector(`[data-bind="form.items.${state.form.items.length - 1}.name"]`)?.focus();
    },
    'item-remove': ({ i }) => {
        state.form.items.splice(Number(i), 1);
        state.errors = {};
        render();
    },
    'item-star': ({ i }) => {
        const it = state.form.items[Number(i)];
        if (!it.name.trim()) return toast('先填名稱才能加入常用食物');
        if (findSaved(it.name)) return toast('已經在常用食物裡');
        if (it.kcal === '') return toast('先填熱量才能加入常用食物');
        state.savedFoods.push({ id: state.nextId++, ...fromFormItem(it), last_used: null });
        toast(`已把「${it.name.trim()}」加入常用食物`);
    },
    estimate: ({ i }) => {
        const it = state.form.items[Number(i)];
        it.kcal = String(estimateKcal(it));
        render();
    },
    'form-save': () => {
        const f = state.form;
        state.errors = validateForm();
        if (Object.keys(state.errors).length) {
            f.items.forEach((it, i) => {
                if (state.errors[`form.items.${i}.name`] || state.errors[`form.items.${i}.kcal`]) it.open = true;
            });
            return render();
        }
        const data = { date: f.date, time: f.time, type: f.type, note: f.note.trim(), items: f.items.map(fromFormItem) };
        if (f.id) Object.assign(state.meals.find((m) => m.id === f.id), data);
        else state.meals.push({ id: state.nextId++, ...data });
        state.mealsDate = f.date;
        state.form = null;
        state.screen = 'meals';
        state.errors = {};
        toast(`已儲存${MEAL_TYPES[data.type].label}`);
    },

    // 喝水
    'water-add': ({ ml }) => addWater(Number(ml)),
    'water-custom-add': () => {
        const ml = Number(state.waterCustom);
        if (!Number.isInteger(ml) || ml < CONFIG.waterMin || ml > CONFIG.waterMax) {
            state.errors = { waterCustom: `請輸入 ${CONFIG.waterMin}～${num(CONFIG.waterMax)} ml 的整數。` };
            return render();
        }
        state.waterCustom = '';
        state.errors = {};
        addWater(ml);
    },
    'water-delete': ({ id }) => {
        state.waterLogs = state.waterLogs.filter((l) => l.id !== Number(id));
        toast('已刪除這筆紀錄');
    },

    // 常用食物
    'food-new': () => openSheet('food-edit', { food: { id: null, ...toFormItem(food('', '', null, null, null, null, null, null)) } }),
    'food-edit': ({ id }) => {
        const f = state.savedFoods.find((x) => x.id === Number(id));
        openSheet('food-edit', { food: { ...toFormItem(f), id: f.id } });
    },
    'food-save': () => {
        const f = state.sheetData.food;
        state.errors = validateFood(f);
        if (Object.keys(state.errors).length) return render();
        const data = fromFormItem(f);
        if (f.id) Object.assign(state.savedFoods.find((x) => x.id === f.id), data);
        else state.savedFoods.push({ id: state.nextId++, ...data, last_used: null });
        state.sheet = null;
        toast(`已儲存「${data.name}」`);
    },
    'food-delete-ask': () => {
        state.sheet = 'food-delete';
        render();
    },
    'food-delete-confirm': () => {
        const f = state.sheetData.food;
        state.savedFoods = state.savedFoods.filter((x) => x.id !== f.id);
        state.sheet = null;
        toast(`已刪除「${f.name}」`);
    },
};

/* ---------- 事件 ---------- */

document.getElementById('app').addEventListener('click', (e) => {
    // 飲食頁的日期文字點下去直接打開日期選擇器
    if (e.target.matches('.date-label input')) {
        try {
            e.target.showPicker();
        } catch {
            // 不支援 showPicker 的瀏覽器維持原生行為
        }
        return;
    }
    const el = e.target.closest('[data-action]');
    if (!el || el.disabled) return;
    // 點面板外的遮罩才關閉，點面板內部不關閉
    if (el.dataset.action === 'close-sheet' && e.target !== el) return;
    actions[el.dataset.action]?.(el.dataset, el);
});

document.getElementById('app').addEventListener('input', (e) => {
    const el = e.target.closest('[data-bind]');
    if (!el || el.dataset.change === 'render') return;
    setPath(el.dataset.bind, el.value);
    refreshLive();
});

// 日期、時間欄位在 change 時重繪：餐別依時間自動選擇、其他日期改用餐別代表時間
document.getElementById('app').addEventListener('change', (e) => {
    const el = e.target.closest('[data-change="render"]');
    if (!el) return;
    const bind = el.dataset.bind;
    if (bind === 'mealsDate') {
        state.mealsDate = el.value && el.value <= TODAY ? el.value : TODAY;
        return render();
    }
    const f = state.form;
    setPath(bind, el.value);
    if (bind === 'form.time') {
        f.timeTouched = true;
        if (!f.typeTouched && f.time) f.type = typeByTime(f.time);
    }
    if (bind === 'form.date' && !f.timeTouched) f.time = f.date === TODAY ? NOW : MEAL_TYPES[f.type].time;
    render();
});

document.querySelector('.toolbar').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    if (b.dataset.tbScreen) TOOLBAR_SETUP[b.dataset.tbScreen]();
    if (b.dataset.scenario) {
        Object.assign(state, seedData(b.dataset.scenario), { scenario: b.dataset.scenario, mealsDate: TODAY });
        go(state.screen === 'form' ? 'today' : state.screen);
    }
    if (b.dataset.viewport) {
        state.viewport = b.dataset.viewport;
        render();
    }
    if (b.dataset.themeBtn) {
        state.theme = b.dataset.themeBtn;
        render();
    }
});

render();
