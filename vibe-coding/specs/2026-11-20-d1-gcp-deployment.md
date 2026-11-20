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
| 網域 | 目前沒有網域，部署分兩階段進行（見 §3） |

## 3. 兩階段上線

沒有網域也能先部署。少的只有 HTTPS 才能用的功能，買好網域後再補上。

| 階段 | 網址 | 可用功能 | 不能用的功能 |
|---|---|---|---|
| A：先用 IP 上線 | `http://<靜態 IP>` | 帳密登入、首次設定、體重、今日頁，以及之後的業務功能 | Google 登入（Google 不接受 IP 當 redirect URI，沒設憑證時按鈕會自動隱藏）、PWA 安裝／離線（service worker 需要 HTTPS，`app.ts` 已檢查支援才註冊，不會報錯） |
| B：網域 + HTTPS | `https://<網域>` | 全部功能 | — |

### 3.1 階段 B：VM 前面放 Cloudflare（已決，2026-11-20）

**架構維持 GCP VM + Laravel，不改用 Cloudflare Workers/Pages。** 原因：
- Workers 只能執行 JavaScript／WebAssembly，不能跑 PHP。
- Pages 只能放靜態網站，但 Inertia 的每個頁面都需要 Laravel 在伺服器端處理。
- 改用 Workers 等於後端全部改寫成 TypeScript，資料庫也要換，M1、M2 都要重做。

Cloudflare 改成放在 VM 前面，使用免費方案：

| 用途 | 做法 |
|---|---|
| 網域 | 在 Cloudflare Registrar 用成本價購買，DNS 也由 Cloudflare 管理 |
| HTTPS | 由 Cloudflare 處理憑證，詳細設定見下方「連線方式」 |
| 節省 GCP 流量 | `/build/` 由 Cloudflare 邊緣節點快取（檔名有 hash，可以長期快取），大部分靜態檔的流量不經過 GCP 的 1GB 免費額度 |
| 防護 | 隱藏 VM 的 IP，並有 DDoS 防護 |

**連線方式**：兩種做法，到時候再選一種。

| 做法 | 內容 | 優點 | 缺點 |
|---|---|---|---|
| (a) DNS Proxy | DNS 開啟橘色雲朵代理；VM 裝 Cloudflare Origin 憑證，nginx 改聽 443，加密模式設為 Full (strict)；GCP 防火牆的 80／443 只允許 Cloudflare 的 IP 範圍 | 設定單純，VM 不用多跑程式 | 要維護 Cloudflare 的 IP 範圍清單 |
| (b) Cloudflare Tunnel | VM 執行 `cloudflared` 主動連線到 Cloudflare，nginx 只聽 localhost；80／443 完全不對外開放 | 對外開的 port 最少，不需要另外處理憑證 | `cloudflared` 常駐約佔 30～50MB 記憶體 |

不論選哪一種，部署用的 SSH（22 port）都照舊直連 VM 的靜態 IP。

**階段 B 要做的事**
1. 買網域，DNS 指向 VM（或建立 Tunnel）。
2. 依選擇的連線方式設定 nginx。
3. **讓 Laravel 知道前面有 proxy**：
   - `bootstrap/app.php` 設定 `trustProxies`。做法 (a) 信任 Cloudflare 的 IP 範圍，做法 (b) 信任 `127.0.0.1`。少了這步，Laravel 會以為連線是 HTTP，產生的網址和 Google OAuth 的 redirect URI 會不一致。
   - nginx 用 `real_ip_header CF-Connecting-IP` 取得使用者的真實 IP。少了這步，所有請求看起來都來自 Cloudflare，登入失敗次數的限制會變成全部使用者一起被鎖。
4. 設定 Cloudflare 快取規則：`/build/*` 長期快取；`sw.js`、`manifest.webmanifest` 和所有頁面都不快取。
5. 改 `.env`：`APP_URL=https://<網域>`、`SESSION_SECURE_COOKIE=true`。
6. 在 Google Cloud 建立正式環境的 OAuth 憑證，填入 `.env`。

**M4 照片儲存**：Cloudflare R2 是候選方案，有 10GB 免費空間，下載不收流量費（總覽規格 §2 原本就列為候選），到 M4 時再決定。

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
- 防火牆（GCP 防火牆規則 + ufw）只開 22、80、443。GitHub Actions 的 IP 不固定，22 port 只能對外開放，搭配只用金鑰登入與 fail2ban 防護。
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
| `DEPLOY_HOST` | VM 的靜態 IP（階段 B 可改成網域） |

**分支保護**：`main` 設定必須透過 PR 合併，`quality`（lint）與 `ci`（tests）兩項檢查通過才能合併。

## 8. GCP 設定

- 區域選 `us-west1`（Oregon），免費額度只限 `us-west1`、`us-central1`、`us-east1`，這裡離台灣最近。
- 外部 IP 改成靜態 IP，避免重開機後換 IP。GCP 對外部 IPv4 的計費規則改過幾次，設好後到帳單頁確認是否產生費用。
- 免費額度每月只有 1GB 對外流量：nginx 對 `/build/` 設長效快取（檔名有 hash），並開啟 gzip；到階段 B 時再由 Cloudflare 的邊緣快取分擔靜態檔流量（§3.1）。

## 9. 已決（2026-11-20 討論結果）

1. **VM 初始化**：寫成 shell 腳本 `deploy/provision.sh`，連進全新的 Ubuntu VM 後以 sudo 執行一次。腳本要能重複執行（已經做過的步驟會略過），之後重建 VM 也能直接使用。GCP 主控台的操作（建立 VM、靜態 IP、防火牆規則）腳本做不到，另外寫在 `deploy/README.md`。
2. **資料庫備份**：要做。
   - 每天 03:00（Asia/Taipei）由 cron 執行 `pg_dump -Fc`，存到 `/var/backups/fitness-app/`，保留最近 7 份。
   - 備份腳本與 cron 由 `provision.sh` 安裝。
   - `deploy/README.md` 寫還原步驟（`pg_restore`）。
   - 異地備份等有正式資料後再決定。
3. **Migration 原則**：接受。migration 一律寫成新舊版程式都能使用，例如新增欄位先設成可以是空值、刪欄位分兩次部署（先讓程式不再使用，下一次部署才刪）。`dep rollback` 時只切換程式碼，不需要動資料庫。此原則補進 `vibe-coding/coding-style.md`。
4. **網域**：之後再買，在 Cloudflare Registrar 購買並由 Cloudflare 管理 DNS（見 §3.1）。本 milestone 只做階段 A（用 IP、HTTP 上線），階段 B 等買好網域後另開 Task。

## 10. 不包含

- 階段 B 的實際設定（等有網域後另開 Task 或 milestone，本規格先把步驟寫好）
- 檔案改存 S3／R2（總覽規格 §2 提到，等 M4 照片功能時再決定）
- 監控、告警、log 收集
- staging 環境
- 自動部署（目前決定手動按按鈕）
