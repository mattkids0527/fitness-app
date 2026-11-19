# 交接：M1 帳號基礎

- 更新日期：2026-11-19
- 對應任務：[M1 帳號基礎](../milestones/M1-account-foundation.md)
- 分支：`milestone/M1-account-foundation`（已以 `--no-ff` merge commit 合回本地 `main`，**尚未 push**）
- 目前進度：M1 全部完成（T1～T6）。下一步是 M2 Google 登入，開始前先依工作準則討論 spec 與 demo。

## 下次接手從這裡開始

1. `git checkout main`，確認 M1 的 merge commit 在 `main` 上；`docker compose up -d` 啟動資料庫。
2. **尚未 push**：這台機器沒有 `gh`，M1 是在本地合回 `main` 的，CI（Pint、Prettier、ESLint、PHPUnit on PHP 8.4）還沒在 GitHub 上跑過。push `main` 後留意 CI 結果；本地已全部通過（67 tests）。
3. 本地帳號用指令建立：`php artisan user:create --name=... --email=... --generate-password`（註冊已關閉）。
4. 待使用者事後檢視：`vibe-coding/tests/` 的三份測試情境（這次照使用者指示一路做完，沒有先逐條確認）。
5. M2 開始前：從更新後的 `main` 開 `milestone/M2-...` 分支；spec §3.1 已改為「帳號先以指令建立，登入後到設定頁綁定 Google」。

## ⚠️ 特別注意：T1 環境與本地開發需多一步驟

只有 **T1 環境** 與 **本地開發** 相關的事情需要多執行一步驟；其他 Task（T2～T6）照一般流程即可。

自 T1 起，資料庫從 SQLite 改為 docker 裡的 PostgreSQL 16。在本地跑 `composer dev`、`php artisan migrate`、`./vendor/bin/phpunit` 之前，**要先把資料庫容器啟動**：

```bash
docker compose up -d
```

- 容器名稱 `fitness_app_postgres`，對外 port `54329`（避開本機其他專案的 54326～54328）。
- 帳密 `fitness` / `secret`；開發庫 `fitness_app`、測試庫 `fitness_app_testing`。
- 測試庫由 `docker/postgres/init/01-create-testing-database.sql` 在容器**第一次初始化**時建立。若 volume 已存在但沒有測試庫，執行 `docker compose down -v && docker compose up -d` 重建（會清掉開發資料），或手動 `CREATE DATABASE fitness_app_testing OWNER fitness;`。
- 新環境（剛 clone 下來）：`cp .env.example .env` → `php artisan key:generate` → `docker compose up -d` → `php artisan migrate`。
- 舊的 `database/database.sqlite` 已不再使用（已在 .gitignore），可自行刪除。

## T1 改動摘要

- `docker-compose.yml`、`docker/postgres/init/01-create-testing-database.sql`：只起 postgres:16。
- `.env` / `.env.example`：`DB_CONNECTION=pgsql`、`APP_LOCALE=zh_TW`、`APP_FAKER_LOCALE=zh_TW`。
- `phpunit.xml`：改用 pgsql 的 `fitness_app_testing`。
- `.github/workflows/tests.yml`：加 PostgreSQL service（port 54329）、`pdo_pgsql` 擴充，移除建立 SQLite 檔的步驟。
- `config/app.php`：時區 `Asia/Taipei`。
- `lang/zh_TW/`：auth、pagination、passwords、validation（鍵值與框架英文版逐一比對，無缺漏）；`validation.attributes` 只放 starter kit 共用欄位，模組欄位放各模組語系檔。
- `CLAUDE.md`：更新資料庫、測試與 docker 說明。

## T2 改動摘要

