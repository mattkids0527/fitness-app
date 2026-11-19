/* ==========================================================================
   M1 帳號基礎 demo 互動
   以單一 state 物件驅動畫面：點擊類操作整頁重繪，輸入類操作只更新 data-live 區塊（避免失去焦點）。
   計算公式與後端 profile 模組（BmrCalculator / EnergyTargetCalculator）一致。
   ========================================================================== */

// 與 packages/fitness/profile/src/config/fitness/profile.php 一致的設定值
const CONFIG = {
    activityFactors: { sedentary: 1.2, light: 1.3, moderate: 1.4, active: 1.5 },
    kcalPerKg: 7700,
    goalRate: { lose: { min: 0.1, max: 1.0 }, gain: { min: 0.1, max: 0.5 } },
    proteinPerKg: { lose: 1.8, maintain: 1.2, gain: 1.6 },
    minAge: 13,
    // 熱量目標建議下限：低於時只提醒、不阻擋（T5 由後端設定提供）
    calorieFloor: { male: 1500, female: 1200 },
};

const TODAY = '2026-11-19';

const OPTIONS = {
    gender: { male: '男', female: '女' },
    activity: {
        sedentary: { title: '久坐', desc: '辦公室工作、很少走動' },
        light: { title: '輕度', desc: '偶爾走動、站立' },
        moderate: { title: '中度', desc: '工作需常走動或站立' },
        active: { title: '高度', desc: '體力勞動工作' },
    },
    goal: {
        lose: { title: '減脂', desc: '每日熱量赤字' },
        maintain: { title: '維持', desc: '維持目前體重' },
        gain: { title: '增肌', desc: '每日熱量盈餘' },
    },
};

// lucide 風格圖示（與 starter kit 的 lucide-vue-next 對應）
const ICONS = {
    home: '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
    utensils: '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
    plus: '<path d="M5 12h14"/><path d="M12 5v14"/>',
    dumbbell: '<path d="m6.5 6.5 11 11"/><path d="m21 21-1-1"/><path d="m3 3 1 1"/><path d="m18 22 4-4"/><path d="m2 6 4-4"/><path d="m3 10 7-7"/><path d="m14 21 7-7"/>',
    user: '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    chevronRight: '<path d="m9 18 6-6-6-6"/>',
    chevronLeft: '<path d="m15 18-6-6 6-6"/>',
    camera: '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
    pencil: '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
    droplet: '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
    weight: '<circle cx="12" cy="5" r="3"/><path d="M6.5 8a2 2 0 0 0-1.905 1.46L2.1 18.5A2 2 0 0 0 4 21h16a2 2 0 0 0 1.925-2.54L19.4 9.5A2 2 0 0 0 17.48 8Z"/>',
    flame: '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
    trash: '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
    lock: '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    logOut: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
    alert: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    drumstick: '<path d="M15.4 15.63a7.875 6 135 1 1 6.23-6.23 4.3 3.43 135 0 0-6.23 6.23"/><path d="m8.29 12.71-2.6 2.6a2.5 2.5 0 1 0-1.65 4.65A2.5 2.5 0 1 0 8.7 18.3l2.59-2.59"/>',
    calculator: '<rect width="16" height="20" x="4" y="2" rx="2"/><path d="M8 6h8M16 14v4M16 10h.01M12 10h.01M8 10h.01M12 14h.01M8 14h.01M12 18h.01M8 18h.01"/>',
    activity: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
};

/** 輸出一個 SVG 圖示。 */
function icon(name, size = '') {
    return `<svg class="icon ${size}" viewBox="0 0 24 24">${ICONS[name]}</svg>`;
}

/** 建立空白的首次設定草稿。 */
function emptyOnboarding() {
    return { gender: null, birth_date: '', height_cm: '', weight_kg: '', activity_level: null, goal: null, goal_rate: '0.5', bmr_mode: 'formula', bmr_override: '' };
}

/** 建立 demo 初始資料：已完成設定的使用者與數筆體重紀錄。 */
function seedData() {
    return {
        profile: { gender: 'male', birth_date: '1992-05-10', height_cm: '175', activity_level: 'light', goal: 'lose', goal_rate: '0.5', bmr_override: null },
        weights: [
            { id: 8, weight_kg: 78.5, measured_on: '2026-11-19' },
            { id: 7, weight_kg: 78.9, measured_on: '2026-11-17' },
            { id: 6, weight_kg: 79.0, measured_on: '2026-11-15' },
            { id: 5, weight_kg: 79.4, measured_on: '2026-11-12' },
            { id: 4, weight_kg: 79.3, measured_on: '2026-11-10' },
            { id: 3, weight_kg: 79.8, measured_on: '2026-11-07' },
            { id: 2, weight_kg: 80.1, measured_on: '2026-11-05' },
            { id: 1, weight_kg: 80.6, measured_on: '2026-11-01' },
        ],
    };
}

/** 讀取 demo 外觀偏好（裝置、主題），只是方便檢視，讀不到就用預設。 */
function loadPrefs() {
    try {
        return JSON.parse(localStorage.getItem('m1-demo-prefs')) || {};
    } catch {
        return {};
    }
}

const prefs = loadPrefs();

const state = {
    screen: 'onboarding',
    step: 1,
    viewport: prefs.viewport || 'mobile',
    theme: prefs.theme || 'light',
    user: { name: 'Demo 使用者', email: 'demo@example.com' },
    ...seedData(),
    ob: emptyOnboarding(),
    edit: null,
    newWeight: { weight_kg: '', measured_on: TODAY },
    sheet: null,
    deleting: null,
    errors: {},
    toast: null,
};

/* ---------- 計算 ---------- */

/** 依出生日期計算到今天的足歲。 */
function ageOf(birthDate) {
    if (!birthDate) return null;
    const [y, m, d] = birthDate.split('-').map(Number);
    const [ty, tm, td] = TODAY.split('-').map(Number);
    return ty - y - (tm < m || (tm === m && td < d) ? 1 : 0);
}

