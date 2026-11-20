# D2 發版版號規格

- 建立日期：2026-11-21
- 狀態：已確認（2026-11-21）
- 範圍：部署流程（`.github/workflows/deploy.yml`、`deploy.php`）、設定頁新增「版本」分頁；不改資料表

## 1. 目標

- 每次正式部署都要有版號（例如 `v1.3.0`），部署成功後在 `main` 的那個 commit 上留下 git tag 與 GitHub Release。
- 使用者在 App 的設定頁可以看到目前正式站跑的是哪一版。

## 2. 已決（2026-11-21）

- 部署時自動打 tag：Run workflow 時填版號，workflow 驗證後部署，成功才打 tag、建 GitHub Release。
- 設定頁「版本」只顯示目前版本（版號、更新日期），不列更新歷史；（原本也顯示 commit 與部署時分，v1.3.2 驗收後使用者決定拿掉，2026-11-21）各版內容看 GitHub Release（自動產生的 release notes）。
- 版號規則（SemVer `vMAJOR.MINOR.PATCH`）：
  - M 系列新功能升中版號：M4 → `v1.4.0`。
  - 修 bug、介面調整（U 系列）、部署流程（D 系列）升最後一碼：例如 `v1.3.1`。
  - 第一個 tag 是 `v1.3.0`，內容包含 M1～M3、U1 與本次 D2。

## 3. 部署流程

`deploy.yml`（`workflow_dispatch`）新增必填輸入 `version`：

| 步驟 | 做法 |
|---|---|
| 驗證版號 | 格式必須是 `v數字.數字.數字`；同名 tag 不可已存在；必須大於目前最大的 `v*` tag（以 `sort -V` 比較）。任一條不符就中止，不部署 |
| 固定 commit | 以 workflow 觸發時的 `GITHUB_SHA` 部署（`dep deploy --revision`），避免 VM 拉到之後才進 `main` 的 commit |
| 寫入版本資訊 | 以環境變數 `DEPLOY_VERSION` 傳給 Deployer；新 task 在新版本目錄寫 `version.json`：`{"version": "v1.3.0", "commit": "<完整 sha>", "deployed_at": "<ISO 8601>"}` |
| 部署成功後 | 在該 commit 打 annotated tag 並 push，再以 `gh release create <版號> --generate-notes` 建 Release |
| 部署失敗 | 不打 tag，同一個版號可以修好後重新部署 |

- workflow 的 `permissions` 改為 `contents: write`（打 tag、建 Release 需要）。
- `version.json` 放在每個版本目錄內，`dep rollback` 退回舊版本時，設定頁顯示的版號也跟著退回。
- 本地手動 `dep deploy` 沒有給 `DEPLOY_VERSION` 時照常部署，但不寫 `version.json`（設定頁顯示「未標示版本」），並印出提醒。
- `version.json` 加入 `.gitignore`，不進版控。

## 4. 設定頁「版本」分頁

- 路由 `GET /settings/version`，名稱 `version`，需登入；放在 `routes/settings.php`（與其他設定頁同層，屬 `app/` 共用功能，不另開模組）。
- 讀取：`app/Support/AppVersion.php` 讀 `base_path('version.json')`，檔案不存在或格式錯誤時回傳空值，不丟錯。
- 頁面 `resources/js/pages/settings/Version.vue`，設定分頁最後加「版本」：

| 欄位 | 有 `version.json` | 沒有（本地開發、手動部署未給版號） |
|---|---|---|
| 版本 | `v1.3.0` | 未標示版本 |
| 更新日期 | `2026-11-21`（部署時間換成 Asia/Taipei 的日期） | — |

`version.json` 仍保留 commit，只給在 VM 上除錯用，不傳給畫面。

## 5. 「我的」頁入口

- 手機要進設定頁得先點其中一個設定項目，所以「我的」頁設定清單在「登出」上方加一列「版本」，右邊顯示目前版號（沒有時顯示「未標示」），點進去是 `/settings/version`。
- 版號由 `profile` 模組的 `ProfileController::show` 以 `appVersion` prop 帶入（只放版號字串）。

## 6. 不包含

- 更新紀錄列表、CHANGELOG.md。
- 從舊 tag 重新部署（退版用 `dep rollback`）。
- 前端 PWA 提示「有新版本」。
