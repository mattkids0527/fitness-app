# M3 飲食紀錄（手動）＋喝水

- 狀態：完成（2026-11-20，已合併 `main`、部署並在正式站驗收；交接見 [handoff](../handoffs/2026-11-20-M3-meal-water.md)）
- 建立日期：2026-11-20
- 分支：`develop`（每個 Task 完成就 commit 並 push；PR `develop` → `main` 由使用者開與合併）
- 依據規格：[M3 飲食紀錄＋喝水規格](../specs/2026-11-20-m3-meal-water.md)、[專案總覽規格 v1.0](../specs/2026-11-19-project-overview.md) §3.3、§3.5
- 設計定稿：[ui/features/m3-meal-water](../ui/features/m3-meal-water/README.md)
- 寫作規範：[模組化寫作規範](../coding-style.md)

## 目標

使用者可以手動記錄每一餐（多個食物項目、熱量與營養素），用常用食物、最近吃過、複製整餐快速帶入；可以記錄今天喝的水並看到建議量。今日頁顯示實際攝取熱量與蛋白質、今日餐點、喝水進度。

## 範圍

**包含**
- `meal` 模組：`meals`、`meal_items`、`saved_foods` 三張表；飲食頁、新增／編輯餐點頁、常用食物管理頁；今日頁 `meal` 區塊
- `water` 模組：`water_logs` 表；喝水面板；今日頁 `water` 區塊
- 今日頁：熱量卡接上實際攝取、今日餐點卡、喝水卡
- ＋選單的「手動記錄」「喝水」改為可用；底部 Tab／側欄的「飲食」改指向飲食頁，移除 `/meals` 即將推出頁

**不包含**
- 拍照分析與 M4 欄位（`photo_path`、`confidence` 等）
- 健身、運動加給的喝水建議量（M5）
- 碳水／脂肪／纖維目標與達成率、週月趨勢（M6、第二階段）
- 喝水補記其他日期

## Tasks

### T1 設計 demo 畫面（完成）
以 M1 定稿的版型與橘色 token 刻 demo：今日頁三張卡、飲食頁、新增／編輯餐點（快速帶入三分頁、選份數、項目編輯、☆、估算熱量、驗證）、常用食物頁與面板、喝水面板、＋選單；手機與桌機、淺色與深色。已移到 `vibe-coding/ui/features/m3-meal-water/` 並更新 `vibe-coding/ui/index.md`。
**完成條件**：使用者確認 demo；features 目錄與 index 已更新。

### T2 `meal` 模組後端
建立 `packages/fitness/meal/`（命名空間 `Fitness\Meal`），註冊到根目錄 `composer.json`（含 tests autoload-dev）與 `bootstrap/providers.php`，附模組 README。內容：
- migration：`meals`、`meal_items`、`saved_foods`（欄位、索引、`(user_id, lower(name))` 唯一索引依規格 §4）；`MealType` enum；`Meal`（`source`、`status` 常數）、`MealItem`、`SavedFood` Model 與 `User` 的關聯
- Repository（含 Contract）：`MealRepository`（依使用者時區切日查詢、只算 `confirmed`、近 14 天餐點、近 30 天最近吃過）、`SavedFoodRepository`
- Service：加總（null 當 0、`has_missing_nutrients`）、份數換算、建立／更新（項目整批替換，交易內完成）、常用食物帶入時更新 `last_used_at`
- FormRequest：餐點、常用食物（驗證依規格 §9；同名以 Exception 轉成欄位錯誤，唯一鍵衝突同樣處理）
- 路由與 Controller（規格 §6.1）：只能存取自己的資料，別人的 404；`create`／`edit` 的 props 帶常用食物、最近吃過、可複製的餐點與 `copy_from` 預填內容
- 監聽 `DailySummaryCollecting` 加入 `meal` 區塊（規格 §8）
- 語系 `FitnessMeal::`；設定檔 `config/fitness/meal.php`（最近吃過天數與筆數、複製整餐天數、項目上限、份數選項）
- 移除 `routes/web.php` 的 `/meals` 即將推出路由（路徑與 `fitness.meal.index` 相同）；底部 Tab／側欄的「飲食」改指向 `fitness.meal.index`，`ComingSoonTest` 只留健身。T4-1 完成前點「飲食」會因頁面元件不存在而出錯，只影響 develop 本地開發
- 實作補充：份數換算在前端完成，後端收到換算後的數值；meal 依賴 profile 的 `UserCalendar` 與 `EnergyTargetCalculator`（規格 §3 已同步）
**完成條件**：程式碼檢視符合寫作規範；`php artisan migrate:fresh --seed` 成功；`route:list` 看得到 `fitness.meal.*`；頁面元件留待 T4，此時 Inertia 頁面不存在不影響路由註冊。

