/* ==========================================================================
   U1 介面精簡 demo
   每個主題左右對照「原本」與「改後」；範例資料沿用 M3 demo 的使用者。
   ========================================================================== */

const DATA = {
    calorieTarget: 1678,
    consumedKcal: 820,
    proteinTarget: 141,
    consumedProtein: 62,
    totals: { protein_g: 62, carbs_g: 88, fat_g: 31, fiber_g: 6 },
    water: { ml: 1250, target: 2750 },
    meals: [
        { type: 'breakfast', label: '早餐', icon: 'sunrise', time: '08:10', items: ['茶葉蛋', '無糖豆漿'], kcal: 211, note: '' },
        { type: 'lunch', label: '午餐', icon: 'sun', time: '12:35', items: ['雞腿便當', '燙青菜', '紅茶'], kcal: 609, note: '公司附近' },
    ],
    savedFoods: [
        { name: '茶葉蛋', portion: '1 顆', grams: 55, kcal: 75, p: 7, c: 1, f: 5, used: '今天' },
        { name: '無糖豆漿', portion: '1 杯', grams: 400, kcal: 136, p: 14, c: 4, f: 8, used: '昨天' },
        { name: '鮪魚蛋餅', portion: '', grams: null, kcal: 320, p: 15, c: 32, f: 14, used: null },
    ],
};

// 營養素：圖示與顏色在改後版本使用
const NUTRIENTS = [
    { key: 'protein_g', label: '蛋白質', icon: 'drumstick', cls: 'n-protein' },
    { key: 'carbs_g', label: '碳水', icon: 'wheat', cls: 'n-carbs' },
    { key: 'fat_g', label: '脂肪', icon: 'nut', cls: 'n-fat' },
    { key: 'fiber_g', label: '纖維', icon: 'leaf', cls: 'n-fiber' },
];

const state = { infoOpen: false };

/** 輸出一個 SVG 圖示。 */
function icon(name, size = '', cls = '') {
    return `<svg class="icon ${size} ${cls}" viewBox="0 0 24 24" aria-hidden="true">${ICONS[name]}</svg>`;
}

/** 千分位。 */
const fmt = (n) => n.toLocaleString('en-US');

/** 對照區塊：標題、說明、左右兩欄。 */
function topic(tags, title, desc, before, after, note = '') {
    const badge = tags.map((t) => `<span class="tag">${t}</span>`).join('');
    return `
    <section class="topic">
        <h2>${badge}${title}</h2>
        <p>${desc}</p>
        <div class="pair">
            <div class="side before"><div class="side-label">原本</div>${before}</div>
            <div class="side after"><div class="side-label">改後</div>${after}${note ? `<div class="note">${note}</div>` : ''}</div>
        </div>
    </section>`;
}

/** 營養素四格：原本是文字標籤在上。 */
function nutrientGridBefore() {
    return `<div class="nutrients">${NUTRIENTS.map(
        (n) => `<div><div class="label">${n.label}</div><b>${DATA.totals[n.key]}<small>g</small></b></div>`,
    ).join('')}</div>`;
}

/** 營養素四格：改後是彩色圖示＋數值，小字標籤留在下方。 */
function nutrientGridAfter() {
    return `<div class="nutrients v2">${NUTRIENTS.map(
        (n) => `<div>${icon(n.icon, 'sm', n.cls)}<b>${DATA.totals[n.key]}<small>g</small></b><div class="label">${n.label}</div></div>`,
    ).join('')}</div>`;
}

/** 單位輸入框外觀。 */
function unitInput(value, unit) {
    return `<div class="unit-input"><span>${value}</span><span>${unit}</span></div>`;
}

/** 餐點項目的營養素欄位：原本每欄都寫「（選填）」。 */
function formBefore() {
    return `<div class="card stack">
        <div class="field"><label>名稱</label>${unitInput('雞腿便當', '')}</div>
        <div class="grid2">
            <div class="field"><label>份量描述（選填）</label>${unitInput('1 個', '')}</div>
            <div class="field"><label>重量（選填）</label>${unitInput('', 'g')}</div>
        </div>
        <div class="field"><label>熱量</label>${unitInput('520', 'kcal')}</div>
        <div class="grid2">${NUTRIENTS.map((n) => `<div class="field"><label>${n.label}（選填）</label>${unitInput('', 'g')}</div>`).join('')}</div>
    </div>`;
}

