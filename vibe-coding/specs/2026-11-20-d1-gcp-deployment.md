# D1 GCP 部署與 CI/CD 規格

> 狀態：已定稿（2026-11-20）。任務見 [D1 milestone](../milestones/D1-gcp-deployment.md)。
> 依據 [專案總覽規格 v1.0](2026-11-19-project-overview.md)：總覽把「部署規劃」列為後續再議，本份即為該規格。M3 已預留給飲食紀錄，部署另以 `D1` 編號，不佔用功能 milestone 序號。
> 「待決」標記的項目需要使用者拍板；「已決」記錄討論結果。

## 1. 目標

把應用部署到 GCP 免費額度的 VM，建立「PR 跑 CI → 合併 `main` → 手動按按鈕部署」的流程。部署工具使用 Deployer（`deploy.php`），每次部署建立新版本目錄並切換連結，網站不中斷，出問題可以 rollback。

## 2. 已決

| 項目 | 結論 |
|---|---|
| 主機 | GCP Compute Engine 免費額度 e2-micro（2 個共享 vCPU、1GB RAM、30GB 標準磁碟），全新的 Ubuntu |
| CI | PR 到 `main` 時跑 lint 與測試（沿用現有的 `lint.yml`、`tests.yml`，並修正 lint 擋不住錯誤的問題） |
| CD 觸發 | 合併 `main` **不自動部署**，在 GitHub Actions 頁面手動按按鈕（`workflow_dispatch`）才部署，而且只能部署 `main` |
| 部署工具 | Deployer 7（`deployer/deployer`，放 composer require-dev），使用內建的 `recipe/laravel.php` |
| 網域 | `wn52777.com`（Cloudflare Registrar），經由 Cloudflare Tunnel 連到 VM，一開始就用網域 + HTTPS 上線（見 §3） |

## 3. 網域與 Cloudflare Tunnel（已決，2026-11-20）

原本規劃先用 IP 上線（階段 A），買網域後再補 HTTPS（階段 B）。使用者已經註冊好 `wn52777.com`，所以**直接用網域上線，不經過 IP 階段**。Google 登入與 PWA 從第一次部署就能使用。

### 3.1 為什麼是「Cloudflare 在 VM 前面」，不是 Workers/Pages

- Workers 只能執行 JavaScript／WebAssembly，不能跑 PHP。
- Pages 只能放靜態網站，但 Inertia 的每個頁面都需要 Laravel 在伺服器端處理。
- 改用 Workers 等於後端全部改寫成 TypeScript，資料庫也要換，M1、M2 都要重做。

所以架構維持 GCP VM + Laravel，Cloudflare（免費方案）放在前面，負責網域、HTTPS、靜態檔快取和防護。

### 3.2 連線方式：Cloudflare Tunnel

```
使用者 ──HTTPS──▶ Cloudflare 邊緣節點（憑證、快取、DDoS 防護）
                        │  Tunnel（由 VM 主動連出去）
                        ▼
VM：cloudflared ──HTTP──▶ nginx（只聽 127.0.0.1:80）──▶ PHP-FPM
```

- VM 上的 `cloudflared` 主動連線到 Cloudflare，所以 **80／443 完全不對外開放**。ufw 只開 22，GCP 防火牆也拿掉 HTTP／HTTPS 規則。
- HTTPS 由 Cloudflare 處理，VM 上不用安裝或更新憑證。
- `cloudflared` 常駐大約佔 30～50MB 記憶體，有 2GB swap 可以承受。
- 部署用的 SSH（22 port）直連 VM 的外部 IP，和 Tunnel 無關。網站不依賴 VM 的 IP，所以**不需要靜態 IP**（見 §8）。
- 另一個做法是 DNS Proxy（橘色雲朵）：VM 要裝 Origin 憑證、開 443，並維護 Cloudflare 的 IP 範圍清單。這個做法不採用。

### 3.3 讓 Laravel 知道「使用者是用 HTTPS 連進來的」

只有 Tunnel 連得到 nginx（只聽 localhost），而 Cloudflare 對外一律是 HTTPS。所以直接在 nginx 這一層處理，**不改 Laravel 程式**：

| 問題 | 做法 |
|---|---|
| Laravel 收到的是 HTTP，會產生 `http://` 網址，Google OAuth 的 redirect URI 也會對不上 | nginx 傳給 PHP-FPM 時固定加上 `HTTPS=on`，Laravel 就會認為是 HTTPS 連線 |
| 所有請求的來源 IP 都是 `127.0.0.1`，登入失敗次數的限制會變成全部使用者一起被鎖 | nginx 的 `real_ip` 模組：只信任來自 `127.0.0.1` 的 `CF-Connecting-IP` 標頭，換成使用者的真實 IP 再傳給 PHP |
| 有人用 `http://` 連進來 | Cloudflare 開啟「Always Use HTTPS」；nginx 也會檢查，看到 `X-Forwarded-Proto: http` 時轉向 `https://` |

