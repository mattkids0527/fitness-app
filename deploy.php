<?php

/**
 * Fitness App 正式環境部署設定（Deployer 7）
 *
 * 前端在執行部署的機器上建置（本地或 GitHub Actions），再上傳到 VM；VM 只負責 composer install、migrate 與快取。
 * 架構與 VM 設定見 deploy/README.md、vibe-coding/specs/2026-11-20-d1-gcp-deployment.md。
 *
 * 用法：
 *   npm run build
 *   DEPLOY_HOST=deploy@<VM 外部 IP>:/var/www/fitness-app vendor/bin/dep deploy
 *   DEPLOY_HOST=<VM 外部 IP> vendor/bin/dep rollback
 *
 * 環境變數：
 *   DEPLOY_HOST          必填。部署目標，格式 [帳號@]IP[:路徑]；帳號預設 deploy，路徑預設 /var/www/fitness-app。
 *                        網域經過 Cloudflare Tunnel，不能用來 SSH，要填 VM 的外部 IP。
 *   DEPLOY_SSH_KEY_FILE  選填。登入 VM 用的私鑰檔案路徑，預設 ~/.ssh/fitness_deploy
 *   DEPLOY_KNOWN_HOSTS_FILE  選填。VM host key 所在的 known_hosts 檔；有提供時只信任這個檔案（GitHub Actions 使用）
 *   DEPLOY_ENV_FILE      選填。要上傳成正式環境 .env 的檔案；不提供時沿用 VM 上現有的 .env
 */

namespace Deployer;

require 'recipe/laravel.php';

set('application', 'fitness-app');
set('repository', 'git@github.com:mattkids0527/fitness-app.git');

// 只部署 main；需要部署其他版本時用 --branch、--tag 或 --revision 指定
set('branch', 'main');
set('keep_releases', 3);

// PHP-FPM 以 deploy 身分執行，部署出來的檔案本來就可寫，不需要另外調整權限
set('writable_mode', 'skip');

// 正式環境的 PHP 版本，與 deploy/provision.sh 一致
set('php_version', '8.4');

/**
 * 解析 DEPLOY_HOST（[帳號@]IP[:路徑]），回傳 user、host、path；沒有設定或格式錯誤時直接中止，避免部署到錯誤的機器。
 *
 * @return array{user: string, host: string, path: string}
 */
function deployTarget(): array
{
    $value = trim((string) getenv('DEPLOY_HOST'));
    if ($value === '') {
        throw new \RuntimeException('請設定 DEPLOY_HOST，格式為 [帳號@]IP[:路徑]，例如：DEPLOY_HOST=deploy@203.0.113.10:/var/www/fitness-app');
    }

    if (! preg_match('#^(?:(?<user>[a-z_][a-z0-9_.-]*)@)?(?<host>[A-Za-z0-9.-]+)(?::(?<path>/[A-Za-z0-9._-][A-Za-z0-9._/-]*))?$#', $value, $m)) {
        throw new \RuntimeException("DEPLOY_HOST 格式錯誤：「{$value}」，應為 [帳號@]IP[:路徑]，例如 deploy@203.0.113.10:/var/www/fitness-app");
    }

    return [
        'user' => $m['user'] ?? '' ?: 'deploy',
        'host' => $m['host'],
        'path' => rtrim($m['path'] ?? '' ?: '/var/www/fitness-app', '/'),
    ];
}

/**
 * 取得登入 VM 用的私鑰路徑；檔案不存在時回傳 null，改由 ssh-agent 或 ssh 設定檔提供金鑰。
 */
function deployIdentityFile(): ?string
{
    $path = getenv('DEPLOY_SSH_KEY_FILE') ?: '~/.ssh/fitness_deploy';
    $expanded = str_starts_with($path, '~/') ? (getenv('HOME') ?: '').substr($path, 1) : $path;

    return is_file($expanded) ? $path : null;
}

/**
 * 檢查要上傳的 .env 內容：必須是正式環境且有 APP_KEY，避免空白或貼錯的設定蓋掉正式環境。回傳統一成 LF 換行的內容。
 */