/** 目前（最新一筆）體重紀錄。 */
function latestWeight() {
    return state.weights[0] || null;
}

/** Mifflin-St Jeor 公式計算 BMR。 */
function bmrOf(gender, weight, height, age) {
    return Math.round(10 * weight + 6.25 * height - 5 * age + (gender === 'male' ? 5 : -161));
}

/** 每週目標速度換算的每日熱量增減。 */
function adjustmentOf(goal, rate) {
    if (goal === 'maintain' || !goal) return 0;
    const kcal = Math.round((Number(rate) * CONFIG.kcalPerKg) / 7);
    return goal === 'lose' ? -kcal : kcal;
}

/** 由草稿與體重算出全部目標；資料不足時回傳 null。 */
function targetsOf(draft, weight) {
    const age = ageOf(draft.birth_date);
    const height = Number(draft.height_cm);
    weight = Number(weight);
    if (!draft.gender || !age || !height || !weight || !draft.activity_level || !draft.goal) return null;

    const bmrCalc = bmrOf(draft.gender, weight, height, age);
    const override = draft.bmr_mode === 'measured' && Number(draft.bmr_override) >= 500 ? Number(draft.bmr_override) : null;
    const bmr = override ?? bmrCalc;
    const factor = CONFIG.activityFactors[draft.activity_level];
    const tdee = Math.round(bmr * factor);
    const adj = adjustmentOf(draft.goal, draft.goal_rate);

    return {
        age, weight, height, factor, bmrCalc, bmr, overridden: override !== null, tdee, adj,
        target: tdee + adj,
        protein: Math.round(weight * CONFIG.proteinPerKg[draft.goal]),
        proteinPerKg: CONFIG.proteinPerKg[draft.goal],
    };
}

/** 已儲存個人資料轉成計算用的草稿格式。 */
function profileDraft() {
    const p = state.profile;
    return { ...p, bmr_mode: p.bmr_override ? 'measured' : 'formula', bmr_override: p.bmr_override ?? '' };
}

/* ---------- 格式化 ---------- */

const WEEKDAYS = ['日', '一', '二', '三', '四', '五', '六'];

/** 數字加千分位。 */
function num(n) {
    return Number(n).toLocaleString('en-US');
}

/** 帶正負號（用真正的減號）的數字。 */
function signed(n) {
    if (n === 0) return '±0';
    return (n > 0 ? '+' : '−') + num(Math.abs(n));
}

/** 日期轉「11月19日 週四」。 */
function longDate(iso) {
    const [y, m, d] = iso.split('-').map(Number);
    return `${m}月${d}日 週${WEEKDAYS[new Date(y, m - 1, d).getDay()]}`;
}

/** 日期轉「11/19（四）」，今天顯示「今天」。 */
function shortDate(iso) {
    if (iso === TODAY) return '今天';
    const [y, m, d] = iso.split('-').map(Number);
    return `${m}/${d}（${WEEKDAYS[new Date(y, m - 1, d).getDay()]}）`;
}

/* ---------- 共用片段 ---------- */

/** 欄位錯誤訊息。 */
function errorOf(key) {
    return state.errors[key] ? `<div class="error">${state.errors[key]}</div>` : '';
}

/** 帶單位的數字輸入框。 */
function numberInput(bind, value, unit, opts = {}) {
    const invalid = state.errors[bind] ? ' invalid' : '';
    return `<div class="input-wrap${invalid}">
        <input type="number" inputmode="decimal" data-bind="${bind}" value="${value ?? ''}" placeholder="${opts.placeholder ?? ''}" step="${opts.step ?? 'any'}" />
        <span class="unit">${unit}</span>
    </div>`;
}

/** 日期輸入框。 */
function dateInput(bind, value, opts = {}) {
    const invalid = state.errors[bind] ? ' invalid' : '';
    return `<div class="input-wrap${invalid}"><input type="date" data-bind="${bind}" value="${value ?? ''}" max="${opts.max ?? ''}" /></div>`;
}

/** 分段按鈕。 */
function segmented(path, value, options) {
    return `<div class="segmented">${Object.entries(options)
        .map(([k, label]) => `<button data-action="set" data-path="${path}" data-value="${k}" class="${value === k ? 'on' : ''}">${label}</button>`)
        .join('')}</div>`;
}

/** 日常活動量選項（直式清單）。 */
function activityChoices(path, value) {
    return `<div class="choice-list">${Object.entries(OPTIONS.activity)
        .map(
            ([k, o]) => `<button class="choice ${value === k ? 'on' : ''}" data-action="set" data-path="${path}" data-value="${k}">
                <div><div class="title">${o.title}</div><div class="desc">${o.desc}</div></div><span class="radio"></span>
            </button>`,
        )
        .join('')}</div>`;
}

/** 目標選項（橫式三格）＋每週速度滑桿。 */
function goalFields(prefix, draft) {
    const row = `<div class="choice-row">${Object.entries(OPTIONS.goal)
        .map(
            ([k, o]) => `<button class="choice ${draft.goal === k ? 'on' : ''}" data-action="set" data-path="${prefix}.goal" data-value="${k}">
                <div class="title">${o.title}</div><div class="desc">${o.desc}</div>
            </button>`,
        )
        .join('')}</div>${errorOf(prefix + '.goal')}`;

    if (!draft.goal || draft.goal === 'maintain') return row;

    const range = CONFIG.goalRate[draft.goal];
    return `${row}
        <div class="field">
            <div data-live="rate:${prefix}"></div>
            <input type="range" min="${range.min}" max="${range.max}" step="0.1" data-bind="${prefix}.goal_rate" value="${draft.goal_rate}" />
            <div class="range-labels"><span>${range.min} kg</span><span>${range.max} kg</span></div>
            <div class="hint">${draft.goal === 'lose' ? '一般建議每週減重約體重的 0.5%～1%。' : '增肌建議每週 0.25 kg 左右，太快容易增加脂肪。'}</div>
        </div>`;
}

