# 交接：M4 Gemini 照片分析

- 更新日期：2026-11-21
- 對應任務：[M4 Gemini 照片分析](../milestones/M4-photo-analysis.md)
- 依據規格：[M4 規格](../specs/2026-11-21-m4-photo-analysis.md)
- 設計定稿：[ui/features/m4-photo-analysis](../ui/features/m4-photo-analysis/README.md)
- 分支：`develop`
- 目前進度：T1 demo、T2 `food-analyzer` 核心、T3 Gemini driver 與模型實測、T4 meal 模組後端完成。下一步是 T5（前端實作）。

## 下次接手從這裡開始

1. `git checkout develop && git pull --ff-only`。
2. 先讀規格 §2（已決 Q1～Q10）與 §3～§6，再讀 milestone 的 T5，前端照 `vibe-coding/ui/features/m4-photo-analysis/` 的 demo 實作，後端 props 見 `packages/fitness/meal/README.md`「頁面 props」。寫程式前依 CLAUDE.md 讀 `vibe-coding/coding-style.md`；driver 寫法參考 `social-login-google`，核心合約見 `packages/fitness/food-analyzer/README.md`。
3. 每個 Task 完成就 commit 並 push，再詢問是否進行下一個 Task。
4. 本地 `.env` 已有 `GEMINI_API_KEY`（使用者提供）、`GEMINI_MODEL=gemini-3.1-flash-lite`、`GEMINI_THINKING_LEVEL=low`；真的打 API 會用掉 Gemini 免費額度與當天 20 次上限，T4 手動測試請用 `FOOD_ANALYZER_DRIVER=fake`（需在 `.env` 暫時改，並在 tinker／測試綁定 `FakeFoodAnalyzer`）或少量呼叫。
5. `develop` 上還有一個沒部署的小改動：未綁定 Google 登入的錯誤說明加上「還沒有帳號的話，請聯絡管理員。」（見 [D2 handoff](2026-11-21-D2-release-versioning.md)）。如果使用者在 M4 完成前想先上線，以 `v1.3.4` 部署；否則跟著 M4 一起以 `v1.4.0` 上線。

## 重要決定（跟總覽規格不同的地方）

- **照片不存伺服器**：只在分析的那次請求裡轉送給 Gemini，不呼叫 `store()`、不產生縮圖。照片在使用者確認前留在瀏覽器記憶體（composable），失敗時可以直接重送。
- **同步分析**：不用 queue，正式環境維持 `QUEUE_CONNECTION=sync`，VM 不加 worker，`provision.sh` 不用改。Gemini 呼叫 25 秒逾時（PHP 預設 30 秒、nginx 60 秒以內）。
- **Gemini 先用免費層**：已告知使用者，Google 可能用上傳內容改進產品，也有次數限制（429 要顯示「Gemini 暫時忙碌」）。圖片以 inline data 送出，**不用 Files API**（避免照片在 Google 端保存 48 小時）。
- **兩種入口**：「拍照」（`capture="environment"`，桌機隱藏，用餐時間＝現在）與「上傳照片」（用 `exifr` 讀拍攝時間當用餐時間）。＋選單原本的「拍照分析」拿掉，改成兩格直接進入。
- **確認前不建立餐點**：`meals.status` 不加新狀態。分析成功導向 `GET /meals/create?analysis={id}`，由伺服器讀回 `food_analyses` 的結果帶入表單；同一筆分析只能建立一次餐點。
- **每日 20 次**：每送出一次給 Gemini 就算一次（含失敗、重新分析）；超過上限不呼叫 Gemini。
- **demo 定案細節**：看不出食物時主按鈕為「重拍／換一張」；信心度三格訊號（高 ≥ 0.75、中 ≥ 0.5、低 < 0.5），「低」用橘色並在展開時提醒；飲食頁由照片建立的餐點標「照片」。

## T2 完成內容（2026-11-21）

