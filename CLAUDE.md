# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 專案概述

以 Laravel 12 官方 Vue Starter Kit 為基礎建立的手機優先飲食／健身紀錄應用（M1 帳號基礎已完成：首次設定、BMR／每日目標、體重紀錄、今日頁、底部 Tab 版型、PWA；M2 Google 登入已完成：設定頁綁定／解除 Google、以已綁定的 Google 帳號登入；D1 部署已完成：正式站 https://wn52777.com，GCP e2-micro + Cloudflare Tunnel，GitHub Actions 手動部署；M3 飲食＋喝水已完成：手動記錄餐點、常用食物／最近吃過／複製整餐快速帶入、喝水面板與建議量、今日頁實際攝取；U1 介面精簡已完成：手機鎖住縮放、營養素與卡片改用圖示；D2 發版版號：部署時填版號、成功後打 tag 與 GitHub Release，設定頁「版本」分頁顯示目前版本）。技術堆疊：Laravel 12 + Inertia.js 2 + Vue 3（TypeScript）+ Tailwind CSS 3 + shadcn-vue（radix-vue）+ Ziggy。資料庫為 PostgreSQL 16（以 `docker-compose.yml` 在本地起容器，對外 port 54329；開發庫 `fitness_app`、測試庫 `fitness_app_testing`）。語系 `zh_TW`（框架內建訊息在 `lang/zh_TW/`），時區 `Asia/Taipei`。

## 常用指令

```bash
# 資料庫容器（本地開發、跑 migrate 或測試前要先啟動）
docker compose up -d
docker compose down              # 停止（加 -v 會連資料一起刪除）

# 開發環境（同時啟動 artisan serve、queue:listen、pail 日誌、vite）
composer dev

# 前端
npm run dev          # Vite dev server
npm run build        # 正式建置
npm run build:ssr    # 含 SSR 建置
npm run lint         # ESLint（會自動 --fix）
npm run format       # Prettier 格式化 resources/
npm run format:check

# PHP 程式風格
vendor/bin/pint

# 帳號（第一版不開放註冊，帳號以指令建立；目前沒有寄信設定，忘記密碼改用指令重設）
php artisan user:create --name="王小明" --email=ming@example.com --generate-password   # 缺的欄位會互動詢問
php artisan user:password ming@example.com --generate-password
php artisan db:seed          # 本地開發帳號（只在 APP_ENV=local；帳密讀 .env 的 DEV_ACCOUNT_*，已完成首次設定，可重複執行）

# 部署（正式部署用 GitHub Actions：Actions → deploy → Run workflow，分支 main，填版號 vX.Y.Z；以下為本地除錯用）
deploy/check-version.sh v1.4.0                                    # 檢查版號能不能用（先 git fetch --tags）
npm run build && DEPLOY_HOST=<VM 外部 IP> vendor/bin/dep deploy   # DEPLOY_HOST 格式 [帳號@]IP[:路徑]；加 DEPLOY_VERSION=vX.Y.Z 才會寫 version.json
DEPLOY_HOST=<VM 外部 IP> vendor/bin/dep rollback                   # 只退程式碼，不退資料庫
DEPLOY_HOST=<VM 外部 IP> vendor/bin/dep releases

# 測試（PHPUnit；使用 PostgreSQL 的 fitness_app_testing，需先 docker compose up -d）
./vendor/bin/phpunit
./vendor/bin/phpunit packages/fitness/profile/tests            # 單一模組
./vendor/bin/phpunit tests/Feature/HomeTest.php                 # 單一檔案
./vendor/bin/phpunit --filter test_last_weight_log_cannot_be_deleted  # 單一測試
```

注意：`tests/Pest.php` 存在，但 composer 並未安裝 Pest，現有測試皆為 PHPUnit class 寫法；新增測試請沿用 PHPUnit 風格（或先安裝 Pest）。