/** BMR 小工具：公式計算或填入實測值。 */
function bmrTool(prefix, draft, note) {
    const measured = draft.bmr_mode === 'measured';
    return `<div class="card bmr-tool">
        <div class="card-title">${icon('calculator', 'sm')} 基礎代謝 BMR</div>
        ${segmented(prefix + '.bmr_mode', draft.bmr_mode, { formula: '公式計算', measured: '填入實測值' })}
        ${
            measured
                ? `<div class="field">${numberInput(prefix + '.bmr_override', draft.bmr_override, 'kcal', { placeholder: '例如 1650', step: 1 })}
                    ${errorOf(prefix + '.bmr_override')}
                    <div class="hint">可填 InBody 等量測結果（500～5000）。</div>
                    <div data-live="bmr-compare:${prefix}"></div></div>`
                : `<div data-live="bmr-formula:${prefix}"></div>`
        }
        ${note ? `<div class="hint mt-8">${note}</div>` : ''}
    </div>`;
}

/** 每日目標拆解（BMR → 日常消耗 → 目標調整 → 熱量目標）。 */
function breakdown(t, draft) {
    const act = OPTIONS.activity[draft.activity_level].title;
    const rateText = draft.goal === 'maintain' ? '維持' : `每週 ${draft.goal === 'lose' ? '−' : '+'}${Number(draft.goal_rate)} kg`;
    return `<div class="breakdown">
        <div class="line"><span>基礎代謝 BMR <span class="badge ${t.overridden ? 'brand' : ''}">${t.overridden ? '實測值' : '公式計算'}</span></span><span>${num(t.bmr)}</span></div>
        <div class="line"><span>× 日常活動 <span class="muted">${act} ${t.factor}</span></span><span>${num(t.tdee)}</span></div>
        <div class="line"><span>目標調整 <span class="muted">${rateText}</span></span><span>${signed(t.adj)}</span></div>
        <div class="line"><span>每日熱量目標</span><span>${num(t.target)} kcal</span></div>
    </div>`;
}

/** 熱量目標低於建議下限（依性別）時的提醒。 */
function lowCalorieWarning(t, gender) {
    const floor = CONFIG.calorieFloor[gender];
    if (!t || !floor || t.target >= floor) return '';
    return `<div class="callout warning">${icon('alert', 'sm')}<div>每日熱量目標低於建議下限（${OPTIONS.gender[gender]}性 ${num(floor)} kcal），長期可能流失肌肉、影響代謝，建議放慢每週速度。</div></div>`;
}

/** 目標摘要卡（首次設定最後一步、編輯頁預覽共用）。 */
function targetsCard(t, draft, title) {
    if (!t) return `<div class="card"><div class="card-title">${title}</div><div class="muted small">資料填寫完整後顯示。</div></div>`;
    return `<div class="card">
        <div class="card-title">${icon('flame', 'sm')} ${title}</div>
        <div class="hero-number">${num(t.target)}<small>kcal / 天</small></div>
        ${breakdown(t, draft)}
        <div class="stat mt-12"><div class="k">${icon('drumstick', 'sm')} 蛋白質目標</div><div class="v">${t.protein}<small>g / 天</small></div>
            <div class="muted small">體重 ${t.weight} kg × ${t.proteinPerKg} g</div></div>
        ${lowCalorieWarning(t, draft.gender)}
    </div>`;
}

/* ---------- 只需局部更新的區塊 ---------- */

/** 依 data-live 名稱產生區塊內容。 */
function renderLive(name) {
    const [kind, prefix] = name.split(':');
    const draft = state[prefix];
    const weight = prefix === 'ob' ? draft?.weight_kg : latestWeight()?.weight_kg;
    const t = draft ? targetsOf(draft, weight) : null;

    switch (kind) {
        case 'rate': {
            const adj = adjustmentOf(draft.goal, draft.goal_rate);
            return `<div class="row between"><span class="field-label" style="margin:0">每週目標速度</span><b>${Number(draft.goal_rate).toFixed(1)} kg</b></div>
                <div class="muted small" style="margin:4px 0 6px">每日熱量 ${signed(adj)} kcal</div>`;
        }
        case 'bmr-formula': {
            if (!t) return `<div class="muted small mt-12">填完性別、出生日期、身高、體重後自動計算。</div>`;
            const c = draft.gender === 'male' ? '+ 5' : '− 161';
            return `<div class="hero-number mt-12">${num(t.bmrCalc)}<small>kcal / 天</small></div>
                <details><summary>計算方式</summary><div class="formula">Mifflin-St Jeor 公式（${OPTIONS.gender[draft.gender]}）<br>
                10 × ${t.weight} kg + 6.25 × ${t.height} cm − 5 × ${t.age} 歲 ${c} = ${num(t.bmrCalc)}</div></details>`;
        }
        case 'bmr-compare':
            return t ? `<div class="formula">公式計算值為 ${num(t.bmrCalc)} kcal，儲存後改用你填的實測值。</div>` : '';
        case 'targets':
            return targetsCard(t, draft, prefix === 'ob' ? '你的每日目標' : '儲存後的每日目標');
        default:
            return '';
    }
}

/** 重新計算所有 data-live 區塊。 */
function refreshLive() {
    document.querySelectorAll('[data-live]').forEach((el) => (el.innerHTML = renderLive(el.dataset.live)));
}

/* ---------- 畫面 ---------- */

