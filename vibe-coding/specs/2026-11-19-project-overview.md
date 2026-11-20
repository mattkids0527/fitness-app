# 專案總覽規格（v1.0 定稿）

> 狀態：已定稿（2026-11-19）。此文件是專案層級的規格，確認後再拆成各模組 spec 與 milestone。
> 程式寫法依 [模組化寫作規範](../coding-style.md)。
> 「待決」標記的項目需要使用者拍板；「已決」記錄討論結果。

## 1. 目標

一個**手機優先、電腦為輔**的飲食與健身紀錄工具：

1. 記錄每天吃的東西（拍照交給 LLM 分析，或手動輸入），取得熱量、蛋白質、碳水、脂肪、纖維。
2. 記錄健身項目，依預先建立好的項目估算消耗熱量。
3. 每日分析：熱量缺口／超標、各營養素達成率，並告知「蛋白質還差多少克」與今日建議飲水量。

非目標（第一版不做）：社群分享、教練／多人管理、穿戴裝置同步、原生 App、部署規劃（後續再議）。

## 2. 技術架構

| 層 | 選擇 | 說明 |
|---|---|---|
| 後端 | Laravel 12（沿用現有 starter kit） | Inertia 回傳頁面，不另做 REST API |
| 前端 | Vue 3 + TS + Tailwind + shadcn-vue | 手機版用底部 Tab 導覽，桌機版沿用側欄 |
| 手機體驗 | PWA（manifest + service worker） | 可加到主畫面；相機用 `<input type="file" accept="image/*" capture="environment">` |
| 第三方登入 | Laravel Socialite | **已決**：第一版只做 Google |
| LLM | **已決**：Google Gemini API（vision + JSON schema 結構化輸出） | 模型 ID 放 `.env`（`GEMINI_MODEL`），M4 實測後決定；後端以介面 `FoodImageAnalyzer` 包起來，日後可換 |
| LLM 套件 | **已決**：Prism（`prism-php/prism`） | 見 §2.1 |
| 非同步 | Laravel Queue（database driver） | 照片分析丟背景 job，前端輪詢狀態 |
| 檔案儲存 | Laravel Storage（Flysystem）+ 縮圖處理 | 見 §2.2 |
| 資料庫 | **已決**：PostgreSQL（本地開發與測試都用） | 見 §2.3 |
| 時區 | 使用者時區（預設 Asia/Taipei） | 「一天」以使用者時區切日 |

### 2.1 Gemini 串接方式

| 方案 | 優點 | 缺點 |
|---|---|---|
| **Prism**（建議） | Laravel 生態的多供應商 LLM 套件，支援 Gemini、圖片輸入與結構化輸出；之後想換 Claude / OpenAI 只改設定 | 多一層抽象，Gemini 新功能可能晚一點支援 |
| `google-gemini-php/laravel` | 專門針對 Gemini，功能貼近官方 API | 綁死 Gemini |
| Laravel `Http` 直接打 REST API | 零依賴、完全掌控 | 要自己處理 schema、錯誤、重試 |

### 2.2 檔案（照片）處理建議

- **前端**：`browser-image-compression`（npm）上傳前壓縮成長邊約 1600px 的 JPEG，手機上傳快、省 LLM token。
- **後端儲存**：Laravel 內建 Storage，開發用 `local` 私有磁碟；之後部署換 S3 相容儲存（AWS S3 / Cloudflare R2，需裝 `league/flysystem-aws-s3-v3`），程式不用改。
- **縮圖**：`intervention/image`（v3）存檔時產生列表用小縮圖。
- **讀取**：飲食照片屬個人資料，放私有磁碟，透過驗證身分的路由或 temporary signed URL 讀取，不放 public。
- 備選：`spatie/laravel-medialibrary`（檔案與 model 關聯、自動縮圖都包好），但這專案只有「餐點照片」一種用途，用內建 Storage + intervention 比較輕。

### 2.3 本地資料庫

- **已決**：本地開發改用 PostgreSQL（Laravel Sail 起 pgsql 容器，或本機安裝），原因是日期／時區查詢、JSON 欄位在 SQLite 與 PostgreSQL 行為不同。
- 測試也改用 PostgreSQL 測試庫；CI 需加 PostgreSQL service，CLAUDE.md 的測試說明在 M1 一併更新。

### 2.4 模組劃分

依[模組化寫作規範](../coding-style.md)，業務功能拆成以下模組（`packages/fitness/<module>/`）：

