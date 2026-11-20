#!/usr/bin/env bash
#
# Fitness App 正式環境 VM 初始化腳本
#
# 在全新的 Ubuntu 22.04 / 24.04（GCP e2-micro）上以 root 執行一次，安裝並設定：
# swap、時區、nginx、PHP-FPM、PostgreSQL、Composer、deploy 帳號、SSH 強化、ufw、fail2ban、自動安全性更新、
# 資料庫每日備份、Cloudflare Tunnel（cloudflared），並建立部署目錄與正式環境 .env 範本。
# 可重複執行：已完成的步驟會略過，設定檔會以最新內容覆寫。
#
# 網站流量只經由 Cloudflare Tunnel 進來：nginx 只聽 127.0.0.1:80，防火牆只開 SSH。
#
# 用法（在 VM 上）：
#   sudo DEPLOY_PUBKEY="ssh-ed25519 AAAA... fitness-deploy" bash provision.sh
#   （執行中會詢問 Cloudflare Tunnel token，貼上後不會顯示在畫面上，也不會留在 shell 歷史紀錄）
#
# 可用環境變數：
#   DEPLOY_PUBKEY            必填（第一次）。部署用公鑰，GitHub Actions 與本地 Deployer 以對應私鑰登入 deploy 帳號
#   CLOUDFLARE_TUNNEL_TOKEN  選填。不提供時會互動詢問；留空則只安裝 cloudflared、不註冊服務
#   APP_DOMAIN               選填。網站網域，預設 wn52777.com
#   DB_PASSWORD              選填。資料庫密碼，預設自動產生；資料庫帳號已存在時不會變更密碼
#
set -euo pipefail

# ---- 可調整的設定 -----------------------------------------------------------

PHP_VERSION="8.4"
PG_VERSION="16"
APP_NAME="fitness-app"
DEPLOY_USER="deploy"
DEPLOY_PATH="/var/www/${APP_NAME}"
DB_NAME="fitness_app"
DB_USER="fitness_app"
SWAP_SIZE="2G"
TIMEZONE="Asia/Taipei"
FPM_SOCKET="/run/php/${APP_NAME}.sock"
BACKUP_DIR="/var/backups/${APP_NAME}"
BACKUP_KEEP="7"
BACKUP_SCRIPT="/usr/local/bin/${APP_NAME}-backup"

APP_DOMAIN="${APP_DOMAIN:-wn52777.com}"
DEPLOY_PUBKEY="${DEPLOY_PUBKEY:-}"
CLOUDFLARE_TUNNEL_TOKEN="${CLOUDFLARE_TUNNEL_TOKEN:-}"
DB_PASSWORD="${DB_PASSWORD:-}"

export DEBIAN_FRONTEND=noninteractive

# ---- 共用小工具 -------------------------------------------------------------

# 印出步驟標題
step() {
    printf '\n\033[1;33m==> %s\033[0m\n' "$*"
}

# 印出提示訊息
info() {
    printf '    %s\n' "$*"
}

# 印出錯誤並結束
die() {
    printf '\033[1;31m錯誤：%s\033[0m\n' "$*" >&2
    exit 1
}

# 判斷是否由 systemd 管理服務（容器內試跑時為否，會略過服務與核心相關步驟）
has_systemd() {
    [ -d /run/systemd/system ]
}

# 以 systemctl 操作服務；沒有 systemd 時略過
svc() {
    if has_systemd; then
        systemctl "$@"
    else
        info "（沒有 systemd，略過 systemctl $*）"
    fi
}

# 套件未安裝時才安裝
apt_install() {
    local missing=()
    local pkg
    for pkg in "$@"; do
        dpkg -s "$pkg" >/dev/null 2>&1 || missing+=("$pkg")
    done
    if [ "${#missing[@]}" -gt 0 ]; then
        apt-get install -y --no-install-recommends "${missing[@]}"
    fi
}

# 產生不含特殊符號的隨機字串（給密碼用，避免 .env 與 SQL 跳脫問題）
random_string() {
    openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c "$1"
}

