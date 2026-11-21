# M5 健身紀錄

- 狀態：進行中（T1～T3 完成）
- 建立日期：2026-11-21
- 分支：`develop`（每個 Task 完成就 commit 並 push；PR `develop` → `main` 由使用者開與合併）
- 依據規格：[M5 健身紀錄規格](../specs/2026-11-21-m5-exercise.md)
- 設計定稿：[ui/features/m5-exercise](../ui/features/m5-exercise/README.md)
- 寫作規範：[模組化寫作規範](../coding-style.md)
- 交接：[handoff](../handoffs/2026-11-21-M5-exercise.md)
- 版號：完成後以 `v1.5.0` 部署（健身紀錄與照片辨識一起上線）

## 目標

使用者以自己的分類與項目記錄運動：「填時長」的分類輸入時長，「逐組」的分類逐組輸入次數 × 重量，存檔時依紀錄日的體重估算消耗熱量（快照）。健身頁依日期列出紀錄；今日頁顯示今天的運動並把消耗加回熱量目標，喝水建議量加上運動時數 × 500 ml。不認得的器材或動作可以拍照／上傳照片請 AI 辨識，確認後建立項目並直接記錄。

## 範圍

**包含**
- `exercise` 模組：分類（記錄方式 `duration`／`sets`）、項目（封存、還原）、紀錄與逐組、快照、熱量／訓練量計算；健身頁、選擇項目面板、記錄頁、分類與項目管理頁；預設資料（2 個分類、7 個項目）
- `profile`：新增 `ProfileCompleted` 事件
- `water`：新增收集型事件 `WaterTargetCollecting`，建議量加上運動
- `daily-summary`／今日頁：`sections.exercise`、熱量目標加回運動消耗
- `exercise-identifier` 核心＋`exercise-identifier-gemini` driver；照片辨識流程（辨識頁、候選頁、帶入新增項目表單）
- 移除 `/workouts` 即將推出頁；＋選單開放「健身」
- 部署：`PRODUCTION_ENV` 加 `EXERCISE_IDENTIFIER_*`，`v1.5.0` 上線

**不包含**
- 營養素目標與達成率（M6）、週／月趨勢與個人最佳（第二階段）
- 穿戴裝置同步、課表／訓練計畫、一次訓練（session）
- 配速、心率、熱身組、kg 以外的重量單位
- 保存辨識照片、背景辨識、queue worker

## Tasks

### T1 設計 demo 畫面（完成）
健身頁、選擇項目面板、記錄頁（填時長、逐組、編輯）、紀錄選單與刪除、分類與項目管理（新增分類、分類選單、刪除限制、項目表單、封存與還原）、照片辨識（辨識頁、辨識中、失敗、次數用完、候選卡、新建分類、帶入表單）、今日頁與喝水面板、桌機版；手機與桌機、淺色與深色。已移到 `vibe-coding/ui/features/m5-exercise/` 並更新 `vibe-coding/ui/index.md`；規格沒寫、隨 demo 定案的做法記在規格 §2.2。
**完成條件**：使用者確認 demo；features 目錄與 index 已更新。

### T2 `exercise` 模組基礎（完成）
建立 `packages/fitness/exercise/`（`Fitness\Exercise`），註冊 `composer.json`（含 tests autoload-dev）與 `bootstrap/providers.php`，附模組 README。內容：migration（`exercise_categories`、`exercises`、`exercise_logs`、`exercise_sets`，欄位與索引依規格 §4，刪除帳號時 cascade）、Model、Repository（含 Contract）、PHP enum `RecordType`；計算服務：填時長與逐組的消耗熱量、逐組估算時間、訓練量、項目「約消耗」估算（Q10），體重取「紀錄日當天或之前最新一筆」（透過 profile 的 Repository 介面）；預設資料（規格 §5 的 2 個分類、7 個項目）。profile 新增 `ProfileCompleted` 事件（首次設定完成時發出），exercise 以 listener 建立預設資料；既有已完成設定的使用者由 migration 補建一次。設定檔 `fitness/exercise.php`（逐組預設每下秒數、組間休息、MET、最多組數、喝水每小時 ml）。
**完成條件**：`composer dump-autoload`、migrate 成功；pint 通過；本地開發帳號有預設資料；以 tinker 驗證計算結果與規格範例一致。
**結果（2026-11-21）**：migrate 與 rollback 重跑皆成功，開發帳號由補建 migration 建立 2 個分類、7 個項目，重跑 `createFor()` 回傳 false 不重複建立；以 tinker 走首次設定確認 listener 建立預設資料、刪除帳號 cascade 清掉紀錄與逐組。計算（70 kg）：室外跑步 30 分 MET 9.0 → 315 kcal；槓鈴臥推 3 組 × 10 下 × 40 kg → 4.5 分、26.3 kcal、訓練量 1,200 kg；12×40、10×50、8×55、10 下徒手 → 6.5 分、37.9 kcal、1,420 kg；喝水 45 分 → 380 ml。pint 通過，PHPUnit 159 個全過。實作補充：profile 的 `WeightLogRepository` 新增 `effectiveOnDate()`（當天或之前最新一筆，之前沒有時取最早一筆，與 demo 一致）；`ProfileCompleted` 在首次設定交易成功後發出；`exercise_logs` 的 Repository 先提供查詢與新增，其餘方法隨 T4 加入。

