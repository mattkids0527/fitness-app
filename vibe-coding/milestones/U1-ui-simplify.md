# U1 介面精簡

- 狀態：進行中
- 建立日期：2026-11-20
- 分支：`develop`（每個 Task 完成就 commit 並 push；PR `develop` → `main` 由使用者開與合併）
- 依據規格：[U1 介面精簡規格](../specs/2026-11-20-u1-ui-simplify.md)
- 設計定稿：[ui/features/u1-ui-simplify](../ui/features/u1-ui-simplify/README.md)

## 目標

鎖住手機上的頁面縮放；能用圖示表達的地方減少文字（營養素、今日頁卡片、＋選單、飲食頁、常用食物頁）。

## 範圍

**包含**
- 縮放鎖定（CSS、viewport、iPhone 手勢）
- 營養素圖示與顏色集中定義，套用到合計格、表單標籤、食物列表、今日頁蛋白質列
- 表單必填標示規則：拿掉「（選填）」，必填打 `*`
- 今日頁熱量卡、餐點卡、喝水卡；＋選單；飲食頁餐點卡；常用食物頁說明框

**不包含**
- 後端、資料表、路由、props
- 底部 Tab 文字、kcal／g 單位、確認類按鈕文字
- 個人資料相關頁（首次設定、我的、目標卡）的蛋白質目標

## Tasks

### T1 設計 demo（完成）
左右對照原本與改後，定稿在 `vibe-coding/ui/features/u1-ui-simplify/`（u1-01～u1-10），已更新 `vibe-coding/ui/index.md`。
**完成條件**：使用者確認 demo。

### T2 縮放鎖定
`app.blade.php` 與 `public/offline.html` 的 viewport 加 `maximum-scale=1, user-scalable=no`；`app.css` 的 `html` 加 `touch-action: manipulation`；`app.ts` 擋 `gesturestart`；`offline.html` 是 service worker 預先快取的檔案，`sw.js` 的 `CACHE_VERSION` 改成 `v2` 讓舊快取更新；確認所有輸入框（含 textarea）在手機上是 16px 以上（目前都是 `text-base`）。
**完成條件**：Playwright 以手機模擬確認點兩下不會改變縮放比例；build、lint、format:check 通過。

### T3 營養素圖示與表單標籤
新增共用的營養素定義（圖示、顏色、名稱），套用到 `NutrientGrid`、`MealItemCard`、常用食物面板與列表、`FoodPicker` 清單、`TodayTargetsCard` 蛋白質列（含 −N g 與達成 ✓）；表單拿掉「（選填）」、名稱與熱量打 `*`，「備註」同規則。（u1-02、u1-03、u1-10，u1-04 的蛋白質列）
**完成條件**：390px 與桌機、淺色與深色對照 demo 檢查；build、lint、format:check 通過。

### T4 今日頁卡片、＋選單、餐點卡、常用食物說明
`TodayTargetsCard` 的熱量目標／已攝取改圖示；`TodayMealsCard`、`TodayWaterCard` 的列與右上連結；`QuickAddDialog` 改三欄大圖示與順序、「記錄餐點」改名；`MealCard` 拿掉項目數；常用食物頁說明框改 ⓘ。只有圖示的按鈕加 `aria-label`。（u1-04～u1-09）
**完成條件**：同 T3；另外跑全部 PHPUnit 確認沒有受影響，主控台沒有新的警告。

### 部署備註
只有前端與文件變更，合併到 `main` 後照常以 Actions 部署；不需要 migration，也不需要改 `provision.sh`。`/build/` 資源檔名有 hash，會自動更新；`offline.html` 是預先快取的，T2 已調整 `CACHE_VERSION`。正式站驗收後寫 handoff 並把 milestone 標為完成。

## 進度

| Task | 狀態 |
|---|---|
| T1 設計 demo | 完成（2026-11-20） |
| T2 縮放鎖定 | 未開始 |
| T3 營養素圖示與表單標籤 | 未開始 |
| T4 今日頁卡片、＋選單、餐點卡、常用食物說明 | 未開始 |