# ---- 各步驟 -----------------------------------------------------------------

# 確認以 root 在支援的 Ubuntu 版本上執行，並檢查必要參數
preflight() {
    step "檢查執行環境"
    [ "$(id -u)" -eq 0 ] || die "請用 sudo 執行"
    # shellcheck source=/dev/null
    . /etc/os-release
    [ "${ID:-}" = "ubuntu" ] || die "只支援 Ubuntu，目前是 ${ID:-unknown}"
    case "${VERSION_ID:-}" in
        22.04 | 24.04) info "Ubuntu ${VERSION_ID}" ;;
        *) die "只測試過 Ubuntu 22.04 / 24.04，目前是 ${VERSION_ID:-unknown}" ;;
    esac

    local auth_keys="/home/${DEPLOY_USER}/.ssh/authorized_keys"
    if [ -z "$DEPLOY_PUBKEY" ] && [ ! -s "$auth_keys" ]; then
        die "第一次執行需要 DEPLOY_PUBKEY（部署用公鑰），用法見腳本開頭說明"
    fi
    if [ -n "$DEPLOY_PUBKEY" ] && ! printf '%s' "$DEPLOY_PUBKEY" | grep -Eq '^(ssh-ed25519|ssh-rsa|ecdsa-sha2-[a-z0-9-]+) [A-Za-z0-9+/=]+'; then
        die "DEPLOY_PUBKEY 看起來不是 SSH 公鑰（應以 ssh-ed25519 開頭的一整行）"
    fi
}

# 建立 swapfile 並調低 swappiness，避免 1GB 記憶體在 composer install 時不足
setup_swap() {
    step "設定 ${SWAP_SIZE} swap"
    if ! has_systemd; then
        info "（容器內略過 swap）"
        return
    fi
    if swapon --show=NAME --noheadings | grep -qx /swapfile; then
        info "已存在 /swapfile，略過"
    else
        fallocate -l "$SWAP_SIZE" /swapfile
        chmod 600 /swapfile
        mkswap /swapfile >/dev/null
        swapon /swapfile
        grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >>/etc/fstab
    fi
    echo 'vm.swappiness=10' >/etc/sysctl.d/99-${APP_NAME}.conf
    sysctl -q -p /etc/sysctl.d/99-${APP_NAME}.conf
}

# 設定系統時區，讓 cron 與 log 時間以台灣時間為準
setup_timezone() {
    step "設定時區 ${TIMEZONE}"
    if has_systemd; then
        timedatectl set-timezone "$TIMEZONE"
        # cron 只在啟動時讀取時區，改完要重啟才會用新的時區排程
        systemctl restart cron 2>/dev/null || true
    else
        ln -sf "/usr/share/zoneinfo/${TIMEZONE}" /etc/localtime
        echo "$TIMEZONE" >/etc/timezone
    fi
}

# 更新套件清單並安裝基本工具
install_base_packages() {
    step "安裝基本套件"
    apt-get update -y
    apt_install ca-certificates curl gnupg git unzip acl openssl sudo openssh-client software-properties-common postgresql-common tzdata cron
}

# 加入 PHP（ondrej/php）與 PostgreSQL（PGDG）官方套件庫
add_repositories() {
    step "加入 PHP 與 PostgreSQL 套件庫"
    if ! grep -rqs 'ondrej/php' /etc/apt/sources.list.d/; then
        add-apt-repository -y ppa:ondrej/php
    else
        info "ondrej/php 已加入，略過"
    fi
    if ! grep -rqs 'apt.postgresql.org' /etc/apt/sources.list.d/; then
        /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
    else
        info "PGDG 已加入，略過"
    fi
    apt-get update -y
}

