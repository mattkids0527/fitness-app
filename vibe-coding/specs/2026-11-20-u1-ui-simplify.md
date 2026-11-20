# U1 介面精簡規格

- 建立日期：2026-11-20
- 狀態：已確認（2026-11-20）
- 設計定稿：[ui/features/u1-ui-simplify](../ui/features/u1-ui-simplify/README.md)
- 範圍：只改前端（`resources/views/app.blade.php`、`public/offline.html`、`resources/css/app.css`、`resources/js`）；不改後端、資料表、路由與 props

## 1. 目標

M3 上線後的體驗改善：
- 手機上鎖住頁面縮放，操作起來像原生 App。
- 能用圖示表達的地方，減少文字。

## 2. 已決（2026-11-20）

- 三種縮放全部鎖住：點兩下、兩指縮放（Android）、兩指縮放（iPhone）。可接受的代價是使用者不能放大頁面。
- 營養素用「圖示＋小字」，第一次看也認得出來；表單欄位保留文字，前面加圖示。
- 改：營養素、今日熱量卡、今日餐點卡的餐別、卡片右上連結、＋選單、飲食頁餐點卡的項目數、常用食物頁說明框。
- 不改：底部 Tab 文字、kcal／g 單位、確認類按鈕（儲存、刪除）的文字。
- demo 確認時一併同意的調整：
  - 表單拿掉「（選填）」，改成必填欄位打橘色 `*`。
  - ＋選單「手動記錄」改名「記錄餐點」。
  - 蛋白質達成目標時顯示 ✓。

## 3. 縮放鎖定

| 情況 | 做法 |
|---|---|
| 點兩下放大 | `resources/css/app.css` 的 `html` 加 `touch-action: manipulation` |
| 兩指縮放（Android） | `app.blade.php` 與 `offline.html` 的 viewport 加 `maximum-scale=1, user-scalable=no`；`offline.html` 由 service worker 預先快取，`sw.js` 的 `CACHE_VERSION` 改成 `v2` |
| 兩指縮放（iPhone） | `app.ts` 對 `document` 的 `gesturestart` 呼叫 `preventDefault()` |
| 點輸入框自動放大（iPhone） | 手機上輸入框維持 16px 以上（目前 `Input`、`UnitInput` 已是 `text-base`，textarea 一併確認） |

## 4. 營養素圖示

- 圖示與顏色集中定義在一處（例如 `composables/useNutrients.ts`），各元件共用，不要各自寫：

| 營養素 | lucide 圖示 | 淺色 | 深色 |
|---|---|---|---|
| 蛋白質 | `Drumstick` | rose-500 | rose-400 |
| 碳水 | `Wheat` | amber-500 | amber-400 |
| 脂肪 | `Nut` | violet-500 | violet-400 |
| 纖維 | `Leaf` | emerald-500 | emerald-400 |
| 熱量（表單標籤） | `Flame` | 沿用文字色 | 沿用文字色 |

- 呈現方式：
  - 格子（`NutrientGrid`）：圖示＋數值，下方小字標籤。
  - 列表（常用食物頁、快速帶入清單）：圖示＋數值，不寫文字。
  - 表單標籤（`MealItemCard`、常用食物面板）：圖示＋文字。
- 只有圖示、沒有文字的地方，以 `sr-only` 或 `aria-label` 提供名稱。

## 5. 各畫面改動

| 畫面情境 | 元件 | 改動 |
|---|---|---|
| u1-02 | `meal/NutrientGrid.vue` | 依 §4 格子樣式 |
| u1-03 | `meal/MealItemCard.vue`、`pages/meal/SavedFoods.vue`、`pages/meal/Form.vue` | 熱量／營養素標籤加圖示；拿掉「（選填）」；名稱、熱量打 `*`；「備註」同規則 |
| u1-04 | `profile/TodayTargetsCard.vue` | 「熱量目標」「已攝取」改成 `Target`、`Utensils` 圖示；蛋白質列用 `Drumstick` 與蛋白質色進度條；「還差 N g」改成「−N g」，達成時顯示 ✓ |
| u1-05 | `meal/TodayMealsCard.vue` | 列拿掉餐別文字，只留時間；「飲食頁 ›」改成圓形箭頭按鈕（`aria-label="飲食頁"`） |
| u1-06 | `water/TodayWaterCard.vue` | 「紀錄 ›」改成圓形箭頭按鈕（`aria-label="喝水紀錄"`） |
| u1-07 | `QuickAddDialog.vue` | 拿掉 description 與小字；三欄大圖示；順序：記錄餐點、喝水、體重、拍照分析、健身；即將推出的淡化並加 `Clock` 標記（`aria-label="即將推出"`） |
| u1-08 | `meal/MealCard.vue` | 拿掉「· N 個項目」 |
| u1-09 | `pages/meal/SavedFoods.vue` | 說明框改成頁首 ⓘ 按鈕，點了顯示說明（含「依最近使用排序」）；副標只留「N 項」 |
| u1-10 | `pages/meal/SavedFoods.vue`、`meal/FoodPicker.vue` | 列表營養素改成彩色小圖示＋數值；「最近使用：」改成 `History` 圖示 |

- `ResponsiveDialog` 的 description 若拿掉，要確認 radix 的 `DialogDescription` 缺少時不會在主控台出現無障礙警告；必要時保留 `sr-only` 說明。
- 個人資料相關頁（首次設定、我的、目標卡）的「蛋白質目標」不在本次範圍。

## 6. 測試

純前端改動，專案沒有前端測試；後端測試只檢查 props，不受影響。不新增測試情境，以既有 PHPUnit 全部通過、`npm run build`／`lint`／`format:check` 通過，加上 390px 手機與桌機、淺色與深色的畫面檢查作為驗收。
