/* ==========================================================================
   M4 照片分析 demo
   上半部是可操作的流程，下半部是每個狀態的靜態畫面。範例資料沿用 M3 demo 的使用者。
   ========================================================================== */

// 圖示由 icons.js 掛在 window 上
const { ICONS } = window;

// demo 的「現在」固定為晚上，方便看出「上傳照片以拍攝時間預設餐別」的差異
const NOW = '19:42';
const TODAY = '2026/11/21';
const DAILY_LIMIT = 20;

// 範例照片：雞腿便當（SVG 插畫，demo 用）
const SAMPLE_PHOTO =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">
<defs><linearGradient id="w" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#c8a27a"/><stop offset="1" stop-color="#9c7350"/></linearGradient>
<radialGradient id="l" cx=".35" cy=".3" r=".9"><stop offset="0" stop-color="#fff" stop-opacity=".25"/><stop offset="1" stop-color="#000" stop-opacity=".15"/></radialGradient></defs>
<rect width="400" height="300" fill="url(#w)"/>
<g stroke="#8a6444" stroke-opacity=".35"><path d="M0 40h400M0 120h400M0 205h400M0 270h400"/></g>
<rect x="42" y="38" width="316" height="226" rx="22" fill="#2b2b2b"/>
<rect x="54" y="50" width="292" height="202" rx="14" fill="#3a3a3a"/>
<rect x="62" y="58" width="150" height="186" rx="10" fill="#f4f1ea"/>
<g fill="#e4ded2"><circle cx="90" cy="90" r="9"/><circle cx="130" cy="120" r="11"/><circle cx="170" cy="85" r="8"/><circle cx="100" cy="170" r="10"/><circle cx="160" cy="200" r="9"/><circle cx="120" cy="215" r="7"/></g>
<g fill="#222"><circle cx="110" cy="100" r="1.6"/><circle cx="150" cy="140" r="1.6"/><circle cx="95" cy="140" r="1.6"/><circle cx="175" cy="175" r="1.6"/><circle cx="135" cy="190" r="1.6"/></g>
<path d="M222 70c40-14 98-8 112 26 12 30-14 58-52 62-26 3-36 18-50 30-10 8-26 2-24-12 2-12 14-16 18-28 6-18-28-56-4-78z" fill="#9b5a25"/>
<path d="M240 84c30-8 70-2 80 20" stroke="#c7843f" stroke-width="7" fill="none" stroke-linecap="round" opacity=".7"/>
<rect x="196" y="186" width="30" height="12" rx="6" fill="#efe3cf" transform="rotate(-35 211 192)"/>
<g fill="#4f9a3a"><ellipse cx="258" cy="212" rx="26" ry="16"/><ellipse cx="292" cy="204" rx="22" ry="15"/><ellipse cx="316" cy="226" rx="20" ry="13"/></g>
<g fill="#86c46b"><ellipse cx="270" cy="222" rx="16" ry="8"/><ellipse cx="305" cy="214" rx="12" ry="7"/></g>
<ellipse cx="318" cy="170" rx="22" ry="17" fill="#c58b45"/><ellipse cx="318" cy="170" rx="11" ry="9" fill="#f2b636"/>
<rect width="400" height="300" fill="url(#l)"/></svg>`);

// 分析結果（雞腿便當）；confidence 0～1，畫面分 高 ≥ 0.75、中 ≥ 0.5、低 < 0.5
const SAMPLE_ITEMS = [
    { name: '滷雞腿', portion: '1 隻', grams: 150, kcal: 330, protein_g: 28, carbs_g: 4, fat_g: 22, fiber_g: 0, confidence: 0.86 },
    { name: '白飯', portion: '1 碗', grams: 200, kcal: 280, protein_g: 5, carbs_g: 62, fat_g: 0.6, fiber_g: 0.6, confidence: 0.9 },
    { name: '炒高麗菜', portion: '1 份', grams: 80, kcal: 60, protein_g: 1.5, carbs_g: 5, fat_g: 4, fiber_g: 2, confidence: 0.68 },
    { name: '滷蛋', portion: '半顆', grams: 25, kcal: 38, protein_g: 3, carbs_g: 0.3, fat_g: 2.6, fiber_g: 0, confidence: 0.42 },
];

const NUTRIENTS = [
    { key: 'protein_g', label: '蛋白質', icon: 'drumstick', cls: 'n-protein' },
    { key: 'carbs_g', label: '碳水', icon: 'wheat', cls: 'n-carbs' },
    { key: 'fat_g', label: '脂肪', icon: 'nut', cls: 'n-fat' },
    { key: 'fiber_g', label: '纖維', icon: 'leaf', cls: 'n-fiber' },
];

const MEAL_TYPES = [
    { key: 'breakfast', label: '早餐', icon: 'sunrise' },
    { key: 'lunch', label: '午餐', icon: 'sun' },
    { key: 'dinner', label: '晚餐', icon: 'moon' },
    { key: 'snack', label: '點心', icon: 'cookie' },
];

// 失敗原因（規格 §3.1）
const ERRORS = {
    not_food: { title: '照片裡看不出食物', message: '換一張照片，或改手動輸入。' },
    busy: { title: 'Gemini 暫時忙碌', message: '免費額度暫時用完，稍等一下再試。' },
    timeout: { title: '分析太久沒有回應', message: '再試一次，或改手動輸入。' },
    failed: { title: '分析沒有完成', message: '再試一次，或改手動輸入。' },
};

const tool = { theme: 'light', device: 'phone', result: 'ok', quota: 17 };
const state = initialState();
let timer = null;

/** 流程的初始狀態。 */
function initialState() {
    return {
        screen: 'today',
        overlay: null,
        source: 'camera',
        photo: SAMPLE_PHOTO,
        photoTime: NOW,
        phase: 'ready',
        error: null,
        hint: '',
        quota: Number(tool.quota),
        items: [],
        openItem: null,
        toast: '',
        savedMeal: null,
    };
}

/** 輸出一個 SVG 圖示。 */
function icon(name, size = '', cls = '') {
    return `<svg class="icon ${size} ${cls}" viewBox="0 0 24 24" aria-hidden="true">${ICONS[name]}</svg>`;
}

/** 數字格式：整數不帶小數，其餘一位。 */
const fmt = (n) => (Number.isInteger(n) ? n.toLocaleString('en-US') : n.toFixed(1));

/** 依時間判斷預設餐別（M3 Q2）。 */
function mealTypeOf(time) {
    const h = Number(time.split(':')[0]);
    if (h >= 5 && h < 10) return 'breakfast';
    if (h >= 10 && h < 14) return 'lunch';
    if (h >= 17 && h < 21) return 'dinner';
    return 'snack';
}

/** 信心度分級。 */
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

/** 營養素列（圖示＋數值）。 */
function macros(item) {
    return NUTRIENTS.map((n) => `<span class="inline-n ${n.cls}">${icon(n.icon, 'xs')}<span class="muted">${fmt(item[n.key])}</span></span>`).join(
        ' ',
    );
}

/** 今天剩餘次數標籤。 */
function quotaChip(quota) {
    if (quota <= 0) return `<span class="quota out">${icon('sparkles', 'xs')}今天的 ${DAILY_LIMIT} 次已用完</span>`;
    return `<span class="quota ${quota <= 3 ? 'low' : ''}">${icon('sparkles', 'xs')}今天還能分析 ${quota} 次</span>`;
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
        <nav class="bottom-nav">
            ${nav('today', '今日', 'house')}
            ${nav('meals', '飲食', 'utensils')}
            <button data-action="open-quick" aria-label="快速新增"><span class="plus">${icon('plus')}</span></button>
            ${nav('workouts', '健身', 'dumbbell')}
            ${nav('me', '我的', 'user')}
        </nav>
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
            <div class="scroll"><div class="page-wrap ${view.narrow ? 'narrow' : ''}">${view.body}</div></div>
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

/** 覆蓋層：＋選單、離開確認。 */
function overlay(s, device) {
    if (s.overlay === 'quick') return quickAdd(device);
    if (s.overlay === 'leave') return leaveDialog();
    return '';
}

/* ---------- 畫面 ---------- */

/** 依目前狀態選擇畫面。 */
function screenView(s, device) {
    if (s.screen === 'analyze') return analyzeView(s, device);
    if (s.screen === 'confirm') return confirmView(s, device);
    if (s.screen === 'meals') return mealsView(s);
    if (s.screen === 'manual') return manualView();
    return todayView();
}

/** ＋選單：拍照、上傳照片各一格；桌機不顯示拍照。 */
function quickAdd(device) {
    const tile = (action, ic, label, cls = '') =>
        `<button data-action="${action}" class="qa-item ${cls}"><span class="ic">${icon(ic, 'lg')}</span>${label}</button>`;
    const soon = `<div class="qa-item off" aria-disabled="true"><span class="badge">${icon('clock', 'xs')}</span><span class="ic">${icon('dumbbell', 'lg')}</span>健身<span class="sr-only">（即將推出）</span></div>`;
    return `<div class="overlay" data-action="close-overlay">
        <div class="sheet" data-stop>
            <div class="grab"></div>
            <h3>快速新增</h3>
            <div class="qa">
                ${tile('noop', 'pencil', '記錄餐點')}
                ${device === 'desktop' ? '' : tile('pick-camera', 'camera', '拍照', 'new')}
                ${tile('pick-upload', 'imageUp', '上傳照片', 'new')}
                ${tile('noop', 'droplet', '喝水')}
                ${tile('noop', 'weight', '體重')}
                ${soon}
            </div>
        </div>
    </div>`;
}

/** 離開確認頁的確認對話框。 */
function leaveDialog() {
    return `<div class="overlay center">
        <div class="dialog">
            <h3>放棄這次分析？</h3>
            <p>分析結果不會儲存，照片也不會留下。這次分析仍算在今天的次數內。</p>
            <div class="btn-row">
                <button class="btn" data-action="leave-cancel">繼續編輯</button>
                <button class="btn primary" data-action="leave-confirm">放棄</button>
            </div>
        </div>
    </div>`;
}

/** 今日頁（流程起點，簡化）。 */
function todayView() {
    return {
        nav: 'today',
        body: `
        <div class="page-head"><h3>今日</h3></div>
        <div class="card">
            <h4>${icon('utensils', 'sm')}今日餐點</h4>
            <div class="meal-card" style="border:0;padding:4px 0">
                <span class="type-icon">${icon('sunrise', 'sm')}</span>
                <div class="grow"><div class="names">茶葉蛋、無糖豆漿</div><div class="t">08:10</div></div>
                <span class="kcal">211<small>kcal</small></span>
            </div>
        </div>
        <div class="note">demo：按下方的 ＋（桌機是側欄的「快速新增」）開始。</div>`,
    };
}

/** 分析頁：照片、補充說明、剩餘次數與動作。 */
function analyzeView(s, device) {
    const analyzing = s.phase === 'analyzing';
    const out = s.quota <= 0 && s.phase !== 'analyzing';
    const isCamera = s.source === 'camera';
    const timeChip = isCamera ? `${icon('clock', 'xs')}現在 ${s.photoTime}` : `${icon('clock', 'xs')}拍攝於 今天 ${s.photoTime}`;
    const swap = analyzing
        ? ''
        : `<button class="swap" data-action="swap">${icon(isCamera ? 'camera' : 'imageUp', 'xs')}${isCamera ? '重拍' : '換一張'}</button>`;

    let status = '';
    if (analyzing) {
        status = `<div class="card analyzing-text">${icon('loaderCircle', 'lg', 'spin')}<div><b>分析中，大約 5～20 秒</b><div class="xsmall muted">請停留在這個畫面，離開會中斷分析。</div></div></div>`;
    } else if (s.phase === 'error') {
        const e = ERRORS[s.error];
        status = `<div class="callout error">${icon('circleAlert')}<div><b>${e.title}</b><p>${e.message}</p></div></div>`;
    } else if (out) {
        status = `<div class="callout error">${icon('circleAlert')}<div><b>今天的 ${DAILY_LIMIT} 次已用完</b><p>明天再試，或改手動輸入。</p></div></div>`;
    }

    const photo = `<div class="photo ${analyzing ? 'scanning' : ''}"><img src="${s.photo}" alt="餐點照片" />${analyzing ? '<div class="beam"></div>' : ''}<span class="chip">${timeChip}</span>${swap}</div>`;
    const hint = `<div class="field">
            <label for="hint-${device}">補充說明</label>
            <textarea id="hint-${device}" class="textarea" data-input="hint" maxlength="200" rows="2" placeholder="例如：飯只吃一半、醬另外放" ${analyzing ? 'disabled' : ''}>${s.hint}</textarea>
            <div class="counter"><span data-counter>${s.hint.length}</span>/200</div>
        </div>`;

    let actions;
    if (analyzing) {
        actions = `<button class="btn primary block" disabled>${icon('loaderCircle', 'sm', 'spin')}分析中…</button><button class="btn ghost" data-action="cancel">取消</button>`;
    } else if (s.phase === 'error') {
        const first =
            s.error === 'not_food'
                ? `<button class="btn primary" data-action="swap">${icon(isCamera ? 'camera' : 'imageUp', 'sm')}${isCamera ? '重拍' : '換一張'}</button>`
                : `<button class="btn primary" data-action="start" ${s.quota <= 0 ? 'disabled' : ''}>${icon('refreshCw', 'sm')}重新分析</button>`;
        actions = `<div class="btn-row" style="flex:1">${first}<button class="btn" data-action="manual">${icon('pencil', 'sm')}改手動輸入</button></div>`;
    } else if (out) {
        actions = `<button class="btn block" data-action="manual">${icon('pencil', 'sm')}改手動輸入</button>`;
    } else {
        actions = `<button class="btn primary block" data-action="start">${icon('sparkles', 'sm')}開始分析</button>`;
    }

    const head = `<div class="page-head"><button class="back" data-action="back-analyze" aria-label="返回">${icon('chevronLeft')}</button><h3>分析餐點照片</h3></div>`;

    if (device === 'desktop') {
        return {
            nav: 'meals',
            body: `${head}
            <div class="cols">
                <div>${photo}</div>
                <div class="stack">
                    ${status}
                    <div class="card stack">${hint}<div>${quotaChip(s.quota)}</div></div>
                    <div class="btn-row">${actions.replace('btn primary block', 'btn primary').replace('btn block', 'btn')}</div>
                </div>
            </div>`,
        };
    }

    return {
        nav: 'meals',
        body: `${head}
            <div class="stack">
                ${photo}
                ${status}
                <div class="card stack">${hint}<div>${quotaChip(s.quota)}</div></div>
            </div>`,
        bar: `<div class="sticky-bar">${actions}</div>`,
    };
}

/** 單一食物項目卡（收合／展開）。 */
function itemCard(item, i, open) {
    const meta = [item.portion, item.grams ? `${fmt(item.grams)} g` : ''].filter(Boolean).join(' · ');
    const unit = (label, value, u, ic = '', cls = '', req = false) =>
        `<div class="field"><span class="label">${ic ? icon(ic, 'xs', cls) : ''}${label}${req ? '<span class="req">*</span>' : ''}</span><div class="unit-input"><span>${value}</span><span>${u}</span></div></div>`;
    const body = open
        ? `<div class="body">
            ${unit('名稱', item.name, '', '', '', true)}
            <div class="grid2">${unit('份量描述', item.portion, '')}${unit('重量', fmt(item.grams), 'g')}</div>
            ${unit('熱量', fmt(item.kcal), 'kcal', 'flame', '', true)}
            <div class="grid2">${NUTRIENTS.map((n) => unit(n.label, fmt(item[n.key]), 'g', n.icon, n.cls)).join('')}</div>
            ${confLevel(item.confidence).label === '低' ? `<div class="callout brand small">${icon('info', 'sm')}<div>這個項目 AI 比較沒把握，份量或名稱可能不準，請對照照片調整。</div></div>` : ''}
            <button class="link-btn" style="color:hsl(var(--destructive))" data-action="remove-item" data-i="${i}">${icon('trash2', 'sm')}刪除這個項目</button>
        </div>`
        : '';
    return `<div class="item ${confLevel(item.confidence).cls} ${open ? 'open' : ''}">
        <div class="head">
            <button class="main" data-action="toggle-item" data-i="${i}">
                <div class="name"><span>${item.name}</span>${confPill(item.confidence)}</div>
                <div class="meta"><span>${meta}</span>${macros(item)}</div>
            </button>
            <span class="kcal">${fmt(item.kcal)}<small>kcal</small></span>
            <button class="ib" aria-label="加入常用食物">${icon('star', 'sm')}</button>
            <button class="ib" data-action="toggle-item" data-i="${i}" aria-label="${open ? '收合' : '展開'}">${icon(open ? 'chevronUp' : 'chevronDown', 'sm')}</button>
        </div>
        ${body}
    </div>`;
}

/** 確認頁：沿用新增餐點表單，上方加分析摘要，項目帶信心度。 */
function confirmView(s, device) {
    const type = mealTypeOf(s.photoTime);
    const total = s.items.reduce((sum, it) => sum + it.kcal, 0);
    const hasLow = s.items.some((it) => confLevel(it.confidence).label === '低');
    const head = `<div class="page-head"><button class="back" data-action="back-confirm" aria-label="返回">${icon('chevronLeft')}</button><h3>確認分析結果</h3></div>`;

    const summary = `<div class="card">
        <div class="summary">
            <div class="thumb"><img src="${s.photo}" alt="餐點照片" /></div>
            <div>
                <b>${icon('sparkles', 'sm')}辨識出 ${s.items.length} 個項目</b>
                <p>數值是 AI 估算，請檢查後儲存。${hasLow ? '標示「低」的項目建議特別確認。' : ''}</p>
            </div>
        </div>
        ${s.hint ? `<div class="quote">補充說明：${s.hint}</div>` : ''}
    </div>`;

    const when = `<div class="card">
        <div class="grid2">
            <div class="field"><span class="label">日期</span><div class="unit-input"><span>${TODAY}</span><span>${icon('calendar', 'sm')}</span></div></div>
            <div class="field"><span class="label">時間</span><div class="unit-input"><span>${s.photoTime}</span><span>${icon('clock', 'sm')}</span></div></div>
        </div>
        <div class="field" style="margin-top:12px"><span class="label">餐別</span>
            <div class="segmented">${MEAL_TYPES.map((t) => `<span class="${t.key === type ? 'on' : ''}">${t.label}</span>`).join('')}</div>
            <div class="hint-line">${s.source === 'upload' ? `${icon('imageUp', 'xs')}時間依照片拍攝時間帶入，可以修改。` : '依時間自動選擇，可以手動改。'}</div>
        </div>
    </div>`;

    const items = `<div class="card">
        <h4>這一餐的項目 <span class="quota" style="padding:1px 8px">${s.items.length}</span></h4>
        ${s.items.map((it, i) => itemCard(it, i, s.openItem === i)).join('')}
        <button class="btn block" style="margin-top:12px">${icon('plus', 'sm')}手動新增項目</button>
    </div>`;
    const picker = `<div class="card"><div class="collapsed-row">快速帶入<span class="xsmall muted" style="font-weight:400">常用食物、最近吃過</span>${icon('chevronDown', 'sm')}</div></div>`;
    const note = `<div class="card field"><label>備註</label><textarea class="textarea" rows="2" placeholder="例如：外食、聚餐"></textarea></div>`;
    const bar = `<div class="sticky-bar"><div class="total"><small>本餐合計</small><b>${fmt(total)} kcal</b></div><button class="btn primary" data-action="save">儲存</button></div>`;

    if (device === 'desktop') {
        return {
            nav: 'meals',
            body: `${head}<div class="cols"><div class="stack">${summary}${when}${picker}</div><div class="stack">${items}${note}</div></div>`,
            bar,
        };
    }
    return { nav: 'meals', body: `${head}<div class="stack">${summary}${when}${items}${picker}${note}</div>`, bar };
}

/** 飲食頁：存檔後回到當天列表，新餐點以「照片」標記。 */
function mealsView(s) {
    const saved = s.savedMeal;
    const breakfast = `<div class="meal-group"><div class="group-title"><span class="type-icon">${icon('sunrise', 'xs')}</span>早餐</div>
        <div class="meal-card"><div class="grow"><div class="t">08:10</div><div class="names">茶葉蛋、無糖豆漿</div></div><span class="kcal">211<small>kcal</small></span><button class="menu" aria-label="更多">${icon('ellipsisVertical', 'sm')}</button></div></div>`;
    let fresh = '';
    if (saved) {
        const t = MEAL_TYPES.find((m) => m.key === saved.type);
        fresh = `<div class="meal-group"><div class="group-title"><span class="type-icon">${icon(t.icon, 'xs')}</span>${t.label}</div>
        <div class="meal-card fresh"><div class="grow"><div class="t">${saved.time}<span class="ai" title="由照片分析建立">${icon('camera', 'xs')}照片</span></div><div class="names">${saved.names}</div></div><span class="kcal">${fmt(saved.kcal)}<small>kcal</small></span><button class="menu" aria-label="更多">${icon('ellipsisVertical', 'sm')}</button></div></div>`;
    }
    const total = 211 + (saved ? saved.kcal : 0);
    const groups = saved && saved.type === 'breakfast' ? fresh + breakfast : breakfast + fresh;
    return {
        nav: 'meals',
        body: `<div class="page-head"><h3>飲食</h3></div>
        <div class="day-head"><b>今天</b><span class="day-total num">${fmt(total)} kcal</span></div>
        ${groups}`,
    };
}

/** 改手動輸入：進 M3 既有的空白新增餐點表單（demo 只放說明）。 */
function manualView() {
    return {
        nav: 'meals',
        body: `<div class="page-head"><button class="back" data-action="nav-today" aria-label="返回">${icon('chevronLeft')}</button><h3>新增餐點</h3></div>
        <div class="callout">${icon('info')}<div><b>這裡是 M3 現有的新增餐點表單（空白）</b><p>照片已丟棄；餐別與時間依現在時間預設。</p></div></div>`,
    };
}

/* ---------- 流程操作 ---------- */

/** 重新渲染流程區。 */
function renderFlow() {
    document.getElementById('flow').innerHTML = frame(tool.device, state);
}

/** 依時間格式化 Date 為 HH:MM。 */
const hhmm = (d) => `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;

