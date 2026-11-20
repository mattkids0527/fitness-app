# M4 Gemini 照片分析

- 狀態：進行中（2026-11-21 T4 完成；下一步 T5。交接見 [handoff](../handoffs/2026-11-21-M4-photo-analysis.md)）
- 建立日期：2026-11-21
- 分支：`develop`（每個 Task 完成就 commit 並 push；PR `develop` → `main` 由使用者開與合併）
- 依據規格：[M4 照片分析規格](../specs/2026-11-21-m4-photo-analysis.md)
- 設計定稿：[ui/features/m4-photo-analysis](../ui/features/m4-photo-analysis/README.md)
- 寫作規範：[模組化寫作規範](../coding-style.md)
- 版號：完成後以 `v1.4.0` 部署

## 目標

使用者拍照或上傳一張餐點照片，同步交給 Gemini 分析，結果帶入新增餐點表單，確認後存成餐點（`source = ai`）。照片不存在伺服器；每人每天最多分析 20 次。

## 範圍

**包含**
- `food-analyzer` 核心模組：合約、Manager、helper、結果 DTO、Exception、`food_analyses` 表、每日上限
- `food-analyzer-gemini` driver：Prism、提示詞與 JSON schema、錯誤轉換；模型實測
- `meal` 模組擴充：分析路由、確認後建立餐點、`meals.food_analysis_id`、`meal_items.confidence`
- 前端：＋選單兩格、分析頁、確認頁（新增餐點表單加摘要與信心度）、飲食頁「照片」標記
- 部署：`PRODUCTION_ENV` 加 Gemini 設定，`v1.4.0` 上線

**不包含**
- 保存照片或縮圖、背景分析、queue worker
- 一餐多張照片、替手動餐點補照片、條碼、營養標示 OCR
- 改用付費層（只要在 Google 端開帳單，不需要改程式）

## Tasks

### T1 設計 demo 畫面（完成）
＋選單、分析頁（拍照／上傳）、分析中、失敗與次數用完、確認分析結果、離開確認、飲食頁照片標記、桌機版；手機與桌機、淺色與深色。已移到 `vibe-coding/ui/features/m4-photo-analysis/` 並更新 `vibe-coding/ui/index.md`。
**完成條件**：使用者確認 demo；features 目錄與 index 已更新。

### T2 `food-analyzer` 核心模組（完成）
建立 `packages/fitness/food-analyzer/`（`Fitness\FoodAnalyzer`），註冊 `composer.json`（含 tests autoload-dev）與 `bootstrap/providers.php`，附模組 README。內容：`FoodImageAnalyzer` 合約與 abstract 基底、`FoodAnalyzerManager`（singleton）、`food_analyzer()` helper；結果 DTO（項目清單：名稱、份量、重量、熱量、四種營養素、信心度）；每種失敗一個 Exception（看不出食物、次數限制、逾時、其他）；`food_analyses` migration、Model、Repository（含 Contract）；分析服務：檢查每日上限（使用者時區切日）→ 呼叫 driver → 寫一筆紀錄（成功或失敗都寫，不含照片）；設定檔 `fitness/food-analyzer.php`（driver、每日上限、逾時）；`FakeFoodAnalyzer` 放在模組 tests。
**完成條件**：`composer dump-autoload`、migrate 成功；pint 通過；用 tinker 以 Fake driver 跑一次分析，`food_analyses` 有紀錄。
**實作補充**：`food_analyses` 多一欄 `result`（jsonb，正規化後的項目），T4 確認頁從這裡帶入；使用者要求另建 `llm-log` 模組與 `llm_logs` 表，記錄每次呼叫的 LLM 種類（例如 `gemini`）、模型、完整 prompt（`content`）、回應、token、耗時，`food_analyses` 以 `llm_log_id` 關聯，driver 合約加 `prompt()`；每日上限 env `FOOD_ANALYZER_DAILY_LIMIT`（預設 20）已寫入 `.env.example`；每日上限以 profile 的 `UserCalendar` 切日；另有 `DriverNotAvailableException`（driver 沒綁定或缺金鑰，不算次數）；例外的 `toFlash()` 回傳 `{code, title, message}`。tinker 驗證成功、429、非預期例外、超過上限四種情況。

