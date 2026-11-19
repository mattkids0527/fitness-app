# 交接：M1 帳號基礎

- 更新日期：2026-11-19
- 對應任務：[M1 帳號基礎](../milestones/M1-account-foundation.md)
- 分支：`milestone/M1-account-foundation`（M1 全部完成後開 PR 合回 `main`）
- 目前進度：T1～T4 已完成，下一步 T5 前端實作與 PWA
- 最後 commit：`docs(ui): finalize M1 account demo screens`（工作目錄乾淨、尚未 push）

## 下次接手從這裡開始

1. 確認在 `milestone/M1-account-foundation` 分支，`docker compose up -d` 啟動資料庫。
2. 先看本檔「T4 改動摘要」與 [畫面情境](../ui/features/m1-account/README.md)，瀏覽器打開 `vibe-coding/ui/features/m1-account/index.html` 對照 demo。
3. 開始 T5 前，先跟使用者討論 T5 的拆分（建議順序：品牌色 token 與版型（底部 Tab／側欄）→ 首次設定頁 → 我的／編輯頁 → 體重紀錄 → 今日頁 → 既有頁面中文化 → PWA），確認後逐步進行。
4. T5 需要的後端小改動：`GET /me/edit` 路由、設定檔 `calorie_floor`、把計算係數與每日目標傳給前端（Dashboard 也要）。

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
| 2026-11-19 | — | 確立分支流程：一個 milestone 一個分支，完成後 PR 合回 `main`（見 CLAUDE.md「分支流程」） |