/** 進入分析頁。 */
function openAnalyze(source) {
    Object.assign(state, {
        screen: 'analyze',
        overlay: null,
        source,
        photo: SAMPLE_PHOTO,
        photoTime: source === 'camera' ? NOW : '12:31',
        phase: 'ready',
        error: null,
        hint: '',
        toast: '',
    });
}

/** 模擬送出分析：先扣次數，2 秒後依工具列設定回傳結果。 */
function startAnalysis() {
    if (state.quota <= 0) return;
    state.quota -= 1;
    state.phase = 'analyzing';
    state.error = null;
    timer = setTimeout(() => {
        if (tool.result === 'ok') {
            Object.assign(state, { screen: 'confirm', phase: 'ready', items: SAMPLE_ITEMS.map((it) => ({ ...it })), openItem: null });
        } else {
            Object.assign(state, { phase: 'error', error: tool.result });
        }
        renderFlow();
    }, 2200);
}

/** 存檔：回到飲食頁並顯示 toast。 */
function saveMeal() {
    const type = mealTypeOf(state.photoTime);
    const label = MEAL_TYPES.find((m) => m.key === type).label;
    state.savedMeal = {
        type,
        time: state.photoTime,
        names: state.items.map((it) => it.name).join('、'),
        kcal: state.items.reduce((sum, it) => sum + it.kcal, 0),
    };
    Object.assign(state, { screen: 'meals', toast: `已新增${label}` });
    setTimeout(() => {
        state.toast = '';
        renderFlow();
    }, 2500);
}

