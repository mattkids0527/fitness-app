# M3 飲食紀錄（手動）＋喝水規格

> 狀態：已定稿（2026-11-20）。Q1～Q8 與 §10 補充細節皆已確認；下一步出 demo 畫面，畫面拍板後寫 milestone。
> 依據 [專案總覽規格 v1.0](2026-11-19-project-overview.md) §3.3、§3.5、§4、§5。
> 程式寫法依 [模組化寫作規範](../coding-style.md)。
> 「待決」標記的項目需要使用者拍板；「已決」記錄討論結果。

## 1. 目標

使用者可以手動記錄每天吃的東西（一餐多個食物項目，含熱量與營養素），並用「常用食物」「最近吃過」「複製整餐」快速帶入；可以記錄喝水並看到今日建議量。今日頁顯示今天的熱量攝取、餐點簡表與喝水進度。

不做：拍照分析（M4）、健身（M5）、營養素目標與達成率分析（M6）、週／月趨勢（第二階段）。

## 2. 已決（2026-11-20）

| # | 問題 | 決定 |
|---|---|---|
| Q1 | `meals` 是否預留 M4 欄位 | 先加 `source`（預設 `manual`）與 `status`（預設 `confirmed`）；`photo_path` 等 M4 欄位到 M4 再加。統計查詢從 M3 起只算 `confirmed` |
| Q2 | 同日同餐別能否多筆 | 可以。畫面依餐別分組。餐別依時間預設：5–10 點早餐、10–14 點午餐、17–21 點晚餐，其餘點心，可手動改 |
| Q3 | 食物項目必填欄位 | 只有名稱、熱量必填；份量描述、重量、蛋白質、碳水、脂肪、纖維選填，未填存 null（表示「不知道」，與 0 區分）。加總時 null 當 0，畫面提示「部分項目沒有填營養素」 |
| Q4 | 常用食物／最近吃過 | 常用食物：使用者按星號加入，存 `saved_foods`（每份營養素），有管理頁。最近吃過：不建表，從 `meal_items` 依名稱取近 30 天。兩者帶入時可選份數 ×0.5／×1／×1.5／×2，營養素依份數換算 |
| Q5 | 複製整餐 | 要做 |
| Q6 | 新增餐點介面 | 整頁（`/meals/create`）；喝水用底部面板 |
| Q7 | 喝水補記 | M3 只能記今天，可以刪除今天的紀錄。建議量 = 體重 × 35 ml；「運動時數 × 500 ml」等 M5 由 exercise 模組以 listener 加入，water 不查健身資料表 |
| Q8 | 今日頁範圍 | 熱量（已攝取／目標／剩餘）、今日餐點簡表、喝水進度與 +250／+500 按鈕。蛋白質還差多少、碳水脂肪分配、纖維達成率留給 M6 |

## 3. 模組

| 模組 | 職責 | 依賴 |
|---|---|---|
| `meal` | 餐點、食物項目、常用食物、最近吃過、複製整餐；飲食頁、新增／編輯餐點頁、常用食物管理頁 | profile（`UserCalendar` 切日、`EnergyTargetCalculator` 取飲食頁的熱量目標，T2 實作時補上）、daily-summary（只監聽事件） |
| `water` | 喝水紀錄、每日建議量；今日頁喝水區塊、喝水面板 | profile（`WeightLogRepository` 取目前體重）、daily-summary（只監聽事件） |

- 「一天」以使用者時區（`users.timezone`）切日，查詢時把當地日期換成 UTC 起訖時間。
- 刪除帳號時，兩個模組的資料都以 FK cascade 一併刪除。

## 4. 資料表

### 4.1 `meals`

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | 主鍵 |
| user_id | bigint，FK → users，cascade | |
| eaten_at | timestamptz | 用餐時間 |
| meal_type | varchar(16) | `breakfast`／`lunch`／`dinner`／`snack`（PHP enum `MealType`） |
| source | varchar(16)，預設 `manual` | M4 加入 `ai` |
| status | varchar(16)，預設 `confirmed` | M4 加入 `analyzing`／`draft`／`failed`；常數定義在 Model |
| note | varchar(500)，nullable | 備註 |
| created_at / updated_at | timestamp | |

索引：`(user_id, eaten_at)`。

### 4.2 `meal_items`

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | 主鍵 |
| meal_id | bigint，FK → meals，cascade | |
| position | smallint | 在這一餐裡的順序 |
| name | varchar(100) | 必填 |
| portion | varchar(50)，nullable | 份量描述，例如「1 碗」 |
| grams | numeric(7,1)，nullable | 重量 |
| kcal | numeric(7,1) | 必填 |
| protein_g / carbs_g / fat_g / fiber_g | numeric(6,1)，nullable | 未填為 null |
| created_at / updated_at | timestamp | |

