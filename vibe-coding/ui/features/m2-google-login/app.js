/* ==========================================================================
   M2 Google 登入 demo 互動
   以單一 state 物件驅動畫面，每次操作整頁重繪。
   「Google 回應」由工具列切換，用來模擬 OAuth 回呼後的各種結果。
   ========================================================================== */

const TODAY = '2026-11-20';

// lucide 風格圖示（與 lucide-vue-next 對應）
const ICONS = {
    home: '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
    utensils: '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
    plus: '<path d="M5 12h14"/><path d="M12 5v14"/>',
    dumbbell: '<path d="m6.5 6.5 11 11"/><path d="m21 21-1-1"/><path d="m3 3 1 1"/><path d="m18 22 4-4"/><path d="m2 6 4-4"/><path d="m3 10 7-7"/><path d="m14 21 7-7"/>',
    user: '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    chevronRight: '<path d="m9 18 6-6-6-6"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
    lock: '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    logOut: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
    alertCircle: '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
    activity: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    flame: '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
};

// Google 官方四色 G 標誌
const GOOGLE_LOGO = `<svg class="g-logo" viewBox="0 0 48 48" aria-hidden="true">
    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
</svg>`;

// 回呼結果對應的文案（實作時放在 FitnessSocialLogin:: 語系檔）
const MESSAGES = {
    loginNotLinked: { title: '這個 Google 帳號尚未連結', body: '請先用 Email 與密碼登入，再到「設定 › 已連結帳號」連結 Google。' },
    loginFailed: { title: 'Google 登入沒有完成', body: '可能是取消了授權或連線逾時，請再試一次。' },
    linkTaken: { title: '這個 Google 帳號已連結到其他帳號', body: '請改用其他 Google 帳號，或先用那個帳號登入並解除連結。' },
    linkFailed: { title: '沒有完成 Google 連結', body: '可能是取消了授權或連線逾時，請再試一次。' },
};

/** 輸出一個 SVG 圖示。 */
function icon(name, size = '') {
    return `<svg class="icon ${size}" viewBox="0 0 24 24">${ICONS[name]}</svg>`;
}

/** 讀取 demo 外觀偏好（裝置、主題），讀不到就用預設。 */
function loadPrefs() {
    try {
        return JSON.parse(localStorage.getItem('m2-demo-prefs')) || {};
    } catch {
        return {};
    }
}

/** 建立 demo 初始資料。 */
function seedData() {
    return {
        screen: 'login',
        loggedIn: false,
        configured: true,
        linked: null,
        response: 'ok',
        alert: null,
        sheet: null,
        redirecting: false,
        toast: null,
    };
}

const prefs = loadPrefs();

const state = {
    viewport: prefs.viewport || 'mobile',
    theme: prefs.theme || 'light',
    user: { name: 'Demo 使用者', email: 'demo@example.com' },
    google: { email: 'demo.user@gmail.com', name: 'Demo User', initial: 'D' },
    ...seedData(),
};

/** 把 YYYY-MM-DD 轉成 YYYY/MM/DD。 */
function slashDate(iso) {
    return iso.replaceAll('-', '/');
}

/** 頁內錯誤提示。 */
function alertBox(key) {
    const m = MESSAGES[key];
    return `<div class="alert" role="alert">${icon('alertCircle', 'sm')}<div class="alert-body"><b>${m.title}</b><span>${m.body}</span></div></div>`;
}

/* ---------- 畫面 ---------- */

/** 登入頁：帳密表單 + 「或」分隔線 + Google 按鈕。 */
function screenLogin() {
    return `<div class="auth"><div class="auth-inner">
        <div class="auth-head">
            <span class="auth-logo">${icon('activity')}</span>
            <div><h1>登入帳號</h1><p>輸入 Email 與密碼登入</p></div>
        </div>
        ${state.alert ? alertBox(state.alert) : ''}
        <div class="field"><label>Email</label><div class="input-wrap"><input type="email" placeholder="email@example.com" /></div></div>
        <div class="field"><label>密碼</label><div class="input-wrap"><input type="password" placeholder="密碼" /></div></div>
        <label class="checkbox"><i></i>記住我</label>
        <button class="btn primary block" style="margin-top:40px" data-action="password-login">登入</button>
        ${
            state.configured
                ? `<div class="divider">或</div>
                   <button class="btn google block" data-action="google" data-intent="login">${GOOGLE_LOGO}使用 Google 帳戶登入</button>`
                : ''
        }
    </div></div>`;
}