/** 餐點項目的營養素欄位：改後必填打星號、選填不另外標示，營養素前加圖示。 */
function formAfter() {
    return `<div class="card stack">
        <div class="field"><label>名稱<span class="req">*</span></label>${unitInput('雞腿便當', '')}</div>
        <div class="grid2">
            <div class="field"><label>份量描述</label>${unitInput('1 個', '')}</div>
            <div class="field"><label>重量</label>${unitInput('', 'g')}</div>
        </div>
        <div class="field"><label>${icon('flame', 'xs')}熱量<span class="req">*</span></label>${unitInput('520', 'kcal')}</div>
        <div class="grid2">${NUTRIENTS.map(
            (n) => `<div class="field"><label>${icon(n.icon, 'xs', n.cls)}${n.label}</label>${unitInput('', 'g')}</div>`,
        ).join('')}</div>
    </div>`;
}

/** 熱量環形。 */
function ring(over = false) {
    const r = 52;
    const c = 2 * Math.PI * r;
    const progress = Math.min(1, DATA.consumedKcal / DATA.calorieTarget);
    return `<div class="ring">
        <svg viewBox="0 0 120 120"><circle cx="60" cy="60" r="${r}" fill="none" class="track" stroke-width="12"/>
        <circle cx="60" cy="60" r="${r}" fill="none" class="bar" stroke-width="12" stroke-linecap="round" stroke-dasharray="${c}" stroke-dashoffset="${c * (1 - progress)}"/></svg>
        <div class="center"><b class="num">${fmt(DATA.calorieTarget - DATA.consumedKcal)}</b><span>${over ? '超過 kcal' : '剩餘 kcal'}</span></div>
    </div>`;
}

/** 蛋白質進度：原本是文字標題。 */
function proteinBefore() {
    const pct = (DATA.consumedProtein / DATA.proteinTarget) * 100;
    return `<div style="margin-top:16px">
        <div style="display:flex;justify-content:space-between;font-size:14px"><span>蛋白質</span><span class="muted num">${DATA.consumedProtein} / ${DATA.proteinTarget} g</span></div>
        <div class="progress"><div style="width:${pct}%"></div></div>
        <div style="margin-top:8px;font-size:14px">還差 <b>${DATA.proteinTarget - DATA.consumedProtein} g</b></div>
    </div>`;
}

/** 蛋白質進度：改後以雞腿圖示帶出，進度條改蛋白質色，「還差」併到同一行。 */
function proteinAfter() {
    const pct = (DATA.consumedProtein / DATA.proteinTarget) * 100;
    return `<div style="margin-top:16px">
        <div style="display:flex;align-items:center;gap:6px;font-size:14px">
            ${icon('drumstick', 'sm', 'n-protein')}<span class="xsmall muted">蛋白質</span>
            <span class="num" style="margin-left:auto"><b>${DATA.consumedProtein}</b><span class="muted"> / ${DATA.proteinTarget} g</span></span>
        </div>
        <div class="progress"><div class="bg-protein" style="width:${pct}%"></div></div>
        <div class="small muted" style="margin-top:6px;text-align:right">−${DATA.proteinTarget - DATA.consumedProtein} g</div>
    </div>`;
}

/** 今日熱量卡。 */
function targetsCard(after) {
    const right = after
        ? `<div style="flex:1">
            <div class="stat"><span class="chip">${icon('target', 'sm')}</span><div><b class="num">${fmt(DATA.calorieTarget)}</b> <span class="small muted">kcal</span></div></div>
            <div class="stat"><span class="chip brand">${icon('utensils', 'sm')}</span><div><b class="num">${fmt(DATA.consumedKcal)}</b> <span class="small muted">kcal</span></div></div>
        </div>`
        : `<div style="flex:1">
            <div class="small muted">熱量目標</div><div class="num" style="font-size:20px;font-weight:700">${fmt(DATA.calorieTarget)} kcal</div>
            <div class="small muted" style="margin-top:8px">已攝取</div><div class="num" style="font-weight:600">${fmt(DATA.consumedKcal)} kcal</div>
        </div>`;
    return `<div class="card"><div class="targets">${ring()}${right}</div>${after ? proteinAfter() : proteinBefore()}</div>`;
}