/** 首次設定（分 4 步）。 */
function screenOnboarding() {
    const d = state.ob;
    const steps = {
        1: {
            title: '先認識一下你',
            desc: '這些資料用來計算基礎代謝（BMR）與每日熱量目標，之後可以在「我的」修改。',
            body: `<div class="field"><span class="field-label">性別</span>${segmented('ob.gender', d.gender, OPTIONS.gender)}${errorOf('ob.gender')}</div>
                <div class="field"><label>出生日期</label>${dateInput('ob.birth_date', d.birth_date, { max: TODAY })}${errorOf('ob.birth_date')}</div>
                <div class="field"><label>身高</label>${numberInput('ob.height_cm', d.height_cm, 'cm', { placeholder: '170' })}${errorOf('ob.height_cm')}</div>`,
        },
        2: {
            title: '體重與日常活動',
            desc: '體重會成為你的第一筆體重紀錄。',
            body: `<div class="field"><label>目前體重</label>${numberInput('ob.weight_kg', d.weight_kg, 'kg', { placeholder: '65.0' })}${errorOf('ob.weight_kg')}</div>
                <div class="field"><span class="field-label">日常活動量</span>${activityChoices('ob.activity_level', d.activity_level)}${errorOf('ob.activity_level')}
                <div class="callout">${icon('info', 'sm')}<div>只算工作與生活的活動量，<b>不含運動</b>。運動消耗之後由健身紀錄另外計算，避免重複。</div></div></div>`,
        },
        3: {
            title: '你的目標',
            desc: '決定每日熱量要減少或增加多少，以及蛋白質目標。',
            body: `<div class="field">${goalFields('ob', d)}</div>`,
        },
        4: {
            title: '確認每日目標',
            desc: 'BMR 預設以公式計算；若有 InBody 等實測值，可以改用實測值。',
            body: `${bmrTool('ob', d)}<div data-live="targets:ob"></div>`,
        },
    };
    const s = steps[state.step];

    return `<div class="onboarding-shell"><div class="ob-card">
        <div class="progress">${[1, 2, 3, 4].map((i) => `<i class="${i <= state.step ? 'on' : ''}"></i>`).join('')}</div>
        <div class="ob-step">步驟 ${state.step} / 4</div>
        <h2 class="ob-title">${s.title}</h2>
        <p class="ob-desc">${s.desc}</p>
        ${s.body}
        <div class="sticky-actions">
            ${state.step > 1 ? `<button class="btn outline" data-action="ob-prev">上一步</button>` : ''}
            <button class="btn primary" data-action="ob-next">${state.step === 4 ? '完成設定' : '下一步'}</button>
        </div>
    </div></div>`;
}

/** 今日（儀表板，M1 只有目標與體重，其他為即將推出）。 */
function screenToday() {
    const t = targetsOf(profileDraft(), latestWeight()?.weight_kg);
    const w = latestWeight();
    const weighedToday = w?.measured_on === TODAY;
    const circ = 2 * Math.PI * 52;

    return `<div class="page">
        <div class="page-header"><div><h2>今日</h2><div class="sub">${longDate(TODAY)}</div></div></div>
        <div class="grid-2">
            <div class="card">
                <div class="ring-wrap">
                    <div class="ring"><svg viewBox="0 0 120 120">
                        <circle cx="60" cy="60" r="52" fill="none" stroke="hsl(var(--muted))" stroke-width="12"/>
                        <circle cx="60" cy="60" r="52" fill="none" stroke="hsl(var(--brand))" stroke-width="12" stroke-linecap="round" stroke-dasharray="${circ}" stroke-dashoffset="${circ}"/>
                    </svg><div class="center"><b>${num(t.target)}</b><span>剩餘 kcal</span></div></div>
                    <div style="flex:1">
                        <div class="muted small">熱量目標</div><div style="font-size:20px;font-weight:700">${num(t.target)} kcal</div>
                        <div class="muted small mt-8">已攝取</div><div style="font-weight:600">0 kcal</div>
                    </div>
                </div>
                <div class="mt-12">
                    <div class="row between small"><span>蛋白質</span><span class="muted">0 / ${t.protein} g</span></div>
                    <div class="bar mt-8"><i style="width:0%"></i></div>
                    <div class="small mt-8">還差 <b>${t.protein} g</b></div>
                </div>
                <div class="callout">${icon('info', 'sm')}<div>飲食紀錄上線後，這裡會即時顯示已攝取與剩餘熱量。</div></div>
            </div>
            <div class="card">
                <div class="card-title">${icon('weight', 'sm')} 體重</div>
                ${
                    weighedToday
                        ? `<div class="hero-number">${w.weight_kg}<small>kg</small></div><div class="muted small">今天已記錄</div>`
                        : `<div style="font-weight:600">今天量體重了嗎？</div><div class="muted small">最近一次：${shortDate(w.measured_on)} ${w.weight_kg} kg</div>`
                }
                <button class="btn ${weighedToday ? 'outline' : 'primary'} block mt-12" data-action="open-sheet" data-sheet="weight">${icon('plus', 'sm')} 記錄體重</button>
            </div>
        </div>
        <div class="section-label">即將推出</div>
        <div class="coming"><span class="icon-box">${icon('utensils')}</span><div><b>今日餐點</b><div class="small">拍照或手動記錄每一餐</div></div></div>
        <div class="coming"><span class="icon-box">${icon('droplet')}</span><div><b>喝水</b><div class="small">今日建議 ${num(Math.round(w.weight_kg * 35))} ml</div></div></div>
        <div class="coming"><span class="icon-box">${icon('dumbbell')}</span><div><b>運動</b><div class="small">有氧與重訓紀錄、消耗熱量</div></div></div>
    </div>`;
}

/** 尚未實作的 Tab 佔位頁。 */
function screenComingSoon(title, iconName, items) {
    return `<div class="page">
        <div class="page-header"><h2>${title}</h2></div>
        <div class="empty">
            <span class="icon-box">${icon(iconName, 'lg')}</span>
            <h3>即將推出</h3>
            <div>這個功能正在開發中，完成後會包含：</div>
            <ul>${items.map((i) => `<li>${i}</li>`).join('')}</ul>
        </div>
    </div>`;
}