/** 今日頁佔位：登入成功後的落點，內容同 M1。 */
function screenToday() {
    return `<div class="page">
        <div class="page-header"><h2>今日</h2></div>
        <div class="card"><div class="card-title">${icon('flame', 'sm')} 今日熱量</div><div class="muted small">今日頁內容同 M1 定稿，這裡只示意登入後的落點。</div></div>
    </div>`;
}

/** 我的：只畫出 M2 有變動的「設定」區塊。 */
function screenMe() {
    const linkedValue = !state.configured ? '' : state.linked ? 'Google' : '未連結';
    return `<div class="page narrow">
        <div class="profile-head">
            <div class="avatar">D</div>
            <div><div class="name">${state.user.name}</div><div class="muted small">${state.user.email}</div></div>
        </div>
        <div class="coming"><span class="icon-box">${icon('info', 'sm')}</span>每日目標、體重、個人資料區塊同 M1，此處省略</div>

        <div class="section-label">設定</div>
        <div class="list">
            <button class="list-row" data-action="go" data-screen="connected">${icon('link', 'sm')}<span class="label">已連結帳號</span><span class="value">${linkedValue}</span><span class="chev">${icon('chevronRight', 'sm')}</span></button>
            <button class="list-row" data-action="toast" data-text="外觀面板同 M1">${icon('sun', 'sm')}<span class="label">外觀</span><span class="chev">${icon('chevronRight', 'sm')}</span></button>
            <button class="list-row" data-action="toast" data-text="帳號與密碼頁同 starter kit">${icon('lock', 'sm')}<span class="label">帳號與密碼</span><span class="chev">${icon('chevronRight', 'sm')}</span></button>
            <button class="list-row danger" data-action="logout">${icon('logOut', 'sm')}<span class="label">登出</span></button>
        </div>
    </div>`;
}

/** Google 這一列：未連結顯示「連結」，已連結顯示 Google 帳號與「解除連結」。 */
function googleProvider() {
    if (!state.linked) {
        return `<div class="provider">
            <span class="provider-logo">${GOOGLE_LOGO}</span>
            <div class="provider-info"><div class="p-name">Google</div><div class="p-sub">尚未連結</div></div>
            <button class="btn outline sm" data-action="google" data-intent="link">連結</button>
        </div>`;
    }
    return `<div class="provider">
            <span class="provider-logo">${GOOGLE_LOGO}</span>
            <div class="provider-info"><div class="p-name row">Google <span class="linked-badge">${icon('check', 'sm')}已連結</span></div><div class="p-sub">可用來登入</div></div>
            <button class="btn outline-danger sm" data-action="open-sheet" data-sheet="unlink">解除連結</button>
        </div>
        <div class="linked-account">
            <span class="avatar">${state.google.initial}</span>
            <div class="who"><div class="e">${state.google.email}</div><div class="t">${state.google.name} · ${slashDate(state.linked)} 連結</div></div>
        </div>`;
}

/** 已連結帳號頁（套 settings Layout）。 */
function screenConnected() {
    const nav = [
        ['profile', '個人帳號'],
        ['password', '密碼'],
        ['appearance', '外觀'],
        ['connected', '已連結帳號'],
    ];
    const body = state.configured
        ? `<div class="list">${googleProvider()}</div>
           <div class="callout">${icon('info', 'sm')}<div>解除連結後仍可用 Email 與密碼登入。Google 帳號的 Email 不必與本站帳號相同。</div></div>`
        : `<div class="list"><div class="empty" style="padding:40px 24px"><span class="icon-box">${icon('link', 'lg')}</span><div>目前沒有可連結的服務</div></div></div>`;

    return `<div class="page">
        <div class="settings">
            <div class="settings-head"><h2>設定</h2><p>管理帳號、密碼、外觀與已連結帳號</p></div>
            <div class="settings-cols">
                <nav class="settings-nav">${nav
                    .map(([k, t]) =>
                        k === 'connected'
                            ? `<button class="on">${t}</button>`
                            : `<button data-action="toast" data-text="「${t}」頁同 starter kit">${t}</button>`,
                    )
                    .join('')}</nav>
                <div class="settings-sep"></div>
                <div class="settings-body">
                    <div class="heading-sm"><h3>已連結帳號</h3><p>連結後可以直接用該帳號登入，不必輸入密碼。</p></div>
                    ${state.alert ? alertBox(state.alert) : ''}
                    ${body}
                </div>
            </div>
        </div>
    </div>`;
}