/** 今日餐點卡與喝水卡。 */
function todayCards(after) {
    const link = (text) => (after ? `<button class="icon-link" aria-label="${text}">${icon('chevronRight', 'sm')}</button>` : `<button class="link">${text}${icon('chevronRight', 'sm')}</button>`);
    const meals = DATA.meals
        .map(
            (m) => `<div class="row">
            <span class="type-icon">${icon(m.icon)}</span>
            <div class="grow">
                <div class="xsmall muted num">${after ? m.time : `${m.label} · ${m.time}`}</div>
                <div class="truncate" style="font-size:14px;font-weight:600">${m.items.join('、')}</div>
            </div>
            <span class="kcal">${m.kcal} <small>kcal</small></span>
        </div>`,
        )
        .join('');
    const pct = (DATA.water.ml / DATA.water.target) * 100;
    return `
    <div class="card">
        <div class="card-title">${icon('utensils', 'sm')} 今日餐點${link('飲食頁')}</div>
        ${meals}
        <button class="btn">${icon('plus', 'sm')} 記錄餐點</button>
    </div>
    <div class="card">
        <div class="card-title">${icon('droplet', 'sm', 'water-icon')} 喝水${link('紀錄')}</div>
        <div><span class="water-amount num">${fmt(DATA.water.ml)}</span> <span class="muted small">/ ${fmt(DATA.water.target)} ml</span></div>
        <div class="water-bar"><div style="width:${pct}%"></div></div>
        <div class="quick"><button>+250 ml</button><button>+500 ml</button></div>
    </div>`;
}

/** ＋選單：原本每格有說明文字。 */
function quickAddBefore() {
    const item = (ic, title, sub, off = false) => `<div class="qa-item ${off ? 'off' : ''}">
        ${off ? '<span class="badge">即將推出</span>' : ''}
        <span class="ic">${icon(ic)}</span>
        <div><b>${title}</b><div class="small ${off ? '' : 'muted'}">${sub}</div></div>
    </div>`;
    return `<div class="sheet"><h3>快速新增</h3><p class="desc">選擇要記錄的項目</p>
        <div class="qa">
            ${item('camera', '拍照分析', 'AI 估算營養素', true)}
            ${item('pencil', '手動記錄', '輸入餐點內容')}
            ${item('dumbbell', '健身', '有氧、重訓', true)}
            ${item('droplet', '喝水', '+250 / +500 ml')}
            ${item('weight', '記錄體重', '建議每天固定時間量')}
        </div></div>`;
}

/** ＋選單：改後拿掉說明，可用的排前面，即將推出改成時鐘小標記並淡化。 */
function quickAddAfter() {
    const item = (ic, title, off = false) => `<div class="qa-item ${off ? 'off' : ''}">
        ${off ? `<span class="badge" title="即將推出">${icon('clock')}</span>` : ''}
        <span class="ic">${icon(ic, 'lg')}</span><b>${title}</b>
    </div>`;
    return `<div class="sheet"><h3 style="margin-bottom:16px">快速新增</h3>
        <div class="qa v2">
            ${item('pencil', '記錄餐點')}
            ${item('droplet', '喝水')}
            ${item('weight', '體重')}
            ${item('camera', '拍照分析', true)}
            ${item('dumbbell', '健身', true)}
        </div></div>`;
}

/** 飲食頁的午餐群組與餐點卡。 */
function mealCard(after) {
    const m = DATA.meals[1];
    const sub = after ? `${m.time}${m.note ? ` · ${m.note}` : ''}` : `${m.time} · ${m.items.length} 個項目${m.note ? ` · ${m.note}` : ''}`;
    return `<div class="group-title"><span class="type-icon">${icon(m.icon)}</span>${m.label}<span class="small muted num">${m.kcal} kcal</span></div>
    <div class="meal-card">
        <div class="body">
            <div class="grow"><div class="truncate" style="font-weight:600">${m.items.join('、')}</div><div class="truncate xsmall muted" style="margin-top:2px">${sub}</div></div>
            <span class="kcal" style="font-size:16px">${m.kcal}<small> kcal</small></span>
        </div>
        <button class="menu">${icon('ellipsis')}</button>
    </div>`;
}

