# 部署（GCP VM + Cloudflare Tunnel）

正式環境部署在 GCP 免費額度的 e2-micro VM，網址是 **https://wn52777.com**。規格見 [D1 GCP 部署與 CI/CD 規格](../vibe-coding/specs/2026-11-20-d1-gcp-deployment.md)。

| 檔案 | 用途 |
|---|---|
| `provision.sh` | VM 初始化腳本：在全新的 Ubuntu 上執行一次，安裝並設定整個執行環境，包含資料庫每日備份與 Cloudflare Tunnel |
| `../deploy.php` | Deployer 部署設定（見 §8） |
| `../.github/workflows/deploy.yml` | GitHub Actions 部署按鈕（見 §9） |

```
使用者 ──HTTPS──▶ Cloudflare（憑證、快取、DDoS 防護）
                        │  Tunnel（由 VM 主動連出去）
                        ▼
VM：cloudflared ──HTTP──▶ nginx（只聽 127.0.0.1:80）──▶ PHP-FPM ──▶ PostgreSQL
```

- 網站流量只經由 Cloudflare Tunnel 進來，VM 不開放 80／443。
- 部署用的 SSH（22 port）直連 VM 的外部 IP。網站不依賴 VM 的 IP，所以用臨時 IP 就可以（見 §3）。

---

## 1. 在你的電腦上產生部署用金鑰

這把金鑰讓本地的 Deployer 和 GitHub Actions 登入 VM 的 `deploy` 帳號。

- 它是部署專用的新金鑰，不要用平常登入的那把。
- 私鑰之後會放進 GitHub Secrets，所以不設密碼。

```bash
ssh-keygen -t ed25519 -f ~/.ssh/fitness_deploy -C fitness-deploy -N ""
```

會產生兩個檔案：

- `~/.ssh/fitness_deploy`：私鑰，不要外流
- `~/.ssh/fitness_deploy.pub`：公鑰，等一下要給 `provision.sh`

## 2. 建立 VM（GCP 主控台）

到 **Compute Engine → VM 執行個體 → 建立執行個體**。下面幾個欄位會影響是否在免費額度內，其他欄位用預設值即可。

| 欄位 | 設定 | 說明 |
|---|---|---|
| 名稱 | `fitness-app` | |
| 區域／可用區 | `us-west1`（Oregon）／`us-west1-b` | 免費額度只有 `us-west1`、`us-central1`、`us-east1`，Oregon 離台灣最近 |
| 機器設定 | E2 → **e2-micro** | 免費額度只包含 e2-micro |
| 開機磁碟 → 作業系統 | **Ubuntu 24.04 LTS**（x86/64） | 腳本只支援 22.04／24.04。**不要選 26.04**，PHP 8.4 的套件庫還沒支援 |
| 開機磁碟 → 類型 | **標準永久磁碟**（Standard persistent disk） | 預設是「平衡型」，**不在免費額度內**，一定要改 |
| 開機磁碟 → 大小 | 30 GB | 免費額度上限 |
| 防火牆 | 「允許 HTTP 流量」「允許 HTTPS 流量」**都不要勾** | 網站走 Cloudflare Tunnel；SSH（22）由 GCP 預設的 `default-allow-ssh` 規則開放 |
| 資料保護 | 不要開啟快照排程 | 快照會另外收費；資料庫備份由 VM 上的 cron 處理 |
| 觀測能力 | 不要安裝 Ops Agent | 會吃記憶體 |

> **已經建好 VM 並勾了 HTTP／HTTPS？** 到 VM 詳細資料頁 → **編輯** → 網路的防火牆區塊，取消勾選「允許 HTTP 流量」「允許 HTTPS 流量」並儲存。不改也不會被連進來（VM 內的 ufw 只開 22，nginx 也只聽 127.0.0.1），但在 GCP 這層一起關掉比較乾淨。

建立後，順便到 **帳單 → 預算與快訊** 設一個 US$5 的預算快訊。外部 IP 本身每月約 US$3.65（見 §3），超過 US$5 代表有其他不在免費額度內的費用。

## 3. 外部 IP（臨時即可，靜態為選擇性）

網站走 Cloudflare Tunnel，由 VM 主動連出去，**網站不依賴 VM 的 IP**。只有部署用的 SSH 需要知道 IP，所以**用 VM 預設的臨時 IP 就可以，這一步可以跳過**。

到 VM 列表記下「外部 IP」欄位的值，後面寫 `<VM 外部 IP>` 的地方都填它。

