# profile 模組

首次設定、個人資料、BMR 計算、體重紀錄，以及每日熱量／營養目標計算。

- 命名空間：`Fitness\Profile`
- Service Provider：`Fitness\Profile\ProfileServiceProvider`
- 設定：`config('fitness.profile.*')`（`src/config/fitness/profile.php`）：活動係數、蛋白質係數、每公斤熱量、每週目標速度範圍、熱量下限、首次設定例外路由等
- 語系：`trans('FitnessProfile::<檔名>.<鍵>')`（`src/lang/zh_TW/`：`attributes`、`options`、`errors`）
- 路由名稱前綴：`fitness.profile.`（`src/routes/web.php`，已套用 `web`、`auth` middleware）

## 依賴

- `daily-summary`：只監聽它的 `DailySummaryCollecting` 事件。

## 資料表

- `users`（擴充）：gender、birth_date、height_cm、activity_level、goal、goal_rate_kg_per_week、bmr_override、timezone、onboarded_at
- `weight_logs`：user_id、weight_kg、measured_on（每次量測都留一筆，目前體重取最新一筆）

## 路由

| 方法 | 路徑 | 名稱 | 頁面／行為 |
|---|---|---|---|
| GET | `/onboarding` | `fitness.profile.onboarding.show` | `profile/Onboarding` |
| POST | `/onboarding` | `fitness.profile.onboarding.store` | 完成首次設定 → 今日頁 |
| GET | `/me` | `fitness.profile.show` | `profile/Show`（個人資料 + BMR + 每日目標） |
| GET | `/me/edit` | `fitness.profile.edit` | `profile/Edit`（含 BMR 小工具與目標預覽） |
| PUT | `/me` | `fitness.profile.update` | 修改個人資料 |
| GET | `/me/weight-logs` | `fitness.profile.weight_logs.index` | `profile/WeightLogs`（`?page=N` 顯示前 N 批） |
| POST | `/me/weight-logs` | `fitness.profile.weight_logs.store` | 新增體重 |
| DELETE | `/me/weight-logs/{id}` | `fitness.profile.weight_logs.destroy` | 刪除自己的體重紀錄（最後一筆不可刪） |

## Middleware

- `EnsureProfileCompleted`：由 Provider 加到 `web` 群組。已登入但 `onboarded_at` 為空的使用者一律導向 `/onboarding`；例外路由列在設定 `onboarding_except_routes`。

## 對外提供

### Repository 介面

- `Repositories\Contracts\ProfileRepository`：更新個人資料、標記完成首次設定
- `Repositories\Contracts\WeightLogRepository`：新增、取最新一筆／最新 N 筆、查詢自己的紀錄、筆數、刪除

### Services

- `BmrCalculator::calculate()`：Mifflin-St Jeor
- `EnergyTargetCalculator::forUser()` / `calculate()`：BMR（手動值優先）× 日常活動係數、依目標增減的每日熱量目標、蛋白質目標；回傳 `EnergyTargets`
- `WeightLogService::history()` / `current()`：最新 N 筆（附較前一筆差值）／目前體重
- `UserCalendar::today()`：以使用者時區取得今天

### Events

（尚無）

### Listeners

- `AddProfileDailySummary`：監聽 `DailySummaryCollecting`，加入 `profile` 區塊（每日目標、目前體重、今天是否已量）