/** 常用食物頁：頁首與列表。 */
function savedFoods(after) {
    const head = `<div class="page-head">
        <button class="round-btn plain">${icon('chevronLeft')}</button>
        <div><h3>常用食物</h3><div class="sub">${after ? `${DATA.savedFoods.length} 項` : `${DATA.savedFoods.length} 項 · 依最近使用排序`}</div></div>
        <div class="actions">
            ${after ? `<div class="popover-wrap"><button class="round-btn ${state.infoOpen ? 'on' : ''}" data-action="info" aria-label="說明">${icon('info')}</button>${state.infoOpen ? '<div class="popover">依最近使用排序。記錄餐點時按項目旁的 ☆ 也能加入。數值以「一份」為準，帶入時可以選份數。</div>' : ''}</div>` : ''}
            <button class="round-btn">${icon('plus')}</button>
        </div>
    </div>`;
    const callout = after ? '' : `<div class="callout">${icon('info', 'sm')}<div>記錄餐點時按項目旁的 ☆ 也能加入。數值以「一份」為準，帶入時可以選份數。</div></div>`;
    const rows = DATA.savedFoods
        .map((f) => {
            const portion = `${f.portion || '1 份'}${f.grams !== null ? ` · ${f.grams} g` : ''}`;
            const macros = after
                ? `<span class="inline-n">${icon('drumstick', '', 'n-protein')}${f.p}</span><span class="inline-n">${icon('wheat', '', 'n-carbs')}${f.c}</span><span class="inline-n">${icon('nut', '', 'n-fat')}${f.f}</span>`
                : ` · 蛋白質 ${f.p} · 碳水 ${f.c} · 脂肪 ${f.f}`;
            const used = after
                ? f.used
                    ? `<span class="inline-n">${icon('history')}${f.used}</span>`
                    : '還沒用過'
                : f.used
                  ? `最近使用：${f.used}`
                  : '還沒用過';
            return `<div class="row">
                <div class="grow">
                    <div style="font-weight:600">${f.name}</div>
                    ${after ? `<div class="xsmall muted">${portion}</div><div class="xsmall muted" style="margin-top:2px">${macros}${used}</div>` : `<div class="xsmall muted">${portion}${macros}</div><div class="xsmall muted">${used}</div>`}
                </div>
                <div class="kcal" style="text-align:right;font-size:16px">${f.kcal}<small style="display:block">kcal / 份</small></div>
            </div>`;
        })
        .join('');
    return `${head}${callout}<div class="list" style="margin-top:12px">${rows}</div>`;
}

