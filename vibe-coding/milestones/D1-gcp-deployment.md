# D1 GCP 部署與 CI/CD

- 狀態：進行中（2026-11-20 開始）
- 建立日期：2026-11-20
- 分支：`milestone/D1-gcp-deployment`（完成後開 PR 合回 `main`）
- 依據規格：[D1 GCP 部署與 CI/CD 規格](../specs/2026-11-20-d1-gcp-deployment.md)

## 目標

把應用部署到 GCP 免費額度的 e2-micro VM（全新的 Ubuntu），以 `https://wn52777.com` 上線：Cloudflare Tunnel 連到 VM，正式環境的 Google 登入一起完成（規格 §3）。流程是：PR 跑 CI → 合併 `main` → 在 GitHub Actions 手動按按鈕部署。部署用 Deployer，網站不中斷、可 rollback；資料庫每天自動備份。

## 範圍

**包含**
- 修正 CI：lint 改成只檢查不修改，讓格式錯誤會擋下 PR
- VM 初始化腳本 `deploy/provision.sh` 與 GCP 主控台操作文件
- 資料庫每日備份與還原步驟
- 網域 `wn52777.com` 與 Cloudflare Tunnel（VM 不開放 80／443）
- Deployer 設定 `deploy.php`
- GitHub Actions 手動部署 workflow `deploy.yml`、Environment 與 Secrets、`main` 分支保護
- 第一次正式部署、正式環境的 Google 登入與上線檢查

**不包含**
- 常駐 queue worker、排程（M4 再加）
- 檔案改存 S3／R2、監控告警、staging、自動部署、異地備份

## Tasks

### T1 修正 CI
`lint.yml` 改成只檢查不修改：`pint --test`、`npm run format:check`、`eslint .`（不加 `--fix`）；`permissions` 改成 `contents: read`；改用 `npm ci` 並開啟 npm 快取。`tests.yml` 的 `coverage: xdebug` 改成 `none`。兩支 workflow 都加 `concurrency`，同分支有新的 push 時取消舊的執行。本地先跑一次三項檢查，確認目前的程式碼本身會通過。
**完成條件**：本地三項檢查都通過；兩支 workflow 的 YAML 語法正確。

### T2 VM 初始化腳本
新增 `deploy/provision.sh`：在全新的 Ubuntu 上以 sudo 執行一次，可以重複執行（已完成的步驟會略過）。內容依規格 §5、§6：
- 2GB swap
- 套件：nginx、PHP 8.4-FPM 與所需擴充（ondrej/php PPA）、PostgreSQL 16（PGDG）、Composer、git、unzip
- `deploy` 帳號：只能用金鑰登入；sudoers 只允許 reload php-fpm
- SSH：關閉密碼登入與 root 登入
- 防火牆與防護：ufw 只開 22／80／443、fail2ban、unattended-upgrades
- PHP-FPM pool 與 OPcache、PostgreSQL 記憶體調整，建立資料庫與專用帳號
- nginx site 設定（root 指向 `current/public`，`/build/` 長效快取，開 gzip）
- `/var/www/fitness-app/shared/.env` 範本

可調整的值（資料庫密碼、`deploy` 的公鑰等）以參數或互動輸入提供，不寫死在 repo。新增 `deploy/README.md`，寫 GCP 主控台的操作：建立 e2-micro（`us-west1`）、靜態 IP、防火牆規則、第一次 SSH 連線、執行腳本。
**完成條件**：shellcheck 沒有錯誤；在本地的 Ubuntu 容器試跑過，systemd 相關步驟除外；使用者在 VM 上執行成功（與 T4 一起驗證）。

### T3 資料庫備份
新增備份腳本，由 `provision.sh` 安裝到 VM 並設定 cron：每天 03:00（Asia/Taipei）執行 `pg_dump -Fc`，存到 `/var/backups/fitness-app/`，保留最近 7 份。`deploy/README.md` 補上還原步驟（`pg_restore`）與手動備份指令。依規格 §9.3，把「migration 要相容新舊版程式」的原則補進 `vibe-coding/coding-style.md`。
**完成條件**：在 VM 上手動執行備份腳本成功，產生備份檔；照 README 把備份還原到一個暫時的資料庫成功。