const ACTIONS = {
    'open-quick': () => (state.overlay = 'quick'),
    'close-overlay': () => (state.overlay = null),
    'pick-camera': () => openAnalyze('camera'),
    'pick-upload': () => openAnalyze('upload'),
    noop: () => (state.overlay = null),
    swap: () => document.getElementById(state.source === 'camera' ? 'file-camera' : 'file-upload').click(),
    start: startAnalysis,
    cancel: () => {
        clearTimeout(timer);
        state.phase = 'ready';
    },
    manual: () => Object.assign(state, { screen: 'manual', phase: 'ready' }),
    'back-analyze': () => {
        clearTimeout(timer);
        Object.assign(state, { screen: 'today', phase: 'ready' });
    },
    'back-confirm': () => (state.overlay = 'leave'),
    'leave-cancel': () => (state.overlay = null),
    'leave-confirm': () => Object.assign(state, { screen: 'today', overlay: null }),
    'toggle-item': (el) => {
        const i = Number(el.dataset.i);
        state.openItem = state.openItem === i ? null : i;
    },
    'remove-item': (el) => {
        state.items.splice(Number(el.dataset.i), 1);
        state.openItem = null;
    },
    save: saveMeal,
    'nav-today': () => Object.assign(state, { screen: 'today', overlay: null }),
    'nav-meals': () => Object.assign(state, { screen: 'meals', overlay: null }),
};