/* ---------- 對話框 ---------- */

/** 解除連結確認（手機底部面板、桌機置中對話框）。 */
function renderSheet() {
    if (state.sheet !== 'unlink') return '';
    return `<div class="overlay" data-action="close-sheet"><div class="sheet" data-stop>
        <div class="grabber"></div>
        <h3>解除連結 Google？</h3>
        <p class="sheet-desc">解除後就不能用 ${state.google.email} 登入，仍可用 Email 與密碼登入，之後也可以重新連結。</p>
        <div class="row"><button class="btn outline" style="flex:1" data-action="close-sheet">取消</button><button class="btn danger" style="flex:1" data-action="unlink">解除連結</button></div>
    </div></div>`;
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
    return state.screen === 'connected' ? 'me' : state.screen;
}

/** 手機底部 Tab。 */
function renderTabbar() {
    return `<nav class="tabbar">${TABS.map((t) =>
        t.key === 'plus'
            ? `<button class="tab" data-action="toast" data-text="＋選單同 M1" aria-label="快速新增"><span class="tab-plus">${icon('plus', 'lg')}</span></button>`
            : `<button class="tab ${activeTab() === t.key ? 'on' : ''}" ${t.soon ? 'data-action="toast" data-text="即將推出"' : `data-action="go" data-screen="${t.key}"`}>${icon(t.icon)}<span>${t.label}</span></button>`,
    ).join('')}</nav>`;
}

/** 桌機側欄。 */
function renderSidebar() {
    return `<aside class="sidebar">
        <div class="brand"><span class="brand-mark">${icon('activity', 'sm')}</span>Fitness</div>
        <button class="btn primary block side-quick" data-action="toast" data-text="＋選單同 M1">${icon('plus', 'sm')} 快速新增</button>
        ${TABS.filter((t) => t.key !== 'plus')
            .map(
                (t) =>
                    `<button class="side-item ${activeTab() === t.key ? 'on' : ''}" ${t.soon ? '' : `data-action="go" data-screen="${t.key}"`}>${icon(t.icon)}${t.label}${t.soon ? '<span class="badge">即將推出</span>' : ''}</button>`,
            )
            .join('')}
        <div class="side-user"><span class="avatar sm">D</span><div><div style="font-weight:600">${state.user.name}</div><div class="muted">${state.user.email}</div></div></div>
    </aside>`;
}

/** 重繪整個 App。 */
function render() {
    const app = document.getElementById('app');
    const device = document.getElementById('device');
    const guest = state.screen === 'login';

    device.className = `device ${state.viewport}`;
    device.dataset.theme = state.theme;

    const screens = { login: screenLogin, today: screenToday, me: screenMe, connected: screenConnected };

    app.className = `app${guest ? ' full' : ''}`;
    app.innerHTML = `
        ${guest ? '' : renderSidebar()}
        <div class="main" ${guest ? 'style="padding:0;background:hsl(var(--background))"' : ''}>
            ${guest ? '' : '<div class="statusbar"><span>9:41</span><span>●●● 100%</span></div>'}
            ${screens[state.screen]()}
        </div>
        ${guest ? '' : renderTabbar()}
        ${renderSheet()}
        ${state.redirecting ? '<div class="redirecting"><div class="spinner"></div>正在前往 Google…<div class="small">（demo 模擬 Google 授權後回到網站）</div></div>' : ''}
        ${state.toast ? `<div class="toast">${icon('check', 'sm')}${state.toast}</div>` : ''}`;

    renderToolbar();
}

/* ---------- demo 工具列 ---------- */

const TOOLBAR_SCREENS = [
    ['login', '登入頁'],
    ['connected', '已連結帳號'],
    ['me', '我的'],
    ['unlink', '解除連結確認'],
];

/** 一組切換按鈕。 */
function toolbarGroup(label, key, options) {
    return (
        `<span class="group-label">${label}</span>` +
        options.map(([v, t]) => `<button data-tb-set="${key}" data-value="${v}" class="${String(state[key]) === v ? 'on' : ''}">${t}</button>`).join('')
    );
}

