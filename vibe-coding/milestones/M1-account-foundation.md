# M1 帳號基礎

- 狀態：進行中
- 建立日期：2026-11-19
- 分支：`milestone/M1-account-foundation`（完成後開 PR 合回 `main`）
- 依據規格：[專案總覽規格 v1.0](../specs/2026-11-19-project-overview.md)（§2 技術架構、§2.4 模組劃分、§3.2 首次設定、§4 資料表、§5 頁面）
- 寫作規範：[模組化寫作規範](../coding-style.md)

## 目標

使用者以管理者用指令建立的帳號登入後（第一版不開放註冊），第一次進站會被導到首次設定頁，填完性別、出生日期、身高、體重、日常活動量、目標後才能使用其他頁面；BMR 小工具可自動計算或手動覆寫；體重可持續記錄。整站改為手機優先版型（底部 Tab），可加到手機主畫面（PWA）。

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
依確認的 demo（[ui/features/m1-account](../ui/features/m1-account/README.md)）實作，拆成以下子步驟，每步完成後 commit 並詢問是否進行下一步。
**完成條件**：`npm run build`、`npm run lint`、`npm run format:check` 通過；手機尺寸實際操作：以指令建立帳號 → 登入 → 被導到首次設定 → 完成後進入今日 → 新增體重紀錄；可「加到主畫面」。

#### T5-1 後端補強
`profile` 模組新增 `GET /me/edit`（`fitness.profile.edit` → `profile/Edit`）；設定檔加 `calorie_floor`（男 1500／女 1200，走 `env()`），`EnergyTargets` 回傳 `calorie_floor` 與 `below_floor`；首次設定、編輯頁 props 帶入計算係數（活動係數、每公斤熱量、蛋白質係數、目標速度範圍、最低年齡、熱量下限），讓前端即時預覽與後端一致。建立 `daily-summary` 模組骨架：擁有今日頁路由（取代 starter kit 的 `dashboard`，同步更新所有導向 `dashboard` 的地方），發出收集型事件，`profile` 以 listener 加入每日目標與最新體重。

#### T5-2 帳號建立指令與關閉註冊
第一版不開放註冊：`config/auth.php` 加開關（`AUTH_REGISTRATION_ENABLED`，預設關閉），關閉時不註冊 `register` 路由、登入頁不顯示註冊連結（保留 `RegisteredUserController` 與 `Register.vue`，日後打開開關即可使用）。新增 artisan 指令 `user:create`（姓名、Email、密碼，缺的以互動方式詢問；可用 `--generate-password` 產生隨機密碼並只顯示一次；建立的帳號 Email 視為已驗證、未完成首次設定），以及 `user:password`（重設指定帳號密碼，因為目前沒有寄信設定，忘記密碼信寄不出去，登入頁也隱藏「忘記密碼」連結）。指令放 `app/Console/Commands/`（帳號屬於 starter kit 的共用範圍）。既有的註冊測試依新行為調整，測試情境先列給使用者確認。

#### T5-3 品牌色與版型
`app.css` 加 `--brand` / `--brand-foreground` / `--brand-soft`（淺色、深色各一組）；`AppLayout` 手機（< 768px）底部 Tab、桌機側欄（快速新增 + 今日／飲食／健身／我的）；＋選單手機為 `Sheet`、桌機為 `Dialog`；`routes/web.php` 暫放 `/meals`、`/workouts` 指向共用的「即將推出」頁，M3／M5 模組建立後移除。

#### T5-4 首次設定頁
`profile/Onboarding.vue` 分 4 步、最後一步才送出；共用計算 composable `useEnergyTargets`；`components/profile/` 放 `BmrTool`、`TargetsBreakdown`、`LowCalorieWarning`；型別 `types/profile.ts`。

#### T5-5 我的、編輯、體重紀錄
`profile/Show.vue`、`profile/Edit.vue`、`profile/WeightLogs.vue`；記錄體重面板為共用元件（今日、我的、＋選單共用）；刪除確認、只剩一筆時停用；超過 20 筆顯示「載入更多」。

#### T5-6 今日頁
熱量環形圖、蛋白質還差多少、體重卡片；餐點、喝水、運動為即將推出卡片。

#### T5-7 既有頁面中文化
登入、忘記／重設密碼、信箱驗證、設定三頁、使用者選單、頁面標題改繁體中文；`/` 未登入導向登入、已登入導向今日（不保留 starter kit 的 Welcome 頁）。

#### T5-8 PWA 與驗收
手寫 `public/manifest.webmanifest`、精簡 `public/sw.js`（只快取 build 資源，離線時顯示離線頁）、icons（橘底活動曲線：192、512、maskable、apple-touch-icon）、`app.blade.php` meta；跑 build／lint／format:check，並以 390px 手機寬度走完完成條件的流程。

### T6 測試
先把測試情境條列給使用者確認（預計涵蓋：BMR 與熱量目標計算、未完成首次設定的導向、首次設定表單驗證、個人資料修改、體重紀錄新增／刪除與只能操作自己的資料），確認後寫成 `vibe-coding/tests/<日期>-profile.md` 並更新 `vibe-coding/tests/index.md`，再於 `packages/fitness/profile/tests/` 撰寫並執行。
**完成條件**：使用者確認情境；全部測試（含既有測試）通過、無 skip；Pint、Prettier、ESLint 通過。

## 進度

| Task | 狀態 |
|---|---|
| T1 環境 | 完成 |
| T2 模組基礎建設 | 完成 |
| T3 profile 後端 | 完成 |
| T4 設計 demo | 完成（[ui/features/m1-account](../ui/features/m1-account/README.md)） |
| T5 前端實作與 PWA | 完成（T5-1～T5-8） |
| T6 測試 | 完成（67 tests 通過） |
