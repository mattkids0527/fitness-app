# 交接：D1 GCP 部署與 CI/CD

- 更新日期：2026-11-20
- 對應任務：[D1 GCP 部署與 CI/CD](../milestones/D1-gcp-deployment.md)
- 依據規格：[D1 GCP 部署與 CI/CD 規格](../specs/2026-11-20-d1-gcp-deployment.md)
- 分支：`develop`（本 milestone 中途改為 develop → main 流程，PR 由使用者在 GitHub 開與合併）
- 目前進度：D1 全部完成（T1～T7）。正式站 **https://wn52777.com** 已上線，並經由 GitHub Actions 部署成功；使用者已在正式站驗收 Google 綁定與 Google 登入。

## 下次接手從這裡開始

1. `git checkout develop && git pull --ff-only`。這份 handoff 與 CLAUDE.md 的更新在 `develop`，等使用者開 PR 合進 `main`。這個 PR 只有文件變更，合併後不需要部署。
2. **日常開發流程**：在 `develop` 上 commit 並 push（會觸發 CI）→ 使用者開 PR `develop` → `main`（`quality`、`ci` 必須通過）→ 合併 → Actions → deploy → Run workflow（分支 `main`）。
3. **CD 只部署程式碼**。VM 的系統設定（PHP 擴充、nginx、cron、queue worker 等）要改 `deploy/provision.sh`，再請使用者到 VM 重跑一次。腳本可重複執行。
4. **改正式環境設定**：從 VM 讀出 `shared/.env`，修改後整份更新 GitHub Secret `PRODUCTION_ENV`，再部署（`deploy/README.md` §9）。不要直接改 VM 上的 `.env`，下次部署會被覆蓋。
5. **M4 會用到 queue job**：正式環境目前是 `QUEUE_CONNECTION=sync`，沒有常駐 worker。M4 要在 `provision.sh` 加入 systemd 常駐的 `queue:work`，並把 `PRODUCTION_ENV` 改成 `QUEUE_CONNECTION=database`。

### 尚未確認的項目（使用者驗收時補看）

- **cron 自動備份**：每天 03:00 執行，部署當天還沒到時間。之後在 VM 執行 `sudo ls -l /var/backups/fitness-app/`，確認有 03:00 的檔案；執行紀錄可用 `journalctl -t fitness-app-backup` 查看。
- **`releases/` 只保留 3 個版本**：目前剛好 3 個（T5 兩個 + Actions 一個，之後 Google 憑證重新部署又多了幾次，應已觸發清理）。可用 `DEPLOY_HOST=<IP> vendor/bin/dep releases` 確認。
- **首次設定、體重紀錄、今日頁的正式站操作，以及手機安裝 PWA**：使用者回報 Google 綁定、登入成功，其他項目還沒個別回報。
- **iOS 加到主畫面（PWA standalone）後實機測 Google 登入**：M2 handoff 留下的風險項目，仍未測。
- **M2 遺留的待決事項**：未綁定的 Google 登入，錯誤說明要不要加上「還沒有帳號的話，請聯絡管理員」。使用者尚未回覆。

## 正式環境架構

```
使用者 ─HTTPS─▶ Cloudflare（wn52777.com，憑證、快取、DDoS 防護）
                  │ Tunnel（VM 主動連出）
                  ▼
GCP e2-micro（us-west1，Ubuntu 24.04，1GB RAM + 2GB swap）
  cloudflared ─▶ nginx（只聽 127.0.0.1:80）─▶ PHP 8.4-FPM（deploy 身分）─▶ PostgreSQL 16（localhost）
  /var/www/fitness-app/{releases,shared/.env,shared/storage,current}
```

