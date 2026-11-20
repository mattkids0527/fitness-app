<?php

/**
 * Fitness App 正式環境部署設定（Deployer 7）
 *
 * 前端在執行部署的機器上建置（本地或 GitHub Actions），再上傳到 VM；VM 只負責 composer install、migrate 與快取。
 * 架構與 VM 設定見 deploy/README.md、vibe-coding/specs/2026-11-20-d1-gcp-deployment.md。
 *
 * 用法：
 *   npm run build
 *   DEPLOY_HOST=<VM 外部 IP> vendor/bin/dep deploy
 *   DEPLOY_HOST=<VM 外部 IP> vendor/bin/dep rollback
 *
 * 環境變數：
 *   DEPLOY_HOST     必填。VM 的外部 IP（網域經過 Cloudflare Tunnel，不能用來 SSH）
 *   DEPLOY_SSH_KEY  選填。登入 deploy 帳號的私鑰，預設 ~/.ssh/fitness_deploy
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
 * 從環境變數讀取部署主機，沒有設定時直接中止，避免部署到錯誤的機器。
 */
function deployHost(): string
{
    $host = getenv('DEPLOY_HOST');
    if ($host === false || trim($host) === '') {
        throw new \RuntimeException('請設定 DEPLOY_HOST（VM 的外部 IP），例如：DEPLOY_HOST=203.0.113.10 vendor/bin/dep deploy');
    }

    return trim($host);
}

/**
 * 取得登入 VM 用的私鑰路徑；檔案不存在時回傳 null，改由 ssh-agent 或 ssh 設定檔提供金鑰。
 */
function deployIdentityFile(): ?string
{
    $path = getenv('DEPLOY_SSH_KEY') ?: '~/.ssh/fitness_deploy';
    $expanded = str_starts_with($path, '~/') ? (getenv('HOME') ?: '').substr($path, 1) : $path;

    return is_file($expanded) ? $path : null;
}

$production = host('production')
    ->setHostname(deployHost())
    ->setRemoteUser('deploy')
    ->setDeployPath('/var/www/fitness-app')
    ->set('labels', ['stage' => 'production']);

if ($identityFile = deployIdentityFile()) {
    $production->setIdentityFile($identityFile);
}

desc('確認本地已建置前端（public/build/manifest.json）');
task('build:check', function () {
    if (! is_file(__DIR__.'/public/build/manifest.json')) {
        throw error('找不到 public/build/manifest.json，請先執行 npm run build');
    }
    info('前端建置檔已存在，將上傳到 VM');
})->once();

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
after('deploy:vendors', 'deploy:upload_build');

// 切換 current 連結後（含 rollback）重新載入 PHP-FPM
after('deploy:symlink', 'php-fpm:reload');
after('rollback', 'php-fpm:reload');

// 部署失敗時解除鎖定，下一次部署才不會被擋住；current 連結維持在舊版本
after('deploy:failed', 'deploy:unlock');