### T3 分類與項目管理（完成）
路由與 controller（`/exercises`）、FormRequest、語系。分類：新增（名稱＋記錄方式，同一使用者不重複）、改名、上下移、刪除（還有未封存項目時擋下；只剩封存項目時刪除並把它們的 `category_id` 設為 null）；建立後不能改記錄方式。項目：新增、編輯（只能移到同記錄方式的分類）、刪除（有紀錄改封存、沒紀錄直接刪除）、還原（原分類不存在時放回同記錄方式的第一個分類）；未封存的項目名稱不重複。前端：`pages/exercise/Manage.vue`（分類分組、已封存收合區）、項目表單頁（即時估算）、新增分類面板、分類選單；型別放 `types/exercise.ts`。
**完成條件**：pint、build、lint、format:check 通過；390px 與桌機、淺色與深色對照 demo m5-12～m5-18。
**結果（2026-11-21）**：pint、build、lint、format:check 通過，PHPUnit 159 個全過。Chrome 擴充功能未連線，畫面尚未在瀏覽器對照 demo（留到 T4 健身頁接上入口後一併看）；改以暫時的 Feature 測試從 HTTP 走完分類新增／同名／改名不改記錄方式／排序、項目新增（逐組必填秒數、填時長存 null）／同名／他人分類、移到不同記錄方式被擋、刪除有項目的分類被擋、無紀錄刪除與有紀錄封存、封存後可建同名、還原（同名衝突、無同記錄方式分類被擋、原分類已刪放回第一個同記錄方式分類）、他人資料 404，共 101 個斷言通過後移除（正式測試在 T6）。實作補充：封存項目不能編輯；還原遇到同名未封存項目或沒有同記錄方式分類時以 flash 說明、不自動建立分類；項目表單支援 `?category=`、`?name=` 帶入（T4 搜尋不到時新增項目會用到）；管理頁返回鍵暫時指向即將推出的 `/workouts`，T4 改成健身頁。另修正 demo `app.js` 兩個未使用參數（`eslint .` 會掃到 `vibe-coding/`，T1 commit 之後 CI `quality` 應該是失敗的）。

### T4 健身紀錄
移除 `routes/web.php` 的 `/workouts` 即將推出頁（`ComingSoon.vue` 沒有其他用途就一併移除），由 exercise 接手。健身頁 `/workouts`：日期切換（沿用 `UserCalendar`，不能記未來）、當日消耗／時間／訓練量、紀錄列表、紀錄選單（編輯、再做一次、刪除）。選擇項目面板（最近做過最多 5 個、依分類分組、搜尋、搜尋不到時新增項目、管理入口；照片辨識按鈕在 T8 接上）。記錄頁 `/workouts/create?exercise={id}` 與編輯頁：填時長（時長必填與快選、距離選填）、逐組（最多 20 組、新增一組、複製上一組、帶入上次紀錄），前端即時估算；存檔時寫入快照（分類名稱、項目名稱、記錄方式、MET、秒數、體重、kcal、訓練量），編輯舊紀錄以紀錄日體重重算。＋選單「健身」開啟選擇項目面板。
**完成條件**：pint、build、lint、format:check 通過；本地手動走完新增、編輯、刪除、再做一次、補記過去日期；對照 demo m5-01～m5-11。

### T5 今日頁與喝水整合
exercise 以 listener 加入 `DailySummaryCollecting` 的 `sections.exercise`（今日消耗、時間、訓練量、紀錄簡表）；今日頁新增「今日運動」卡、熱量目標加回 100% 運動消耗並標「含運動 +N kcal」、拿掉「即將推出：運動」。water 新增收集型事件 `WaterTargetCollecting`（`addExtra(key, ml, label)`），`WaterTargetCalculator` 發出後加總；exercise 以 listener 加入「運動時數 × 500 ml」（四捨五入到 10 ml）；喝水面板與今日頁顯示「體重＋運動」拆解。water、daily-summary 都不依賴 exercise。
**完成條件**：pint、build、lint、format:check 通過；對照 demo m5-26、m5-27、m5-32；沒有運動時今日頁與喝水維持原樣。