**臨時 IP 什麼時候會換？**

- 只有 VM「停止」再「啟動」時會換。
- 重新開機（重設）、GCP 自動維護都不會換。

**如果換了**：

- 網站照常運作，不受影響。
- 本地部署：改用新的 IP 連線即可。第一次連線會提示 host key，這是正常的，因為 IP 換了，VM 的金鑰沒變。
- GitHub Actions：更新 Secrets 的 `DEPLOY_HOST`，並重新產生 `SSH_KNOWN_HOSTS`（步驟在 T6 的說明）。

**想固定 IP 的話**：到 **VPC 網路 → IP 位址**，找到 `fitness-app` 的外部 IP，按右邊的 **⋮ → 升級為靜態 IP 位址**。

> **費用**：VM 上的外部 IPv4 不在免費額度內，約 US$0.005／小時（每月約 US$3.65）。臨時和靜態 IP 只要掛在運作中的 VM 上，價格都一樣。
>
> VM 需要外部 IP 才能連到 Cloudflare、GitHub 和套件庫，拿不掉。如果改成靜態 IP，之後刪除 VM 時記得到同一頁**釋放**它；保留著不用時，費率是 US$0.01／小時，反而比較貴。

## 4. 建立 Cloudflare Tunnel 並設定網域

在 Cloudflare 後台操作。Cloudflare 的介面名稱偶爾會調整，下面以目前的名稱為準。

**1. 建立 Tunnel**

1. 進入 **Zero Trust**（第一次進入會要求設定團隊名稱並選擇方案，選 **Free**）。
2. 到 **Networks → Tunnels → Create a tunnel**，類型選 **Cloudflared**，名稱填 `fitness-app`。
3. 安裝連接器的頁面會顯示一行 `cloudflared service install eyJhIjoi...` 的指令。**只複製 `eyJ` 開頭的那串 token**，等一下執行 `provision.sh` 時會用到。這一頁的安裝步驟不用照著做，腳本會處理。

> token 等於這條 Tunnel 的鑰匙，不要貼到聊天、issue 或 git 裡。

**2. 設定網域對應**（Public Hostname，新版介面可能叫 Published application routes）

| Subdomain | Domain | Service |
|---|---|---|
| （空白） | `wn52777.com` | `HTTP` → `127.0.0.1:80` |
| `www` | `wn52777.com` | `HTTP` → `127.0.0.1:80` |

- Service 請填 `127.0.0.1:80`，不要填 `localhost`。`localhost` 可能會先嘗試 IPv6，但 nginx 只聽 IPv4。
- **不要自己到 DNS 新增 `A 127.0.0.1` 的紀錄。** `127.0.0.1:80` 只填在 Tunnel 的 Service 欄位，意思是「VM 上的 cloudflared 轉給同一台 VM 的 nginx」。DNS 紀錄由 Cloudflare 自動建立，是指向 `<tunnel-id>.cfargotunnel.com` 的 CNAME（橘色雲朵）。如果 DNS 裡寫的是 `127.0.0.1`，瀏覽器會去連你自己的電腦，網站會打不開。
- 存檔後 Cloudflare 會自動建立 DNS 紀錄。如果顯示網域已經有 A 或 CNAME 紀錄而衝突，先到 **DNS → Records** 刪掉舊紀錄再存一次。
- `www` 會由 nginx 轉向 `https://wn52777.com`。

**3. 網域的 SSL 與效能設定**（回到一般的 Cloudflare 後台，選 `wn52777.com`）

| 位置 | 設定 |
|---|---|
| SSL/TLS → Overview | 加密模式選 **Full** |
| SSL/TLS → Edge Certificates | **Always Use HTTPS** 開啟 |
| Speed → Optimization（或 Settings → Content Optimization） | **Rocket Loader** 關閉，它會改寫 `<script>`，可能讓 Vue 無法啟動 |
| Caching → Configuration | **Browser Cache TTL** 選 **Respect Existing Headers** |

最後一項很重要。Cloudflare 預設會快取 `.js` 檔，並把瀏覽器快取時間改成 4 小時，蓋掉 nginx 給 `sw.js` 的 `no-cache`。改成 Respect Existing Headers 後，就完全照 nginx 的設定走：

- `/build/*` 長期快取
- `sw.js`、manifest 和頁面都不快取

## 5. 執行 `provision.sh`

**上傳腳本到 VM**，兩種方式擇一：