- **VM 不開放 80／443**：ufw 只開 22，GCP 防火牆也拿掉了 HTTP／HTTPS。直接連 `http://<IP>` 會逾時。
- **外部 IP 用臨時 IP**，沒有改靜態：網站走 Tunnel，不受 IP 影響，只有部署的 SSH 需要 IP。外部 IPv4 約 US$3.65／月，臨時和靜態價格一樣。換 IP 時改 GitHub Variable `DEPLOY_HOST` 即可。
- **Laravel 判斷 HTTPS、取得真實 IP 的做法**：nginx 傳給 PHP 時固定加 `HTTPS=on`，並用 `real_ip` 只信任來自 127.0.0.1 的 `CF-Connecting-IP`。**沒有改 Laravel 程式**（原本規劃的 `trustProxies` 和 `real_ip` 會互相衝突，已記錄在規格 §3.3）。

## T1 修正 CI

- `lint.yml` 改成只檢查不修改：`pint --test`、`prettier --check`、`eslint .`。`permissions: contents: read`，改用 `npm ci` 並加上快取。
- `tests.yml` 改成 `coverage: none`。兩支 workflow 都加了 `concurrency`。
- 觸發時機：push 到 `develop`／`main`，以及 PR 目標為 `develop`／`main`。PR 頁面會出現 4 項檢查（2 支 workflow × push 與 pull_request 兩種事件）。

## T2 VM 初始化腳本（`deploy/provision.sh`）

- 在 VM 上以 sudo 執行一次，可重複執行。第一次要提供 `DEPLOY_PUBKEY`，Tunnel token 會在執行中互動詢問，輸入時不顯示。
- 安裝與設定的內容見 `deploy/README.md` §5 的表格。
- **只支援 Ubuntu 22.04／24.04**。26.04 實測時 ondrej/php 套件庫還沒有 PHP 8.4 的擴充套件，腳本會直接中止。
- **改 nginx 設定一定要 restart，不能用 reload**。listen 位址從 `0.0.0.0:80` 改成 `127.0.0.1:80` 時，reload 會因 `bind() ... Address already in use` 失敗，而 `systemctl reload` 仍然回報成功。正式站就是因此一度顯示 nginx 歡迎頁，已在 commit `bef13f2` 修正。
- 在 VM 上執行 `php artisan` 一律加 `sudo -u deploy`。用自己的帳號執行會因為沒有權限寫入 `storage/logs` 而出錯，也可能留下不屬於 `deploy` 的檔案。

## T3 資料庫備份

- `/usr/local/bin/fitness-app-backup`：每天 03:00 由 cron 以 `postgres` 身分執行，用 `pg_dump -Fc` 備份到 `/var/backups/fitness-app/`，保留最近 7 份。
- 備份目錄是 `postgres:deploy 2750`（setgid），`deploy` 可以用 scp 下載。使用者自己的帳號要加 `sudo` 才看得到。
- 已在 VM 上驗證：手動備份、還原到暫時的資料庫都成功。
- `coding-style.md` §12 補上原則：migration 要相容新舊版程式，`dep rollback` 不會退回資料庫。

## T4 網域與 Cloudflare Tunnel

- 規格原本是「先用 IP 上線，之後再補網域」，使用者中途買了網域，改為直接用 `wn52777.com` 上線。
- Cloudflare 後台的設定：
  - Tunnel `fitness-app` 的 Public Hostname：`wn52777.com`、`www` → `HTTP 127.0.0.1:80`
  - SSL/TLS 加密模式 Full、Always Use HTTPS
  - Rocket Loader 關閉
  - **Browser Cache TTL 設為 Respect Existing Headers**。預設的 4 小時會蓋掉 nginx 給 `sw.js` 的 `no-cache`。
- 踩過的坑：
  - **DNS 不能自己加 `A 127.0.0.1`**。`127.0.0.1:80` 只填在 Tunnel 的 Service 欄位，DNS 由 Cloudflare 自動建立 CNAME。
  - **測 GitHub 連線要用 `deploy` 帳號**：`sudo -u deploy ssh -T git@github.com`。

## T5 Deployer（`deploy.php`）

- Deployer 7.5.12，使用 `recipe/laravel.php`：
  - 部署 `main`，保留 3 個版本，`writable_mode` 設為 `skip`（PHP-FPM 以 `deploy` 身分執行）。
  - 在 `composer install` 之後上傳 `public/build/`。
  - 切換連結後和 rollback 後都會 reload php-fpm。
  - 部署失敗時自動解鎖。
  - 不轉發 SSH agent。
