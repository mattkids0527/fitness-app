# D2 發版版號

- 狀態：進行中
- 建立日期：2026-11-21
- 分支：`develop`（每個 Task 完成就 commit 並 push；PR `develop` → `main` 由使用者開與合併）
- 依據規格：[D2 發版版號規格](../specs/2026-11-21-d2-release-versioning.md)

## 目標

正式部署必須填版號，部署成功後自動打 tag、建 GitHub Release；設定頁新增「版本」分頁顯示目前版本。第一個版本為 `v1.3.0`。

## 範圍

**包含**
- 設定頁「版本」分頁與讀取 `version.json`
- `deploy.yml` 版號輸入與驗證、固定 commit 部署、成功後打 tag 與 Release
- `deploy.php` 寫入 `version.json`
- 文件：`deploy/README.md`、`CLAUDE.md`

**不包含**
- 更新紀錄列表、CHANGELOG.md、從舊 tag 重新部署、PWA 新版本提示

## Tasks

### T1 設定頁「版本」分頁（完成）
新增 `app/Support/AppVersion.php` 讀 `version.json`（不存在或格式錯誤回傳空值）；`routes/settings.php` 加 `GET /settings/version`（名稱 `version`）；`pages/settings/Version.vue` 顯示版本、部署時間（Asia/Taipei）、短 sha，沒有版本資訊時顯示「未標示版本」；`layouts/settings/Layout.vue` 加「版本」分頁；`.gitignore` 加 `version.json`。測試情境先列給使用者確認再寫。
**完成條件**：PHPUnit、build、lint、format:check、pint 通過；390px 與桌機確認畫面。
- 實作紀錄：`AppVersion` 以 singleton 綁定在 `AppServiceProvider`（路徑 `base_path('version.json')`），測試改綁暫存檔。版號格式不對就整份視為沒有版本資訊；commit 或時間格式不對只留空該欄。測試情境見 [2026-11-21-release-version](../tests/2026-11-21-release-version.md)。
- 追加（T2 之後與使用者確認）：「我的」頁設定清單在「登出」上方加「版本」列，右邊顯示版號，點進去是版本頁；`ProfileController::show` 多帶 `appVersion`，測試 ver-06。

### T2 部署流程加上版號（完成）
`deploy.yml` 加必填輸入 `version`，驗證格式、tag 不重複、大於目前最大 tag；`permissions` 改 `contents: write`；以 `--revision $GITHUB_SHA` 部署並傳 `DEPLOY_VERSION`；`deploy.php` 新增 task 在 `{{release_path}}` 寫 `version.json`（沒給版號時跳過並提醒）；部署成功後打 annotated tag、push、`gh release create --generate-notes`。更新 `deploy/README.md` 與 `CLAUDE.md`（部署指令、版號規則）。
**完成條件**：以 `actionlint`（或人工檢查）確認 workflow 語法；本地以假的版號跑驗證腳本確認格式、重複、比舊版小三種情況會被擋；`dep` 指令能正常載入設定（`vendor/bin/dep list`）。
- 實作紀錄：版號檢查獨立成 `deploy/check-version.sh`（可在本地先跑），checkout 改 `fetch-depth: 0` 才拿得到 tag。版號只透過環境變數 `DEPLOY_VERSION` 使用，不直接嵌進指令。`version.json` 的 commit 取自 Deployer 寫的 `REVISION`，task 掛在 `deploy:update_code` 之後；本地沒給 `DEPLOY_VERSION` 只警告不中止，格式錯誤則中止。tag 由 `github-actions[bot]` 建立，push tag 不會觸發其他 workflow。驗證：actionlint 通過；在暫存 repo 測試空值、`1.3.0`、`v1.3`、`v01.3.0`、夾帶 `;` 被擋，已存在、比最新小被擋，`v1.9.10` 正確判斷大於 `v1.9.2`；`dep deploy --plan` 確認 task 順序，錯誤的 `DEPLOY_VERSION` 會中止；deploy task 寫出的 JSON 經 `AppVersion` 讀回正確。實際的 tag 與 Release 要等 T3 在 GitHub 上部署時才驗證得到。

### T3 上線 v1.3.0
使用者合併 PR 到 `main` 後，以 `v1.3.0` 執行部署；確認 GitHub 上出現 tag 與 Release、正式站設定頁顯示 `v1.3.0`。寫 handoff，milestone 標為完成。
**完成條件**：正式站驗收。

## 進度

- 2026-11-21 T1 完成：設定頁「版本」分頁、5 個測試，全部 PHPUnit 通過。
- 2026-11-21 追加：「我的」頁「版本」列與測試 ver-06，全部 128 個 PHPUnit 通過。
- 2026-11-21 T3 進行中：已以 `v1.3.1`（T2）與 `v1.3.2`（「我的」頁版本列）部署，GitHub 上有對應 tag 與 Release，使用者驗收 OK（沒有 `v1.3.0` tag，第一版直接是 `v1.3.1`）。驗收後調整：版本頁拿掉 commit，部署時間改成只顯示「更新日期」，待以 `v1.3.3` 部署。
- 2026-11-21 T2 完成：deploy workflow 版號輸入與檢查、固定 commit、成功後打 tag 與 Release，Deployer 寫 `version.json`，文件更新。