- `packages/fitness/food-analyzer/`：`FoodImageAnalyzer` 合約與基底、`FoodAnalyzerManager`、`food_analyzer()`、`FoodImage`／`FoodItem`／`FoodAnalysisResult`、6 個 Exception、`food_analyses`、`FoodAnalysisService`（`analyze()`、`remainingToday()`）、`FakeFoodAnalyzer` 與 `FakesFoodAnalyzer` trait。細節見模組 README。
- 使用者追加：新增 `packages/fitness/llm-log/`（`llm_logs` 表），每次呼叫 LLM 記錄使用者、LLM 種類（provider，例如 `gemini`）、模型、用途（`feature`）、完整 prompt（`content`）、回應、token、耗時；`food_analyses` 只留分析結果並以 `llm_log_id` 關聯，兩筆在同一個交易寫入。
- 跟規格不同（已同步規格）：`food_analyses` 多 `result` 欄（正規化項目，T4 從這裡帶入）；依賴 profile 的 `UserCalendar`。
- 每日上限：`config('fitness.food_analyzer.daily_limit')` ← env `FOOD_ANALYZER_DAILY_LIMIT`（預設 20），`.env.example` 已加 `FOOD_ANALYZER_*`。T7 記得把這些與 `GEMINI_*` 加進 `PRODUCTION_ENV`，並在 CLAUDE.md 補上 `llm-log` 模組。
- driver 要做的事：實作 `prompt()`（回傳送出的完整文字 prompt，`analyze()` 要送同一段）與 `analyze()`，把 429／逾時／schema 不符轉成 `ProviderRateLimitedException`／`AnalysisTimeoutException`／`AnalysisFailedException`，看不出食物丟 `NoFoodDetectedException`（或回傳 0 個項目），已拿到回應時以 `withResponse()` 附上 token 用量。
- 測試（T6）尚未寫；本地 tinker 驗證過。

## T3 完成內容（2026-11-21）

- `packages/fitness/food-analyzer-gemini/`：`Drivers\Gemini`（Prism structured、inline 圖片、JSON schema、提示詞、錯誤轉換），綁定 `fitness.food_analyzer.gemini`；README 有金鑰申請步驟。安裝 `prism-php/prism` `^0.103`。
- 模型實測結果與選定模型 `gemini-3.1-flash-lite` 記在 milestone「模型實測」。用的是 Wikimedia 公開照片，使用者的真實照片還沒測，T7 驗收時順便看。
- 本地開發庫留有 3 筆真實呼叫紀錄（`llm_logs` 3～5），可以看 `content` 的 prompt 長相。
- Driver 的自動測試（Prism fake）在 T6 寫。

## T4 完成內容（2026-11-21）

- 路由：`GET/POST /meals/analyze`（`MealAnalysisController`），確認頁沿用 `GET /meals/create?analysis={id}&taken_at=`。`meal/Analyze.vue` 還不存在，T5 建立前打開分析頁會出錯。
- migration `2026_11_21_100001`：`meals.food_analysis_id`（唯一、null on delete）、`meal_items.confidence`。
- 失敗 flash `error` 為 `{code, title, message, remaining}`；前端 `FlashError` 型別在 T5 補 `code`、`remaining`。`code` 為 `daily_limit`、`unavailable`、`no_food`、`rate_limited`、`timeout`、`failed`，`no_food` 時主按鈕改「重拍／換一張」。
- 飲食頁、今日頁的餐點摘要多 `source`（`manual`／`ai`），T5 用來標「照片」。

## 這次 session 的其他變更

- D2 收尾：milestone 標完成、寫 handoff；文件更正第一個 tag 是 `v1.3.1`（不是 `v1.3.0`）。
- 使用者確認：超過熱量目標變紅的畫面 OK、03:00 自動備份正常、iOS PWA 下 Google 登入與解除綁定正常。

## 尚未處理（之後再排）

- Google OAuth 同意畫面仍是「測試中」。
- D1 列的部署改善：`rollback.yml`、SSH 走 Tunnel、部署金鑰分開、異地備份。