- 使用者已從 WSL 部署並測試 rollback 成功。

## T6 GitHub Actions 部署按鈕

- `.github/workflows/deploy.yml`：只能手動觸發，只能部署 `main`，使用 Environment `production`，`concurrency: production`。
- **Environment `production` 的設定**（使用者已設定完成，並限制只有 `main` 能使用）：

  | 名稱 | 類型 | 內容 |
  |---|---|---|
  | `DEPLOY_HOST` | Variable | `deploy@<IP>:/var/www/fitness-app`，格式 `[帳號@]IP[:路徑]` |
  | `DEPLOY_SSH_KEY` | Secret | 部署用私鑰 |
  | `DEPLOY_HOST_KEY` | Secret | VM 的 host 公鑰，不綁 IP |
  | `PRODUCTION_ENV` | Secret | 整份正式環境 `.env` |

- `deploy.php` 的 `deploy:upload_env`：
  - 有提供 `DEPLOY_ENV_FILE` 時，先檢查 `APP_ENV=production`、`APP_KEY` 不能是空的。
  - 檢查通過後把舊版備份成 `shared/.env.previous`，再替換。
  - 在 `deploy:shared` 之前執行，所以會比 `config:cache` 先完成。
  - 本地部署沒有提供 `.env` 時，沿用 VM 上現有的 `.env`。
- 環境變數 `DEPLOY_SSH_KEY_FILE` 是私鑰的**路徑**（和 GitHub Secret `DEPLOY_SSH_KEY` 的**內容**不同，所以刻意不同名）。另外支援 `DEPLOY_KNOWN_HOSTS_FILE`，會嚴格比對 host key。
- 驗證：
  - `DEPLOY_HOST` 的 10 種寫法解析正確。
  - `.env` 上傳在 sshd 容器裡實測：三種錯誤情況都中止、CRLF 轉 LF、備份、權限 600。
  - host key 錯誤時，連線會被拒絕。
  - actionlint 通過。
- **分支保護**：一個人開發要把 **Require approvals 設為 0**，否則 PR 作者無法核准自己的 PR，會永遠卡在 Review required。

## T7 第一次正式部署

- 使用者已把 `develop` 合進 `main`，經由 Actions 部署成功：前端檔案的 hash 有更新，`/build/` 在 Cloudflare 為 `HIT`，`sw.js` 為 `no-cache`。
- 正式環境的 Google 登入：
  - 第一次 `redirect_uri_mismatch` 的原因：建立的 OAuth 用戶端類型是「**電腦版應用程式**」，下載的 JSON 是 `installed`、`redirect_uris` 為 `http://localhost`，無法新增重新導向 URI。
  - 改成建立「**網頁應用程式**」用戶端，重新導向 URI 為 `https://wn52777.com/auth/google/callback`；「已授權的 JavaScript 來源」不用填。
  - 更新 `PRODUCTION_ENV` 並重新部署後，綁定和登入都成功。
- 舊的電腦版用戶端（`492458390286-51jcge...`）已經不用了，使用者可以到 Google Cloud 刪除。

## 本 milestone 沒有新增 PHPUnit 測試

範圍是設定檔與腳本，沒有改動應用程式碼，所以沒有新增測試情境檔。改用各 Task 的完成條件，以及在容器、正式站上的實測來驗證。

## 之後可以考慮的改善（未排入）

- `rollback.yml`：用按鈕退回上一版，目前要在本地執行 `dep rollback`。
- SSH 也改走 Tunnel：關閉 22 port，連部署也不依賴 IP。需要 Cloudflare Access 的服務憑證，並調整 `deploy.php` 和 `deploy.yml`。
- GitHub Actions 和本地部署各用一把金鑰，目前共用 `~/.ssh/fitness_deploy`。
- 異地備份，例如 GCS 或 R2。
- OAuth 同意畫面發布為正式版：目前是「測試中」，只有測試使用者能用 Google 登入。