> 原本規劃的「`trustProxies` 信任 `127.0.0.1` 再加上 nginx `real_ip`」兩者會互相衝突：`real_ip` 把來源 IP 換成使用者的 IP 後，Laravel 就不會信任那個請求帶的 proxy 標頭。改成上表的做法，兩個問題都在 nginx 解決。

### 3.4 Cloudflare 後台設定

| 項目 | 設定 |
|---|---|
| Tunnel | Zero Trust → Networks → Tunnels 建立 `fitness-app`，Public Hostname 把 `wn52777.com` 指到 `http://127.0.0.1:80`；`www.wn52777.com` 也指過來，由 nginx 轉向不含 www 的網址 |
| SSL/TLS | 加密模式設為 Full；開啟 Always Use HTTPS |
| 快取 | Browser Cache TTL 設為 Respect Existing Headers，完全依照 nginx 給的 `Cache-Control`：`/build/*` 長期快取，`sw.js`、`manifest.webmanifest` 不快取，頁面（HTML）不快取。預設的 4 小時會蓋掉 `sw.js` 的 `no-cache`（第一次部署時實測發現） |
| 其他 | 關閉 Rocket Loader，它會改寫 `<script>` 的載入方式，可能讓 Vue 無法啟動 |

### 3.5 正式環境的 Google 登入

- 在 Google Cloud 建立正式環境專用的 OAuth 用戶端（和本地開發分開），redirect URI 為 `https://wn52777.com/auth/google/callback`。
- 憑證只填在 VM 的 `shared/.env`。
- OAuth 同意畫面維持「測試中」狀態，把自己的 Google 帳號加為測試使用者即可。這個專案只用到 `openid email profile`，之後要開放給其他人使用時，再改成「已發布」。

### 3.6 M4 照片儲存

Cloudflare R2 是候選方案，有 10GB 免費空間，下載不收流量費（總覽規格 §2 原本就列為候選），到 M4 時再決定。

## 4. 部署架構

```
GitHub Actions（手動觸發 deploy.yml，只能選 main）
  1. composer install、npm ci、npm run build      ← 前端在 runner 上建置，VM 記憶體不夠
  2. vendor/bin/dep deploy production             ← 透過 SSH 連到 VM
        │
        ▼
VM：/var/www/fitness-app
  ├── releases/<n>/      每次部署一個新目錄，保留最近 3 個
  ├── shared/.env        正式環境設定（只放在 VM 上，不進 git、不進 GitHub Secrets）
  ├── shared/storage/    上傳檔案、log、session 與快取檔
  └── current → releases/<n>    nginx 的 root 指向 current/public
```

Deployer 每次部署的步驟：

1. `deploy:prepare`：建立新版本目錄，`git clone` 指定的 commit
2. `deploy:vendors`：在 VM 上 `composer install --no-dev --optimize-autoloader`
3. 自訂 task：把 runner 建好的 `public/build/` 上傳到新版本目錄
4. 連結 `shared/.env`、`shared/storage`，執行 `storage:link`
5. `artisan migrate --force`
6. `artisan config:cache`、`route:cache`、`view:cache`、`event:cache`
7. `deploy:symlink`：`current` 連結切到新版本（到這一步才算正式上線）
8. 自訂 task：reload php-fpm（清掉 OPcache）
9. `deploy:cleanup`：刪掉超過 3 個的舊版本

任何一步失敗都不會切換 `current`，網站維持舊版。部署成功但程式有問題時，可以用 `dep rollback production` 切回上一版（資料庫 migration 不會自動退回，見 §9）。

## 5. VM 環境（1GB RAM 的取捨）

| 項目 | 設定 |
|---|---|
| 安裝方式 | 直接安裝套件，**不用 Docker**（省下記憶體）；本地開發仍用 docker-compose |
| 軟體 | nginx、PHP 8.4-FPM（ondrej/php PPA，與 CI 一致）、PostgreSQL 16（PGDG apt 套件庫）、Composer 2、git、unzip。**不裝 Node** |
| swap | 2GB swapfile，避免 `composer install` 時記憶體不足 |
| PHP-FPM | `pm = ondemand`、`pm.max_children = 4`，開啟 OPcache |
| PostgreSQL | 與應用程式同一台 VM，只聽 localhost；`shared_buffers = 128MB`、`max_connections = 20` |
| Queue | 目前沒有排入 queue 的 job，先用 `QUEUE_CONNECTION=sync`，不常駐 worker。M4（Gemini 照片分析）會用到 queue job，屆時再加 systemd 常駐的 `queue:work` |
| 排程 | 目前沒有排程任務，先不設 cron；之後需要時再加 `schedule:run` |

