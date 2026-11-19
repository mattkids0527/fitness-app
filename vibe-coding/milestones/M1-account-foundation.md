# M1 帳號基礎

- 狀態：進行中
- 建立日期：2026-11-19
- 依據規格：[專案總覽規格 v1.0](../specs/2026-11-19-project-overview.md)（§2 技術架構、§2.4 模組劃分、§3.2 首次設定、§4 資料表、§5 頁面）
- 寫作規範：[模組化寫作規範](../coding-style.md)

## 目標

使用者註冊／登入後，第一次進站會被導到首次設定頁，填完性別、出生日期、身高、體重、日常活動量、目標後才能使用其他頁面；BMR 小工具可自動計算或手動覆寫；體重可持續記錄。整站改為手機優先版型（底部 Tab），可加到手機主畫面（PWA）。

## 範圍

**包含**
- 本地、測試、CI 改用 PostgreSQL；語系繁體中文；時區 Asia/Taipei
- 模組基礎建設與 `profile` 模組
- 首次設定、BMR 小工具、個人資料修改、體重紀錄
- 手機版底部 Tab 版型、PWA

**不包含**（後續 milestone）
- Google 登入（M2）、飲食／喝水（M3）、照片分析（M4）、健身（M5）、每日分析（M6）
- 底部 Tab 中尚未實作的功能頁，先放「即將推出」佔位

## Tasks

### T1 環境：PostgreSQL、語系、時區
新增 `docker-compose.yml`，只起一個 `postgres:16` 容器（對外 port 54329，避免和本機其他專案衝突），PHP 仍以 `composer dev` 在本機執行；`.env` / `.env.example` 改為 pgsql 連線，另建 `fitness_app_testing` 測試庫，`phpunit.xml` 改指向它；`.github/workflows/tests.yml` 加 PostgreSQL service 並移除建立 SQLite 檔的步驟；`config/app.php` 時區改 `Asia/Taipei`，`APP_LOCALE=zh_TW`，加入繁體中文的 Laravel 內建語系檔（驗證、密碼、分頁訊息）；同步更新 CLAUDE.md 的資料庫與測試說明。
**完成條件**：`php artisan migrate:fresh` 在 PostgreSQL 成功；現有測試全部通過；表單驗證錯誤訊息顯示中文。

### T2 模組基礎建設與 `profile` 骨架
建立 `packages/fitness/profile/`，依寫作規範放 `composer.json`、`README.md`、`src/ProfileServiceProvider.php`、`src/config/fitness/profile.php`（`binds` / `singletons`）、`src/lang/zh_TW/`、`src/routes/web.php`；根目錄 `composer.json` 加入 psr-4 與 autoload-dev、`bootstrap/providers.php` 註冊 Provider、`phpunit.xml` 的 testsuite 加入 `packages/fitness/*/tests/Unit`、`packages/fitness/*/tests/Feature`。
**完成條件**：`composer dump-autoload` 無錯誤；`php artisan route:list` 看得到模組路由；`config('fitness.profile')` 取得到設定；在模組 tests 放一支暫時的空測試確認 phpunit 有掃到後移除。

### T3 `profile` 後端
在 `profile` 模組建立 migration：擴充 `users`（gender、birth_date、height_cm、activity_level、goal、goal_rate_kg_per_week、bmr_override、timezone、onboarded_at）與新表 `weight_logs`；建立 `WeightLog` Model、`ProfileRepository` / `WeightLogRepository`（含 Contracts）；`Services/BmrCalculator`（Mifflin-St Jeor）與 `Services/EnergyTargetCalculator`（BMR × 日常活動係數、依目標調整的每日熱量目標、蛋白質目標 g/kg）；`EnsureProfileCompleted` middleware（未完成首次設定導向 `/onboarding`，設定頁與登出除外）；首次設定、個人資料修改、體重新增／刪除的 Controller 與 FormRequest，暫時回傳最簡 Inertia 頁面名稱，畫面留待 T5。
**完成條件**：程式碼檢視確認符合寫作規範；以 tinker 手動驗證 BMR 計算結果與手算一致（不寫入資料庫）；`php artisan migrate:fresh` 成功。

### T4 設計 demo 畫面
以設計師角色在 `vibe-coding/ui/prototypes/<日期時間-畫面名稱>/` 用 html / css / js 刻 demo：手機版整體版型（底部 Tab：今日／飲食／＋／健身／我的）與桌機版對照、首次設定流程（分步填寫）、BMR 小工具、「我的」頁（個人資料、體重紀錄列表與新增）。與使用者來回調整直到確認，確認後移到 `vibe-coding/ui/features/`，寫 README 條列畫面情境並更新 `vibe-coding/ui/index.md`。
**完成條件**：使用者確認 demo；features 目錄與 index 已更新。

### T5 前端實作與 PWA
依確認的 demo 實作：`AppLayout` 加入手機版底部 Tab（桌機維持側欄）、`resources/js/pages/profile/` 下的首次設定、個人資料、體重紀錄頁，`resources/js/types/profile.ts` 型別，必要時更新 `HandleInertiaRequests::share()` 與 `types/index.ts`；starter kit 既有頁面（登入、註冊、設定、Dashboard）文字改繁體中文；加入 PWA（manifest、icons、service worker）。
**完成條件**：`npm run build`、`npm run lint`、`npm run format:check` 通過；手機尺寸實際操作：註冊 → 被導到首次設定 → 完成後進入首頁 → 新增體重紀錄；可「加到主畫面」。

### T6 測試
先把測試情境條列給使用者確認（預計涵蓋：BMR 與熱量目標計算、未完成首次設定的導向、首次設定表單驗證、個人資料修改、體重紀錄新增／刪除與只能操作自己的資料），確認後寫成 `vibe-coding/tests/<日期>-profile.md` 並更新 `vibe-coding/tests/index.md`，再於 `packages/fitness/profile/tests/` 撰寫並執行。
**完成條件**：使用者確認情境；全部測試（含既有測試）通過、無 skip；Pint、Prettier、ESLint 通過。

## 進度

| Task | 狀態 |
|---|---|
| T1 環境 | 完成 |
| T2 模組基礎建設 | 未開始 |
| T3 profile 後端 | 未開始 |
| T4 設計 demo | 未開始 |
| T5 前端實作與 PWA | 未開始 |
| T6 測試 | 未開始 |