### T6 健身紀錄測試
先把測試情境條列給使用者確認，再撰寫並記錄到 `vibe-coding/tests/`。範圍：計算服務（填時長、逐組、訓練量、體重取用）、預設資料（完成首次設定建立一次、既有使用者補建、刪光不再長回來）、分類與項目規則（名稱不重複、記錄方式不能改、移動限制、刪除／封存／還原）、紀錄（快照、編輯重算、只能存取自己的、不能記未來、組數上限）、今日頁與喝水整合。
**完成條件**：全部 PHPUnit 通過；健身紀錄可以在本地驗收。

### T7 `exercise-identifier` 核心與 Gemini driver
建立 `packages/fitness/exercise-identifier/`（核心：driver 合約與 abstract 基底、`ExerciseIdentifierManager`、`exercise_identifier()`、候選 DTO、每種失敗一個 Exception、`exercise_identifications` 表與 Repository、辨識服務：每日上限以使用者時區切日、失敗也算一次、寫 `llm_logs`（`feature = exercise_identify`）；`FakeExerciseIdentifier` 放模組 tests）與 `packages/fitness/exercise-identifier-gemini/`（Prism，圖片 inline data，JSON schema 結構化輸出最多 3 個候選，提示詞帶入使用者現有分類與項目名稱，錯誤轉換；只綁定 `fitness.exercise_identifier.gemini`；附 README）。設定 `EXERCISE_IDENTIFIER_DRIVER`／`EXERCISE_IDENTIFIER_DAILY_LIMIT`（預設 10）／`EXERCISE_IDENTIFIER_TIMEOUT`，沿用 `GEMINI_*` 金鑰，寫入 `.env.example`。
**完成條件**：migrate、pint 通過；tinker 以 Fake driver 驗證成功、失敗、超過上限；本地以真實 API 辨識幾張器材／動作照片，結果記在本檔。**需要使用者**：提供或同意使用測試照片。

### T8 照片辨識流程
exercise 模組：辨識頁 `GET /exercises/identify?mode=camera|upload`、`POST /exercises/identify`（驗證圖片、只在記憶體讀取不呼叫 `store()`）、候選頁（伺服器讀回該筆辨識，只能讀自己的、尚未建立項目的）、帶入新增項目表單（分類預設同名同記錄方式的現有分類，沒有則新建，可改選同記錄方式的分類）、存檔後建立項目（必要時一併建立分類）並導向記錄頁；「已有這個項目」直接進記錄頁並記下 `exercise_id`；同一筆辨識只能建立一次項目；失敗以 flash `error` 帶 `code` 與 `remaining`。前端：選擇項目面板的拍照辨識／上傳照片辨識、辨識頁（沿用 M4 壓縮與照片暫存做法）、候選頁、表單摘要卡、離開確認。
**完成條件**：pint、build、lint、format:check 通過；以 Fake driver 本地走完拍照 → 候選 → 建立項目 → 記錄，`storage/` 沒有新增檔案；對照 demo m5-19～m5-25、m5-29。

### T9 照片辨識測試
先把測試情境條列給使用者確認，再撰寫並記錄到 `vibe-coding/tests/`。範圍：辨識服務（每日上限與切日、與餐點分析分開計次、成功與失敗都寫紀錄）、Gemini driver（`Http::fakeSequence` 驗證請求內容與回應解析、錯誤轉換、不連外）、exercise 流程（上傳驗證、照片不寫入 Storage、只能讀自己的辨識、已有項目、新建分類、同一筆不能建立兩次）。
**完成條件**：全部 PHPUnit 通過。

### T10 部署 `v1.5.0` 與驗收
`PRODUCTION_ENV` 加 `EXERCISE_IDENTIFIER_*`；使用者開 PR `develop` → `main` 並合併後，以 GitHub Actions 部署 `v1.5.0`；確認正式站 migration（含既有使用者補建預設資料）、健身紀錄、今日頁與喝水、照片辨識；更新 CLAUDE.md、handoff 與本檔狀態。
**完成條件**：正式站驗收通過，GitHub Release `v1.5.0` 建立。**需要使用者**：更新 `PRODUCTION_ENV`、開 PR 合併、觸發部署、實機驗收。
