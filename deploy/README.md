# 部署（GCP VM）

正式環境部署在 GCP 免費額度的 e2-micro VM。規格見 [D1 GCP 部署與 CI/CD 規格](../vibe-coding/specs/2026-11-20-d1-gcp-deployment.md)。

| 檔案 | 用途 |
|---|---|
| `provision.sh` | VM 初始化腳本：在全新的 Ubuntu 上執行一次，安裝並設定整個執行環境，包含資料庫每日備份 |

目前是**階段 A**：用 `http://<靜態 IP>` 上線。Google 登入與 PWA 安裝要等有網域、改成 HTTPS 後才能使用。

---

## 1. 在你的電腦上產生部署用金鑰

這把金鑰讓本地的 Deployer 和 GitHub Actions 登入 VM 的 `deploy` 帳號。它是部署專用的新金鑰，不要用平常登入的那把。私鑰之後會放進 GitHub Secrets，所以不設密碼。

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
| 防火牆 | 勾選「允許 HTTP 流量」「允許 HTTPS 流量」 | SSH（22）由 GCP 預設的 `default-allow-ssh` 規則開放 |
| 資料保護 | 不要開啟快照排程 | 快照會另外收費；資料庫備份由 VM 上的 cron 處理 |
| 觀測能力 | 不要安裝 Ops Agent | 會吃記憶體 |

建立後，順便到 **帳單 → 預算與快訊** 設一個 US$1 的預算快訊。萬一有設定不在免費額度內，會馬上收到通知。

## 3. 外部 IP 改成靜態 IP

VM 預設的外部 IP 是臨時的，停機再開機後可能會變。改成靜態 IP 後，Deployer、GitHub Secrets 和之後的網域都指向同一個位址。

到 **VPC 網路 → IP 位址**，找到 `fitness-app` 的外部 IP，按右邊的 **⋮ → 升級為靜態 IP 位址**，名稱填 `fitness-app-ip`。

> GCP 對外部 IPv4 的計費規則調整過幾次，設好後隔天到帳單頁確認一下有沒有產生費用。

## 4. 執行 `provision.sh`

**上傳腳本到 VM**，兩種方式擇一：

- 在主控台的 VM 列表按 **SSH**，開啟瀏覽器版終端機，右上角 **上傳檔案** 選 `deploy/provision.sh`。
- 或是在已安裝 gcloud CLI 的電腦上執行：

  ```bash
  gcloud compute scp deploy/provision.sh fitness-app:~ --zone us-west1-b
  ```

**在 VM 上執行**：把 `DEPLOY_PUBKEY` 換成步驟 1 產生的 `~/.ssh/fitness_deploy.pub` 的內容（以 `ssh-ed25519` 開頭的一整行）：

```bash
sudo DEPLOY_PUBKEY="ssh-ed25519 AAAA...（貼上公鑰）... fitness-deploy" bash provision.sh
```

大約 5～10 分鐘會跑完。腳本做的事：

| 項目 | 內容 |
|---|---|
| 系統 | 2GB swap、時區 Asia/Taipei、每日自動安全性更新 |
| 軟體 | nginx、PHP 8.4-FPM（含 OPcache）、PostgreSQL 16、Composer、git |
| 帳號 | `deploy`：只能用金鑰登入，sudo 只允許 `systemctl reload php8.4-fpm`；另外產生從 GitHub 拉程式碼用的 deploy key |
| 安全性 | SSH 關閉密碼登入與 root 登入、ufw 只開 22／80／443、fail2ban（SSH 失敗 5 次封鎖 1 小時） |
| PHP-FPM | 以 `deploy` 身分執行，`pm = ondemand`，最多 4 個 worker |
| PostgreSQL | 只聽 localhost，`shared_buffers = 128MB`、`max_connections = 20`；建立資料庫 `fitness_app` 和同名的帳號，密碼隨機產生 |
| 備份 | 每天 03:00 備份資料庫到 `/var/backups/fitness-app/`，保留最近 7 份（見 [§6](#6-資料庫備份與還原)） |
| nginx | root 指向 `/var/www/fitness-app/current/public`；`/build/` 長效快取；`sw.js`、manifest 不快取；開啟 gzip；擋掉隱藏檔與 `index.php` 以外的 `.php` |
| 部署目錄 | `/var/www/fitness-app/shared/.env`：正式環境設定，`APP_KEY` 與資料庫密碼自動產生，`APP_URL` 自動帶入 VM 的外部 IP |

腳本可以重複執行。已安裝的套件、已存在的資料庫帳號，以及 `.env` 都不會被覆寫；nginx、PHP、PostgreSQL 的設定檔會更新成腳本裡的最新內容。

## 5. 跑完之後

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

登入後確認 VM 可以連到 GitHub，看到 `Hi mattkids0527/fitness-app! You've successfully authenticated` 就代表成功：

```bash
ssh -T git@github.com
```

**3. 檢查正式環境設定**

```bash
nano /var/www/fitness-app/shared/.env
```

確認 `APP_URL` 是 `http://<靜態 IP>`。如果是在步驟 3 改成靜態 IP **之前**就執行了腳本，這裡可能是舊的 IP，要手動改掉。

**4. 確認 nginx 正常**

用瀏覽器開 `http://<VM 外部 IP>`，看到 **404 Not Found** 是正常的，因為程式還沒部署，nginx 已經在回應。

## 6. 資料庫備份與還原

`provision.sh` 會安裝備份腳本 `/usr/local/bin/fitness-app-backup`，每天 03:00（台灣時間）由 cron 以 `postgres` 身分執行。

- 備份格式是 `pg_dump` 的自訂格式（`.dump`），檔名例如 `fitness_app-20261121-030000.dump`。
- 只保留最近 7 份，舊的自動刪除。
- 備份目錄的群組是 `deploy`，可以用部署金鑰把備份下載回自己的電腦。

### 查看備份

```bash
ls -lh /var/backups/fitness-app/
```

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

## 疑難排解

- **用 `deploy` 帳號登不進去**：主控台的瀏覽器版 SSH 是用 Google 自動產生的金鑰登入你自己的帳號，不受腳本「關閉密碼登入」的影響，可以先從那邊登入檢查 `/home/deploy/.ssh/authorized_keys`。腳本對 SSH 的設定都在 `/etc/ssh/sshd_config.d/99-fitness-app.conf`。
- **自己的 IP 被 fail2ban 封鎖**：用主控台的瀏覽器版 SSH 登入，執行 `sudo fail2ban-client set sshd unbanip <你的 IP>`。
- **查看服務狀態**：`systemctl status nginx php8.4-fpm postgresql`。nginx 的錯誤記錄在 `/var/log/nginx/error.log`。