# 安裝 nginx、PHP-FPM 與 Laravel 所需擴充、PostgreSQL
install_stack() {
    step "安裝 nginx、PHP ${PHP_VERSION}、PostgreSQL ${PG_VERSION}"
    apt_install nginx \
        "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-opcache" \
        "php${PHP_VERSION}-pgsql" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" \
        "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" "php${PHP_VERSION}-bcmath" \
        "php${PHP_VERSION}-intl" "php${PHP_VERSION}-gd" "php${PHP_VERSION}-readline" \
        "postgresql-${PG_VERSION}"
}

# 安裝 Composer（驗證官方簽章）；已安裝時更新到最新版
install_composer() {
    step "安裝 Composer"
    if command -v composer >/dev/null 2>&1; then
        composer self-update --2 --no-interaction -q || true
        info "已安裝 $(composer --version 2>/dev/null | head -n1)"
        return
    fi
    local tmp expected actual
    tmp="$(mktemp -d)"
    expected="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o "${tmp}/composer-setup.php"
    actual="$(php -r "echo hash_file('sha384', '${tmp}/composer-setup.php');")"
    [ "$expected" = "$actual" ] || die "Composer 安裝程式簽章不符"
    php "${tmp}/composer-setup.php" --quiet --install-dir=/usr/local/bin --filename=composer
    rm -rf "$tmp"
}