## 6. 安全性

- 建立 `deploy` 帳號，只允許 SSH 金鑰登入，關閉密碼登入與 root 登入。
- `deploy` 帳號的 sudo 只開放 `systemctl reload php8.4-fpm` 這一條指令（寫在 sudoers）。
- 防火牆（GCP 防火牆規則 + ufw）只開 22。網站流量全部經由 Cloudflare Tunnel 進來，80／443 不對外開放。GitHub Actions 的 IP 不固定，22 port 只能對外開放，搭配只用金鑰登入與 fail2ban 防護。
- 開啟 `unattended-upgrades`，自動安裝安全性更新。
- 正式環境 `.env`：`APP_ENV=production`、`APP_DEBUG=false`、新產生的 `APP_KEY`、資料庫使用專用帳號與強密碼。
- 正式環境的帳號用 SSH 連進 VM 後執行 `php artisan user:create` 建立；`db:seed` 只在 local 環境執行，不會在正式環境建立開發帳號。

## 7. GitHub 設定

**`deploy.yml`**
- 觸發：`workflow_dispatch`；job 開頭檢查 `github.ref == 'refs/heads/main'`，不是 `main` 就直接失敗。
- 使用 GitHub Environment `production`，Secrets 放在這個 Environment 底下。
- `concurrency: production`，同一時間只會有一個部署在跑。

**Secrets（Environment `production`）**

| 名稱 | 內容 |
|---|---|
| `SSH_PRIVATE_KEY` | `deploy` 帳號的私鑰（為部署專用的新金鑰） |
| `SSH_KNOWN_HOSTS` | VM 的 host key（`ssh-keyscan` 產生），避免中間人攻擊 |
| `DEPLOY_HOST` | VM 的外部 IP（網域經過 Cloudflare Tunnel，不能用來 SSH）；臨時 IP 換掉時要更新這個值與 `SSH_KNOWN_HOSTS` |

**分支保護**：`main` 設定必須透過 PR 合併，`quality`（lint）與 `ci`（tests）兩項檢查通過才能合併。

## 8. GCP 設定

- 區域選 `us-west1`（Oregon），免費額度只限 `us-west1`、`us-central1`、`us-east1`，這裡離台灣最近。
- 外部 IP 使用臨時 IP 即可（已決，2026-11-20）。網站走 Tunnel，不受 IP 影響；臨時 IP 只在 VM 停止再啟動時才會換，換了只要更新部署用的 `DEPLOY_HOST` 與 `SSH_KNOWN_HOSTS`。外部 IPv4 不在免費額度內，約 US$0.005／小時，臨時與靜態價格相同。之後若想讓 SSH 也不依賴 IP，可以把 SSH 改走 Tunnel（另開 Task）。
- 免費額度每月只有 1GB 對外流量：nginx 對 `/build/` 設長效快取（檔名有 hash），並開啟 gzip；Cloudflare 的邊緣快取會再分擔大部分靜態檔流量（§3.4）。經過 Tunnel 的流量仍然算在 GCP 的對外流量裡。

## 9. 已決（2026-11-20 討論結果）

1. **VM 初始化**：寫成 shell 腳本 `deploy/provision.sh`，連進全新的 Ubuntu VM 後以 sudo 執行一次。腳本要能重複執行（已經做過的步驟會略過），之後重建 VM 也能直接使用。GCP 主控台的操作（建立 VM、防火牆規則）腳本做不到，另外寫在 `deploy/README.md`。
2. **資料庫備份**：要做。
   - 每天 03:00（Asia/Taipei）由 cron 執行 `pg_dump -Fc`，存到 `/var/backups/fitness-app/`，保留最近 7 份。
   - 備份腳本與 cron 由 `provision.sh` 安裝。
   - `deploy/README.md` 寫還原步驟（`pg_restore`）。
   - 異地備份等有正式資料後再決定。
3. **Migration 原則**：接受。migration 一律寫成新舊版程式都能使用，例如新增欄位先設成可以是空值、刪欄位分兩次部署（先讓程式不再使用，下一次部署才刪）。`dep rollback` 時只切換程式碼，不需要動資料庫。此原則補進 `vibe-coding/coding-style.md`。
4. **網域**：已在 Cloudflare Registrar 購買 `wn52777.com`，以 Cloudflare Tunnel 連到 VM，直接用網域上線；正式環境的 Google 登入也在本 milestone 完成（見 §3）。

## 10. 不包含

- 檔案改存 S3／R2（總覽規格 §2 提到，等 M4 照片功能時再決定）
- 監控、告警、log 收集
- staging 環境
- 自動部署（目前決定手動按按鈕）
