# M4 Gemini 照片分析規格

> 狀態：已定稿（2026-11-21）。demo 已確認，見 [ui/features/m4-photo-analysis](../ui/features/m4-photo-analysis/README.md)；下一步寫 milestone。
> 依據 [專案總覽規格 v1.0](2026-11-19-project-overview.md) §2、§2.1、§2.2、§3.3、§4，以及 [M3 規格](2026-11-20-m3-meal-water.md) Q1（`meals.source`／`status` 預留）、[D1 規格](2026-11-20-d1-gcp-deployment.md) §3.6 與 Queue 說明。
> 程式寫法依 [模組化寫作規範](../coding-style.md)。
> 「待決」標記的項目需要使用者拍板；「已決」記錄討論結果。

## 1. 目標

使用者拍照或上傳一張餐點照片，交給 Gemini 分析，取得食物項目（名稱、份量、重量、熱量、蛋白質、碳水、脂肪、纖維、信心度）。結果帶入「新增餐點」表單，使用者檢查、修改後確認存檔，之後跟手動記錄的餐點一樣計入統計。

**照片不存在伺服器**：只在分析的那一次請求裡轉送給 Gemini，請求結束就消失。

不做：一餐多張照片、餐點保存照片或縮圖、背景分析、條碼掃描、營養標示 OCR、分析結果自動存檔（一定要使用者確認）。

## 2. 已決（2026-11-21）

| # | 問題 | 決定 |
|---|---|---|
| Q1 | 照片要不要存 | **不存**。伺服器不把照片寫入任何磁碟或資料庫；只有 nginx／PHP 處理上傳時的暫存檔，請求結束自動刪除。照片在使用者確認存檔前留在瀏覽器記憶體，失敗時可以直接重送 |
| Q2 | 背景處理還是同步 | **同步**。上傳的請求直接等 Gemini 回應（約 5～20 秒），畫面顯示分析中。不用 queue，正式環境維持 `QUEUE_CONNECTION=sync`，VM 不加 worker（推翻總覽規格 §2「照片分析丟背景 job、前端輪詢」） |
| Q3 | 入口 | **兩種**：「拍照」（直接開後鏡頭）、「上傳照片」（相簿或檔案）。桌機只顯示「上傳照片」。兩者走同一個分析流程 |
| Q4 | 用餐時間與餐別預設 | 拍照：現在時間。上傳：照片的拍攝時間（瀏覽器讀 EXIF `DateTimeOriginal`），讀不到用現在時間。餐別依時間套用 M3 Q2 的規則。確認頁都可以改 |
| Q5 | Gemini 免費層或付費層 | **先用免費層**。已知取捨：Google 可能用上傳內容改進產品；有每分鐘／每日次數限制，被限制時顯示專屬錯誤。之後改付費層只要在 Google 端開帳單，程式不用改 |
| Q6 | 送出前填什麼 | 照片＋選填的「補充說明」（例如「飯只吃一半」），補充說明一起送給 Gemini。餐別、時間在確認頁調整 |
| Q7 | 信心度 | 每個項目顯示 高／中／低，「低」的項目以橘色標示提醒檢查。`meal_items.confidence` 存 0～1，手動項目為 null；使用者改過的項目仍保留原本的信心度 |
| Q8 | 每日上限 20 次怎麼算 | 每送出一次給 Gemini 就算一次（含失敗與重新分析）；超過上限不呼叫 Gemini。畫面顯示「今天還能分析 N 次」 |
| Q9 | 分析失敗 | 留在原畫面顯示原因，提供「重新分析」（用瀏覽器裡的同一張照片）與「改手動輸入」（照片丟棄，進空白的新增餐點表單） |
| Q10 | 還沒確認的餐點 | 不存在：確認前不建立餐點，`meals.status` 不新增狀態。離開確認頁就放棄這次分析（瀏覽器詢問是否離開） |

## 3. 使用流程

1. ＋選單的「拍照分析」（目前是「即將推出」）拿掉，改成「拍照」「上傳照片」兩格，點了直接進入（demo 確認，2026-11-21）。
2. 選好照片後，前端先讀拍攝時間（上傳時），再以 `browser-image-compression` 壓成長邊 1600px 的 JPEG。
3. 分析頁顯示照片預覽、補充說明欄位、「今天還能分析 N 次」，按「開始分析」。
4. 前端以 `multipart/form-data` 送出照片與補充說明；後端檢查每日上限 → 呼叫 `food_analyzer()` → 寫一筆 `food_analyses`（不含照片）→ 回傳項目。
5. 成功：進入確認表單（沿用新增餐點表單），上方顯示照片（瀏覽器端的預覽），項目已帶入並標示信心度，可以增刪修改；餐別與時間依 Q4 預設。
6. 按「確認」→ 以 `source = ai`、`status = confirmed` 建立餐點，帶上 `food_analysis_id`；之後與手動餐點相同（可編輯、刪除、複製整餐、加入常用食物）。
7. 失敗：依 Q9 處理。

### 3.1 失敗原因