### T3 `food-analyzer-gemini` driver 與模型實測（完成）
建立 `packages/fitness/food-analyzer-gemini/`，安裝 `prism-php/prism`，只綁定 `fitness.food_analyzer.gemini`。圖片以 inline data 送出（不用 Files API），JSON schema 結構化輸出，提示詞要求繁體中文、整份數值、看不出食物時回報；把 429、逾時、schema 不符等轉成 T2 的 Exception；記錄 model、token 用量、耗時。附 README 說明 API 金鑰申請步驟。接著用 5～10 張常見餐點照片比較候選模型（準確度、耗時、token），結果記在本檔，決定 `GEMINI_MODEL`。
**完成條件**：本地以真實 API 分析成功；實測結果與選定模型寫入本檔。**需要使用者**：到 Google AI Studio 申請 API 金鑰填進本地 `.env`，並提供或同意使用測試照片。
**實作補充**：Prism `^0.103`；Gemini 3 以後的思考程度以 `GEMINI_THINKING_LEVEL`（預設 `low`）設定，不設時 `gemini-3.8-flash` 超過 25 秒；503 過載也視為「Gemini 暫時忙碌」；失敗但沒有回應時，錯誤訊息存進 `llm_logs.response.error_message`。結果見下方「模型實測」。

### T4 `meal` 模組後端（完成）
migration：`meals.food_analysis_id`（可 null，null on delete）、`meal_items.confidence`（可 null）。路由：`POST /meals/analyze`（照片＋補充說明，驗證 jpeg／png／webp、5MB 以內，只在記憶體讀取不呼叫 `store()`）；成功後導向 `GET /meals/create?analysis={id}`，由伺服器讀回該筆分析（只能讀自己的、尚未建立餐點的）帶入表單 defaults，重新整理也不會遺失結果；失敗以 `back()->with('error', ...)` 回傳失敗種類與今日剩餘次數。`store` 接受 `food_analysis_id` 與項目 `confidence`，設定 `source = ai`；同一筆分析只能建立一次餐點。飲食頁與今日頁的餐點資料帶 `source`。分析頁與確認頁的 props 帶今日剩餘次數。
**完成條件**：pint 通過；以 Fake driver 從本地手動走完上傳 → 確認 → 存檔，資料正確，`storage/` 沒有新增檔案。
**實作補充**：分析頁 `GET /meals/analyze?mode=camera|upload`（`meal/Analyze`，頁面檔在 T5 建立）。拍攝時間由前端隨分析送出 `taken_at`，再帶到確認頁 `create?analysis={id}&taken_at=`，伺服器據此決定日期、時間與餐別（未來時間或格式不對改用現在）；確認頁 props 多 `analysis`（項目數、補充說明、`time_source`、`quota`）。同一筆分析重複存檔或重新打開確認頁時，導向已建立那一餐的編輯頁並帶 flash `error`。信心度只存在 `source = ai` 的餐點，複製整餐不帶信心度。驗證方式：在 scratchpad 寫一支不進 repo 的 HTTP 流程測試（Fake driver，92 個斷言）走完上傳 → 確認 → 存檔 → 重複存檔 → 失敗與上限 → 他人分析 404，`storage/` 前後比對沒有新增檔案；正式測試在 T6 依確認的情境撰寫。

### T5 前端實作
＋選單：「拍照分析」拿掉，改成「拍照」（`capture="environment"`，桌機隱藏）與「上傳照片」兩格。分析頁 `pages/meal/Analyze.vue`：`browser-image-compression` 壓縮、`exifr` 讀拍攝時間（上傳）、預覽、重拍／換一張、補充說明（200 字）、剩餘次數、分析中（掃描動畫、取消）、失敗與次數用完的畫面；照片暫存在 composable（瀏覽器記憶體），跨頁到確認頁顯示縮圖。確認頁沿用 `pages/meal/Form.vue`：分析摘要卡、信心度標籤（`components/meal/ConfidenceBadge.vue`）、低信心提醒、時間依拍攝時間帶入的提示、離開時詢問放棄。飲食頁 `MealCard` 標「照片」。照 demo 實作，型別放 `types/meal.ts`。
**完成條件**：build、lint、format:check 通過；390px 與桌機、淺色與深色對照 demo 確認；iPhone／Android 實機另在 T7 驗收。

