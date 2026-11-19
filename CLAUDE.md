# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 專案概述

以 Laravel 12 官方 Vue Starter Kit 為基礎建立的健身應用（目前仍是 starter kit 初始狀態）。技術堆疊：Laravel 12 + Inertia.js 2 + Vue 3（TypeScript）+ Tailwind CSS 3 + shadcn-vue（radix-vue）+ Ziggy。資料庫為 PostgreSQL 16（以 `docker-compose.yml` 在本地起容器，對外 port 54329；開發庫 `fitness_app`、測試庫 `fitness_app_testing`）。語系 `zh_TW`（框架內建訊息在 `lang/zh_TW/`），時區 `Asia/Taipei`。

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

# 測試（PHPUnit；使用 PostgreSQL 的 fitness_app_testing，需先 docker compose up -d）
./vendor/bin/phpunit
./vendor/bin/phpunit tests/Feature/DashboardTest.php           # 單一檔案
./vendor/bin/phpunit --filter test_guests_are_redirected_to_the_login_page  # 單一測試
php artisan test --filter=ProfileUpdateTest
```

注意：`tests/Pest.php` 存在，但 composer 並未安裝 Pest，現有測試皆為 PHPUnit class 寫法；新增測試請沿用 PHPUnit 風格（或先安裝 Pest）。

CI（`.github/workflows/`）在 push/PR 到 `main`、`develop` 時執行：Pint + Prettier + ESLint，以及 `npm run build` 後跑 PHPUnit（PHP 8.4、Node 22，PostgreSQL 16 以 service container 提供，同樣對應 port 54329）。Feature 測試會渲染 Inertia 頁面，需要已建置的 Vite manifest，本地若測試因 manifest 缺失失敗，先跑 `npm run build`。

測試庫 `fitness_app_testing` 由 `docker/postgres/init/` 的 SQL 在容器**第一次初始化**時建立；若 volume 早於此腳本存在，需 `docker compose down -v` 重建，或手動 `CREATE DATABASE fitness_app_testing`。

## 架構

### 後端 → 前端：Inertia
- 沒有獨立 API；controller / route closure 透過 `Inertia::render('路徑/Page', $props)` 回傳頁面，元件名稱對應 `resources/js/pages/{name}.vue`（解析邏輯在 `resources/js/app.ts`）。
- 路由分檔：`routes/web.php`（入口，`require` 其他檔）、`routes/auth.php`（登入/註冊/密碼重設/信箱驗證）、`routes/settings.php`（個人資料、密碼、外觀設定）。
- 全域共享 props 定義在 `app/Http/Middleware/HandleInertiaRequests.php::share()`（`name`、`quote`、`auth.user`），對應的 TS 型別在 `resources/js/types/index.ts`（`SharedData`、`User`、`BreadcrumbItem`、`NavItem`）。修改共享資料時兩邊要同步。
- 表單驗證使用 `app/Http/Requests/` 下的 FormRequest。

### 前端結構（`resources/js`，alias `@` → `resources/js`）
- `pages/`：Inertia 頁面（`auth/`、`settings/`、`Dashboard.vue`、`Welcome.vue`）。
- `layouts/`：頁面以 `AppLayout`（內部委派給 `layouts/app/AppSidebarLayout` 或 `AppHeaderLayout`）或 `AuthLayout`（委派給 `layouts/auth/*`）包裝；設定頁再套 `layouts/settings/Layout.vue`。切換整站版型時改這兩個入口檔即可。
- `components/`：應用元件（側欄、導覽、使用者選單等）；側欄導覽項目定義在 `AppSidebar.vue`。
- `components/ui/`：shadcn-vue 產生的元件，每個元件資料夾有 `index.ts` 匯出；`lib/utils.ts` 提供 `cn()`（clsx + tailwind-merge）。
- `composables/useAppearance.ts`：淺色/深色/系統主題，於 `app.ts` 呼叫 `initializeTheme()`。
- 前端用 Ziggy 的 `route('name')` 產生網址（`ZiggyVue` 已全域註冊）。

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

### 分支流程（一個 milestone 一個分支）

- 每份 milestone 開始實作前，從最新的 `main` 開一個分支，名稱與 milestone 檔名對應：`milestone/<milestone 檔名>`（例如 `milestone/M1-account-foundation`）。
- 該 milestone 的所有 Task 都在這個分支上 commit；milestone 與 handoff 檔內記錄對應的分支名稱。
- 開始 session 時先確認目前分支與進行中的 milestone 相符，不在 `main` 上直接開發。
- milestone 全部 Task 完成後，push 分支並開 PR 合回 `main`（讓 CI 的 Pint、Prettier、ESLint、PHPUnit 在合併前執行），以 merge commit（`--no-ff`）合併，不用 squash，保留每個 milestone 的起訖節點。
- 合併後，下一份 milestone 再從更新後的 `main` 開新分支。

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