# water 模組

喝水紀錄與每日建議飲水量：喝水面板（所有頁面都能從＋選單打開）、今日頁的 `water` 區塊。沒有獨立頁面。

- 命名空間：`Fitness\Water`
- Service Provider：`Fitness\Water\WaterServiceProvider`
- 設定：`config('fitness.water.*')`（`src/config/fitness/water.php`）：`ml_per_kg`（env `WATER_ML_PER_KG`，預設 35）、`round_to_ml`、`min_ml`／`max_ml`（單筆上下限）、`quick_amounts`（快速按鈕）
- 語系：`trans('FitnessWater::<檔名>.<鍵>')`（`src/lang/zh_TW/`：`attributes`、`messages` 成功提示）
- 路由名稱前綴：`fitness.water.`（`src/routes/web.php`，已套用 `web`、`auth` middleware）

## 依賴

- `profile`：`WeightLogRepository`（目前體重）、`UserCalendar`（使用者時區切日）。
- `daily-summary`：只監聽它的 `DailySummaryCollecting` 事件。

## 規則

- 只能記今天：喝水時間為記錄當下；只能刪除自己今天（使用者時區）的紀錄，其他一律 404。
- 建議量 = 目前體重 × `ml_per_kg`，四捨五入到 `round_to_ml`；沒有體重紀錄時 `target_ml` 為 null。之後健身模組（M5）以 listener 加上運動時數的加給，本模組不查健身資料表。
- `drank_at` 為 timestamptz，查詢條件與寫入都帶時區位移。

## 資料表

- `water_logs`：user_id（cascade）、amount_ml、drank_at（timestamptz）；索引 `(user_id, drank_at)`

## 路由

| 方法 | 路徑 | 名稱 | 行為 |
|---|---|---|---|
| POST | `/water-logs` | `fitness.water.store` | 記錄今天喝的水（`amount_ml`，整數 10～2000）→ 回原頁面，toast「已記錄 N ml」 |
| DELETE | `/water-logs/{waterLog}` | `fitness.water.destroy` | 刪除自己今天的紀錄 → 回原頁面 |

## 共享資料 `water`

由 Provider 以 `Inertia::share('water', Inertia::optional(...))` 提供：一般換頁不會查詢、props 裡沒有 `water`；喝水面板打開時以 partial reload `router.reload({ only: ['water'] })` 取得。記錄或刪除後回到原頁面時同樣不含 `water`，前端要再 reload 一次（或在送出時帶 `only: ['water']`）。

格式（今日頁 `sections.water` 相同）：

```ts
{
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

## 對外提供

### Repository 介面

- `Repositories\Contracts\WaterLogRepository`：某段時間的紀錄、查自己某段時間的某筆、新增、刪除

### Services

- `WaterService`：`day()`／`today()` 摘要、`record()`、`deleteToday()`
- `WaterTargetCalculator`：`forUser()`、`calculate()`

### Events

（尚無）

### Listeners

- `AddWaterDailySummary`：監聽 `DailySummaryCollecting`，加入 `water` 區塊