### T6 測試
先把測試情境條列給使用者確認，再撰寫並記錄到 `vibe-coding/tests/`。範圍：`food-analyzer`（每日上限與切日、成功與各種失敗都寫紀錄、超過上限不呼叫 driver）、`food-analyzer-gemini`（以 Prism fake 驗證請求內容與回應解析、錯誤轉換）、`meal`（上傳驗證、照片沒有寫入 Storage、導向確認頁、只能讀自己的分析、建立餐點帶 `source`／`confidence`／`food_analysis_id`、同一筆分析不能建立兩次）。
**完成條件**：全部 PHPUnit 通過。

### T7 部署 `v1.4.0` 與驗收
更新 `CLAUDE.md`（專案概述、模組、`.env` 項目）與 `deploy/README.md`（Gemini 設定）。使用者在 GitHub Secret `PRODUCTION_ENV` 加入 `GEMINI_API_KEY`、`GEMINI_MODEL` 等，合併 PR 後以 `v1.4.0` 部署。正式站驗收：iPhone 拍照與上傳（含拍攝時間帶入）、Android、桌機上傳、失敗重試、次數顯示。寫 handoff，milestone 標為完成。
**完成條件**：正式站驗收通過。

## 模型實測（T3，2026-11-21）

- 照片：Wikimedia Commons 的公開照片 7 張（雞腿便當、牛肉麵、滷肉飯套餐、蛋炒飯、打拋豬飯＋荷包蛋、珍珠奶茶、風景），以 PHP GD 縮成長邊 1600px、品質 82 的 JPEG（約 210～550KB），模擬前端壓縮。使用者的真實照片尚未測。
- 條件：同一份提示詞與 schema、`thinkingLevel = low`、temperature 0.2；本地 WSL 連線，免費層金鑰。
- 金鑰可用模型中，`gemini-2.5-flash` 回 404（新使用者不再提供）；`gemini-3.8-flash` 連續 4 次 503 過載；`gemini-flash-latest` 90 秒沒有回應；`minimal` 思考程度在 3.8 不支援。

| 模型 | 成功 | 平均耗時 | 最久 | 平均 token（入／出） | 觀察 |
|---|---|---|---|---|---|
| `gemini-3.1-flash-lite` | 7/7 | 3.9 秒 | 5.8 秒 | 1319／391 | 項目拆得最細（牛肉麵拆成牛肉、麵、湯、青菜；便當多列肉燥），符合提示詞；滷肉套餐的滷肉誤認成「滷雞腿肉」 |
| `gemini-3.5-flash-lite` | 7/7 | 3.5 秒 | 5.5 秒 | 1319／232 | 最快、token 最少；牛肉麵、炒飯只列一個項目，不方便改份量 |
| `gemini-3.5-flash` | 7/7 | 7.5 秒 | 20.6 秒 | 1319／192 | 命名最準（滷爌肉、白飯含滷汁）；但兩次超過 13 秒，離 25 秒逾時太近 |
| `gemini-3.8-flash` | 0/4 | — | — | — | 503 過載，暫時不能用 |

- 三個可用模型都正確判斷風景照「看不出食物」。
- 補充說明有效：便當加「飯只吃一半」後白飯回 0.5 碗、140 大卡。
- 信心度普遍偏高（主菜多為 0.9～0.95），「低」很少出現；上線後觀察，必要時調整提示詞。
- **決定**：`GEMINI_MODEL=gemini-3.1-flash-lite`（速度穩定、項目拆得細方便修改）。之後 `gemini-3.8-flash` 不再過載、或 `gemini-3.5-flash` 速度穩定時可以再比較，只要改 env。

## 進度

| Task | 狀態 |
|---|---|
| T1 設計 demo | 完成（2026-11-21） |
| T2 food-analyzer 核心 | 完成（2026-11-21） |
| T3 Gemini driver 與模型實測 | 完成（2026-11-21） |
| T4 meal 模組後端 | 完成（2026-11-21） |
| T5 前端實作 | 未開始 |
| T6 測試 | 未開始 |
| T7 部署 v1.4.0 與驗收 | 未開始 |
