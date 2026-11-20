# 交接：M4 Gemini 照片分析

- 更新日期：2026-11-21
- 對應任務：[M4 Gemini 照片分析](../milestones/M4-photo-analysis.md)
- 依據規格：[M4 規格](../specs/2026-11-21-m4-photo-analysis.md)
- 設計定稿：[ui/features/m4-photo-analysis](../ui/features/m4-photo-analysis/README.md)
- 分支：`develop`
- 目前進度：規格定稿、demo 確認（T1 完成）、milestone Task 拆分已確認。**還沒寫任何程式**，下一步是 T2。

## 下次接手從這裡開始

1. `git checkout develop && git pull --ff-only`。
2. 先讀規格 §2（已決 Q1～Q10）與 §3～§6，再讀 milestone 的 T2。寫程式前依 CLAUDE.md 讀 `vibe-coding/coding-style.md`；Driver 模式可參考 `social-login`／`social-login-google`（Manager、helper、`FakeSocialProvider`）。
3. T2 開始前跟使用者確認一次再動手；每個 Task 完成就 commit 並 push，再詢問是否進行下一個 Task。
4. T3 需要使用者：到 Google AI Studio 申請 `GEMINI_API_KEY` 填進本地 `.env`，並準備 5～10 張真實餐點照片做模型實測。可以在 T2 進行時先請使用者準備。
5. `develop` 上還有一個沒部署的小改動：未綁定 Google 登入的錯誤說明加上「還沒有帳號的話，請聯絡管理員。」（見 [D2 handoff](2026-11-21-D2-release-versioning.md)）。如果使用者在 M4 完成前想先上線，以 `v1.3.4` 部署；否則跟著 M4 一起以 `v1.4.0` 上線。

## 重要決定（跟總覽規格不同的地方）

- **照片不存伺服器**：只在分析的那次請求裡轉送給 Gemini，不呼叫 `store()`、不產生縮圖。照片在使用者確認前留在瀏覽器記憶體（composable），失敗時可以直接重送。
- **同步分析**：不用 queue，正式環境維持 `QUEUE_CONNECTION=sync`，VM 不加 worker，`provision.sh` 不用改。Gemini 呼叫 25 秒逾時（PHP 預設 30 秒、nginx 60 秒以內）。
- **Gemini 先用免費層**：已告知使用者，Google 可能用上傳內容改進產品，也有次數限制（429 要顯示「Gemini 暫時忙碌」）。圖片以 inline data 送出，**不用 Files API**（避免照片在 Google 端保存 48 小時）。
- **兩種入口**：「拍照」（`capture="environment"`，桌機隱藏，用餐時間＝現在）與「上傳照片」（用 `exifr` 讀拍攝時間當用餐時間）。＋選單原本的「拍照分析」拿掉，改成兩格直接進入。
- **確認前不建立餐點**：`meals.status` 不加新狀態。分析成功導向 `GET /meals/create?analysis={id}`，由伺服器讀回 `food_analyses` 的結果帶入表單；同一筆分析只能建立一次餐點。
- **每日 20 次**：每送出一次給 Gemini 就算一次（含失敗、重新分析）；超過上限不呼叫 Gemini。
- **demo 定案細節**：看不出食物時主按鈕為「重拍／換一張」；信心度三格訊號（高 ≥ 0.75、中 ≥ 0.5、低 < 0.5），「低」用橘色並在展開時提醒；飲食頁由照片建立的餐點標「照片」。

## 這次 session 的其他變更

- D2 收尾：milestone 標完成、寫 handoff；文件更正第一個 tag 是 `v1.3.1`（不是 `v1.3.0`）。
- 使用者確認：超過熱量目標變紅的畫面 OK、03:00 自動備份正常、iOS PWA 下 Google 登入與解除綁定正常。

## 尚未處理（之後再排）

- Google OAuth 同意畫面仍是「測試中」。
- D1 列的部署改善：`rollback.yml`、SSH 走 Tunnel、部署金鑰分開、異地備份。