/** 流程區的點擊事件（以 data-action 分派）。 */
function onFlowClick(e) {
    const el = e.target.closest('[data-action]');
    if (!el) return;
    // 點面板本身不關閉
    if (el.dataset.action === 'close-overlay' && e.target.closest('[data-stop]')) return;
    const fn = ACTIONS[el.dataset.action];
    if (!fn) return;
    // 按鈕觸發檔案選擇時不重繪
    if (el.dataset.action === 'swap') return fn(el);
    fn(el);
    renderFlow();
}

/** 補充說明輸入：只更新字數，不重繪（保留游標）。 */
function onFlowInput(e) {
    if (e.target.dataset.input !== 'hint') return;
    state.hint = e.target.value;
    const counter = e.target.closest('.field').querySelector('[data-counter]');
    if (counter) counter.textContent = state.hint.length;
}

/** 選了自己的照片：只在瀏覽器預覽，不上傳。拍照用現在時間，上傳用檔案時間代替 EXIF 拍攝時間。 */
function onFilePicked(e) {
    const file = e.target.files[0];
    if (!file) return;
    Object.assign(state, {
        photo: URL.createObjectURL(file),
        photoTime: e.target.id === 'file-camera' ? NOW : hhmm(new Date(file.lastModified)),
        phase: 'ready',
        error: null,
    });
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
    return frame(device, { ...initialState(), quota: 17, ...patch }, extraCls);
}