/** 整頁輸出。 */
function render() {
    document.getElementById('app').innerHTML = `
    <section class="topic">
        <h2><span class="tag">0</span>縮放鎖定</h2>
        <p>這個 demo 頁本身已套用三種鎖定，用手機開啟可以實際試：點兩下、兩指縮放都不會放大頁面。</p>
        <div class="zoom-box">
            <table>
                <tr><th>情況</th><th>做法</th></tr>
                <tr><td>點兩下放大</td><td>全站 CSS：<code>html { touch-action: manipulation }</code>（同時去掉點擊的 300ms 延遲）</td></tr>
                <tr><td>兩指縮放（Android）</td><td>viewport 加 <code>maximum-scale=1, user-scalable=no</code></td></tr>
                <tr><td>兩指縮放（iPhone）</td><td>Safari 忽略上一列，以 JS 對 <code>gesturestart</code> 呼叫 <code>preventDefault()</code></td></tr>
                <tr><td>點輸入框自動放大（iPhone）</td><td>手機上輸入框已是 16px，不會觸發；之後新增輸入框也維持 16px 以上</td></tr>
            </table>
        </div>
    </section>

    ${topic(
        ['1'],
        '營養素改成圖示＋小字',
        '四種營養素固定配色與圖示：雞腿＝蛋白質、麥穗＝碳水、堅果＝脂肪、葉子＝纖維。合計格保留小字幫助辨識；表單欄位保留文字、前面加圖示。',
        `<div class="screen"><div class="card">${nutrientGridBefore()}</div></div>`,
        `<div class="screen"><div class="card">${nutrientGridAfter()}</div></div>`,
    )}
    ${topic(
        ['1'],
        '餐點表單的欄位標籤',
        '「（選填）」出現 7 次。改成只在必填欄位打橘色 *，其他欄位不另外標示；熱量與營養素標籤前加圖示。',
        `<div class="screen">${formBefore()}</div>`,
        `<div class="screen">${formAfter()}</div>`,
        '同一規則套用到常用食物面板，以及餐點表單的「備註（選填）」。',
    )}
    ${topic(
        ['2', '1'],
        '今日頁熱量卡',
        '「熱量目標」「已攝取」改成靶心與餐具圖示；蛋白質列改用雞腿圖示與蛋白質色，「還差 79 g」縮成 −79 g。',
        `<div class="screen">${targetsCard(false)}</div>`,
        `<div class="screen">${targetsCard(true)}</div>`,
        '達成蛋白質目標時，−79 g 的位置改成 ✓ 圖示。',
    )}
    ${topic(
        ['3', '4'],
        '今日餐點卡、喝水卡',
        '餐別已經有圖示（日出、太陽、月亮、餅乾），拿掉「早餐 ·」只留時間；卡片右上角「飲食頁 ›」「紀錄 ›」改成圓形箭頭按鈕。',
        `<div class="screen">${todayCards(false)}</div>`,
        `<div class="screen">${todayCards(true)}</div>`,
    )}
    ${topic(
        ['5'],
        '＋選單',
        '拿掉標題下的說明與每格小字，改成三欄大圖示；可用的排前面，即將推出的淡化並以時鐘小標記表示。',
        `<div class="screen" style="padding:0;overflow:hidden">${quickAddBefore()}</div>`,
        `<div class="screen" style="padding:0;overflow:hidden">${quickAddAfter()}</div>`,
        '「手動記錄」改名「記錄餐點」：拿掉說明後，「手動記錄」看不出要記錄什麼。',
    )}
    ${topic(
        ['6'],
        '飲食頁餐點卡',
        '拿掉「· 3 個項目」，項目名稱已經列在上一行；備註照常顯示。',
        `<div class="screen">${mealCard(false)}</div>`,
        `<div class="screen">${mealCard(true)}</div>`,
    )}
    ${topic(
        ['7', '1'],
        '常用食物頁',
        '說明框改成右上角 ⓘ，點了才顯示（可以點改後的 ⓘ 試試）；列表營養素改成彩色小圖示，「最近使用：」改成時鐘圖示；頁首副標拿掉「依最近使用排序」，移進 ⓘ 說明。',
        `<div class="screen">${savedFoods(false)}</div>`,
        `<div class="screen">${savedFoods(true)}</div>`,
    )}`;
}

// 主題切換
document.querySelectorAll('[data-theme-btn]').forEach((btn) =>
    btn.addEventListener('click', () => {
        document.body.dataset.theme = btn.dataset.themeBtn;
        document.querySelectorAll('[data-theme-btn]').forEach((b) => b.classList.toggle('on', b === btn));
    }),
);

// 對照／只看改後
document.querySelectorAll('[data-view-btn]').forEach((btn) =>
    btn.addEventListener('click', () => {
        document.body.dataset.view = btn.dataset.viewBtn;
        document.querySelectorAll('[data-view-btn]').forEach((b) => b.classList.toggle('on', b === btn));
    }),
);

// ⓘ 說明開關
document.getElementById('app').addEventListener('click', (e) => {
    if (e.target.closest('[data-action="info"]')) {
        state.infoOpen = !state.infoOpen;
        render();
    }
});

// iPhone Safari 會忽略 user-scalable=no，另外擋下兩指縮放手勢
document.addEventListener('gesturestart', (e) => e.preventDefault());

render();