# 建立 deploy 帳號、登入用公鑰、拉 GitHub 程式碼用的 deploy key，以及只允許 reload php-fpm 的 sudo 權限
setup_deploy_user() {
    step "設定 ${DEPLOY_USER} 帳號"
    local home="/home/${DEPLOY_USER}"
    if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
        useradd --create-home --shell /bin/bash "$DEPLOY_USER"
    fi
    passwd -l "$DEPLOY_USER" >/dev/null

    install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "${home}/.ssh"
    local auth_keys="${home}/.ssh/authorized_keys"
    touch "$auth_keys"
    if [ -n "$DEPLOY_PUBKEY" ] && ! grep -qxF "$DEPLOY_PUBKEY" "$auth_keys"; then
        echo "$DEPLOY_PUBKEY" >>"$auth_keys"
        info "已加入部署公鑰"
    fi
    chmod 600 "$auth_keys"
    chown "$DEPLOY_USER:$DEPLOY_USER" "$auth_keys"

    # VM 從 GitHub 拉程式碼用的金鑰（公鑰要加到 GitHub repo 的 Deploy keys，唯讀）
    if [ ! -f "${home}/.ssh/id_ed25519" ]; then
        sudo -u "$DEPLOY_USER" ssh-keygen -q -t ed25519 -N '' -C "${DEPLOY_USER}@${APP_NAME}-vm" -f "${home}/.ssh/id_ed25519"
    fi
    local known="${home}/.ssh/known_hosts"
    if ! sudo -u "$DEPLOY_USER" ssh-keygen -F github.com -f "$known" >/dev/null 2>&1; then
        ssh-keyscan -t ed25519,rsa,ecdsa github.com 2>/dev/null >>"$known"
        chown "$DEPLOY_USER:$DEPLOY_USER" "$known"
        chmod 644 "$known"
    fi

    local sudoers="/etc/sudoers.d/${APP_NAME}-deploy"
    local tmp
    tmp="$(mktemp)"
    cat >"$tmp" <<EOF
# 部署時清除 OPcache 用；deploy 帳號只能執行這一條 sudo 指令
${DEPLOY_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl reload php${PHP_VERSION}-fpm
EOF
    visudo -cqf "$tmp" || die "sudoers 設定檔語法錯誤"
    install -m 440 -o root -g root "$tmp" "$sudoers"
    rm -f "$tmp"
}

# 關閉 SSH 密碼登入與 root 登入（先檢查設定正確才重新載入，避免把自己鎖在門外）
harden_ssh() {
    step "強化 SSH 設定"
    if [ ! -d /etc/ssh/sshd_config.d ]; then
        info "（沒有安裝 sshd，略過）"
        return
    fi
    cat >/etc/ssh/sshd_config.d/99-${APP_NAME}.conf <<'EOF'
# Fitness App：只允許金鑰登入
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
EOF
    install -d -m 755 /run/sshd
    sshd -t || die "sshd 設定檢查失敗，請檢查 /etc/ssh/sshd_config.d/"
    svc reload ssh
}

# 防火牆只開 SSH（網站流量走 Cloudflare Tunnel），並啟用 fail2ban 防 SSH 暴力破解
setup_firewall() {
    step "設定 ufw 與 fail2ban"
    apt_install ufw fail2ban python3-systemd
    cat >/etc/fail2ban/jail.d/${APP_NAME}.local <<'EOF'
[sshd]
enabled  = true
backend  = systemd
maxretry = 5
findtime = 10m
bantime  = 1h
EOF
    if has_systemd; then
        ufw allow OpenSSH >/dev/null
        # 網站流量走 Cloudflare Tunnel（由 VM 主動連出），80／443 不對外開放；移除舊版腳本開過的規則
        ufw delete allow 80/tcp >/dev/null 2>&1 || true
        ufw delete allow 443/tcp >/dev/null 2>&1 || true
        ufw --force enable >/dev/null
        ufw status | sed 's/^/    /'
    else
        info "（容器內略過 ufw）"
    fi
    svc enable --now fail2ban
    svc restart fail2ban
}

# 開啟每日自動安裝安全性更新
setup_unattended_upgrades() {
    step "開啟自動安全性更新"
    apt_install unattended-upgrades
    cat >/etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF
}

# 設定 PHP-FPM pool（以 deploy 身分執行、ondemand 省記憶體）與 OPcache
configure_php() {
    step "設定 PHP-FPM 與 OPcache"
    local fpm_dir="/etc/php/${PHP_VERSION}/fpm"
    if [ -f "${fpm_dir}/pool.d/www.conf" ]; then
        mv "${fpm_dir}/pool.d/www.conf" "${fpm_dir}/pool.d/www.conf.disabled"
    fi
    cat >"${fpm_dir}/pool.d/${APP_NAME}.conf" <<EOF
; Fitness App：以 deploy 身分執行，部署出來的檔案不需另外調整權限
[${APP_NAME}]
user = ${DEPLOY_USER}
group = ${DEPLOY_USER}
listen = ${FPM_SOCKET}
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; 1GB 記憶體：閒置時不保留 worker，最多 4 個
pm = ondemand
pm.max_children = 4
pm.process_idle_timeout = 10s
pm.max_requests = 500
EOF

    # 正式環境的 PHP 設定，FPM 與 CLI 共用
    local ini
    ini="$(
        cat <<'EOF'
; Fitness App 正式環境設定
expose_php = Off
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 12M

; 部署時會 reload php-fpm 清除快取，所以不必每次檢查檔案時間
opcache.enable = 1
opcache.memory_consumption = 64
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
EOF
    )"
    printf '%s\n' "$ini" >"${fpm_dir}/conf.d/99-${APP_NAME}.ini"
    # CLI（artisan、composer）沿用設定，但要即時看到檔案變更
    printf '%s\nopcache.enable_cli = 0\nmemory_limit = 512M\n' "$ini" >"/etc/php/${PHP_VERSION}/cli/conf.d/99-${APP_NAME}.ini"

    "php-fpm${PHP_VERSION}" -t 2>&1 | sed 's/^/    /'
    svc enable "php${PHP_VERSION}-fpm"
    svc restart "php${PHP_VERSION}-fpm"
}