CI（`.github/workflows/`）在 push/PR 到 `main`、`develop` 時執行：`lint.yml`（job `quality`：`pint --test`、`prettier --check`、`eslint .`，只檢查不修改）與 `tests.yml`（job `ci`：`npm run build` 後跑 PHPUnit；PHP 8.4、Node 22，PostgreSQL 16 以 service container 提供，同樣對應 port 54329）。`main` 有分支保護：必須透過 PR，`quality`、`ci` 通過才能合併。CD 是 `deploy.yml`，只能手動觸發且只部署 `main`，必須填版號（見下方「部署」的版號規則）。Feature 測試會渲染 Inertia 頁面，需要已建置的 Vite manifest，本地若測試因 manifest 缺失失敗，先跑 `npm run build`。`assertInertia()` 會檢查頁面檔存在，路徑設定在 `config/inertia.php`（`resources/js/pages`，小寫）。測試情境紀錄在 `vibe-coding/tests/`。

測試庫 `fitness_app_testing` 由 `docker/postgres/init/` 的 SQL 在容器**第一次初始化**時建立；若 volume 早於此腳本存在，需 `docker compose down -v` 重建，或手動 `CREATE DATABASE fitness_app_testing`。

## 架構

### 後端 → 前端：Inertia
- 沒有獨立 API；controller / route closure 透過 `Inertia::render('路徑/Page', $props)` 回傳頁面，元件名稱對應 `resources/js/pages/{name}.vue`（解析邏輯在 `resources/js/app.ts`）。
- 路由分檔：`routes/web.php`（入口：`/` 未登入導向登入、已登入導向今日頁 `/today`；暫放 `/workouts` 的「即將推出」頁，健身模組完成後移除）、`routes/auth.php`（登入/註冊/密碼重設/信箱驗證）、`routes/settings.php`（帳號資料、密碼、外觀設定）。業務路由在各模組的 `src/routes/web.php`。
- 註冊預設關閉：`config('auth.registration_enabled')`（`AUTH_REGISTRATION_ENABLED`）為 false 時不註冊 `register` 路由；`config('auth.password_reset_enabled')`（`AUTH_PASSWORD_RESET_ENABLED`）控制登入頁是否顯示「忘記密碼」。登入後首頁是 `fitness.daily_summary.show`（starter kit 原本的 `dashboard` 已移除）。
- 全域共享 props 定義在 `app/Http/Middleware/HandleInertiaRequests.php::share()`（`name`、`quote`、`auth.user`、`flash`），對應的 TS 型別在 `resources/js/types/index.ts`（`SharedData`、`Flash`、`User`、`BreadcrumbItem`、`NavItem`）。修改共享資料時兩邊要同步。模組自己的共享資料由模組 Provider 以 `Inertia::share()` 加入（例如 `social_login.providers`，型別在 `types/<module>.ts`），`app/` 不依賴模組。
- 一次性結果訊息：redirect 時 `->with('success', '文字')` 由 `AppToast` 顯示；`->with('error', ['title' => ..., 'message' => ...])` 由 `components/FlashAlert.vue` 在頁面內顯示（放在需要的頁面，例如登入頁、已連結帳號頁）。
- 表單驗證使用 `app/Http/Requests/` 下的 FormRequest（模組的放在模組內 `Http/Requests/`）。
- 第三方登入：核心 `social-login`（`social_accounts` 表、driver 合約與 `SocialProviderManager`、`social_login()` helper、綁定／登入／解除流程）＋ driver `social-login-google`（Socialite，綁定 `fitness.social_login.google`）。只有已登入者能在 `/settings/connected-accounts` 綁定，未綁定的第三方帳號不能登入、不自動建帳號。啟用清單 `SOCIAL_LOGIN_PROVIDERS`，Google 憑證 `GOOGLE_CLIENT_ID`／`GOOGLE_CLIENT_SECRET`／`GOOGLE_REDIRECT_URI`（沒填時按鈕自動隱藏，建立步驟見 `packages/fitness/social-login-google/README.md`）。登入與綁定共用 callback，以 session 記錄意圖。新增平台只需新增 `social-login-<平台>` driver 模組。測試以 `Fitness\SocialLogin\Tests\Fakes\FakeSocialProvider` 取代 driver，不連外。
- `profile` 模組透過 HTTP Kernel 的 `appendMiddlewareToGroup()` 把 `EnsureProfileCompleted` 加到 `web` middleware 群組（不要改用 Router 的 `pushMiddlewareToGroup()`，Kernel 重新同步群組時會被蓋掉）：已登入但未完成首次設定（`users.onboarded_at` 為空）的使用者，所有頁面都會被導向 `/onboarding`。新增「未完成設定也要能進入」的路由時，把路由名稱加到 `config('fitness.profile.onboarding_except_routes')`。測試用的 `UserFactory` 預設為已完成設定，要測未完成狀態用 `->notOnboarded()`。

