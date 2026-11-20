# llm-log 模組

LLM API 的呼叫紀錄：每呼叫一次寫一筆，記錄哪個使用者、用哪個 LLM（例如 `gemini`）、哪個功能、送出的完整 prompt、回應、token 用量與耗時，用來除錯、調整提示詞與估算成本。沒有路由與頁面；由呼叫 LLM 的模組（目前是 `food-analyzer`）寫入。

- 命名空間：`Fitness\LlmLog`
- Service Provider：`Fitness\LlmLog\LlmLogServiceProvider`
- 設定：`config('fitness.llm_log.*')`（`src/config/fitness/llm-log.php`）：只有 `binds`

## 規則

- **不存圖片**：`content` 只存送出的文字 prompt（系統提示詞＋使用者的補充說明），照片不寫入任何地方。
- 成功與失敗都寫；失敗時 `error` 為呼叫端的失敗種類（例如 `rate_limited`、`timeout`）。
- 本模組不知道誰會呼叫它；呼叫端以 `feature` 標示用途。

## 資料表

- `llm_logs`：user_id（cascade）、provider（LLM 種類，例如 `gemini`）、model（模型 ID）、feature（用途，例如 `food_analysis`）、content（text，送出的完整 prompt）、response（jsonb，LLM 回傳的結果）、status（`succeeded`／`failed`）、error、input_tokens、output_tokens、duration_ms、timestampsTz；索引 `(user_id, created_at)`、`(provider, created_at)`

## 對外提供

### Repository 介面

- `Repositories\Contracts\LlmLogRepository`：`create(userId, attributes)`

### Events / Listeners

（尚無）