| 模組 | 職責 | 依賴 |
|---|---|---|
| `profile` | 首次設定、個人資料、BMR 計算、體重紀錄、每日熱量／營養目標計算 | daily-summary（只監聽事件） |
| `social-login` | 第三方帳號綁定／解綁／登入的共用流程、`social_accounts` 表、driver 合約 | profile |
| `social-login-google` | Google driver（Socialite） | social-login |
| `meal` | 餐點、食物項目、常用食物、照片儲存、分析結果確認流程 | profile |
| `food-analyzer` | 食物照片分析的 driver 合約、Manager、用量上限、`food_analyses` 紀錄 | — |
| `food-analyzer-gemini` | Gemini driver（Prism） | food-analyzer |
| `exercise` | 健身項目、有氧／重訓紀錄、熱量計算 | profile |
| `water` | 喝水紀錄與建議量 | profile |
| `daily-summary` | 今日頁（每日分析）；發出收集型事件，由 profile / meal / exercise / water 各自加入當日摘要（M1 先建骨架） | — |

## 3. 功能模組

### 3.1 帳號與登入
- Email 登入（starter kit 已有）。
- **已決**：第一版不開放註冊，帳號由管理者以 artisan 指令 `user:create` 建立，使用者登入後自行到設定頁修改密碼；註冊功能保留程式碼，以設定開關關閉。
- 設定頁「已連結帳號」：綁定／解除綁定 Google；綁定後可用 Google 登入。
- **已決**：帳號一定先有 Email + 密碼（第一版由指令建立）；綁定 Google 是登入後的動作。
  - 未綁定的 Google 帳號嘗試登入 → 拒絕，提示「請先以帳號密碼登入，再到設定頁綁定 Google」。
  - 因為每個帳號都有密碼，Google 帳號遺失時仍可用帳密登入並解除綁定。

### 3.2 首次設定（Onboarding）
- 登入後若個人資料未完成，middleware 強制導向 `/onboarding`，完成前不能使用其他頁面。
- 欄位：性別、出生日期、身高、體重、日常活動量（不含運動）、目標（減脂／維持／增肌）與每週目標速度。
- **BMR 計算小工具**：Mifflin-St Jeor 公式自動算出，使用者可改成實測值（例如 InBody）。
  - 男：`10×體重kg + 6.25×身高cm − 5×年齡 + 5`
  - 女：`10×體重kg + 6.25×身高cm − 5×年齡 − 161`
- 之後可在設定頁修改；體重另建紀錄表，每次更新都留歷史。

### 3.3 飲食紀錄
- 一筆「餐」= 日期時間 + 餐別（早餐／午餐／晚餐／點心）+ 多個「食物項目」。
- 食物項目欄位：名稱、份量描述、重量 g（選填）、熱量、蛋白質、碳水、脂肪、纖維。
- 輸入方式：
  1. **拍照／上傳圖片** → 背景 job 呼叫 Gemini → 回傳食物項目草稿（含信心度）→ 使用者檢視、修改後確認存檔。
  2. **手動輸入**。
  3. **已決**：**常用食物／最近吃過**快速帶入（第一版就做）。
- Gemini 回傳用固定 JSON schema；原始回應與 token 用量另存，方便除錯與算成本。
- 照片分析每人每日上限 **20 次**（已決，放 `.env` 可調）。

### 3.4 健身紀錄
- **健身項目（範本）**：先建立再使用。系統內建常見項目（附 MET 值），使用者可新增自訂項目。
- 項目分兩類：
  - **有氧／時間型**：`kcal = MET × 體重kg × 時數`
  - **重訓型（已決）**：記錄**每一組的次數 × 重量**
    - 熱量估算（已決）：`估算時間 = Σ(每組次數 × 每下秒數) + (組數 − 1) × 組間休息秒數`，`kcal = MET × 體重kg × 估算時間(小時)`
    - 每下秒數（預設 3 秒）、組間休息（預設 90 秒）、MET 值（預設 5.0）都設在項目上，可調整
    - 另外顯示**訓練量** `Σ(次數 × 重量)`，方便追蹤進步（不影響熱量）
- 建立項目時即時顯示「以你目前體重，做一次約消耗 X kcal」。
- 紀錄的消耗熱量在存檔時寫死（快照），之後體重變動不影響歷史紀錄。
- 記錄介面提供「複製上一組」「帶入上次這個動作的紀錄」，手機上減少輸入。

### 3.5 喝水紀錄（已決：要做）
- 快速按鈕：+250 ml / +500 ml / 自訂；可刪除誤按的紀錄。
- 每日建議量：`體重kg × 35 ml + 當日運動時數 × 500 ml`。

