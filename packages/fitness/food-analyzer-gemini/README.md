# food-analyzer-gemini 模組

`food-analyzer` 的 Gemini driver：以 Prism（`prism-php/prism`）呼叫 Gemini 分析餐點照片。只做一件事：實作 `FoodImageAnalyzer` 並綁定到容器 `fitness.food_analyzer.gemini`。

- 命名空間：`Fitness\FoodAnalyzerGemini`
- Service Provider：`Fitness\FoodAnalyzerGemini\FoodAnalyzerGeminiServiceProvider`
- 設定：`config('fitness.food_analyzer_gemini.*')`（`src/config/fitness/food-analyzer-gemini.php`）

| 設定 | env | 預設 | 說明 |
|---|---|---|---|
| `api_key` | `GEMINI_API_KEY` | — | 沒填時 driver 視為設定不齊全，分析回「照片分析暫時無法使用」 |
| `model` | `GEMINI_MODEL` | `gemini-3.1-flash-lite` | 依 M4 模型實測決定 |
| `thinking_level` | `GEMINI_THINKING_LEVEL` | `low` | Gemini 3 以後的 thinkingLevel；空值用模型預設（較慢）。部分模型不支援 `minimal` |
| `temperature` | — | 0.2 | 營養估算要穩定 |
| `connect_timeout` | — | 5 秒 | 整體逾時用 `FOOD_ANALYZER_TIMEOUT`（25 秒） |

## 依賴

- `food-analyzer`：合約、DTO 與例外。
- `prism-php/prism`：Gemini API 呼叫與結構化輸出。

## 規則

- **圖片以 inline data 送出**（`Image::fromBase64()`），不用 Files API，避免照片在 Google 端保存 48 小時。
- **結構化輸出**：JSON schema 為 `{is_food: bool, items: [{name, portion, grams, kcal, protein_g, carbs_g, fat_g, fiber_g, confidence}]}`，欄位與 `meal_items` 相同。
- **提示詞**（`Gemini::prompt()`）：繁體中文與台灣用語、數值以整份計、信心度 0～1、看不出食物時 `is_food = false`；使用者的補充說明接在最後，並註明只用來修正辨識與份量。`prompt()` 回傳的文字就是送出的內容，由 `food-analyzer` 寫入 `llm_logs.content`。
- **錯誤轉換**：

| 情況 | 轉成 |
|---|---|
| 429（次數限制）、503（Gemini 過載） | `ProviderRateLimitedException`（「Gemini 暫時忙碌」） |
| 連線逾時（cURL error 28） | `AnalysisTimeoutException` |
| 其他 HTTP 錯誤（例如 404 模型不存在）、連線失敗、回應不符 schema | `AnalysisFailedException` |
| `is_food = false` 或沒有項目 | `NoFoodDetectedException` |

拿到回應後才失敗時會附上原始回應與 token 用量（`withResponse()`）。token：input 為 `promptTokenCount`，output 含思考 token。

## 申請 Gemini API 金鑰

1. 用 Google 帳號登入 [Google AI Studio](https://aistudio.google.com/)。
2. 左側選單 **Get API key** → **Create API key**，選擇（或建立）一個 Google Cloud 專案。
3. 複製金鑰，填到 `.env` 的 `GEMINI_API_KEY`。正式環境改 GitHub Environment `production` 的 Secret `PRODUCTION_ENV` 後重新部署，不要直接改 VM 的 `.env`。
4. 免費層有每分鐘／每日次數限制，且 Google 可能用上傳內容改進產品；要改付費層只要在 AI Studio 對該專案開啟帳單，程式不用改。
5. 金鑰可用的模型可以這樣查（金鑰放 header，不要放網址）：

```bash
curl -s -H "x-goog-api-key: $GEMINI_API_KEY" "https://generativelanguage.googleapis.com/v1beta/models?pageSize=200"
```

## 對外提供

- 容器綁定 `fitness.food_analyzer.gemini` → `Drivers\Gemini`