# 調整 PostgreSQL 記憶體設定，並建立應用程式專用的資料庫帳號與資料庫
configure_postgres() {
    step "設定 PostgreSQL"
    local conf_dir="/etc/postgresql/${PG_VERSION}/main/conf.d"
    install -d "$conf_dir"
    cat >"${conf_dir}/${APP_NAME}.conf" <<'EOF'
# Fitness App：1GB 記憶體 VM 的保守設定，只聽 localhost
listen_addresses = 'localhost'
max_connections = 20
shared_buffers = 128MB
effective_cache_size = 256MB
work_mem = 4MB
maintenance_work_mem = 32MB
EOF
    if has_systemd; then
        systemctl enable "postgresql@${PG_VERSION}-main" >/dev/null 2>&1 || true
        systemctl restart postgresql
    else
        pg_ctlcluster "$PG_VERSION" main restart || pg_ctlcluster "$PG_VERSION" main start
    fi

    local role_exists
    role_exists="$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname = '${DB_USER}'")"
    if [ "$role_exists" = "1" ]; then
        info "資料庫帳號 ${DB_USER} 已存在，不變更密碼"
        DB_PASSWORD=""
    else
        [ -n "$DB_PASSWORD" ] || DB_PASSWORD="$(random_string 32)"
        sudo -u postgres psql -q -v ON_ERROR_STOP=1 -v pw="$DB_PASSWORD" <<SQL
CREATE ROLE ${DB_USER} LOGIN PASSWORD :'pw';
SQL
        info "已建立資料庫帳號 ${DB_USER}"
    fi

    local db_exists
    db_exists="$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname = '${DB_NAME}'")"
    if [ "$db_exists" != "1" ]; then
        sudo -u postgres createdb --owner="$DB_USER" --encoding=UTF8 --template=template0 "$DB_NAME"
        info "已建立資料庫 ${DB_NAME}"
    else
        info "資料庫 ${DB_NAME} 已存在，略過"
    fi
}