### T4 網域與 Cloudflare Tunnel
調整 `provision.sh`，依規格 §3：
- nginx 只聽 `127.0.0.1:80`，`server_name` 設為 `wn52777.com`，`www` 轉向不含 www 的網址
- 傳給 PHP-FPM 時固定加上 `HTTPS=on`；`real_ip` 只信任來自 `127.0.0.1` 的 `CF-Connecting-IP`；看到 `X-Forwarded-Proto: http` 時轉向 https
- ufw 只開 22（移除 80／443）
- 有提供 Tunnel token 時安裝 `cloudflared` 並註冊成系統服務
- `.env` 範本改成 `APP_URL=https://wn52777.com`、`SESSION_SECURE_COOKIE=true`

`deploy/README.md` 補上 Cloudflare 後台的操作（建立 Tunnel、Public Hostname、SSL/TLS、Rocket Loader），以及拿掉 GCP 防火牆的 HTTP／HTTPS 規則；原本依 IP 上線的說明一併改寫。使用者在 VM 上執行腳本，T2、T3、T4 一起驗證。
**完成條件**：容器內試跑通過；使用者在 VM 上執行成功，T2、T3 的驗證指令都通過；瀏覽 `https://wn52777.com` 得到 nginx 回應（程式尚未部署，會是 404）；直接連 `http://<靜態 IP>` 連不上。

### T5 Deployer 設定
`composer require --dev deployer/deployer`。新增根目錄的 `deploy.php`：使用 `recipe/laravel.php`，部署路徑 `/var/www/fitness-app`，保留 3 個版本，`.env` 和 `storage` 放 shared。加上幾個自訂 task：
- 上傳本地已建置的 `public/build/`
- `event:cache`
- 切換連結後 reload php-fpm

主機位址從環境變數讀取，不寫死在 repo。先從本地（WSL）執行 `npm run build && vendor/bin/dep deploy production`，部署到 VM，確認整套流程可用；用 `php artisan user:create` 建立正式環境帳號。
**完成條件**：從本地部署成功，`https://wn52777.com` 能登入並完成首次設定；`dep rollback production` 可以切回上一版。

### T6 GitHub Actions 部署 workflow
新增 `.github/workflows/deploy.yml`，依規格 §7：
- 觸發方式 `workflow_dispatch`，不是 `main` 就直接失敗
- 使用 Environment `production`，設定 `concurrency: production`
- 步驟：安裝相依套件、`npm run build`、用 Secrets 設定 SSH、執行 `dep deploy production`

整理 GitHub 網頁上要做的設定步驟，寫進 `deploy/README.md`：建立 Environment 與三個 Secrets、`main` 分支保護（必須透過 PR，`quality` 與 `ci` 都通過才能合併）。由使用者在 GitHub 上操作。
**完成條件**：YAML 語法正確；使用者已完成 Environment、Secrets 與分支保護設定。

### T7 第一次正式部署與收尾
在 Google Cloud 建立正式環境的 OAuth 用戶端（redirect URI `https://wn52777.com/auth/google/callback`，規格 §3.5），憑證填入 VM 的 `shared/.env`。本分支 push 後開 PR，確認新的 CI 正常運作（格式錯誤會讓 CI 失敗）。合併 `main` 後，在 Actions 頁面手動觸發部署，然後照上線檢查清單逐項確認：
- 登入、首次設定、體重紀錄、今日頁
- 在設定頁綁定 Google，登出後用 Google 登入
- 網址都是 `https://`；手機瀏覽器可以安裝成 PWA
- `/build/` 的檔案在回應標頭中有 `cf-cache-status: HIT`（Cloudflare 邊緣快取生效）
- 由 cron 自動產生的備份檔
- 重新部署一次，確認 `releases/` 只保留 3 個版本

寫 handoff，更新 `CLAUDE.md`（部署相關指令與架構說明），milestone 狀態改成完成。
**完成條件**：經由 GitHub Actions 部署成功，上線檢查清單全部通過；handoff 與 `CLAUDE.md` 已更新。

## 測試

本 milestone 主要是設定檔與腳本，不新增 PHPUnit 測試，改用各 Task 的完成條件和 T7 的上線檢查清單驗證。如果過程中需要改到應用程式碼，例如正式環境專用的設定，再依工作準則先列測試情境給使用者確認。

## 備註

- 這台機器目前沒有安裝 `gh` CLI。開 PR 可以由使用者在 GitHub 網頁操作，或先安裝 `gh` 並登入。
- 腳本和文件中出現的 IP、金鑰、密碼一律用佔位符，實際的值不進 repo。