### T3 `water` 模組後端
建立 `packages/fitness/water/`（命名空間 `Fitness\Water`），註冊方式同 T2，附模組 README。內容：
- migration `water_logs`；`WaterLog` Model；`WaterLogRepository`（含 Contract，依使用者時區取今天的紀錄）
- `WaterTargetCalculator`：透過 profile 的 `WeightLogRepository` 取目前體重，`× 35 ml` 四捨五入到 50 ml，沒有體重紀錄時回傳 null；係數放設定檔
- 路由 `POST /water-logs`、`DELETE /water-logs/{waterLog}`（只能刪自己今天的），回原頁面並以 `success` flash 顯示 toast
- 監聽 `DailySummaryCollecting` 加入 `water` 區塊
- 喝水面板在所有頁面都能打開，需要今天的喝水資料：由模組 Provider 以 `Inertia::share('water', Inertia::optional(...))` 提供（已確認 inertia-laravel 2.0.28 會先合併共享資料再過濾 optional），一般換頁不查詢，打開面板時以 `router.reload({ only: ['water'] })` 載入；記錄／刪除後回原頁面時也不含 `water`，前端要再 reload
- 語系 `FitnessWater::`；設定檔 `config/fitness/water.php`（每公斤 ml、四捨五入單位、單筆上下限）
**完成條件**：程式碼檢視符合寫作規範；`migrate:fresh` 成功；`route:list` 看得到 `fitness.water.*`；今日頁的 props 有 `water` 區塊。

### T4 前端實作
依 demo 實作，拆成以下子步驟，每步完成後 commit、push 並詢問是否進行下一步。型別放 `types/meal.ts`、`types/water.ts`，並在 `types/daily-summary.ts` 加入兩個區塊。
**完成條件**：`npm run build`、`npm run lint`、`npm run format:check` 通過；390px 手機與桌機寬度對照 demo 檢查。

#### T4-1 飲食頁
`pages/meal/Index.vue`：日期切換、當日合計卡、依餐別分組的餐點卡、餐點選單與刪除確認、常用食物入口；底部 Tab／側欄「飲食」指向飲食頁。

#### T4-2 新增／編輯餐點頁
`pages/meal/Form.vue`：基本資料（餐別依時間自動選）、快速帶入三分頁、選份數面板、項目清單（展開編輯、☆、估算熱量、刪除）、備註、底部合計與儲存、驗證錯誤；「再吃一次」預填提示。

#### T4-3 常用食物頁
`pages/meal/SavedFoods.vue`：列表、新增／編輯面板、刪除確認。

#### T4-4 喝水面板、＋選單與今日頁
`components/water/WaterSheet.vue` 掛在 `AppSidebarLayout`，開關狀態放 `useQuickActions`；＋選單「手動記錄」「喝水」改為可用；今日頁 `TodayTargetsCard` 接上實際攝取熱量與蛋白質（拿掉「飲食紀錄上線後…」提示、超標變紅、缺營養素提醒），新增今日餐點卡與喝水卡，「即將推出」只剩運動。

### T5 測試
先把測試情境條列給使用者確認（預計涵蓋：餐點 CRUD 與驗證、只能存取自己的餐點、依使用者時區切日、加總與缺營養素判斷、份數換算、最近吃過與複製整餐的範圍、常用食物 CRUD 與同名拒絕、帶入時更新最近使用、喝水新增刪除與上下限、只能刪自己今天的喝水、建議量計算與無體重時為 null、今日頁兩個區塊、刪除帳號一併刪除資料），確認後寫成 `vibe-coding/tests/<日期>-meal-water.md` 並更新 `vibe-coding/tests/index.md`，再於兩個模組的 `tests/` 撰寫並執行。
**完成條件**：使用者確認情境；全部測試（含既有測試）通過、無 skip；Pint、Prettier、ESLint 通過；CI 通過。

### 部署備註
M3 新增 migration，合併到 `main` 後照常以 Actions 部署（deploy 會執行 migrate）；不需要改 `provision.sh`。正式站驗收後再寫 handoff 並把 milestone 標為完成。

## 進度

| Task | 狀態 |
|---|---|
| T1 設計 demo | 完成（2026-11-20） |
| T2 meal 模組後端 | 完成（2026-11-20） |
| T3 water 模組後端 | 完成（2026-11-20） |
| T4 前端實作 | 完成（2026-11-20） |
| T5 測試 | 完成（2026-11-20；31 個新測試，全部 122 個通過） |