| 情況 | 畫面說明 | 算不算一次 |
|---|---|---|
| 今天已達上限 | 今天的 20 次已用完，明天再試或改手動輸入 | 不算（沒有呼叫） |
| 看不出是食物 | 照片裡看不出食物，換一張或改手動輸入 | 算 |
| Gemini 次數限制（免費層 429） | Gemini 暫時忙碌，稍等一下再試 | 算 |
| 逾時 | 分析太久沒有回應，再試一次 | 算 |
| 其他錯誤 | 分析沒有完成，再試一次 | 算 |

## 4. 模組

| 模組 | 職責 | 依賴 |
|---|---|---|
| `food-analyzer`（核心） | `FoodImageAnalyzer` 合約、abstract 基底、Manager、`food_analyzer()` helper、結果 DTO、每種失敗一個 Exception、`food_analyses` 紀錄、每日上限 | — |
| `food-analyzer-gemini`（driver） | 以 Prism 呼叫 Gemini：圖片以 inline data 隨請求送出（**不用** Files API，避免檔案留在 Google 端 48 小時），JSON schema 結構化輸出；只綁定 `fitness.food_analyzer.gemini` | food-analyzer |
| `meal`（擴充） | 分析路由與頁面、確認後建立餐點（`source`、`food_analysis_id`、項目 `confidence`） | food-analyzer、profile |

- 測試以 `FakeFoodAnalyzer` 取代 Gemini，不連外，比照 `social-login` 的 `FakeSocialProvider`。
- 提示詞與 JSON schema 放在 driver 模組；回傳的名稱、份量一律繁體中文，數值以「整份」計。
- 上傳的檔案只在記憶體中讀取（`UploadedFile` 的內容），不呼叫 `store()`；測試確認 Storage 沒有寫入任何檔案。

## 5. 資料表

### 5.1 `food_analyses`（food-analyzer 模組，新表）

| 欄位 | 型別 | 說明 |
|---|---|---|
| user_id | FK users，cascade | 計算每日用量 |
| provider | varchar(32) | 例如 `gemini` |
| model | varchar(64) | 實際使用的模型 ID |
| status | varchar(16) | `succeeded`／`failed` |
| error | varchar(32)，可 null | 失敗種類（§3.1） |
| hint | varchar(200)，可 null | 使用者的補充說明 |
| raw_response | jsonb，可 null | Gemini 回傳的文字結果（不含照片） |
| input_tokens、output_tokens | integer，可 null | 估算成本 |
| duration_ms | integer | 呼叫耗時 |
| created_at | timestampTz | 每日上限以使用者時區切日計算 |

### 5.2 `meals`（新增欄位）

| 欄位 | 型別 | 說明 |
|---|---|---|
| food_analysis_id | FK food_analyses，可 null，null on delete | 由照片分析建立的餐點才有 |
| `source` | 既有欄位 | 新增值 `ai` |

### 5.3 `meal_items`（新增欄位）

| 欄位 | 型別 | 說明 |
|---|---|---|
| confidence | decimal(3,2)，可 null | 0～1（Q7） |

migration 都是新增可 null 欄位或新表，相容舊版程式（`coding-style.md` §12）。

## 6. 部署與環境

- 套件：`prism-php/prism`；npm `browser-image-compression`、`exifr`（讀拍攝時間）。
- `.env`：`GEMINI_API_KEY`、`GEMINI_MODEL`、`FOOD_ANALYZER_DRIVER=gemini`、`FOOD_ANALYZER_DAILY_LIMIT=20`、`FOOD_ANALYZER_TIMEOUT=25`（秒）。正式環境更新 GitHub Secret `PRODUCTION_ENV` 後部署。
- 上傳大小：壓縮後約 300～500KB，後端驗證上限 5MB；VM 現有 `client_max_body_size 12M`、`upload_max_filesize 10M` 已足夠。
- 逾時：Gemini 呼叫 25 秒逾時，在 PHP 預設 `max_execution_time` 30 秒與 nginx 60 秒之內；實測若不夠再一起調整 `provision.sh`。
- 不需要 queue worker、排程或新的 VM 系統設定。
- 模型實測：用 5～10 張常見餐點照片比較候選模型的準確度、耗時與 token 用量，結果記錄在 milestone，決定 `GEMINI_MODEL`。
- 版號：M4 完成後以 `v1.4.0` 部署。

## 7. 決策紀錄

| 日期 | 決定 |
|---|---|
| 2026-11-21 | 照片不存伺服器，改為同步分析，推翻總覽規格的背景 job＋輪詢 |
| 2026-11-21 | 入口分「拍照」「上傳照片」；上傳以照片拍攝時間預設用餐時間 |
| 2026-11-21 | Gemini 先用免費層 |
| 2026-11-21 | 補充說明、信心度、每次送出都算一次，依建議做 |
| 2026-11-21 | demo 確認：＋選單兩格直接進入；看不出食物時主按鈕為「重拍／換一張」；信心度三格訊號（高 ≥ 0.75、中 ≥ 0.5、低 < 0.5）；飲食頁照片分析建立的餐點標「照片」 |