/** 我的：每日目標、體重、個人資料、設定。 */
function screenMe() {
    const p = state.profile;
    const draft = profileDraft();
    const t = targetsOf(draft, latestWeight()?.weight_kg);
    const [w, prev] = state.weights;
    const diff = prev ? Math.round((w.weight_kg - prev.weight_kg) * 10) / 10 : null;
    const goalText = p.goal === 'maintain' ? '維持' : `${OPTIONS.goal[p.goal].title} · 每週 ${Number(p.goal_rate)} kg`;
    const appearance = { light: '淺色', dark: '深色', system: '跟隨系統' }[state.theme];

    const row = (label, value, action = 'go', extra = 'data-screen="profile-edit"') =>
        `<button class="list-row" data-action="${action}" ${extra}><span class="label">${label}</span><span class="value">${value}</span><span class="chev">${icon('chevronRight', 'sm')}</span></button>`;

    return `<div class="page">
        <div class="profile-head">
            <div class="avatar">D</div>
            <div><div class="name">${state.user.name}</div><div class="muted small">${state.user.email}</div></div>
        </div>
        <div class="grid-2">
            <div class="card">
                <div class="card-title">${icon('flame', 'sm')} 每日目標<button class="link" data-action="go" data-screen="profile-edit">調整 ${icon('chevronRight', 'sm')}</button></div>
                <div class="stat-grid">
                    <div class="stat"><div class="k">熱量</div><div class="v">${num(t.target)}<small>kcal</small></div></div>
                    <div class="stat"><div class="k">蛋白質</div><div class="v">${t.protein}<small>g</small></div></div>
                </div>
                <details><summary class="small muted mt-12" style="cursor:pointer;margin-top:12px">怎麼算的？</summary>${breakdown(t, draft)}</details>
                ${lowCalorieWarning(t, p.gender)}
            </div>
            <div class="card">
                <div class="card-title">${icon('weight', 'sm')} 體重<button class="link" data-action="go" data-screen="weights">全部紀錄 ${icon('chevronRight', 'sm')}</button></div>
                <div class="row between">
                    <div><div class="hero-number">${w.weight_kg}<small>kg</small></div>
                    <div class="muted small">${shortDate(w.measured_on)}${diff !== null ? ` · 較前次 <span class="delta ${diff < 0 ? 'down' : diff > 0 ? 'up' : ''}">${diff > 0 ? '+' : diff < 0 ? '−' : '±'}${Math.abs(diff)} kg</span>` : ' · 第一筆紀錄'}</div></div>
                </div>
                <button class="btn outline block mt-12" data-action="open-sheet" data-sheet="weight">${icon('plus', 'sm')} 記錄體重</button>
            </div>
        </div>

        <div class="section-label row between"><span>個人資料</span></div>
        <div class="list">
            ${row('性別', OPTIONS.gender[p.gender])}
            ${row('出生日期', `${p.birth_date.replaceAll('-', '/')}（${ageOf(p.birth_date)} 歲）`)}
            ${row('身高', `${p.height_cm} cm`)}
            ${row('日常活動量', OPTIONS.activity[p.activity_level].title)}
            ${row('目標', goalText)}
            ${row('基礎代謝 BMR', `${num(t.bmr)} kcal${t.overridden ? '（實測）' : ''}`)}
        </div>

        <div class="section-label">設定</div>
        <div class="list">
            <button class="list-row" disabled>${icon('link', 'sm')}<span class="label">已連結帳號</span><span class="badge">即將推出</span></button>
            <button class="list-row" data-action="open-sheet" data-sheet="appearance">${icon('sun', 'sm')}<span class="label">外觀</span><span class="value">${appearance}</span><span class="chev">${icon('chevronRight', 'sm')}</span></button>
            <button class="list-row" data-action="toast" data-text="導向 starter kit 的帳號設定頁">${icon('lock', 'sm')}<span class="label">帳號與密碼</span><span class="chev">${icon('chevronRight', 'sm')}</span></button>
            <button class="list-row danger" data-action="toast" data-text="已登出（demo）">${icon('logOut', 'sm')}<span class="label">登出</span></button>
        </div>
    </div>`;
}

/** 編輯個人資料（含 BMR 小工具與即時預覽）。 */
function screenProfileEdit() {
    const d = state.edit;
    const w = latestWeight();
    return `<div class="page narrow">
        <div class="page-header"><button class="back" data-action="go" data-screen="me">${icon('chevronLeft')}</button><h2>編輯個人資料</h2></div>
        <div class="card">
            <div class="field"><span class="field-label">性別</span>${segmented('edit.gender', d.gender, OPTIONS.gender)}</div>
            <div class="field"><label>出生日期</label>${dateInput('edit.birth_date', d.birth_date, { max: TODAY })}${errorOf('edit.birth_date')}</div>
            <div class="field"><label>身高</label>${numberInput('edit.height_cm', d.height_cm, 'cm')}${errorOf('edit.height_cm')}</div>
        </div>
        <div class="card"><div class="field"><span class="field-label">日常活動量（不含運動）</span>${activityChoices('edit.activity_level', d.activity_level)}</div></div>
        <div class="card"><div class="field"><span class="field-label">目標</span>${goalFields('edit', d)}</div></div>
        ${bmrTool('edit', d, `以最新體重 ${w.weight_kg} kg（${shortDate(w.measured_on)}）計算；體重請到「體重紀錄」新增。`)}
        <div data-live="targets:edit"></div>
        <div class="sticky-actions">
            <button class="btn outline" data-action="go" data-screen="me">取消</button>
            <button class="btn primary" data-action="save-profile">儲存</button>
        </div>
    </div>`;
}