### 前端結構（`resources/js`，alias `@` → `resources/js`）
- `pages/`：Inertia 頁面（`auth/`、`settings/`（個人帳號、密碼、外觀、版本）、`profile/`、`daily-summary/`、`social-login/`、`meal/`、`ComingSoon.vue`）。
- 業務模組：`profile`（首次設定、BMR／每日目標、體重紀錄）、`daily-summary`（今日頁；發出收集型事件 `DailySummaryCollecting`，各模組以 listener `addSection()` 加入當日摘要，前端依 `sections.<key>` 顯示）、`social-login`（已連結帳號頁、登入頁的第三方按鈕 `components/social-login/SocialLoginButtons.vue`）、`meal`（飲食頁、新增／編輯餐點、常用食物；`meals`／`meal_items`／`saved_foods`，依賴 profile 的 `UserCalendar` 切日與 `EnergyTargetCalculator`；份數換算在前端 `composables/useMealFood.ts`）、`water`（喝水面板 `components/water/WaterSheet.vue` 掛在 `AppSidebarLayout`，資料由模組 Provider 以 `Inertia::share('water', Inertia::optional(...))` 提供，打開面板時 `router.reload({ only: ['water'] })` 載入；建議量＝最新體重 × 35 ml）。
- `layouts/`：頁面以 `AppLayout`（內部委派給 `layouts/app/AppSidebarLayout` 或 `AppHeaderLayout`）或 `AuthLayout`（委派給 `layouts/auth/*`）包裝；設定頁再套 `layouts/settings/Layout.vue`（分頁：個人帳號、密碼、外觀、已連結帳號；新增設定分頁改這裡的 `sidebarNavItems`）。切換整站版型時改這兩個入口檔即可。
- `components/`：應用元件。版型：手機（< 768px）用 `AppBottomNav.vue` 底部 Tab，桌機用 `AppSidebar.vue` 側欄；`QuickAddDialog.vue`（＋選單）、`profile/WeightLogSheet.vue`、`AppToast.vue` 掛在 `layouts/app/AppSidebarLayout.vue`，開關狀態在 `composables/useQuickActions.ts`、`useToast.ts`。`ResponsiveDialog.vue` 手機為底部面板、桌機為置中對話框；`AppPage.vue` 是頁面標題＋內容容器。模組元件放 `components/<module>/`，型別放 `types/<module>.ts`。
- 品牌色：橘色，`resources/css/app.css` 的 `--primary`（shadcn 預設按鈕即為橘色）、`--brand-soft`（選取狀態淺底，Tailwind `bg-brand-soft`）、`--page`（頁面底色，`bg-page`）。設計定稿在 `vibe-coding/ui/features/m1-account/`、`m2-google-login/`。
- 營養素的圖示與顏色集中在 `composables/useNutrients.ts`（蛋白質 `Drumstick`／rose、碳水 `Wheat`／amber、脂肪 `Nut`／violet、纖維 `Leaf`／emerald），列表用 `components/meal/MacroValues.vue`；表單標籤用 `components/FieldLabel.vue`，必填打 `*`、選填不標示（不寫「（選填）」）。只有圖示的按鈕要加 `aria-label`，被省略的文字以 `sr-only` 保留。
- BMR／每日目標的前端即時預覽在 `composables/useEnergyTargets.ts`，係數由後端 props（`options.calculation`）帶入，公式要與後端 `EnergyTargetCalculator` 保持一致。
- 手機鎖住縮放：`app.blade.php`（與 `offline.html`）的 viewport `maximum-scale=1, user-scalable=no`、`app.css` 的 `html { touch-action: manipulation }`、`app.ts` 擋 iOS `gesturestart`。新增輸入框在手機上要維持 16px 以上，避免 iPhone 點擊時自動放大。
- PWA：`public/manifest.webmanifest`、`public/sw.js`（頁面不快取、`/build/` 快取、離線顯示 `public/offline.html`）、`public/icons/`；service worker 只在正式建置註冊（`app.ts`）。改快取策略時調整 `sw.js` 的 `CACHE_VERSION`。
- `components/ui/`：shadcn-vue 產生的元件，每個元件資料夾有 `index.ts` 匯出；`lib/utils.ts` 提供 `cn()`（clsx + tailwind-merge）。
- `composables/useAppearance.ts`：淺色/深色/系統主題，於 `app.ts` 呼叫 `initializeTheme()`。
- 前端用 Ziggy 的 `route('name')` 產生網址（`ZiggyVue` 已全域註冊）。

