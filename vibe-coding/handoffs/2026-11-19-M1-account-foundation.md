# 交接：M1 帳號基礎

- 更新日期：2026-11-19
- 對應任務：[M1 帳號基礎](../milestones/M1-account-foundation.md)
- 目前進度：T1 環境 已完成，下一步 T2 模組基礎建設

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

## 驗證結果

- `php artisan migrate:fresh` 在 PostgreSQL 成功。
- `./vendor/bin/phpunit`：27 tests / 64 assertions 全部通過（跑在 `fitness_app_testing`）。
- 驗證訊息為中文，例如「電子郵件必須是有效的電子郵件地址。」「密碼至少要 8 個字元。」
- Pint 通過。
- CI 尚未實際在 GitHub 上跑過（尚未 push），第一次 push 時留意 tests workflow。

## 進度紀錄

| 日期 | Task | 內容 |
|---|---|---|
| 2026-11-19 | T1 | 完成：PostgreSQL、語系 zh_TW、時區 Asia/Taipei |
