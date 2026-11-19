# 交接：M1 帳號基礎

- 更新日期：2026-11-19
- 對應任務：[M1 帳號基礎](../milestones/M1-account-foundation.md)
- 分支：`milestone/M1-account-foundation`（M1 全部完成後開 PR 合回 `main`）
- 目前進度：T1～T3 已完成，下一步 T4 設計 demo 畫面

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
| 2026-11-19 | — | 確立分支流程：一個 milestone 一個分支，完成後 PR 合回 `main`（見 CLAUDE.md「分支流程」） |