/** 更新工具列按鈕的選取狀態並保存偏好。 */
function renderToolbar() {
    const current = state.sheet === 'unlink' ? 'unlink' : state.screen;
    document.getElementById('tb-screens').innerHTML =
        '<span class="group-label">畫面</span>' + TOOLBAR_SCREENS.map(([k, label]) => `<button data-tb-screen="${k}" class="${current === k ? 'on' : ''}">${label}</button>`).join('');
    document.getElementById('tb-scenarios').innerHTML =
        toolbarGroup('Google 設定', 'configured', [
            ['true', '已設定'],
            ['false', '未設定'],
        ]) +
        '&nbsp;' +
        toolbarGroup('連結狀態', 'linkedFlag', [
            ['false', '未連結'],
            ['true', '已連結'],
        ]) +
        '&nbsp;' +
        toolbarGroup('Google 回應', 'response', [
            ['ok', '授權成功'],
            ['taken', '已被其他帳號連結'],
            ['cancel', '取消授權'],
        ]);
    document.querySelectorAll('[data-viewport]').forEach((b) => b.classList.toggle('on', b.dataset.viewport === state.viewport));
    document.querySelectorAll('[data-theme-btn]').forEach((b) => b.classList.toggle('on', b.dataset.themeBtn === state.theme));
    try {
        localStorage.setItem('m2-demo-prefs', JSON.stringify({ viewport: state.viewport, theme: state.theme }));
    } catch {
        // 無法存偏好時不影響 demo
    }
}

// 連結狀態以日期表示，工具列用布林切換
Object.defineProperty(state, 'linkedFlag', { get: () => Boolean(state.linked) });

/* ---------- 操作 ---------- */

/** 顯示短暫提示（同 AppToast，2.5 秒後消失）。 */
function toast(text) {
    state.toast = text;
    render();
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => {
        state.toast = null;
        render();
    }, 2500);
}

/** 切換畫面；flash 類的錯誤提示只顯示一次，換頁就清除。 */
function go(screen) {
    state.alert = null;
    state.sheet = null;
    state.screen = screen;
    state.loggedIn = screen !== 'login';
    render();
    document.querySelector('.main')?.scrollTo(0, 0);
}

/** 模擬 OAuth 回呼：依意圖（登入／連結）與工具列的 Google 回應分流。 */
function googleCallback(intent) {
    const r = state.response;
    if (intent === 'login') {
        // 登入情境只看「取消授權」與「連結狀態」：有綁定紀錄就登入，沒有就拒絕
        if (r !== 'cancel' && state.linked) return go('today');
        go('login');
        state.alert = r === 'cancel' ? 'loginFailed' : 'loginNotLinked';
        return render();
    }
    go('connected');
    if (r === 'ok') {
        state.linked = TODAY;
        return toast('已連結 Google');
    }
    state.alert = r === 'cancel' ? 'linkFailed' : 'linkTaken';
    render();
}

const actions = {
    go: (ds) => go(ds.screen),
    toast: (ds) => toast(ds.text),
    'password-login': () => go('today'),
    logout: () => go('login'),
    google: (ds) => {
        state.redirecting = true;
        render();
        setTimeout(() => {
            state.redirecting = false;
            googleCallback(ds.intent);
        }, 900);
    },
    'open-sheet': (ds) => {
        state.sheet = ds.sheet;
        render();
    },
    'close-sheet': () => {
        state.sheet = null;
        render();
    },
    unlink: () => {
        state.sheet = null;
        state.linked = null;
        state.alert = null;
        toast('已解除連結 Google');
    },
};

document.addEventListener('click', (e) => {
    // 點對話框內容時不觸發外層遮罩的關閉
    const target = e.target.closest('[data-action], [data-stop], [data-tb-screen], [data-tb-set], [data-viewport], [data-theme-btn], #tb-reset');
    if (!target || target.hasAttribute('data-stop')) return;
    const ds = target.dataset;

    if (ds.action) return actions[ds.action]?.(ds);
    if (ds.tbScreen) {
        if (ds.tbScreen === 'unlink') {
            state.configured = true;
            state.linked = state.linked || TODAY;
            go('connected');
            return actions['open-sheet']({ sheet: 'unlink' });
        }
        return go(ds.tbScreen);
    }
    if (ds.tbSet === 'configured') state.configured = ds.value === 'true';
    if (ds.tbSet === 'linkedFlag') state.linked = ds.value === 'true' ? TODAY : null;
    if (ds.tbSet === 'response') state.response = ds.value;
    if (ds.tbSet) {
        state.alert = null;
        return render();
    }
    if (ds.viewport) state.viewport = ds.viewport;
    if (ds.themeBtn) state.theme = ds.themeBtn;
    if (target.id === 'tb-reset') Object.assign(state, seedData());
    render();
});

render();