### 3.6 每日分析
- 當日消耗：`BMR × 日常活動係數 + 當日健身消耗`（活動係數不含運動，避免重複計算）。
- 熱量目標：依目標調整（例：減脂每週 0.5 kg ≈ 每日 −550 kcal）。
- 顯示：
  - 熱量：已攝取／目標／剩餘（缺口或超標）
  - 蛋白質：目標 = 體重 × g/kg（依目標 1.2～2.0），顯示「還差 N g」，並換算常見食物（例：約 1 塊雞胸肉）
  - 碳水、脂肪：依剩餘熱量分配
  - 纖維：每 1000 kcal 約 14 g
  - 喝水：已喝／建議量
- 週／月趨勢圖（熱量、體重）：第二階段。

## 4. 資料表初稿

| 資料表 | 主要欄位 |
|---|---|
| `users`（擴充） | gender, birth_date, height_cm, activity_level, goal, goal_rate_kg_per_week, bmr_override, timezone, onboarded_at |
| `social_accounts` | user_id, provider, provider_user_id, email, name, avatar（詳見 [M2 規格 §2.1](2026-11-20-m2-google-login.md)：每個平台各一組、第三方帳號唯一） |
| `weight_logs` | user_id, weight_kg, measured_on |
| `meals` | user_id, eaten_at, meal_type, source(manual/ai), photo_path, status(analyzing/draft/confirmed/failed), note |
| `meal_items` | meal_id, name, portion, grams, kcal, protein_g, carbs_g, fat_g, fiber_g, confidence |
| `food_analyses` | meal_id, provider, model, raw_response(jsonb), input_tokens, output_tokens, error |
| `saved_foods` | user_id, name, portion, 每份營養素, last_used_at |
| `exercises` | user_id(null=系統內建), name, type(cardio/strength), met, sec_per_rep, rest_sec |
| `exercise_logs` | user_id, exercise_id, performed_at, duration_min(有氧), kcal(快照), volume_kg(重訓), note |
| `exercise_sets` | exercise_log_id, set_no, reps, weight_kg |
| `water_logs` | user_id, amount_ml, drank_at |

每日統計先以查詢即時計算，資料量變大再加快取表。

## 5. 頁面初稿（手機）

底部 Tab：**今日**（儀表板）／**飲食**／**＋**（快速新增：拍照、手動、健身、喝水）／**健身**／**我的**

- 今日：熱量環形圖、營養素進度條、蛋白質還差多少、喝水進度、今日餐點與運動列表
- 飲食：依日期切換的餐點列表、拍照分析結果確認頁、常用食物
- 健身：今日紀錄、重訓逐組輸入、項目管理
- 我的：個人資料、BMR 工具、體重紀錄、已連結帳號、外觀設定

## 6. 里程碑建議順序

1. **M1 帳號基礎**：本地 PostgreSQL、中文化、手機版版型（底部 Tab）、PWA、Onboarding + BMR 工具、體重紀錄
2. **M2 Google 登入**：Socialite、綁定／解除綁定
3. **M3 飲食紀錄（手動）+ 喝水**：餐點 CRUD、常用食物、喝水紀錄
4. **M4 Gemini 照片分析**：上傳壓縮、照片儲存、queue job、結果確認流程、用量上限、模型實測
5. **M5 健身紀錄**：內建項目、自訂項目、有氧／重訓逐組紀錄與熱量計算
6. **M6 每日分析儀表板**：熱量缺口、營養素、蛋白質／喝水建議

每個里程碑開始前，依工作準則先出 demo 畫面確認。

## 7. 決策紀錄

| 項目 | 決定 |
|---|---|
| 「還需要多少 Kg」的意思 | 蛋白質還差多少克（以體重 × g/kg 計算目標） |
| 第三方登入 | 第一版只做 Google；必須先有帳密帳號，註冊後才能綁定，未綁定不可用 Google 登入 |
| LLM | Gemini API，透過 Prism 串接 |
| 照片分析上限 | 每人每日 20 次 |
| 重訓紀錄 | 逐組記錄次數 × 重量；熱量以估算時間 × MET 計算 |
| 喝水紀錄、常用食物 | 第一版都做 |
| 資料庫 | PostgreSQL，本地開發與測試都用；部署後續再規劃 |
| 介面語言 | 只做繁體中文 |
| 程式架構 | 模組化（packages），規範見 `vibe-coding/coding-style.md` |
| 模組 vendor | `packages/fitness/`，命名空間 `Fitness\` |
| 品牌色 | 橘 `hsl(21 90% 48%)`（Tailwind orange-600），其餘沿用 shadcn-vue token（M1 T4 定案） |
| 註冊 | 第一版不開放，帳號以 `user:create` 指令建立，密碼由使用者登入後自行修改（M1 T5 定案） |
| 熱量目標下限 | 低於 男 1,500／女 1,200 kcal 時只提醒、不阻擋儲存，數值放設定檔可調（M1 T4 定案） |

## 8. 待決事項

無。部署方式後續另開規格討論。