/** 體重紀錄：新增表單與列表。 */
function screenWeights() {
    const only = state.weights.length === 1;
    const rows = state.weights
        .map((w, i) => {
            const prev = state.weights[i + 1];
            const diff = prev ? Math.round((w.weight_kg - prev.weight_kg) * 10) / 10 : null;
            const cls = diff === null ? '' : diff < 0 ? 'down' : diff > 0 ? 'up' : '';
            const text = diff === null ? '' : `${diff > 0 ? '+' : diff < 0 ? '−' : '±'}${Math.abs(diff)}`;
            return `<div class="weight-row">
                <div class="date"><div class="d">${shortDate(w.measured_on)}</div><div class="w">${w.measured_on.replaceAll('-', '/')}${i === 0 ? ' · 目前體重' : ''}</div></div>
                <span class="kg">${w.weight_kg.toFixed(1)}</span>
                <span class="delta ${cls}">${text}</span>
                <button class="icon-btn" title="${only ? '至少需保留一筆體重紀錄' : '刪除'}" data-action="ask-delete" data-id="${w.id}" ${only ? 'disabled' : ''}>${icon('trash', 'sm')}</button>
            </div>`;
        })
        .join('');

    return `<div class="page narrow">
        <div class="page-header"><button class="back" data-action="go" data-screen="me">${icon('chevronLeft')}</button><h2>體重紀錄</h2></div>
        <div class="card">
            <div class="card-title">新增紀錄</div>
            <div class="form-pair">
                ${numberInput('newWeight.weight_kg', state.newWeight.weight_kg, 'kg', { placeholder: String(latestWeight().weight_kg) })}
                ${dateInput('newWeight.measured_on', state.newWeight.measured_on, { max: TODAY })}
            </div>
            ${errorOf('newWeight.weight_kg')}${errorOf('newWeight.measured_on')}
            <button class="btn primary block mt-12" data-action="add-weight" data-from="page">新增</button>
        </div>
        <div class="section-label row between"><span>歷史紀錄</span><span>共 ${state.weights.length} 筆</span></div>
        <div class="list">${rows}</div>
        ${only ? `<div class="callout">${icon('info', 'sm')}<div>至少需保留一筆體重紀錄，用來計算 BMR 與每日目標。</div></div>` : ''}
        <div class="muted small" style="text-align:center;margin-top:12px">每頁 20 筆，超過時底部顯示「載入更多」</div>
    </div>`;
}

/* ---------- 底部面板 ---------- */

/** 依 state.sheet 產生底部面板（桌機為對話框）。 */
function renderSheet() {
    if (!state.sheet) return '';
    let body = '';

    if (state.sheet === 'plus') {
        const quick = (iconName, label, sub) =>
            `<button class="quick" disabled><span class="badge">即將推出</span><span class="icon-box">${icon(iconName)}</span><div>${label}<div class="muted small" style="font-weight:400">${sub}</div></div></button>`;
        body = `<h3>快速新增</h3><p class="sheet-desc">選擇要記錄的項目</p>
            <div class="quick-grid">
                ${quick('camera', '拍照分析', 'AI 估算營養素')}
                ${quick('pencil', '手動記錄', '輸入餐點內容')}
                ${quick('dumbbell', '健身', '有氧、重訓')}
                ${quick('droplet', '喝水', '+250 / +500 ml')}
                <button class="quick wide" data-action="open-sheet" data-sheet="weight"><span class="icon-box">${icon('weight')}</span><div>記錄體重<div class="muted small" style="font-weight:400">目前 ${latestWeight().weight_kg} kg</div></div><span style="margin-left:auto">${icon('chevronRight', 'sm')}</span></button>
            </div>`;
    }

    if (state.sheet === 'weight') {
        body = `<h3>記錄體重</h3><p class="sheet-desc">建議每天固定時間量，例如起床如廁後。</p>
            <div class="field"><label>體重</label>${numberInput('newWeight.weight_kg', state.newWeight.weight_kg, 'kg', { placeholder: String(latestWeight().weight_kg) })}${errorOf('newWeight.weight_kg')}</div>
            <div class="field"><label>量測日期</label>${dateInput('newWeight.measured_on', state.newWeight.measured_on, { max: TODAY })}${errorOf('newWeight.measured_on')}</div>
            <button class="btn primary block mt-12" style="margin-top:20px" data-action="add-weight" data-from="sheet">儲存</button>`;
    }

    if (state.sheet === 'confirm-delete') {
        const w = state.weights.find((x) => x.id === state.deleting);
        body = `<h3>刪除體重紀錄？</h3><p class="sheet-desc">${shortDate(w.measured_on)} ${w.weight_kg} kg，刪除後無法復原。${state.weights[0].id === w.id ? '刪除後會以前一筆作為目前體重，每日目標也會重新計算。' : ''}</p>
            <div class="row"><button class="btn outline" style="flex:1" data-action="close-sheet">取消</button><button class="btn danger" style="flex:1" data-action="delete-weight">刪除</button></div>`;
    }

    if (state.sheet === 'appearance') {
        body = `<h3>外觀</h3><p class="sheet-desc">沿用 starter kit 的淺色／深色／跟隨系統。</p>
            ${segmented('theme', state.theme, { light: '淺色', dark: '深色', system: '跟隨系統' })}`;
    }

    return `<div class="overlay" data-action="close-sheet"><div class="sheet"><div class="grabber"></div>${body}</div></div>`;
}

/* ---------- 版型 ---------- */

const TABS = [
    { key: 'today', label: '今日', icon: 'home' },
    { key: 'meals', label: '飲食', icon: 'utensils', soon: true },
    { key: 'plus' },
    { key: 'workout', label: '健身', icon: 'dumbbell', soon: true },
    { key: 'me', label: '我的', icon: 'user' },
];

