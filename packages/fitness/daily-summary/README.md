# daily-summary 模組

今日頁（每日分析）。本模組只負責發出收集型事件與顯示頁面，各模組以 listener 把自己的當日摘要加進來；之後的飲食、喝水、健身摘要都以新增 listener 擴充，不修改本模組。

- 命名空間：`Fitness\DailySummary`
- Service Provider：`Fitness\DailySummary\DailySummaryServiceProvider`
- 路由名稱前綴：`fitness.daily_summary.`（`src/routes/web.php`，已套用 `web`、`auth`、`verified` middleware）

## 依賴

無。「今天」以使用者的 `timezone` 欄位切日，未設定時用 `config('app.timezone')`。

## 路由

| 方法 | 路徑 | 名稱 | 頁面／行為 |
|---|---|---|---|
| GET | `/today` | `fitness.daily_summary.show` | `daily-summary/Today`（登入後的首頁） |

## 對外提供

### Events

- `Events\DailySummaryCollecting`（收集型）：屬性 `user`、`date`（使用者時區的今天 00:00）；listener 以 `addSection(key, data)` 加入摘要，頁面 props `sections.<key>` 取得。

| section key | 提供模組 | 內容 |
|---|---|---|
| `profile` | profile | `targets`（每日熱量／蛋白質目標，未完成設定為 null）、`current_weight`、`weighed_today` |
