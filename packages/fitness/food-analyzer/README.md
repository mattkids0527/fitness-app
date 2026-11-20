# food-analyzer 模組

食物照片分析的核心：driver 合約與 Manager、結果 DTO、每種失敗一個 Exception、`food_analyses` 紀錄、每日上限。不依賴任何外部服務套件；實際的分析（Gemini 等）由 driver 模組提供，例如 `food-analyzer-gemini`。沒有路由與頁面，由 `meal` 模組呼叫。

- 命名空間：`Fitness\FoodAnalyzer`
- Service Provider：`Fitness\FoodAnalyzer\FoodAnalyzerServiceProvider`
- 設定：`config('fitness.food_analyzer.*')`（`src/config/fitness/food-analyzer.php`，env 範例見 `.env.example`）：`driver`（env `FOOD_ANALYZER_DRIVER`，預設 `gemini`）、`daily_limit`（env `FOOD_ANALYZER_DAILY_LIMIT`，預設 20）、`timeout`（env `FOOD_ANALYZER_TIMEOUT`，秒，預設 25）、`hint_max_length`（200）
- 語系：`trans('FitnessFoodAnalyzer::errors.<失敗種類>.title|message')`
- Helper：`food_analyzer()` 取得 `FoodAnalyzerManager`

## 依賴

- `profile`：`UserCalendar`（以使用者時區切日計算每日上限）。
- `llm-log`：`LlmLogRepository`（每次呼叫 LLM 寫一筆，`feature = food_analysis`）。

## 規則

- **照片不存**：照片以 `FoodImage`（內容＋MIME）在記憶體中交給 driver，本模組不寫入任何磁碟或資料庫。`FoodImage::fromUploadedFile()` 只讀 PHP 的上傳暫存檔，不呼叫 `store()`。
- **每日上限**：每送出一次給 driver 就算一次（含失敗與重新分析），以 `food_analyses` 當天（使用者時區）的筆數計算。超過上限丟 `DailyLimitExceededException`，不呼叫 driver、不寫紀錄。
- **紀錄**：呼叫 driver 後一定在同一個交易裡寫兩筆：`llm_logs`（LLM 種類、模型、driver `prompt()` 回傳的完整 prompt、回應、token、耗時）與 `food_analyses`（以 `llm_log_id` 關聯）。成功 `succeeded`、失敗 `failed`＋失敗種類；driver 回傳 0 個項目視為看不出食物。driver 丟出本模組以外的例外時，記錄後包成 `AnalysisFailedException`（原例外寫進 log）。
- 並發的兩次請求可能都通過上限檢查，最多多出幾次；每日上限只為控制成本，不另外加鎖。

## 資料表

- `food_analyses`：user_id（cascade）、llm_log_id（FK `llm_logs`，null on delete）、status（`succeeded`／`failed`）、error（失敗種類）、hint（補充說明，200 字）、result（jsonb，正規化後的項目，供確認頁帶入）、timestampsTz；索引 `(user_id, created_at)`
- provider、model、prompt、原始回應、token、耗時記在 `llm-log` 模組的 `llm_logs`。

## 對外提供

### Driver 合約（driver 模組實作）

- `Drivers\Contracts\FoodImageAnalyzer`：`analyze(FoodImage, ?hint)`（回傳 `FoodAnalysisResult`，失敗只丟本模組例外）、`prompt(?hint)`（送出的完整文字 prompt，寫入 `llm_logs.content`）、`model()`、`label()`、`isConfigured()`
- `Drivers\FoodImageAnalyzer`：abstract 基底，`isConfigured()` 預設 true、`timeout()` 讀設定
- `Drivers\FoodImage`：照片內容與 MIME，`fromUploadedFile()`、`base64()`
- `Drivers\FoodItem`：name、portion、grams、kcal、proteinG、carbsG、fatG、fiberG、confidence（0～1）；`fromArray()`／`toArray()` 用 snake_case，欄位與 `meal_items` 相同
- `Drivers\FoodAnalysisResult`：items、rawResponse、inputTokens、outputTokens（後三者寫入 `llm_logs`）
- driver 模組在自己的 Provider 綁定 `fitness.food_analyzer.<driver>`

### Services

- `FoodAnalyzerManager`：`get(?driver)`（沒有綁定或設定不齊全丟 `DriverNotAvailableException`）、`defaultDriver()`
- `FoodAnalysisService`：`analyze(User, FoodImage, ?hint)`（回傳成功的 `FoodAnalysis`）、`remainingToday(User)`、`dailyLimit()`

### Repository 介面

- `Repositories\Contracts\FoodAnalysisRepository`：`countBetween()`、`create()`

### Exceptions

都繼承 `FoodAnalyzerException`：`errorCode()` 為失敗種類，`toFlash()` 回傳 `{code, title, message}` 給前端；driver 已拿到回應才失敗時以 `withResponse()` 附上原始回應與 token 用量。

| 類別 | errorCode | 寫紀錄（算一次） |
|---|---|---|
| `DailyLimitExceededException` | `daily_limit` | 否 |
| `DriverNotAvailableException` | `unavailable` | 否 |
| `NoFoodDetectedException` | `no_food` | 是 |
| `ProviderRateLimitedException` | `rate_limited` | 是 |
| `AnalysisTimeoutException` | `timeout` | 是 |
| `AnalysisFailedException` | `failed` | 是 |

### 測試

- `Tests\Fakes\FakeFoodAnalyzer`：不連外的 driver，`nextResult`／`nextException` 設定下一次的結果，`calls` 記錄收到的照片與補充說明。
- `Tests\Concerns\FakesFoodAnalyzer`：`fakeFoodAnalyzer()` 綁定假 driver 為 `fitness.food_analyzer.fake` 並設為預設，`foodImage()` 產生假照片。

### Events / Listeners

（尚無）