M4 的信心度（`confidence`）到 M4 再加。

### 4.3 `saved_foods`

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | 主鍵 |
| user_id | bigint，FK → users，cascade | |
| name | varchar(100) | |
| portion / grams / kcal / protein_g / carbs_g / fat_g / fiber_g | 同 `meal_items` | 一份的數值 |
| last_used_at | timestamptz，nullable | 最近一次被帶入的時間，用來排序 |
| created_at / updated_at | timestamp | |

唯一索引：`(user_id, lower(name))`，同一個使用者不能有兩個同名的常用食物。

### 4.4 `water_logs`

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | 主鍵 |
| user_id | bigint，FK → users，cascade | |
| amount_ml | integer | |
| drank_at | timestamptz | 記錄當下的時間 |
| created_at / updated_at | timestamp | |

索引：`(user_id, drank_at)`。

## 5. 計算規則

- **加總**：一餐、一天的熱量與營養素 = 各項目加總，null 當 0。只要有任一項目的蛋白質、碳水、脂肪有 null，就回傳 `has_missing_nutrients = true`（纖維常常不知道，不列入提示）。
- **份數換算**：帶入常用食物或最近吃過時，`grams`、`kcal`、各營養素乘以份數，四捨五入到小數 1 位；份數不是 1 時，份量描述加上倍數，例如「1 碗 ×1.5」。
- **由營養素估熱量**（表單輔助）：熱量沒填、但蛋白質／碳水／脂肪有填時，顯示「依營養素估算 X kcal」按鈕，`蛋白質×4 + 碳水×4 + 脂肪×9`，按下才帶入。
- **最近吃過**：近 30 天 `confirmed` 餐點的項目，依名稱（不分大小寫）取最近一次，最多 20 筆，依時間新到舊。
- **喝水建議量**：`目前體重 × 35 ml`，四捨五入到 50 ml；係數放設定檔。沒有體重紀錄時不顯示建議量。

## 6. 路由與頁面

### 6.1 `meal`（路由名稱前綴 `fitness.meal.`）

| 方法 | 路徑 | 名稱 | 頁面／行為 |
|---|---|---|---|
| GET | `/meals` | `index` | `meal/Index`：`?date=YYYY-MM-DD`，預設今天 |
| GET | `/meals/create` | `create` | `meal/Form`：`?date=`、`?type=`、`?copy_from={meal id}` |
| POST | `/meals` | `store` | 新增 → 回飲食頁該日期 |
| GET | `/meals/{meal}/edit` | `edit` | `meal/Form` |
| PUT | `/meals/{meal}` | `update` | 整筆覆蓋（項目整批替換） |
| DELETE | `/meals/{meal}` | `destroy` | 刪除 |
| GET | `/meals/saved-foods` | `saved_foods.index` | `meal/SavedFoods`：常用食物管理頁 |
| POST | `/meals/saved-foods` | `saved_foods.store` | 新增常用食物（管理頁手動新增，或在表單按星號） |
| PUT | `/meals/saved-foods/{savedFood}` | `saved_foods.update` | 修改 |
| DELETE | `/meals/saved-foods/{savedFood}` | `saved_foods.destroy` | 刪除 |

- 只能存取自己的資料，別人的一律 404。
- 「最近吃過」「常用食物」「最近的餐點（複製整餐用）」由 `create`／`edit` 頁面的 props 一併帶入，不另開 API。
- 移除 `routes/web.php` 暫放的 `/meals` 即將推出頁。

### 6.2 `water`（路由名稱前綴 `fitness.water.`）

| 方法 | 路徑 | 名稱 | 行為 |
|---|---|---|---|
| POST | `/water-logs` | `store` | 記錄今天喝的水，回到原頁面 |
| DELETE | `/water-logs/{waterLog}` | `destroy` | 刪除自己今天的紀錄 |

喝水沒有獨立頁面：今日頁的喝水卡片、＋選單的「喝水」都打開同一個喝水面板（`components/water/WaterSheet.vue`，掛在 `AppSidebarLayout`），開關狀態放 `useQuickActions`。面板資料來自共享資料 `water`（`Inertia::optional`，打開面板時以 partial reload 載入，格式同 §8 的 `water` 區塊）。

## 7. 畫面與流程

### 7.1 飲食頁（`/meals`）
- 上方日期切換：‹ 前一天｜11月20日 週五｜後一天 ›，點日期可選日期；不能切到未來。
- 當日合計卡：熱量（已攝取／目標）、蛋白質、碳水、脂肪、纖維的克數；有缺營養素時顯示提示。
- 依早餐／午餐／晚餐／點心分組，每組列出該餐別的餐點卡片（時間、項目名稱摘要、熱量），沒有紀錄的餐別顯示「＋ 新增早餐」。
- 餐點卡片的選單：編輯、再吃一次（開新增頁並帶 `copy_from`）、刪除（確認後刪除）。
- 頁面底部入口：常用食物。