### 部署（正式環境）

完整步驟與疑難排解見 [deploy/README.md](deploy/README.md)，規格見 `vibe-coding/specs/2026-11-20-d1-gcp-deployment.md`。
- 架構：Cloudflare（`wn52777.com`，HTTPS、`/build/` 邊緣快取）→ Tunnel → GCP e2-micro（1GB RAM）上的 nginx（只聽 127.0.0.1:80）→ PHP 8.4-FPM（以 `deploy` 身分執行）→ PostgreSQL 16。VM 只開 SSH；nginx 對 PHP 固定 `HTTPS=on`，並以 `CF-Connecting-IP` 作為使用者 IP，所以 Laravel 不需要 `trustProxies`。
- VM 環境由 `deploy/provision.sh` 建立（可重複執行）。CD 只部署程式碼；要改 PHP 擴充、nginx、cron、queue worker 等系統設定時，改這支腳本，再到 VM 重跑。
- 部署用 Deployer（`deploy.php`，`recipe/laravel.php`，保留 3 個版本）。前端在 runner／本地建置後上傳，VM 不跑 npm。
- 版號：`vMAJOR.MINOR.PATCH`，新功能（M 系列）升中間碼，修 bug／介面調整（U）／部署流程（D）升最後一碼；第一版 `v1.3.0`。workflow 以 `deploy/check-version.sh` 擋下格式錯誤、重複、比最新 tag 小的版號，以 `--revision=$GITHUB_SHA` 部署，Deployer 在版本目錄寫 `version.json`（版號、commit、部署時間，`.gitignore` 已排除），成功後才打 annotated tag 並 `gh release create --generate-notes`。設定頁「版本」由 `app/Support/AppVersion.php` 讀 `version.json`，`dep rollback` 後顯示的版號跟著退回。
- GitHub Environment `production`：`DEPLOY_HOST`（Variable，`[帳號@]IP[:路徑]`）、`DEPLOY_SSH_KEY`、`DEPLOY_HOST_KEY`、`PRODUCTION_ENV`（整份正式環境 `.env`，每次部署覆蓋 VM 的 `shared/.env`，舊版存成 `.env.previous`）。改正式設定要改 `PRODUCTION_ENV` 再部署，不要直接改 VM 的 `.env`。
- 正式環境 `QUEUE_CONNECTION=sync`、沒有排程；之後有 queue job（M4）要在 `provision.sh` 加 worker。資料庫每天 03:00 備份到 `/var/backups/fitness-app/`（保留 7 份）。
- migration 要相容新舊版程式（`coding-style.md` §12），因為 `dep rollback` 不會退回資料庫。在 VM 上執行 artisan 一律 `sudo -u deploy php artisan ...`。

