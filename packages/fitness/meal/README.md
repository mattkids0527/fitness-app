# meal 模組

飲食紀錄：餐點（一餐多個食物項目）、常用食物、最近吃過、複製整餐；飲食頁、新增／編輯餐點頁、常用食物管理頁，以及今日頁的 `meal` 區塊。

- 命名空間：`Fitness\Meal`
- Service Provider：`Fitness\Meal\MealServiceProvider`
- 設定：`config('fitness.meal.*')`（`src/config/fitness/meal.php`）：項目上限、照片類型與大小上限（`photo_mimetypes`、`photo_max_kb`）、最近吃過天數與筆數、複製整餐天數與筆數、份數選項、餐別時段與預設時間
- 語系：`trans('FitnessMeal::<檔名>.<鍵>')`（`src/lang/zh_TW/`：`attributes`、`errors`、`messages` 成功提示、`options` 餐別名稱）
- 路由名稱前綴：`fitness.meal.`（`src/routes/web.php`，已套用 `web`、`auth` middleware）

## 依賴

- `profile`：`UserCalendar`（使用者時區切日）、`EnergyTargetCalculator`（飲食頁顯示熱量目標）。
- `daily-summary`：只監聽它的 `DailySummaryCollecting` 事件。
- `food-analyzer`：`FoodAnalysisService`（送出分析、讀回自己成功的分析、今天剩餘次數）、`FoodImage`、`FoodAnalyzerException`。`meals.food_analysis_id` 外鍵指向它的 `food_analyses`。

## 規則

- 「一天」以使用者時區的 00:00～24:00 切日；`eaten_at` 為 timestamptz，查詢條件與寫入都帶時區位移。
- 統計（飲食頁、今日頁、最近吃過、複製整餐）只計算 `status = confirmed` 的餐點；M3 的手動餐點都是 `confirmed`。
- 營養素為 null 表示「不知道」，加總時當 0；蛋白質／碳水／脂肪任一為 null 時 `has_missing_nutrients = true`（纖維不列入）。
- 修改餐點時項目整批替換；項目帶 `saved_food_id` 時，更新該常用食物的 `last_used_at`（只更新自己的）。
- 份數換算（×0.5／×1／×1.5／×2）在前端完成，後端收到的是換算後的數值。
- 常用食物名稱不分大小寫不可重複：Service 先檢查，同時送出由唯一索引擋下，兩者都轉成 `SavedFoodNameTakenException`（導回並顯示 `errors.name`）。
- 只能存取自己的餐點與常用食物，別人的一律 404。
- **照片分析**：照片以 `FoodImage::fromUploadedFile()` 在記憶體中交給分析服務，不呼叫 `store()`、不寫入 Storage。成功導向確認頁 `create?analysis={id}`，由伺服器讀回分析結果，重新整理不會遺失；確認前不建立餐點。
- 由照片分析建立的餐點 `source = ai`、帶 `food_analysis_id`；一筆分析只能建立一餐（先檢查，唯一索引擋同時送出），重複時導向那一餐的編輯頁並以 flash `error` 說明。
- `meal_items.confidence` 只存在 `source = ai` 的餐點；編輯時前端把原本的信心度送回來就會保留，手動餐點一律存 null。複製整餐帶入的項目不含信心度。

## 資料表

- `meals`：user_id（cascade）、eaten_at（timestamptz）、meal_type、source（`manual`／`ai`，預設 `manual`）、status（預設 `confirmed`）、note、food_analysis_id（FK `food_analyses`，可 null、唯一、null on delete）；索引 `(user_id, eaten_at)`
- `meal_items`：meal_id（cascade）、position、name、portion、grams、kcal、protein_g、carbs_g、fat_g、fiber_g、confidence（decimal(3,2)，可 null）；索引 `(meal_id, position)`
- `saved_foods`：user_id（cascade）、name、portion、grams、kcal、protein_g、carbs_g、fat_g、fiber_g（一份的數值）、last_used_at；唯一索引 `(user_id, lower(name))`

## 路由

