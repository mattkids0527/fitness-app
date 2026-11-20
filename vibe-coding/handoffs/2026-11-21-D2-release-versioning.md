# 交接：D2 發版版號

- 更新日期：2026-11-21
- 對應任務：[D2 發版版號](../milestones/D2-release-versioning.md)
- 依據規格：[D2 規格](../specs/2026-11-21-d2-release-versioning.md)
- 分支：`develop`（已由使用者以 PR #5、#6 合併 `main`）
- 目前進度：D2 全部完成（T1～T3）。正式站目前是 `v1.3.3`，GitHub 上有 `v1.3.1`～`v1.3.3` 三個 tag 與 Release。

## 下次接手從這裡開始

1. `git checkout develop && git pull --ff-only`。
2. `develop` 上有一個還沒部署的程式變更：未綁定的 Google 帳號登入時，錯誤說明最後加上「還沒有帳號的話，請聯絡管理員。」（`packages/fitness/social-login/src/lang/zh_TW/errors.php` 的 `not_linked.message`，測試同步修改）。使用者開 PR 合併後，以 `v1.3.4` 部署。
3. 下一個里程碑是 **M4 Gemini 照片分析**，版號升到 `v1.4.0`。開始前先討論 spec 與 demo 畫面；D1 handoff 提醒的 queue worker（`provision.sh` 加 `queue:work`、`PRODUCTION_ENV` 改 `QUEUE_CONNECTION=database`）與照片儲存位置要在 spec 裡決定。

## 重點

### 發版流程
- Actions → deploy → Run workflow，分支 `main`，填版號。版號規則：M 系列升中間碼，U／D 系列與修 bug 升最後一碼。
- `deploy/check-version.sh` 會擋下格式錯誤、已存在、比最新 tag 小的版號，可以先在本地跑（要先 `git fetch --tags`）。
- 部署失敗不打 tag，同一個版號修好後可以再用。
- Deployer 在版本目錄寫 `version.json`（不進 git）。設定頁「版本」與「我的」頁的版本列由 `app/Support/AppVersion.php` 讀取，只顯示版號與更新日期。`dep rollback` 後顯示的版號會跟著退回。

### 跟規格不同的地方
- 規格原訂第一個 tag 是 `v1.3.0`，實際第一個是 `v1.3.1`。CLAUDE.md、`deploy/README.md`、規格都已註明。
- 版本頁原本也顯示 commit 與部署時分，`v1.3.2` 驗收後使用者決定只留版號與更新日期。

## 這次確認的項目（2026-11-21）

- 今日頁與飲食頁「超過熱量目標變紅」：使用者在畫面上確認 OK。
- cron 自動備份：`/var/backups/fitness-app/` 有 `fitness_app-20261121-030001.dump`（03:00 產生），正常。
- iOS 加到主畫面（PWA standalone）後，Google 登入與解除綁定都成功。
- M2 待決事項：未綁定的 Google 登入錯誤說明要加「還沒有帳號的話，請聯絡管理員」，已實作（見上方第 2 點）。

## 尚未處理（之後再排）

- Google OAuth 同意畫面還是「測試中」，只有測試使用者能用 Google 登入；要開放給其他人時再發布成正式版。
- D1 handoff「之後可以考慮的改善」：`rollback.yml`、SSH 改走 Tunnel、Actions 與本地各用一把部署金鑰、異地備份。使用者決定之後再做。