### 程式風格
- Prettier：4 空白縮排、單引號、行寬 150，含 organize-imports 與 tailwindcss 外掛（自動排序 import 與 class）。
- `components.json` 中 shadcn-vue 的 alias 寫的是 `resources/js/Components`（大寫 C），實際目錄為小寫 `components`；用 shadcn-vue CLI 新增元件後需確認放到正確位置。

### 模組化寫作規則

業務功能一律以模組開發，完整規範與範例見 [vibe-coding/coding-style.md](vibe-coding/coding-style.md)，撰寫程式前必讀。重點：

- **模組位置**：`packages/fitness/<module>/`（kebab-case），命名空間 `Fitness\<Module>`；`src/` 放程式、`tests/` 放該模組測試。`app/` 只放跨模組共用程式。
- **註冊**：新增模組時同步更新根目錄 `composer.json` 的 psr-4（含 tests 的 autoload-dev）與 `bootstrap/providers.php`。
- **Service Provider**：`boot()` 依序呼叫 `setupConfig` / `bindInstances` / `registerSingletons` / `setupLang` / `setupMigrations` / `setupRoutes` / `setupEvents`，一個掛載點一個方法。
- **設定集中**：模組設定檔 `src/config/fitness/<module>.php`，以 `binds`（Repository 介面 → 實作）與 `singletons`（Manager）陣列宣告容器綁定；可調整數值走 `env()` → 設定檔。
- **Repository**：資料存取一律透過 `Repositories/Contracts/` 介面，以 `app(介面::class)` 取得；跨模組只用對方的 Repository 介面或事件，不直接查對方資料表。
- **Driver 模式**：同一件事有多種實作時，拆成「核心模組（Contract + abstract 基底 + Manager + helper）」與「driver 模組（`<核心>-<實作>`，只綁定 `fitness.<module>.<driver>`）」。
- **事件驅動擴充**：模組間以事件溝通，發出方不依賴監聽方；擴充功能優先新增 listener，而非修改核心模組。收集型事件以 `addXxx()` / `getXxx()` 讓其他模組加入資料。
- **歸屬**：migration、語系（`lang/zh_TW`，以 `Fitness<Module>::` 取用）、路由（名稱 `fitness.<module>.<action>`）、Exception（每種失敗一個類別）都放在擁有它的模組內。
- **前端**：頁面放 `resources/js/pages/<module>/`，模組元件放 `components/<module>/`，型別放 `types/<module>.ts`。

# USER GUIDE

## 工作準則

- 開始 session 時應依序讀取：工作準則 → 工作目錄 → 工作原則。
- 程式碼每個方法上應註解，簡短扼要說明用意。
- 程式碼註解不要包含跟工作有關的編號（R1、N1 等等），這些對程式碼來說不重要。
- 若使用者有要求程式碼撰寫風格，可以依照使用者風格編寫 CLAUDE.md，並可隨著專案進行、因應專案方向不同而變更。
- 撰寫 tests 測試前，應先把測試情境條列給使用者查閱，請使用者回覆是否增減情境，確認後才開始撰寫與執行，並把測試情境條列記錄下來（記錄方式見工作原則）。
- 若使用者要求設計師技術協助，AI Agent 以設計師身份協助，依需求先刻一份 demo 畫面；畫面確認沒問題後才開始開發。

## 工作目錄

請依照以下目錄分配放置各用途的檔案。若開始 session 前缺少以下目錄，請建立並告知使用者：「工作目錄少了以下目錄，會依序建立」。