# 安裝資料庫每日備份：備份腳本、備份目錄與 cron（每天 03:00 以 postgres 身分執行，保留最近 7 份）
setup_backup() {
    step "設定資料庫每日備份"
    # 目錄群組設為 deploy 並加上 setgid：postgres 寫入的備份檔群組自動為 deploy，deploy 可以用 scp 下載
    install -d -m 2750 -o postgres -g "$DEPLOY_USER" "$BACKUP_DIR"

    cat >"$BACKUP_SCRIPT" <<SCRIPT
#!/usr/bin/env bash
#
# Fitness App 資料庫備份（由 provision.sh 產生，修改請改 provision.sh 後重跑）
# 以 pg_dump 自訂格式備份到 ${BACKUP_DIR}，只保留最近 ${BACKUP_KEEP} 份。
# 手動執行：sudo -u postgres ${BACKUP_SCRIPT}
#
set -euo pipefail
umask 027

if [ "\$(id -un)" != "postgres" ]; then
    echo "請以 postgres 身分執行：sudo -u postgres \$0" >&2
    exit 1
fi

cd "${BACKUP_DIR}"
file="${DB_NAME}-\$(date +%Y%m%d-%H%M%S).dump"

# 先寫到暫存檔，成功後才改名，避免中斷時留下不完整的檔案被當成有效備份
pg_dump --format=custom --dbname="${DB_NAME}" --file="\$file.partial"
mv "\$file.partial" "\$file"

# 只保留最近 ${BACKUP_KEEP} 份，並清掉之前失敗留下的暫存檔
ls -1t ${DB_NAME}-*.dump | tail -n +$((BACKUP_KEEP + 1)) | xargs -r rm -f --
rm -f ./*.partial

echo "備份完成：${BACKUP_DIR}/\$file（\$(du -h "\$file" | cut -f1)）"
SCRIPT
    chmod 755 "$BACKUP_SCRIPT"

    cat >/etc/cron.d/${APP_NAME}-backup <<CRON
# Fitness App：每天 03:00（系統時區 ${TIMEZONE}）備份資料庫，結果寫入 syslog（journalctl -t ${APP_NAME}-backup）
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
0 3 * * * postgres ${BACKUP_SCRIPT} 2>&1 | logger -t ${APP_NAME}-backup
CRON
    chmod 644 /etc/cron.d/${APP_NAME}-backup
    svc enable --now cron
    info "備份目錄 ${BACKUP_DIR}，手動備份：sudo -u postgres ${BACKUP_SCRIPT}"
}

# 設定 nginx：只聽 127.0.0.1 給 cloudflared 轉入，root 指向 current/public，/build/ 長效快取，開 gzip
configure_nginx() {
    step "設定 nginx（${APP_DOMAIN}）"
    cat >/etc/nginx/sites-available/${APP_NAME} <<EOF
# Fitness App：只接受本機 cloudflared 轉進來的連線（HTTPS 由 Cloudflare 處理）

# www 一律轉向不含 www 的網址
server {
    listen 127.0.0.1:80;
    server_name www.${APP_DOMAIN};
    return 301 https://${APP_DOMAIN}\$request_uri;
}

server {
    listen 127.0.0.1:80 default_server;
    server_name ${APP_DOMAIN};

    root ${DEPLOY_PATH}/current/public;
    index index.php;
    charset utf-8;
    client_max_body_size 12M;
    server_tokens off;

    # 連線都來自本機的 cloudflared，改用 Cloudflare 提供的使用者真實 IP（登入次數限制、log 才會正確）
    set_real_ip_from 127.0.0.1;
    real_ip_header CF-Connecting-IP;

    # 使用者用 http 連到 Cloudflare 時轉向 https（Cloudflare 的 Always Use HTTPS 沒開時的保險）
    if (\$http_x_forwarded_proto = "http") {
        return 301 https://\$host\$request_uri;
    }

    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/javascript application/json application/manifest+json image/svg+xml;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Vite 產生的檔名含 hash，可以長期快取（Cloudflare 邊緣節點也會依此快取）
    location /build/ {
        expires max;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files \$uri =404;
    }

    # service worker 與 manifest 每次都要向伺服器確認，避免舊版卡在使用者手機上
    location ~ ^/(sw\.js|manifest\.webmanifest)\$ {
        add_header Cache-Control "no-cache";
        try_files \$uri =404;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ ^/index\.php(/|\$) {
        fastcgi_pass unix:${FPM_SOCKET};
        fastcgi_split_path_info ^(.+\.php)(/.*)\$;
        include fastcgi_params;
        # 部署以 symlink 切換版本，必須用 realpath 才會指到新版本
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        # 使用者到 Cloudflare 一律是 HTTPS：讓 Laravel 產生 https 網址、secure cookie 與正確的 OAuth redirect URI
        fastcgi_param HTTPS on;
        fastcgi_param SERVER_PORT 443;
        fastcgi_hide_header X-Powered-By;
    }

    # 其他 .php 與隱藏檔一律不提供
    location ~ \.php\$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }
}
EOF
    ln -sf /etc/nginx/sites-available/${APP_NAME} /etc/nginx/sites-enabled/${APP_NAME}
    rm -f /etc/nginx/sites-enabled/default
    nginx -t 2>&1 | sed 's/^/    /'
    svc enable nginx
    svc reload nginx || svc restart nginx
}

# 安裝 cloudflared，並以 Tunnel token 註冊成系統服務（token 不提供時互動詢問，可留空稍後再設定）
setup_cloudflared() {
    step "設定 Cloudflare Tunnel"
    if [ ! -f /usr/share/keyrings/cloudflare-main.gpg ]; then
        install -d -m 755 /usr/share/keyrings
        curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg -o /usr/share/keyrings/cloudflare-main.gpg
    fi
    if [ ! -f /etc/apt/sources.list.d/cloudflared.list ]; then
        echo 'deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main' \
            >/etc/apt/sources.list.d/cloudflared.list
        apt-get update -y
    fi
    apt_install cloudflared
    info "$(cloudflared --version 2>/dev/null | head -n1)"

    if [ -f /etc/systemd/system/cloudflared.service ]; then
        info "cloudflared 服務已註冊，略過（要換 token 請先執行 sudo cloudflared service uninstall）"
        svc enable --now cloudflared
        return
    fi
    if [ -z "$CLOUDFLARE_TUNNEL_TOKEN" ] && [ -t 0 ]; then
        printf '    請貼上 Cloudflare Tunnel token（輸入時不會顯示；直接按 Enter 可略過）：'
        read -rs CLOUDFLARE_TUNNEL_TOKEN
        echo
    fi
    if [ -z "$CLOUDFLARE_TUNNEL_TOKEN" ]; then
        info "沒有提供 token，略過註冊；之後可重跑本腳本，或執行 sudo cloudflared service install <token>"
        return
    fi
    if ! has_systemd; then
        info "（沒有 systemd，略過 cloudflared service install）"
        return
    fi
    cloudflared service install "$CLOUDFLARE_TUNNEL_TOKEN"
    info "cloudflared 服務已註冊並啟動"
}

# 建立 Deployer 的部署目錄，第一次執行時產生正式環境 .env（之後不覆寫）
setup_app_directory() {
    step "建立部署目錄 ${DEPLOY_PATH}"
    install -d -m 755 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "$DEPLOY_PATH" "${DEPLOY_PATH}/shared"

    local env_file="${DEPLOY_PATH}/shared/.env"
    if [ -f "$env_file" ]; then
        info "已存在 ${env_file}，不覆寫"
        return
    fi
    if [ -z "$DB_PASSWORD" ]; then
        info "資料庫帳號原本就存在，無法得知密碼：請手動在 .env 填入 DB_PASSWORD"
    fi

    cat >"$env_file" <<EOF
APP_NAME=Fitness
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=https://${APP_DOMAIN}

APP_LOCALE=zh_TW
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=zh_TW

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

AUTH_REGISTRATION_ENABLED=false
AUTH_PASSWORD_RESET_ENABLED=false

LOG_CHANNEL=daily
LOG_DAILY_DAYS=14
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
# 經由 Cloudflare 一律是 HTTPS，cookie 只在 HTTPS 傳送
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
# 目前沒有 queue job；之後需要時改 database 並常駐 queue:work
QUEUE_CONNECTION=sync

CACHE_STORE=database

MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="\${APP_NAME}"

SOCIAL_LOGIN_PROVIDERS=google

# 正式環境專用的 OAuth 用戶端，redirect URI 為 https://${APP_DOMAIN}/auth/google/callback；沒填時登入頁不顯示 Google 按鈕
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="\${APP_URL}/auth/google/callback"
EOF
    chown "$DEPLOY_USER:$DEPLOY_USER" "$env_file"
    chmod 600 "$env_file"
    info "已產生 ${env_file}"
}

# 印出接下來要手動完成的事項與目前的 Tunnel 狀態
print_summary() {
    local home="/home/${DEPLOY_USER}"
    local tunnel="未註冊（重跑本腳本並貼上 token）"
    if has_systemd && systemctl is-active --quiet cloudflared 2>/dev/null; then
        tunnel="執行中"
    fi
    step "完成"
    cat <<EOF
    Cloudflare Tunnel：${tunnel}

    接下來請完成：

    1. 把下面這把公鑰加到 GitHub repo → Settings → Deploy keys（Allow write access 不要勾）：

       $(cat "${home}/.ssh/id_ed25519.pub")

    2. 用部署用私鑰測試登入 deploy 帳號（在你的電腦上執行）：

       ssh -i ~/.ssh/fitness_deploy ${DEPLOY_USER}@<VM 靜態 IP>

    3. 確認正式環境設定：sudo -u ${DEPLOY_USER} nano ${DEPLOY_PATH}/shared/.env

    目前還沒部署程式，瀏覽 https://${APP_DOMAIN} 會看到 nginx 的 404，這是正常的。
EOF
}

# ---- 主流程 -----------------------------------------------------------------

# 依序執行所有步驟
main() {
    # 以 postgres 身分執行指令時避免「無法切換到 /root」的警告
    cd /tmp
    preflight
    setup_swap
    setup_timezone
    install_base_packages
    add_repositories
    install_stack
    install_composer
    setup_deploy_user
    harden_ssh
    setup_firewall
    setup_unattended_upgrades
    configure_php
    configure_postgres
    setup_backup
    configure_nginx
    setup_cloudflared
    setup_app_directory
    print_summary
}

main "$@"
