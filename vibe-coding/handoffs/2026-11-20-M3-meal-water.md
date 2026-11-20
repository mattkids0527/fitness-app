# 交接：M3 飲食紀錄（手動）＋喝水

- 更新日期：2026-11-20
- 對應任務：[M3 飲食紀錄＋喝水](../milestones/M3-meal-water.md)
- 依據規格：[M3 規格](../specs/2026-11-20-m3-meal-water.md)
- 設計定稿：[ui/features/m3-meal-water](../ui/features/m3-meal-water/README.md)
- 測試情境：[2026-11-20-meal-water](../tests/2026-11-20-meal-water.md)
- 分支：`develop`（已由使用者以 PR #2 合併 `main` 並部署）
- 目前進度：M3 全部完成（T1～T5）。正式站已部署（含 4 張新表的 migration），使用者驗收通過。

## 下次接手從這裡開始

1. `git checkout develop && git pull --ff-only`；`docker compose up -d`；拉下來後 `composer dump-autoload`（新增 `Fitness\Meal`、`Fitness\Water`）與 `php artisan migrate`。
2. 測試共 122 個（M3 新增 31 個），本地與 CI 都通過。
3. 本地開發帳號（user id 1）留有示範資料：3 筆常用食物（茶葉蛋、無糖豆漿、鮪魚蛋餅）與今天、昨天共 5 餐。**不要對開發庫跑 `migrate:fresh`**，會清掉資料；需要乾淨資料庫時用測試庫驗證。

## 模組重點

### `meal`（`packages/fitness/meal/`）
- 表：`meals`、`meal_items`、`saved_foods`（`(user_id, lower(name))` 唯一索引）。時間欄位都是 timestamptz，Model 設 `$dateFormat = 'Y-m-d H:i:sP'`，查詢條件以帶時區的字串綁定。
- 依賴 profile：`UserCalendar` 依使用者時區切日、`EnergyTargetCalculator` 取熱量目標。
- 份數換算在前端（`composables/useMealFood.ts`），後端收到的是換算後的數值。
- 加總時 null 當 0，任一項目缺營養素就 `has_missing_nutrients = true`。
- 常用食物同名（含唯一鍵衝突）轉成 `SavedFoodNameTakenException` → 欄位錯誤。
- `create` 的 `?type=` 只有在是有效餐別時才算「使用者已選餐別」，之後改時間不再自動切換。
- 今日頁 `meal` 區塊由 `AddMealDailySummary` 加入。

### `water`（`packages/fitness/water/`）
- 表：`water_logs`；只能新增與刪除今天的紀錄（別人的或其他日期 404）。
- 建議量：profile `WeightLogRepository::latestForUser` 的體重 × 35 ml，四捨五入到 50 ml；沒有體重時為 null。
- 喝水面板在每頁都能打開：Provider 以 `Inertia::share('water', Inertia::optional(...))` 提供，平常不查詢；開面板與新增／刪除後以 `router.reload({ only: ['water', ...] })` 重新載入（今日頁同時帶 `sections`）。
- 今日頁 `water` 區塊由 `AddWaterDailySummary` 加入。

### 前端
- 頁面：`pages/meal/Index.vue`、`Form.vue`、`SavedFoods.vue`；元件在 `components/meal/`、`components/water/`；型別 `types/meal.ts`、`types/water.ts`。
- 表單都加 `novalidate`，避免瀏覽器原生驗證跳出英文提示，錯誤一律由後端回傳。
- 今日頁 `TodayTargetsCard` 接上實際攝取熱量與蛋白質，超過目標變紅。
- 「即將推出」只剩運動（`/workouts`）。

## 測試注意

- `assertInertia` 會把 props 轉 JSON 再比對，整數值的 float（例如 950.0）會變成 int。meal 的測試以 `CreatesMeals::asJson()` 處理，期望值用整數寫。

## 尚未確認的項目

- 今日頁與飲食頁「超過熱量目標變紅」只在邏輯與測試驗證過，沒有實際看過畫面。
- D1 留下的項目：cron 自動備份檔、iOS PWA standalone 實機測 Google 登入。
- M2 留下的待決事項：未綁定的 Google 登入，錯誤說明要不要加「還沒有帳號的話，請聯絡管理員」。