| 方法 | 路徑 | 名稱 | 頁面／行為 |
|---|---|---|---|
| GET | `/meals` | `fitness.meal.index` | `meal/Index`：`?date=Y-m-d`，預設今天，格式不對或未來日期改為今天 |
| GET | `/meals/create` | `fitness.meal.create` | `meal/Form`：`?date=`、`?type=`、`?copy_from={id}`（自己的餐點預填，日期為今天、餐別沿用）；`?analysis={id}&taken_at=Y-m-d H:i` 為照片分析確認頁（不是自己成功的分析 404，已建立餐點導向該餐編輯頁） |
| POST | `/meals` | `fitness.meal.store` | 新增（帶 `food_analysis_id` 時為 `source = ai`）→ 飲食頁該日期 |
| GET | `/meals/analyze` | `fitness.meal.analyze` | `meal/Analyze`：`?mode=camera`／`upload`（預設） |
| POST | `/meals/analyze` | `fitness.meal.analyze.store` | `photo`（jpeg／png／webp，5MB 以內）、`hint`（200 字）、`taken_at`（Y-m-d H:i）→ 成功導向確認頁；失敗回分析頁，flash `error` 為 `{code, title, message, remaining}` |
| GET | `/meals/{meal}/edit` | `fitness.meal.edit` | `meal/Form` |
| PUT | `/meals/{meal}` | `fitness.meal.update` | 修改（項目整批替換）→ 飲食頁該日期 |
| DELETE | `/meals/{meal}` | `fitness.meal.destroy` | 刪除 → 飲食頁該日期 |
| GET | `/meals/saved-foods` | `fitness.meal.saved_foods.index` | `meal/SavedFoods` |
| POST | `/meals/saved-foods` | `fitness.meal.saved_foods.store` | 新增 → 回原頁面 |
| PUT | `/meals/saved-foods/{savedFood}` | `fitness.meal.saved_foods.update` | 修改 → 回原頁面 |
| DELETE | `/meals/saved-foods/{savedFood}` | `fitness.meal.saved_foods.destroy` | 刪除 → 回原頁面 |

成功時以 `success` flash 顯示 toast（`FitnessMeal::messages`）。

## 頁面 props

- `meal/Index`：`date`、`today`、`meals`（摘要，含 `source`）、`totals`、`has_missing_nutrients`、`calorie_target`（沒有每日目標時為 null）、`meal_types`、`saved_foods_count`
- `meal/Form`：`today`、`meal_types`、`saved_foods`、`recent_foods`、`copyable_meals`、`limits`（`max_items`、`servings`），以及 `meal`（編輯，項目含 `confidence`）或 `defaults`（新增：`date`、`time`、`meal_type`、`type_chosen`、`note`、`items`、`copied_from`、`food_analysis_id`）；`analysis`（確認頁才有，否則 null）：`id`、`item_count`、`hint`、`time_source`（`photo`／`now`）、`quota`（`remaining`、`limit`）
- `meal/Analyze`：`mode`（`camera`／`upload`）、`today`、`quota`（`remaining`、`limit`）、`hint_max_length`
- `meal/SavedFoods`：`today`、`saved_foods`

日期時間一律為使用者時區的 `Y-m-d`／`H:i` 字串。`meal_types` 每筆含 `value`、`label`、`default_time`、`hours`（自動選餐別的時段，點心為 null）。

## 對外提供

### Repository 介面

- `Repositories\Contracts\MealRepository`：查自己的餐點、某段時間已確認的餐點、最近吃過、新增／修改／替換項目／刪除
- `Repositories\Contracts\SavedFoodRepository`：列表、查自己的、同名檢查、新增／修改／刪除、標記最近使用

### Services

- `MealService`：`mealsOn()`、`recentFoods()`、`copyableMeals()`、`analysisForConfirm()`、`create()`、`update()`、`delete()`
- `SavedFoodService`：`all()`、`create()`、`update()`、`delete()`
- `NutritionCalculator::summarize()`：加總與缺營養素判斷
- `MealPresenter`：轉成前端用的陣列

### Events

（尚無）

### Listeners

- `AddMealDailySummary`：監聽 `DailySummaryCollecting`，加入 `meal` 區塊（`totals`、`has_missing_nutrients`、`meals`、`meal_types`）