function validatedEnvContent(string $file): string
{
    if (! is_file($file)) {
        throw error("找不到 DEPLOY_ENV_FILE 指定的檔案：{$file}");
    }

    $content = str_replace("\r\n", "\n", (string) file_get_contents($file));
    $values = [];
    foreach (explode("\n", $content) as $line) {
        if (preg_match('/^\s*(APP_ENV|APP_KEY)\s*=\s*"?([^"\s#]*)"?/', $line, $m)) {
            $values[$m[1]] = $m[2];
        }
    }

    if (($values['APP_ENV'] ?? '') !== 'production') {
        throw error('.env 的 APP_ENV 必須是 production，已中止部署');
    }
    if (($values['APP_KEY'] ?? '') === '') {
        throw error('.env 缺少 APP_KEY，已中止部署（請沿用 VM 上原本的 APP_KEY，換掉會讓所有登入狀態失效）');
    }

    return rtrim($content, "\n")."\n";
}

$target = deployTarget();

$production = host('production')
    ->setHostname($target['host'])
    ->setRemoteUser($target['user'])
    ->setDeployPath($target['path'])
    // VM 用自己的 GitHub Deploy key 拉程式碼，不需要轉發本機的 SSH agent
    ->setForwardAgent(false)
    ->set('labels', ['stage' => 'production']);

if ($identityFile = deployIdentityFile()) {
    $production->setIdentityFile($identityFile);
}

// 指定 known_hosts 時嚴格比對 host key：連到的不是預期的 VM 就拒絕連線
if ($knownHosts = getenv('DEPLOY_KNOWN_HOSTS_FILE')) {
    $production->setSshArguments([
        '-o UserKnownHostsFile='.$knownHosts,
        '-o StrictHostKeyChecking=yes',
    ]);
}

desc('確認本地已建置前端（public/build/manifest.json）');
task('build:check', function () {
    if (! is_file(__DIR__.'/public/build/manifest.json')) {
        throw error('找不到 public/build/manifest.json，請先執行 npm run build');
    }
    info('前端建置檔已存在，將上傳到 VM');
})->once();

desc('上傳正式環境 .env（有提供 DEPLOY_ENV_FILE 時），並把舊版備份成 shared/.env.previous');
task('deploy:upload_env', function () {
    $file = getenv('DEPLOY_ENV_FILE');
    if ($file === false || trim($file) === '') {
        info('沒有提供 DEPLOY_ENV_FILE，沿用 VM 上現有的 .env');

        return;
    }

    $content = validatedEnvContent(trim($file));
    $local = tempnam(sys_get_temp_dir(), 'fitness-env-');
    try {
        file_put_contents($local, $content);
        chmod($local, 0600);
        run('mkdir -p {{deploy_path}}/shared');
        upload($local, '{{deploy_path}}/shared/.env.upload');
    } finally {
        @unlink($local);
    }

    // 先備份再替換；mv 是原子操作，不會出現寫到一半的 .env
    run('cd {{deploy_path}}/shared && chmod 600 .env.upload && if [ -f .env ]; then cp -p .env .env.previous; fi && mv .env.upload .env');
    info('已更新 shared/.env（舊版備份在 shared/.env.previous）');
});

desc('上傳本地建置好的前端到新版本目錄');
task('deploy:upload_build', function () {
    upload(__DIR__.'/public/build/', '{{release_path}}/public/build/');
});

desc('重新載入 PHP-FPM，清除 OPcache 讓新版本的程式生效');
task('php-fpm:reload', function () {
    run('sudo systemctl reload php{{php_version}}-fpm');
});

// 先確認前端已建置再開始部署；VM 記憶體有限，不在 VM 上執行 npm build
before('deploy', 'build:check');

// .env 要在連結 shared 檔案與 config:cache 之前更新，新設定才會隨這次部署生效
before('deploy:shared', 'deploy:upload_env');
after('deploy:vendors', 'deploy:upload_build');

// 切換 current 連結後（含 rollback）重新載入 PHP-FPM
after('deploy:symlink', 'php-fpm:reload');
after('rollback', 'php-fpm:reload');

// 部署失敗時解除鎖定，下一次部署才不會被擋住；current 連結維持在舊版本
after('deploy:failed', 'deploy:unlock');