/** 渲染畫面一覽。 */
function renderGallery() {
    const items = SAMPLE_ITEMS.map((it) => ({ ...it }));
    const base = { screen: 'analyze', photo: SAMPLE_PHOTO };
    document.getElementById('gallery').innerHTML = [
        shot('m4-01', '＋選單', '「拍照」「上傳照片」各一格，點了直接進入；原本的「拍照分析」拿掉。', still('phone', { overlay: 'quick' })),
        shot(
            'm4-02',
            '分析頁：拍照',
            '照片下方標示「現在」；可重拍；補充說明選填；顯示今天剩餘次數。',
            still('phone', { ...base, source: 'camera', photoTime: NOW }),
        ),
        shot(
            'm4-03',
            '分析頁：上傳照片',
            '標示照片拍攝時間（之後帶入用餐時間）；可換一張。',
            still('phone', { ...base, source: 'upload', photoTime: '12:31', hint: '飯只吃一半，滷蛋是半顆' }),
        ),
        shot(
            'm4-04',
            '分析中',
            '照片上有掃描動畫；補充說明鎖定；可取消（已送出的仍算一次）。',
            still('phone', { ...base, source: 'upload', photoTime: '12:31', phase: 'analyzing', quota: 16 }),
        ),
        shot(
            'm4-05',
            '分析失敗：可重試',
            'Gemini 忙碌、逾時、其他錯誤共用此版型，主按鈕「重新分析」用同一張照片。',
            still('phone', { ...base, phase: 'error', error: 'busy', quota: 16 }),
        ),
        shot(
            'm4-06',
            '分析失敗：看不出食物',
            '重試同一張沒有意義，主按鈕改成「重拍／換一張」。',
            still('phone', { ...base, source: 'upload', photoTime: '12:31', phase: 'error', error: 'not_food', quota: 16 }),
        ),
        shot('m4-07', '今天次數用完', '不能送出分析，只留「改手動輸入」。', still('phone', { ...base, quota: 0 })),
        shot(
            'm4-08',
            '確認分析結果',
            '沿用新增餐點表單；上方摘要＋縮圖；每個項目有信心度，「低」的外框與標籤用橘色，展開後有提醒。',
            still('phone', { screen: 'confirm', source: 'upload', photoTime: '12:31', hint: '飯只吃一半，滷蛋是半顆', items, openItem: 3 }, 'short'),
        ),
        shot(
            'm4-09',
            '離開確認頁',
            '按返回時詢問；放棄後照片與結果都不保留。',
            still('phone', { screen: 'confirm', source: 'upload', photoTime: '12:31', items, overlay: 'leave' }),
        ),
        shot(
            'm4-10',
            '存檔後回飲食頁',
            'toast 提示；由照片建立的餐點在時間旁標「照片」（可不做）。',
            still('phone', {
                screen: 'meals',
                toast: '已新增午餐',
                savedMeal: { type: 'lunch', time: '12:31', names: '滷雞腿、白飯、炒高麗菜、滷蛋', kcal: 708 },
            }),
        ),
        shot('m4-11', '桌機：＋選單', '桌機沒有相機，只有「上傳照片」。', still('desktop', { overlay: 'quick' }), { wide: true }),
        shot('m4-12', '桌機：分析頁', '左邊照片，右邊補充說明與按鈕。', still('desktop', { ...base, source: 'upload', photoTime: '12:31' }), {
            wide: true,
        }),
        shot(
            'm4-13',
            '桌機：確認分析結果',
            '兩欄：左邊摘要、時間、快速帶入；右邊項目與備註。',
            still('desktop', { screen: 'confirm', source: 'upload', photoTime: '12:31', items, openItem: null }),
            {
                wide: true,
            },
        ),
    ].join('');
}

/* ---------- 工具列 ---------- */

/** 工具列：主題、裝置、模擬結果、剩餘次數、重置。 */
function onToolbar(e) {
    const el = e.target.closest('[data-tool]');
    if (!el || el.tagName === 'SELECT') return;
    const { tool: key, value } = el.dataset;
    if (key === 'reset') {
        clearTimeout(timer);
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
    renderFlow();
}

document.querySelector('.toolbar').addEventListener('click', onToolbar);
document.querySelector('.toolbar').addEventListener('change', onToolbarSelect);
document.getElementById('flow').addEventListener('click', onFlowClick);
document.getElementById('flow').addEventListener('input', onFlowInput);
document.getElementById('file-camera').addEventListener('change', onFilePicked);
document.getElementById('file-upload').addEventListener('change', onFilePicked);

// iOS Safari 忽略 user-scalable=no，另外擋兩指縮放
document.addEventListener('gesturestart', (e) => e.preventDefault());

document.body.dataset.theme = tool.theme;
renderFlow();
renderGallery();