| 用途 | 目錄 |
|---|---|
| 目錄首頁 | `vibe-coding/` |
| 規格目錄 | `vibe-coding/specs/` |
| 任務目錄 | `vibe-coding/milestones/` |
| 交接任務 | `vibe-coding/handoffs/` |
| 設計畫面：討論中的 demo | `vibe-coding/ui/prototypes/` |
| 設計畫面：已拍板的畫面 | `vibe-coding/ui/features/` |
| 設計畫面：開發紀錄索引 | `vibe-coding/ui/index.md` |
| 測試：情境紀錄索引 | `vibe-coding/tests/index.md` |

## 工作原則

- 先討論 spec 規格，之後再決定開始寫程式的方向。
- 討論 spec 規格、確認實作方向後，開始寫 milestone。
- milestone 是計畫書，內容包含：
  - 要實作的 spec 規格書
  - 需要開始的任務，並把任務拆分成 Task 步驟
  - 步驟定義以一個段落為主（請與使用者討論拆分方向後決定）
- 每一份 milestone 依照每一項 Task 依序進行，每執行完一步，詢問使用者是否進行下一步 Task。
- handoff 記錄這次改動的進度交接，下次開始 session 時參考。

### 分支流程（develop → main，2026-11-20 起）

- 開發一律在 `develop` 分支上 commit，不在 `main` 上直接開發，也不再開 `milestone/*` 分支（M1、M2 與 D1 的前半段是舊流程留下的分支）。
- 每完成一個 Task 就 commit 並 push 到 `origin/develop`；push 到 develop 會觸發 CI（Pint、Prettier、ESLint、PHPUnit）。
- PR（`develop` → `main`）由使用者自己在 GitHub 開並合併；AI 不替使用者合併，也不在本地把 develop merge 回 main。
- milestone 與 handoff 檔內記錄分支為 `develop`。
- 開始 session 時先確認目前在 `develop`，並與 `origin/develop` 同步（`git pull --ff-only`）。

### 測試情境紀錄（`vibe-coding/tests/`）

- `vibe-coding/tests/` 只放測試情境的文件；測試程式碼與測試資料仍放在專案根目錄的 `tests/`（pytest 預設從這裡找測試）。

- `vibe-coding/tests/index.md` 若不存在，請建立，並在檔案開頭註記：「該文件記錄專案測試情境，建立原則每個任務或模組建立 日期+測試名稱 並把測試情境簡短扼要條列式紀錄，格式是id/test_name，並在index.md 條列式紀錄  id/name/datetime(YYYY-MM-dd His)」。
- 每個任務或模組建立一份情境檔，檔名為「日期-測試名稱」，例如 `vibe-coding/tests/2026-11-12-parser.md`。檔內把測試情境簡短條列，每條格式為 `id/test_name`，後面附一句情境說明。
- 每新增一份情境檔，就在 `vibe-coding/tests/index.md` 加一條 `id/name/datetime`，datetime 格式為 `YYYY-MM-dd His`（例如 `2026-11-12 113833`）。

### 畫面開發紀錄（`vibe-coding/ui/`）

- `vibe-coding/ui/index.md` 若不存在，請建立，並在檔案開頭註記：「該文件記錄畫面開發紀錄」。
- 討論中的 demo 畫面存在 `vibe-coding/ui/prototypes/<日期時間-畫面名稱>/`（例如 `vibe-coding/ui/prototypes/20261112-1430-dashboard/`），以 html、css、js 檔案存放。
- 討論拍板的 demo 畫面，把檔案轉移到 `vibe-coding/ui/features/` 底下。
- 每個畫面進到 `vibe-coding/ui/features/` 時，在該畫面目錄內把畫面情境簡短條列（例如 `vibe-coding/ui/features/<畫面名稱>/README.md`），每條格式為 `id/ui_name`；並在 `vibe-coding/ui/index.md` 加一條 `id/name/datetime`，datetime 格式為 `YYYY-MM-dd His`。