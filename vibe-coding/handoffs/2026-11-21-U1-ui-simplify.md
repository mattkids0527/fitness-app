# 交接：U1 介面精簡

- 更新日期：2026-11-21
- 對應任務：[U1 介面精簡](../milestones/U1-ui-simplify.md)
- 依據規格：[U1 規格](../specs/2026-11-20-u1-ui-simplify.md)
- 設計定稿：[ui/features/u1-ui-simplify](../ui/features/u1-ui-simplify/README.md)
- 分支：`develop`（已由使用者以 PR #3 合併 `main` 並部署）
- 目前進度：U1 全部完成（T1～T4）。正式站已部署，使用者驗收通過，包含手機實測縮放鎖定。

## 下次接手從這裡開始

1. `git checkout develop && git pull --ff-only`。這份 handoff 與 milestone 狀態只改了文件，等使用者下次開 PR 一起合進 `main`，不需要部署。
2. U1 只改前端，沒有 migration 與後端變更；測試仍是 122 個，全部通過。
3. 之後新增畫面時要遵守的規則已寫進 CLAUDE.md「前端結構」：營養素圖示、表單必填標示、只有圖示的按鈕要有名稱、手機輸入框 16px 以上。

## 重點

### 縮放鎖定
- `resources/views/app.blade.php` 與 `public/offline.html`：viewport 加 `maximum-scale=1, user-scalable=no`（Android 兩指縮放）。
- `resources/css/app.css`：`html { touch-action: manipulation }`（點兩下放大，同時去掉點擊延遲）。
- `resources/js/app.ts`：`preventPinchZoom()` 擋 iOS `gesturestart`（Safari 忽略 `user-scalable=no`）；`offline.html` 有同樣的內嵌 script。
- `public/sw.js` 的 `CACHE_VERSION` 改成 `v2`，讓預先快取的 `offline.html` 更新。
- headless Chromium 不會模擬點兩下與兩指縮放，自動化只能檢查設定是否生效；實際手勢要用手機確認。

### 圖示取代文字
- 營養素：`composables/useNutrients.ts`（`NUTRIENTS`、`MACROS`、`nutrientMeta()`）集中定義圖示與顏色，不要在元件裡各自寫。
  - 格子（`NutrientGrid`）：圖示＋數值＋小字。
  - 列表（`MacroValues`，用在常用食物頁、快速帶入、餐點項目收合摘要）：圖示＋數值。
  - 表單標籤：圖示＋文字。
- 表單標籤：`components/FieldLabel.vue`（`icon`、`icon-class`、`required`）。必填打橘色 `*`，輸入框加 `aria-required`；選填不標示。日期、時間、餐別一定有預設值，不打 `*`。
- 今日頁：熱量卡用 `Target`／`Utensils` 圖示；蛋白質列顯示「−N g」，達成時顯示 ✓；餐點卡每列只留時間；餐點卡與喝水卡的右上連結改成圓形箭頭按鈕（`aria-label` 為「飲食頁」「喝水紀錄」）。
- ＋選單：三欄大圖示，「手動記錄」改名「記錄餐點」，即將推出的淡化並加時鐘標記。
- 飲食頁餐點卡拿掉項目數；常用食物頁說明改成頁首 ⓘ（點外面或按 Esc 關閉）。
- 拿掉的文字（餐別、熱量目標、已攝取、即將推出、最近使用）都以 `sr-only` 保留給螢幕閱讀器。

### 其他
- ESLint 會檢查 `vibe-coding/` 底下的 demo js。demo 的多個 script 共用變數時要掛在 `window` 上，不要宣告沒用到的全域 `const`（`aec1a59` 曾因此讓 CI 的 `quality` 失敗，已在 `15f5112` 修正）。

## 尚未確認的項目

- 今日頁與飲食頁「超過熱量目標變紅」：M3 和 U1 都只在邏輯與測試驗證過，沒有實際看過畫面。
- D1 留下的項目：cron 自動備份檔、iOS PWA standalone 實機測 Google 登入。
- M2 留下的待決事項：未綁定的 Google 登入，錯誤說明要不要加「還沒有帳號的話，請聯絡管理員」。