### 7.2 新增／編輯餐點頁（`/meals/create`、`/meals/{id}/edit`）
- 上方：日期（預設瀏覽中的日期）、時間（今天預設現在，其他日期預設該餐別的代表時間 08:00／12:30／18:30／15:00）、餐別（依時間自動選，可改）。
- 帶入區（分頁）：常用食物／最近吃過／複製整餐（近 14 天的餐點）。點選後選份數，加入下方項目清單；複製整餐會把該餐所有項目加入。
- 項目清單：每個項目可展開編輯所有欄位、刪除、按星號加入常用食物；「＋ 新增項目」手動輸入。
- 底部固定列：本餐合計熱量、儲存按鈕。備註放在最下方。
- `copy_from` 帶入的內容只是預填，使用者確認後按儲存才建立。

### 7.3 常用食物管理頁（`/meals/saved-foods`）
- 列表依最近使用時間排序；可新增、編輯（底部面板表單）、刪除。

### 7.4 喝水面板
- 今天已喝／建議量的進度、+250 ml、+500 ml、自訂（輸入 ml）。
- 下方列出今天的紀錄（時間、ml），每筆可刪除。

### 7.5 今日頁
- 熱量卡沿用現有的 `TodayTargetsCard`（環形圖＋蛋白質進度），把目前寫死為 0 的已攝取熱量、蛋白質改成 meal 區塊的實際加總，並拿掉「飲食紀錄上線後…」的提示：已攝取／目標／剩餘，超過時顯示「超過 N kcal」。目標取自 profile 區塊的 `targets.calorie_target`、`protein_target_g`，前端組合，meal 模組不依賴 profile。
- 今日餐點簡表：依餐別列出，點擊進入編輯；沒有紀錄時顯示新增入口。
- 喝水卡：進度條、+250／+500 直接記錄，點卡片打開喝水面板。
- 既有的目標卡、體重卡保留；版面在 demo 階段決定。

### 7.6 ＋選單
- 「手動記錄」→ `/meals/create`（今天，餐別依現在時間）。
- 「喝水」→ 喝水面板。
- 「拍照分析」「健身」維持即將推出。

## 8. 今日頁 sections

```ts
// meal 模組加入
meal: {
    totals: { kcal: number; protein_g: number; carbs_g: number; fat_g: number; fiber_g: number };
    has_missing_nutrients: boolean;
    meals: { id: number; date: string; time: string; meal_type: MealType; note: string | null; kcal: number; item_names: string[] }[]; // date、time 為使用者時區
    meal_types: { value: MealType; label: string; default_time: string; hours: [number, number] | null }[]; // 餐別名稱
}
// water 模組加入
water: {
    total_ml: number;
    target_ml: number | null; // 沒有體重紀錄時為 null
    weight_kg: number | null;
    ml_per_kg: number;
    logs: { id: number; amount_ml: number; time: string }[]; // time 為使用者時區 H:i，新的在前
    quick_amounts: number[];
    min_ml: number;
    max_ml: number;
}
```

## 9. 驗證規則

| 欄位 | 規則 |
|---|---|
| 餐點日期 | 必填，不能晚於使用者時區的今天 |
| 餐別 | 必填，四種之一 |
| 項目數 | 1～30 |
| 名稱 | 必填，最多 100 字 |
| 份量描述 | 選填，最多 50 字 |
| 重量 | 選填，0～5000 g |
| 熱量 | 必填，0～5000 kcal |
| 蛋白質／碳水／脂肪／纖維 | 選填，0～1000 g |
| 備註 | 選填，最多 500 字 |
| 喝水量 | 必填，整數 10～2000 ml（上下限放設定檔） |
| 常用食物 | 欄位同食物項目；同名已存在時拒絕，提示「常用食物裡已經有同名的項目」 |

## 10. 已決：補充細節（2026-11-20）

| # | 項目 | 決定 |
|---|---|---|
| Q9 | 常用食物重名 | 不可重複，`(user_id, lower(name))` 唯一；表單裡的星號以名稱判斷是否已加入 |
| Q10 | 份數選項 | 固定 ×0.5／×1／×1.5／×2，不開放自訂 |
| Q11 | 由營養素估熱量 | 做（§5），按下按鈕才帶入 |
| Q12 | 數量 | 「最近吃過」取 20 筆；「複製整餐」取近 14 天 |
| Q13 | 喝水建議量 | 四捨五入到 50 ml |
| Q14 | 今日頁 +250／+500 | 按下直接記錄並顯示 toast，不跳確認；按錯從喝水面板刪除 |
