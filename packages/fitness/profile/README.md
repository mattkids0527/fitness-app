# profile 模組

首次設定、個人資料、BMR 計算、體重紀錄，以及每日熱量／營養目標計算。

- 命名空間：`Fitness\Profile`
- Service Provider：`Fitness\Profile\ProfileServiceProvider`
- 設定：`config('fitness.profile.*')`（`src/config/fitness/profile.php`）
- 語系：`trans('FitnessProfile::<檔名>.<鍵>')`（`src/lang/zh_TW/`）
- 路由名稱前綴：`fitness.profile.`（`src/routes/web.php`，已套用 `web`、`auth` middleware）

## 依賴

無（其他模組依賴本模組）。

## 對外提供

### Repository 介面

（尚無）

### Events

（尚無）

### Listeners

（尚無）