- 在主控台的 VM 列表按 **SSH**，開啟瀏覽器版終端機，右上角 **上傳檔案** 選 `deploy/provision.sh`。腳本放在 WSL 裡的話，可以先複製到 Windows 的「下載」資料夾（`<使用者>` 換成你的 Windows 使用者名稱）：

  ```bash
  cp deploy/provision.sh /mnt/c/Users/<使用者>/Downloads/
  ```

- 或是在已安裝 gcloud CLI 的電腦上執行：

  ```bash
  gcloud compute scp deploy/provision.sh fitness-app:~ --zone us-west1-b
  ```

**在 VM 上執行**：把 `DEPLOY_PUBKEY` 換成步驟 1 產生的 `~/.ssh/fitness_deploy.pub` 的內容（以 `ssh-ed25519` 開頭的一整行）：

```bash
sudo DEPLOY_PUBKEY="ssh-ed25519 AAAA...（貼上公鑰）... fitness-deploy" bash provision.sh
```

- 執行到「設定 Cloudflare Tunnel」時會要求貼上步驟 4 的 token。輸入時畫面不會顯示，貼上後按 Enter 即可。
- 如果還沒建立 Tunnel，直接按 Enter 略過，之後再重跑一次腳本補上。

大約 5～10 分鐘會跑完。腳本做的事：