- `packages/fitness/profile/`：`composer.json`、`README.md`、`src/ProfileServiceProvider.php`（setupConfig / bindInstances / registerSingletons / setupLang / setupRoutes）、`src/config/fitness/profile.php`（`binds`、`singletons` 皆為空）、`src/lang/zh_TW/messages.php`、`src/routes/web.php`（`web` + `auth` middleware，名稱前綴 `fitness.profile.`，目前無路由）。
- 根目錄 `composer.json`：psr-4 `Fitness\Profile\`、autoload-dev `Fitness\Profile\Tests\`。
- `bootstrap/providers.php`：註冊 `ProfileServiceProvider`。
- `phpunit.xml`：Unit / Feature testsuite 加 `packages/fitness/*/tests/...`，source 加 `packages/fitness/*/src`。
- Provider 尚無 `setupMigrations` / `setupEvents`，等 T3 有 migration、事件時再加（寫作規範：沒用到的掛載點不寫）。
- 驗證方式：暫時加一條路由與兩支空測試，確認 `route:list` 看得到 `fitness.profile.*`、`config('fitness.profile')` 與 `FitnessProfile::` 語系可取得、phpunit 掃到模組 Unit / Feature 測試（29 tests 通過），之後已移除。

## T3 改動摘要

- migration（模組內）：`users` 加 profile 欄位；新表 `weight_logs`（不設每日唯一，每次量測都留紀錄，目前體重取最新一筆）。
- `Enums/`：`Gender`、`ActivityLevel`、`Goal`（附中文 label 與 `options()`）。用 enum 而非 User 常數，因為 User 在 `app/`，不讓它依賴模組。
- `Services/`：`BmrCalculator`、`EnergyTargetCalculator`（+ `EnergyTargets`）、`OnboardingService`（交易內寫資料 + 第一筆體重 + 標記完成）、`WeightLogService`（最後一筆不能刪 → `CannotDeleteLastWeightLogException`）、`UserCalendar`（使用者時區的今天）。
- `EnsureProfileCompleted`：Provider 加到 `web` 群組，例外路由在設定 `onboarding_except_routes`。
- Controller 回傳的 Inertia 頁面 `profile/Onboarding`、`profile/Show`、`profile/WeightLogs` 尚未建立，T5 實作（頁面切分可依 T4 demo 再調整）。
- `app/Models/User.php`：補 casts（`birth_date` 用 `date:Y-m-d`，避免 UTC 序列化後前端差一天）。
- `UserFactory`：預設為已完成首次設定，新增 `notOnboarded()` state，讓既有測試不被導向。
- 設定預設值（可在 `src/config/fitness/profile.php` 調整，待使用者確認）：
  - 日常活動係數（不含運動）：久坐 1.2／輕度 1.3／中度 1.4／高度 1.5
  - 蛋白質 g/kg：減脂 1.8／維持 1.2／增肌 1.6
  - 每公斤 7700 kcal；每週速度：減脂 0.1～1.0、增肌 0.1～0.5 kg
  - 熱量目標目前沒有下限保護（例如不低於 BMR）
- BMR 即時預覽（首次設定畫面輸入時）留到 T5 以前端公式計算；後端在 `/me` 頁提供計算結果。
- tinker 驗證：3 組 BMR 與 3 組熱量／蛋白質目標皆與手算一致，未寫入資料庫。

## T4 改動摘要

- demo 定稿：`vibe-coding/ui/features/m1-account/`（`index.html`、`styles.css`、`app.js`），畫面情境見該目錄 `README.md`（m1-01～m1-17）；`vibe-coding/ui/index.md` 已登記。
- 使用者決定：
  - 品牌色：橘 `hsl(21 90% 48%)`（Tailwind orange-600），淺底 `hsl(33 100% 96%)`；T5 在 `resources/css/app.css` 加 `--brand` / `--brand-foreground` / `--brand-soft` token（淺色、深色各一組）。
  - 熱量下限提醒：每日熱量目標低於 男 1,500／女 1,200 kcal 時只提醒、不阻擋。T5 在 `config/fitness/profile.php` 加 `calorie_floor`（`male` / `female`，走 `env()`），由後端算好或連同目標一起傳給前端，不要在前端寫死數值。
  - 編輯個人資料為獨立頁面：T5 新增 `GET /me/edit`（`fitness.profile.edit`）與 `profile/Edit` 頁；`profile/Show` 只顯示。
  - 首次設定分 4 步（基本資料 → 體重與活動量 → 目標與速度 → BMR 與每日目標確認），只在最後一步送出一次 `POST /onboarding`。
  - 刪除體重：垃圾桶按鈕 + 確認對話框；只剩一筆時停用按鈕並顯示說明。
- T5 實作提醒：
  - 首次設定與編輯頁的 BMR／每日目標即時預覽在前端用同一套公式計算，係數從後端 props 帶入（`activity_factors`、`kcal_per_kg`、`protein_g_per_kg`、`goal_rate`），避免前後端數值不一致。
  - 今日頁需要每日目標與最新體重，Dashboard 要從後端取得（可用 `EnergyTargetCalculator::forUser()`）。
  - 手機底部 Tab 與桌機側欄以 768px（Tailwind `md`）切換；「＋」在手機是底部面板，桌機是置中對話框。
  - 體重增減顏色：下降綠、上升玫瑰紅，不用品牌橘，避免跟按鈕混淆。

## T5 改動摘要（拆成 T5-1～T5-8，各自一個 commit）

- **T5-1 後端補強**：`GET /me/edit`；設定 `calorie_floor`（男 1500／女 1200，`PROFILE_CALORIE_FLOOR_*`），`EnergyTargets` 多 `calorie_floor`、`below_floor`；表單選項加 `calculation`（前端預覽用係數）；體重列表改成 `?page=N` 顯示前 N 批並附較前一筆差值（`WeightLogService::history()` / `current()`）。新增 `daily-summary` 模組（`/today`、收集型事件 `DailySummaryCollecting`），profile 以 listener `AddProfileDailySummary` 加入每日目標與體重。starter kit 的 `dashboard`、`Welcome` 移除，`/` 依登入狀態導向。spec 模組表的依賴已更新（daily-summary 無依賴；profile 監聽它的事件）。
- **T5-2 帳號**：註冊預設關閉（`AUTH_REGISTRATION_ENABLED`），登入頁隱藏註冊與忘記密碼連結（`AUTH_PASSWORD_RESET_ENABLED`）；`user:create`、`user:password` 指令（`app/Console/Commands/`）。發布 `config/inertia.php` 把測試頁面路徑改成小寫 `pages`。
- **T5-3 版型**：橘色 token（`--primary`、`--brand-soft`、`--page`）、手機底部 Tab／桌機側欄、＋選單（`ResponsiveDialog`）、「即將推出」頁。`APP_NAME=Fitness`。
- **T5-4 首次設定**：4 步、最後一步才送出；`useEnergyTargets` 與後端同公式；`components/profile/` 的 `BmrTool`、`TargetsCard`、`TargetsBreakdown`、`LowCalorieWarning`、`GoalField`、`ChoiceList`。enum 選項多了 `description`（語系 `activity_level_description`、`goal_description`）。
- **T5-5 我的／編輯／體重**：`profile/Show`、`Edit`、`WeightLogs`；共用 `WeightLogForm` / `WeightLogSheet`；全站提示 `AppToast`。
- **T5-6 今日頁**：`daily-summary/Today`（熱量環形圖、蛋白質還差、體重卡、即將推出卡片）。
- **T5-7 中文化**：登入、忘記／重設密碼、信箱驗證、設定三頁、刪除帳號、使用者選單、外觀選項、sr-only 文字。未使用的 `AppHeader.vue`／`AuthCardLayout`／`AuthSplitLayout` 沒改。
- **T5-8 PWA**：manifest、icons（`public/icons/`）、`sw.js`、`offline.html`、blade meta（含 `viewport-fit=cover`，底部 Tab 才吃得到 safe-area）；`favicon.ico` 換成 `favicon.svg`。
- 驗收：以 390px 手機寬度實際操作「指令建帳號 → 登入 → 首次設定 → 今日 → ＋記錄體重」，數值與手算一致；桌機、深色模式截圖檢查；manifest／icons 200、service worker 控制頁面、離線顯示離線頁。

## T6 改動摘要

- 情境：`vibe-coding/tests/2026-11-19-profile.md`、`2026-11-19-daily-summary.md`、`2026-11-19-account.md`（已登記到 `tests/index.md`）。
- 測試：`packages/fitness/profile/tests/{Unit,Feature}`、`packages/fitness/daily-summary/tests/Feature`、`tests/Feature/Console/UserCommandsTest.php`、`tests/Feature/ComingSoonTest.php`、`tests/Feature/HomeTest.php`、`tests/Feature/Auth/RegistrationTest.php`（改為驗證註冊關閉）。
- 結果：67 tests / 444 assertions 全部通過、無 skip；Pint、Prettier、ESLint 通過。
- 測試抓到的問題：`EnsureProfileCompleted` 原本用 Router 的 `pushMiddlewareToGroup()` 掛到 `web` 群組，HTTP Kernel 重新同步群組時被蓋掉，導致（至少在測試環境）未完成設定的使用者沒被導向。已改用 Kernel 的 `appendMiddlewareToGroup()`。

## 已知事項

- `npx vue-tsc --noEmit` 在 starter kit 原本的 `tsconfig.json`（`types` 引用 `vue/tsx`、`./resources/js/types`）就會失敗，CI 沒有跑型別檢查；這次新寫的檔案以暫時的 tsconfig 檢查過，沒有新增型別錯誤（剩下的都是 starter kit 既有的）。
- 本地開發庫留有測試帳號（`ui-test@example.com`、`accept*@example.com`）已在收尾時刪除。
- 計算係數與熱量下限仍是預設值，使用者可再調整 `packages/fitness/profile/src/config/fitness/profile.php`。

## T1 驗證結果

- `php artisan migrate:fresh` 在 PostgreSQL 成功。
- `./vendor/bin/phpunit`：27 tests / 64 assertions 全部通過（跑在 `fitness_app_testing`）。
- 驗證訊息為中文，例如「電子郵件必須是有效的電子郵件地址。」「密碼至少要 8 個字元。」
- Pint 通過。
- CI 尚未實際在 GitHub 上跑過（尚未 push），第一次 push 時留意 tests workflow。

## 進度紀錄

| 日期 | Task | 內容 |
|---|---|---|
| 2026-11-19 | T1 | 完成：PostgreSQL、語系 zh_TW、時區 Asia/Taipei |
| 2026-11-19 | T2 | 完成：profile 模組骨架與註冊 |
| 2026-11-19 | T3 | 完成：profile 後端（migration、Repository、BMR／熱量計算、首次設定導向、Controller／FormRequest） |
| 2026-11-19 | T4 | 完成：demo 定稿於 `vibe-coding/ui/features/m1-account/`（品牌色橘、熱量下限提醒、編輯頁獨立） |
| 2026-11-19 | T5 | 完成：T5-1～T5-8（後端補強與 daily-summary、帳號指令與關閉註冊、版型、首次設定、我的／編輯／體重、今日、中文化、PWA） |
| 2026-11-19 | T6 | 完成：67 tests 通過；修正 EnsureProfileCompleted 的掛載方式 |
| 2026-11-19 | — | M1 以 `--no-ff` merge commit 合回本地 `main`（尚未 push） |
| 2026-11-19 | — | 確立分支流程：一個 milestone 一個分支，完成後 PR 合回 `main`（見 CLAUDE.md「分支流程」） |