/** 目前畫面對應的 Tab。 */
function activeTab() {
    return ['profile-edit', 'weights'].includes(state.screen) ? 'me' : state.screen;
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
        <div class="side-user"><span class="avatar sm">D</span><div><div style="font-weight:600">${state.user.name}</div><div class="muted">${state.user.email}</div></div></div>
    </aside>`;
}

/** 重繪整個 App。 */
function render() {
    const app = document.getElementById('app');
    const device = document.getElementById('device');
    const onboarding = state.screen === 'onboarding';
    const withSticky = onboarding || state.screen === 'profile-edit';
    const darkSystem = state.theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches;

    device.className = `device ${state.viewport}`;
    device.dataset.theme = state.theme === 'dark' || darkSystem ? 'dark' : 'light';

    const screens = {
        onboarding: screenOnboarding,
        today: screenToday,
        meals: () => screenComingSoon('飲食', 'utensils', ['拍照交給 AI 分析熱量與營養素', '手動輸入餐點', '常用食物、最近吃過快速帶入']),
        workout: () => screenComingSoon('健身', 'dumbbell', ['有氧／時間型紀錄', '重訓逐組記錄次數 × 重量', '依體重估算消耗熱量']),
        me: screenMe,
        'profile-edit': screenProfileEdit,
        weights: screenWeights,
    };

    app.className = `app${onboarding ? ' full' : ''}`;
    app.innerHTML = `
        ${onboarding ? '' : renderSidebar()}
        <div class="main ${withSticky ? 'no-tabbar' : ''}">
            <div class="statusbar"><span>9:41</span><span>●●● 100%</span></div>
            ${screens[state.screen]()}
        </div>
        ${withSticky ? '' : renderTabbar()}
        ${renderSheet()}
        ${state.toast ? `<div class="toast">${icon('check', 'sm')}${state.toast}</div>` : ''}`;

    refreshLive();
    renderToolbar();
}

/* ---------- demo 工具列 ---------- */

const TOOLBAR_SCREENS = [
    ['onboarding', '首次設定'],
    ['today', '今日'],
    ['meals', '飲食'],
    ['plus', '＋選單'],
    ['me', '我的'],
    ['profile-edit', '編輯個人資料'],
    ['weights', '體重紀錄'],
    ['weight', '記錄體重'],
];

/** 更新工具列按鈕的選取狀態並保存偏好。 */
function renderToolbar() {
    const current = state.sheet === 'plus' || state.sheet === 'weight' ? state.sheet : state.screen;
    document.getElementById('tb-screens').innerHTML =
        '<span class="group-label">畫面</span>' +
        TOOLBAR_SCREENS.map(([k, label]) => `<button data-tb-screen="${k}" class="${current === k ? 'on' : ''}">${label}</button>`).join('') +
        (state.screen === 'onboarding' ? '<button data-tb-fill>填入範例</button>' : '');
    document.querySelectorAll('[data-viewport]').forEach((b) => b.classList.toggle('on', b.dataset.viewport === state.viewport));
    document.querySelectorAll('[data-theme-btn]').forEach((b) => b.classList.toggle('on', b.dataset.themeBtn === state.theme));
    try {
        localStorage.setItem('m1-demo-prefs', JSON.stringify({ viewport: state.viewport, theme: state.theme }));
    } catch {
        // 無法存偏好時不影響 demo
    }
}

/* ---------- 驗證 ---------- */

/** 驗證個人資料欄位，回傳錯誤物件（key 為 data-bind 路徑）。 */
function validateProfile(prefix, d, fields) {
    const e = {};
    const age = ageOf(d.birth_date);
    const has = (f) => fields.includes(f);
    if (has('gender') && !d.gender) e[`${prefix}.gender`] = '請選擇性別。';
    if (has('birth_date')) {
        if (!d.birth_date) e[`${prefix}.birth_date`] = '請填寫出生日期。';
        else if (age < CONFIG.minAge) e[`${prefix}.birth_date`] = `需年滿 ${CONFIG.minAge} 歲。`;
    }
    if (has('height_cm') && !(Number(d.height_cm) >= 100 && Number(d.height_cm) <= 250)) e[`${prefix}.height_cm`] = '身高需介於 100 到 250 公分。';
    if (has('weight_kg') && !(Number(d.weight_kg) >= 20 && Number(d.weight_kg) <= 300)) e[`${prefix}.weight_kg`] = '體重需介於 20 到 300 公斤。';
    if (has('activity_level') && !d.activity_level) e[`${prefix}.activity_level`] = '請選擇日常活動量。';
    if (has('goal') && !d.goal) e[`${prefix}.goal`] = '請選擇目標。';
    if (has('bmr_override') && d.bmr_mode === 'measured') {
        const v = Number(d.bmr_override);
        if (!Number.isInteger(v) || v < 500 || v > 5000) e[`${prefix}.bmr_override`] = 'BMR 實測值需為 500 到 5000 的整數。';
    }
    return e;
}

/** 驗證體重輸入。 */
function validateWeight() {
    const e = {};
    const v = Number(state.newWeight.weight_kg);
    if (!(v >= 20 && v <= 300)) e['newWeight.weight_kg'] = '體重需介於 20 到 300 公斤。';
    if (!state.newWeight.measured_on || state.newWeight.measured_on > TODAY) e['newWeight.measured_on'] = '量測日期不能晚於今天。';
    return e;
}

/* ---------- 操作 ---------- */

/** 依點路徑（例如 ob.gender）寫入 state。 */
function setPath(path, value) {
    const keys = path.split('.');
    const last = keys.pop();
    keys.reduce((o, k) => o[k], state)[last] = value;
}

/** 顯示短暫提示。 */
function toast(text) {
    state.toast = text;
    render();
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => {
        state.toast = null;
        render();
    }, 2000);
}

/** 切換畫面並重置捲動位置。 */
function go(screen) {
    state.errors = {};
    state.sheet = null;
    if (screen === 'profile-edit') state.edit = profileDraft();
    state.screen = screen;
    render();
    document.querySelector('.main')?.scrollTo(0, 0);
}

const STEP_FIELDS = { 1: ['gender', 'birth_date', 'height_cm'], 2: ['weight_kg', 'activity_level'], 3: ['goal'], 4: ['bmr_override'] };

const actions = {
    go: (ds) => go(ds.screen),
    set: (ds) => {
        setPath(ds.path, ds.value);
        delete state.errors[ds.path];
        // 切換目標時，把每週速度夾回新目標允許的範圍
        if (ds.path.endsWith('.goal') && CONFIG.goalRate[ds.value]) {
            const draft = state[ds.path.split('.')[0]];
            const range = CONFIG.goalRate[ds.value];
            const rate = Number(draft.goal_rate);
            if (!(rate >= range.min && rate <= range.max)) draft.goal_rate = String(Math.min(0.5, range.max));
        }
        render();
    },
    'open-sheet': (ds) => {
        state.errors = {};
        if (ds.sheet === 'weight') state.newWeight = { weight_kg: '', measured_on: TODAY };
        state.sheet = ds.sheet;
        render();
    },
    'close-sheet': () => {
        state.sheet = null;
        render();
    },
    toast: (ds) => toast(ds.text),
    'ob-prev': () => {
        state.errors = {};
        state.step--;
        render();
    },
    'ob-next': () => {
        state.errors = validateProfile('ob', state.ob, STEP_FIELDS[state.step]);
        if (Object.keys(state.errors).length) return render();
        if (state.step < 4) {
            state.step++;
            render();
            document.querySelector('.main')?.scrollTo(0, 0);
            return;
        }
        const d = state.ob;
        state.profile = {
            gender: d.gender, birth_date: d.birth_date, height_cm: d.height_cm, activity_level: d.activity_level, goal: d.goal,
            goal_rate: d.goal === 'maintain' ? null : d.goal_rate,
            bmr_override: d.bmr_mode === 'measured' ? Number(d.bmr_override) : null,
        };
        state.weights = [{ id: Date.now(), weight_kg: Number(d.weight_kg), measured_on: TODAY }];
        go('today');
        toast('設定完成，開始記錄吧！');
    },
    'save-profile': () => {
        state.errors = validateProfile('edit', state.edit, ['birth_date', 'height_cm', 'bmr_override']);
        if (Object.keys(state.errors).length) return render();
        const d = state.edit;
        state.profile = {
            gender: d.gender, birth_date: d.birth_date, height_cm: d.height_cm, activity_level: d.activity_level, goal: d.goal,
            goal_rate: d.goal === 'maintain' ? null : d.goal_rate || '0.5',
            bmr_override: d.bmr_mode === 'measured' ? Number(d.bmr_override) : null,
        };
        go('me');
        toast('已儲存個人資料');
    },
    'add-weight': (ds) => {
        state.errors = validateWeight();
        if (Object.keys(state.errors).length) return render();
        const kg = Math.round(Number(state.newWeight.weight_kg) * 10) / 10;
        state.weights.push({ id: Date.now(), weight_kg: kg, measured_on: state.newWeight.measured_on });
        // 依日期新到舊排序；同一天以後新增的在前
        state.weights.sort((a, b) => (a.measured_on === b.measured_on ? b.id - a.id : b.measured_on.localeCompare(a.measured_on)));
        state.newWeight = { weight_kg: '', measured_on: TODAY };
        if (ds.from === 'sheet') state.sheet = null;
        toast(`已記錄體重 ${kg} kg`);
    },
    'ask-delete': (ds) => {
        state.deleting = Number(ds.id);
        state.sheet = 'confirm-delete';
        render();
    },
    'delete-weight': () => {
        state.weights = state.weights.filter((w) => w.id !== state.deleting);
        state.sheet = null;
        toast('已刪除');
    },
};

/* ---------- 事件 ---------- */

document.getElementById('app').addEventListener('click', (e) => {
    const el = e.target.closest('[data-action]');
    if (!el || el.disabled) return;
    // 點面板內部不關閉面板，只有點到遮罩本身才關
    if (el.classList.contains('overlay') && e.target !== el) return;
    if (el.dataset.path === 'theme') {
        state.theme = el.dataset.value;
        return render();
    }
    actions[el.dataset.action]?.(el.dataset, el);
});

document.getElementById('app').addEventListener('input', (e) => {
    const el = e.target;
    if (!el.dataset.bind) return;
    setPath(el.dataset.bind, el.value);
    // 輸入時清掉該欄位的錯誤提示，不整頁重繪以保留焦點
    if (state.errors[el.dataset.bind]) {
        delete state.errors[el.dataset.bind];
        el.closest('.input-wrap')?.classList.remove('invalid');
        el.closest('.field, .card')?.querySelector('.error')?.remove();
    }
    refreshLive();
});

document.querySelector('.toolbar').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    if (b.dataset.tbScreen) {
        const k = b.dataset.tbScreen;
        if (k === 'plus' || k === 'weight') {
            if (state.screen === 'onboarding') go('today');
            actions['open-sheet']({ sheet: k });
        } else {
            go(k);
        }
    }
    if ('tbFill' in b.dataset) {
        state.ob = { gender: 'male', birth_date: '1992-05-10', height_cm: '175', weight_kg: '78.5', activity_level: 'light', goal: 'lose', goal_rate: '0.5', bmr_mode: 'formula', bmr_override: '' };
        state.errors = {};
        render();
    }
    if (b.dataset.viewport) state.viewport = b.dataset.viewport;
    if (b.dataset.themeBtn) state.theme = b.dataset.themeBtn;
    if (b.id === 'tb-reset') {
        Object.assign(state, seedData(), { ob: emptyOnboarding(), step: 1, screen: 'onboarding', sheet: null, errors: {} });
    }
    if (b.dataset.viewport || b.dataset.themeBtn || b.id === 'tb-reset') render();
});

render();