| 項目 | 內容 |
|---|---|
| 系統 | 2GB swap、時區 Asia/Taipei、每日自動安全性更新 |
| 軟體 | nginx、PHP 8.4-FPM（含 OPcache）、PostgreSQL 16、Composer、git、cloudflared |
| 帳號 | `deploy`：只能用金鑰登入，sudo 只允許 `systemctl reload php8.4-fpm`；另外產生從 GitHub 拉程式碼用的 deploy key |
| 安全性 | SSH 關閉密碼登入與 root 登入、ufw 只開 22、fail2ban（SSH 失敗 5 次封鎖 1 小時） |
| PHP-FPM | 以 `deploy` 身分執行，`pm = ondemand`，最多 4 個 worker |
| PostgreSQL | 只聽 localhost，`shared_buffers = 128MB`、`max_connections = 20`；建立資料庫 `fitness_app` 和同名的帳號，密碼隨機產生 |
| 備份 | 每天 03:00 備份資料庫到 `/var/backups/fitness-app/`，保留最近 7 份（見 [§7](#7-資料庫備份與還原)） |
| nginx | 只聽 `127.0.0.1:80`，root 指向 `/var/www/fitness-app/current/public`。<br>傳給 PHP 時標記為 HTTPS，並改用 `CF-Connecting-IP` 作為使用者 IP（見規格 §3.3）。<br>`www` 與 `http` 轉向 `https://wn52777.com`。<br>`/build/` 長效快取；`sw.js`、manifest 不快取；開啟 gzip。<br>擋掉隱藏檔，以及 `index.php` 以外的 `.php` |
| Tunnel | 安裝 `cloudflared`，用 token 註冊成系統服務 |
| 部署目錄 | `/var/www/fitness-app/shared/.env`：正式環境設定，`APP_URL=https://wn52777.com`，`APP_KEY` 與資料庫密碼自動產生 |

腳本可以重複執行。已安裝的套件、已存在的資料庫帳號、已註冊的 Tunnel，以及 `.env` 都不會被覆寫；nginx、PHP、PostgreSQL 的設定檔會更新成腳本裡的最新內容。

## 6. 跑完之後

**1. 加入 GitHub Deploy key**

腳本最後會印出一把公鑰（`deploy@fitness-app-vm`），VM 部署時要用它從 GitHub 拉程式碼。到 GitHub repo 的 **Settings → Deploy keys → Add deploy key** 貼上，**不要**勾選 Allow write access。

之後要再看這把公鑰，可以在 VM 上執行：

```bash
sudo cat /home/deploy/.ssh/id_ed25519.pub
```

**2. 從你的電腦測試登入**

```bash
ssh -i ~/.ssh/fitness_deploy deploy@<VM 外部 IP>
```

登入後確認 `deploy` 帳號可以連到 GitHub，看到 `Hi mattkids0527/fitness-app! You've successfully authenticated` 就代表成功：

```bash
ssh -T git@github.com
```

> 這個測試要以 `deploy` 帳號執行，Deploy key 是放在 `deploy` 帳號底下的。如果是在瀏覽器版 SSH（你自己的帳號）測試，請改用 `sudo -u deploy ssh -T git@github.com`。用自己的帳號直接執行會出現 `Permission denied (publickey)`，這是正常的。

**3. 確認網站連得到**

- 用瀏覽器開 `https://wn52777.com`。看到 **404 Not Found** 是正常的，因為程式還沒部署，但已經代表 Cloudflare → Tunnel → nginx 整條路是通的。
- Cloudflare Zero Trust 的 Tunnels 頁面，`fitness-app` 應該顯示 **HEALTHY**。
- 直接開 `http://<VM 外部 IP>` 應該**連不上**（逾時），代表 VM 沒有對外開放網站。

**4. 檢查服務與備份**

在瀏覽器版 SSH（你自己的帳號，有完整 sudo 權限）執行：

```bash
swapon --show && sudo ufw status && systemctl is-active nginx php8.4-fpm postgresql fail2ban cron cloudflared
```

```bash
sudo -u postgres fitness-app-backup
```

**5. 檢查正式環境設定**

```bash
sudo -u deploy nano /var/www/fitness-app/shared/.env
```

`APP_URL` 應該是 `https://wn52777.com`。Google 登入的憑證（`GOOGLE_CLIENT_ID`、`GOOGLE_CLIENT_SECRET`）在第一次部署時再填。

## 7. 資料庫備份與還原

`provision.sh` 會安裝備份腳本 `/usr/local/bin/fitness-app-backup`，每天 03:00（台灣時間）由 cron 以 `postgres` 身分執行。

- 備份格式是 `pg_dump` 的自訂格式（`.dump`），檔名例如 `fitness_app-20261121-030000.dump`。
- 只保留最近 7 份，舊的自動刪除。
- 備份目錄的群組是 `deploy`，可以用部署金鑰把備份下載回自己的電腦。

### 查看備份

```bash
sudo ls -lh /var/backups/fitness-app/
```

> 備份目錄只開放給 `postgres` 和 `deploy` 讀取，所以用自己的帳號查看時要加 `sudo`。

查看每天備份的執行結果：

```bash
journalctl -t fitness-app-backup --since "7 days ago"
```

### 手動備份

在風險較高的部署之前，例如有刪欄位的 migration，建議先手動備份一次：

```bash
sudo -u postgres fitness-app-backup
```

### 下載備份到自己的電腦

目前沒有異地備份，重要的時候可以手動下載一份。在你的電腦上執行：

```bash
scp -i ~/.ssh/fitness_deploy "deploy@<VM 外部 IP>:/var/backups/fitness-app/*.dump" ./
```

### 先還原到暫時的資料庫驗證

確認備份檔可以正常還原，不會影響正式資料：

```bash
sudo -u postgres createdb --owner=fitness_app fitness_app_restore_test
sudo -u postgres pg_restore --no-owner --role=fitness_app --dbname=fitness_app_restore_test /var/backups/fitness-app/<檔名>.dump
sudo -u postgres psql -d fitness_app_restore_test -c '\dt'
sudo -u postgres dropdb fitness_app_restore_test
```

只想確認最新一份備份能不能還原的話，可以用這一行。整段都以 `postgres` 身分執行，檔名才讀得到：

```bash
sudo -u postgres bash -c 'f=$(ls -1t /var/backups/fitness-app/*.dump | head -1); dropdb --if-exists fitness_app_restore_test; createdb --owner=fitness_app fitness_app_restore_test && pg_restore --no-owner --role=fitness_app --dbname=fitness_app_restore_test "$f" && echo "restore OK: $f"; dropdb fitness_app_restore_test'
```

### 還原正式資料庫

這會用備份**覆蓋**目前的資料，備份時間點之後新增的資料都會消失。

1. 先把目前的狀態備份一份，萬一還原錯了還可以回來：

   ```bash
   sudo -u postgres fitness-app-backup
   ```

2. 讓網站進入維護模式，避免還原過程中有人寫入資料：

   ```bash
   sudo -u deploy php /var/www/fitness-app/current/artisan down
   ```

3. 還原。`--clean --if-exists` 會先刪掉現有的資料表再重建；`--single-transaction` 讓中途失敗時整個取消，不會只還原一半：

   ```bash
   sudo -u postgres pg_restore --clean --if-exists --single-transaction --no-owner --role=fitness_app --dbname=fitness_app /var/backups/fitness-app/<檔名>.dump
   ```

4. 清除快取並結束維護模式：

   ```bash
   sudo -u deploy php /var/www/fitness-app/current/artisan optimize:clear
   ```

   ```bash
   sudo -u deploy php /var/www/fitness-app/current/artisan optimize
   ```

   ```bash
   sudo -u deploy php /var/www/fitness-app/current/artisan up
   ```

> 備份檔裡的資料表結構是當時的版本。`--clean` 只會刪除備份檔裡有的資料表，備份之後才新增的資料表會原樣留著。如果備份之後部署過新的 migration，還原後要再執行一次 `php artisan migrate --force`，或是用 `dep rollback` 切回和備份同一個時期的程式。

## 8. 部署（Deployer）

部署設定在根目錄的 `deploy.php`，使用 Deployer 內建的 Laravel 流程：

- 前端在**執行部署的機器**上建置，然後上傳到 VM。
- VM 只負責 `composer install`、migrate 和快取。
- 預設部署 `main` 分支。

**正式部署請用 GitHub Actions 的按鈕（見 [§9](#9-github-actions-部署按鈕)）**。從本地部署主要用在除錯，或是 GitHub Actions 無法使用的時候。

`deploy.php` 讀取的環境變數：

| 變數 | 必填 | 說明 |
|---|---|---|
| `DEPLOY_HOST` | 是 | 部署目標，格式 `[帳號@]IP[:路徑]`，例如 `deploy@203.0.113.10:/var/www/fitness-app`。帳號預設 `deploy`，路徑預設 `/var/www/fitness-app`，所以只填 IP 也可以 |
| `DEPLOY_SSH_KEY_FILE` | 否 | 私鑰檔案路徑，預設 `~/.ssh/fitness_deploy` |
| `DEPLOY_KNOWN_HOSTS_FILE` | 否 | 只信任這個 known_hosts 檔裡的 VM host key（GitHub Actions 使用） |
| `DEPLOY_ENV_FILE` | 否 | 要上傳成正式環境 `.env` 的檔案。不提供時沿用 VM 上現有的 `.env` |
| `DEPLOY_VERSION` | 否 | 這次部署的版號（`vX.Y.Z`），寫進新版本目錄的 `version.json`，設定頁「版本」會顯示。不提供時不寫，設定頁顯示「未標示版本」。本地部署不會打 tag |

### 從本地（WSL）部署

**第一次部署前，先用部署金鑰連線一次**，把 VM 的 host key 記到 `~/.ssh/known_hosts`。Deployer 不能回答「是否信任這台主機」的提示：

```bash
ssh -i ~/.ssh/fitness_deploy deploy@<VM 外部 IP> exit
```

**部署**：先在要部署的版本上建置前端，再執行 Deployer：

```bash
npm run build
```

```bash
DEPLOY_HOST=<VM 外部 IP> vendor/bin/dep deploy
```

部署流程依序是：

1. 確認 `public/build/manifest.json` 存在。
2. 在 VM 建立新的版本目錄，從 GitHub 拉取 `main`。
3. 有提供 `DEPLOY_ENV_FILE` 時：檢查內容（`APP_ENV` 必須是 `production`、`APP_KEY` 不能是空的），把舊版備份成 `shared/.env.previous`，再上傳新的 `.env`。
   然後連結 `shared/.env`、`shared/storage`。
4. 執行 `composer install --no-dev`。
5. 上傳 `public/build/`。
6. 建立 `storage:link`；執行 `config`、`route`、`view`、`event` 的快取，以及 `migrate --force`。
7. 把 `current` 切換到新版本，reload php-fpm。
8. 刪除超過 3 個的舊版本。

任何一步失敗都不會切換 `current`，網站維持舊版本。

> 前端是用你本地的程式碼建置的。如果本地所在的分支和 `main` 的前端程式不同，網站會出現不一致的畫面。正式部署請在 GitHub Actions 上執行（T6），那裡會先 checkout `main` 再建置。

**退回上一版**

```bash
DEPLOY_HOST=<VM 外部 IP> vendor/bin/dep rollback
```

`rollback` 只會切換程式碼，不會退回資料庫（見 `vibe-coding/coding-style.md` §12）。

**其他常用指令**

```bash
DEPLOY_HOST=<VM 外部 IP> vendor/bin/dep releases
```

```bash
DEPLOY_HOST=<VM 外部 IP> vendor/bin/dep ssh
```

- `releases`：列出 VM 上的版本與部署時間。
- `ssh`：以 `deploy` 帳號連進 VM，並切換到 `current` 目錄。

**部署被鎖住**：上一次部署中途被中斷時會出現 `Deploy locked`，確認沒有其他部署正在執行後解除：

```bash
DEPLOY_HOST=<VM 外部 IP> vendor/bin/dep deploy:unlock
```

### 修改正式環境的 `.env`

設定 GitHub Actions 部署（§9）之後，**正式環境 `.env` 的來源是 GitHub Secret `PRODUCTION_ENV`**。

- 要改設定：到 GitHub 修改 `PRODUCTION_ENV`，再按一次部署按鈕。新的 `.env` 會在建立快取之前上傳，所以會隨這次部署生效。
- 不要直接改 VM 上的 `shared/.env`，下次部署時會被覆蓋。
- 改錯了想退回：VM 上的 `shared/.env.previous` 是上一版。

**緊急情況下直接改 VM**：例如 GitHub 無法使用時。改完一定要重建快取並 reload php-fpm，網站才會讀到新設定。因為部署時已經執行 `config:cache`，PHP 也會用 OPcache 快取程式。事後記得把同樣的修改也更新到 `PRODUCTION_ENV`，不然下次部署會被蓋回去。

```bash
sudo -u deploy nano /var/www/fitness-app/shared/.env
```

```bash
cd /var/www/fitness-app/current && sudo -u deploy php artisan config:cache && sudo systemctl reload php8.4-fpm
```

**正式環境的 Google 登入**：在 Google Cloud 建立正式環境專用的 OAuth 用戶端（網頁應用程式），已授權的重新導向 URI 填 `https://wn52777.com/auth/google/callback`。把用戶端 ID 和密鑰填入 `PRODUCTION_ENV` 的 `GOOGLE_CLIENT_ID`、`GOOGLE_CLIENT_SECRET` 後重新部署，登入頁就會出現 Google 按鈕。詳細步驟見 [social-login-google 模組說明](../packages/fitness/social-login-google/README.md)。

### 建立正式環境帳號

第一版不開放註冊，帳號用指令建立。第一次部署完成後執行：

```bash
ssh -t -i ~/.ssh/fitness_deploy deploy@<VM 外部 IP> "cd /var/www/fitness-app/current && php artisan user:create --generate-password"
```

指令會互動詢問姓名和 email，最後印出產生的密碼，請自行妥善保存。

**重設密碼**（在你的電腦上執行）：

```bash
ssh -t -i ~/.ssh/fitness_deploy deploy@<VM 外部 IP> "cd /var/www/fitness-app/current && php artisan user:password <email>"
```

不加 `--generate-password` 時會請你輸入兩次新密碼；加上的話會隨機產生密碼並只顯示一次。

> **在 VM 上執行 `php artisan` 一律用 `deploy` 帳號。** 如果是從瀏覽器版 SSH（你自己的帳號）操作，請先 `cd /var/www/fitness-app/current`，再用 `sudo -u deploy php artisan ...` 執行，例如：
>
> ```bash
> sudo -u deploy php artisan user:password <email>
> ```
>
> 用自己的帳號或 root 直接執行，可能會在 `storage/` 留下不屬於 `deploy` 的 log 或快取檔，網站寫不進去就會出錯。用 `sudo find /var/www/fitness-app/shared/storage ! -user deploy` 可以檢查，沒有輸出才是正常的。

## 9. GitHub Actions 部署按鈕

`.github/workflows/deploy.yml` 只能手動觸發，而且只能部署 `main`，每次都要填版號。流程如下：

1. 在 GitHub 的機器上 checkout `main`（含所有 tag），用 `deploy/check-version.sh` 檢查版號，不符就停止，不會部署。
2. 安裝相依套件，`npm run build`。
3. 從 Environment `production` 讀取部署目標、SSH 金鑰、VM host key、`.env`。
4. 執行 `vendor/bin/dep deploy --revision=<觸發當下的 commit>`，和 §8 的流程相同，會上傳 `.env`，並寫入 `version.json`。
5. 部署成功後，在該 commit 打 tag（例如 `v1.3.0`）並建立 GitHub Release，說明由 GitHub 依合併的 PR 自動產生。部署失敗不打 tag，同一個版號修好後可以再用。

### 版號規則

格式 `vMAJOR.MINOR.PATCH`，第一個版本是 `v1.3.0`（M1～M3、U1、D2）。

| 這次上線的內容 | 怎麼升 | 例子 |
|---|---|---|
| 新功能（M 系列 milestone） | 升中間碼，最後一碼歸零 | `v1.3.2` → `v1.4.0` |
| 修 bug、介面調整（U 系列）、部署流程（D 系列） | 升最後一碼 | `v1.4.0` → `v1.4.1` |

版號會被擋下的情況：格式不是 `v數字.數字.數字`、tag 已經存在、比目前最大的 `v*` tag 小。要先確認可以在本地執行 `git fetch --tags && deploy/check-version.sh v1.4.0`。

### 第一次設定

**1. 建立 Environment**

到 GitHub repo → **Settings → Environments → New environment**，名稱填 `production`。

建立後，在 **Deployment branches and tags** 選 **Selected branches and tags**，加入 `main`。這樣只有 `main` 能讀取下面的 Secrets。

**2. 設定四個值**

| 類型 | 名稱 | 值 | 怎麼取得 |
|---|---|---|---|
| Variable | `DEPLOY_HOST` | `deploy@<VM 外部 IP>:/var/www/fitness-app` | GCP 主控台 VM 列表的「外部 IP」 |
| Secret | `DEPLOY_SSH_KEY` | 部署用私鑰的完整內容，包含 `-----BEGIN` 和 `-----END` 兩行 | 在 WSL 執行 `cat ~/.ssh/fitness_deploy` |
| Secret | `DEPLOY_HOST_KEY` | VM 的 SSH host 公鑰，例如 `ssh-ed25519 AAAA... root@...` | 在 VM 的瀏覽器版 SSH 執行 `cat /etc/ssh/ssh_host_ed25519_key.pub` |
| Secret | `PRODUCTION_ENV` | 整份正式環境 `.env` | 在 VM 的瀏覽器版 SSH 執行 `sudo -u deploy cat /var/www/fitness-app/shared/.env` |

- **`DEPLOY_HOST` 放在 Variables 分頁**，方便隨時查看目前的值。如果放在 Secrets，workflow 也讀得到。
- **`DEPLOY_HOST_KEY`** 只存公鑰，不綁 IP，workflow 會用 `DEPLOY_HOST` 的 IP 組出 `known_hosts`。所以 VM 換 IP 時不用改，只有重建 VM 時才要更新。
- **`PRODUCTION_ENV`** 一定要從 VM 上的 `.env` **整份複製**，不要重新產生：
  - `APP_KEY` 換掉會讓所有登入狀態失效。
  - `DB_PASSWORD` 要和 `provision.sh` 建立的資料庫帳號一致。
  - 要開啟 Google 登入的話，可以在這時候一起填上 `GOOGLE_CLIENT_ID`、`GOOGLE_CLIENT_SECRET`。
- **GitHub Secret 存進去後就看不到內容了**，只能整份覆蓋。請在密碼管理器之類的地方，保留一份完整的 `.env`。

**3. 保護 `main` 分支**

到 **Settings → Branches → Add branch ruleset**（或 Add classic branch protection rule），對象設 `main`：

- 勾選 **Require a pull request before merging**。
  - 底下的 **Require approvals**（Ruleset 是 **Required approvals**）要**取消勾選或設為 0**。GitHub 預設要求 1 位核准，但 PR 作者不能核准自己的 PR，一個人開發時會出現「Review required」而永遠無法合併。
- 勾選 **Require status checks to pass**，加入 `quality`（lint）和 `ci`（tests）。這兩個名稱要等 workflow 在 GitHub 上跑過一次後，才搜尋得到。

### 部署

1. 開 PR 把 `develop` 合併進 `main`。
2. 到 **Actions → deploy → Run workflow**，分支選 `main`，在 **版號** 填這次的版號（例如 `v1.4.0`），按 **Run workflow**。
3. 完成後，repo 的 **Releases** 會出現該版號；正式站「設定 → 版本」顯示同一個版號。

> `deploy.yml` 必須已經在 `main` 上，Actions 頁面才會出現 **Run workflow** 按鈕。所以第一次要先把 `develop` 合進 `main`。

### 更新 `PRODUCTION_ENV`

GitHub Secret 存進去後**看不到內容**，只能整份覆蓋。但 VM 上的 `shared/.env` 就是最近一次部署上傳的那份，內容和 `PRODUCTION_ENV` 相同，所以更新時從 VM 讀出來改：

1. 讀出目前的內容，下面兩種方式擇一：

   在 GCP 瀏覽器版 SSH 執行：

   ```bash
   sudo -u deploy cat /var/www/fitness-app/shared/.env
   ```

   或是在你的電腦執行：

   ```bash
   ssh -i ~/.ssh/fitness_deploy deploy@<VM 外部 IP> cat /var/www/fitness-app/shared/.env
   ```

2. 整份複製到編輯器，只修改需要改的值。`APP_KEY`、`DB_PASSWORD` 不要動。
3. GitHub → Settings → Environments → `production` → 編輯 `PRODUCTION_ENV` → 貼上**整份**修改後的內容 → Update secret。
4. Actions → deploy → Run workflow（分支 `main`）。
5. 如果有存成暫存檔，用完就刪掉。不要放在雲端同步的資料夾或專案目錄裡。

改錯了想恢復：VM 上的 `shared/.env.previous` 是上一版。用 `sudo -u deploy cat /var/www/fitness-app/shared/.env.previous` 讀出來，貼回 `PRODUCTION_ENV`，再部署一次即可。

### 常見的變更

| 想做的事 | 做法 |
|---|---|
| VM 外部 IP 換了 | 改 Variable `DEPLOY_HOST`，再按一次部署（版號升最後一碼） |
| 修改 `.env`（例如加 Google 憑證） | 從 VM 讀出目前的 `.env`，修改後整份更新 Secret `PRODUCTION_ENV`，再按一次部署（見上一節） |
| 重建了 VM | 更新 `DEPLOY_HOST_KEY`、`DEPLOY_HOST`；`PRODUCTION_ENV` 的 `DB_PASSWORD` 要換成新 VM 產生的密碼 |
| 換部署金鑰 | 新的公鑰加進 VM 的 `/home/deploy/.ssh/authorized_keys`，再更新 `DEPLOY_SSH_KEY` |

## 疑難排解

- **部署成功，但「Tag and Release」步驟失敗**：正式站已經是新版本，只差 tag 或 Release。若 tag 已經推上去，到 GitHub **Releases → Draft a new release** 選該 tag，按 **Generate release notes** 後發布；若 tag 也沒推上去，在本地對該次部署的 commit（Actions 記錄的 `GITHUB_SHA`）執行 `git tag -a v1.4.0 -m "Release v1.4.0" <commit> && git push origin v1.4.0`，再建 Release。不要用同一個版號重跑部署，會因為 tag 已存在被擋下。如果 repo 有 tag 保護規則（Rulesets 對 `v*`），要允許 GitHub Actions 建立 tag。
- **`dep rollback` 之後的版號**：`version.json` 在每個版本目錄內，退版後設定頁會顯示舊版本的版號；GitHub 上的 tag 與 Release 不會變。

- **用 `deploy` 帳號登不進去**：主控台的瀏覽器版 SSH 是用 Google 自動產生的金鑰登入你自己的帳號，不受腳本「關閉密碼登入」的影響，可以先從那邊登入檢查 `/home/deploy/.ssh/authorized_keys`。腳本對 SSH 的設定都在 `/etc/ssh/sshd_config.d/99-fitness-app.conf`。
- **自己的 IP 被 fail2ban 封鎖**：用主控台的瀏覽器版 SSH 登入，執行 `sudo fail2ban-client set sshd unbanip <你的 IP>`。
- **查看服務狀態**：`systemctl status nginx php8.4-fpm postgresql cloudflared`。nginx 的錯誤記錄在 `/var/log/nginx/error.log`。
- **開網站出現 Cloudflare 的 502／1033 錯誤**：Tunnel 連不到 nginx。
  - 先看 `systemctl status cloudflared`、`journalctl -u cloudflared -n 50`。
  - 確認 Public Hostname 的 Service 是 `HTTP` → `127.0.0.1:80`。
  - 在 VM 上用 `curl -H "Host: wn52777.com" http://127.0.0.1/` 測試 nginx 本身有沒有回應。
- **網站打不開，DNS 查到 `127.0.0.1`**：DNS 被手動設成 A 紀錄了。刪掉那筆紀錄，改在 Tunnel 的 Public Hostname 新增網域，讓 Cloudflare 自動建立 CNAME（見 §4）。
- **GitHub Actions 部署出現 `Host key verification failed`**：`DEPLOY_HOST_KEY` 和 VM 實際的 host key 不一致。可能是重建了 VM，或是 `DEPLOY_HOST` 填成別台機器的 IP。到 VM 上重新執行 `cat /etc/ssh/ssh_host_ed25519_key.pub` 確認。
- **GitHub Actions 部署出現 `.env 的 APP_ENV 必須是 production` 或 `缺少 APP_KEY`**：`PRODUCTION_ENV` 貼錯或是空的，VM 上的 `.env` 沒有被動到。
- **要換 Tunnel token**：先執行 `sudo cloudflared service uninstall`，再重跑 `provision.sh` 貼上新的 token。
