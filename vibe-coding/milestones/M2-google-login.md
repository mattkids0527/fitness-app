# M2 Google 登入

- 狀態：進行中
- 建立日期：2026-11-20
- 分支：`milestone/M2-google-login`（完成後開 PR 合回 `main`）
- 依據規格：[M2 Google 登入規格](../specs/2026-11-20-m2-google-login.md)、[專案總覽規格 v1.0](../specs/2026-11-19-project-overview.md) §3.1、§2.4
- 寫作規範：[模組化寫作規範](../coding-style.md)（§7 Driver 模式）

## 目標

已登入的使用者可在設定頁「已連結帳號」綁定／解除綁定 Google；綁定後可在登入頁用 Google 登入。未綁定的 Google 帳號不能登入、不自動建立帳號。資料表與核心流程不綁死 Google，日後加 LINE 只需新增 driver 模組。

## 範圍

**包含**
- `social-login` 核心模組：`social_accounts` 表、driver 合約、Manager、綁定／登入／解除綁定流程、已連結帳號頁
- `social-login-google` driver 模組（Socialite）
- 登入頁 Google 按鈕、「我的」與設定側欄的入口
- OAuth 回呼後的結果提示（成功／失敗訊息）

**不包含**
- LINE 或其他平台（只保留擴充點）
- 以第三方帳號註冊、以 email 自動綁定
- 站內大頭貼、儲存 access／refresh token、呼叫 Google API
- 部署與正式網域設定

## Tasks

### T1 設計 demo 畫面
以設計師角色在 `vibe-coding/ui/prototypes/<日期時間>-m2-google-login/` 用 html / css / js 刻 demo，沿用 M1 定稿的版型與橘色 token：登入頁（帳密表單下方「或」分隔線 + 「使用 Google 登入」按鈕、未綁定時的錯誤提示）、已連結帳號頁（未連結／已連結兩種狀態：頭像、Google email、連結時間、連結／解除連結按鈕）、解除連結確認對話框、綁定成功／失敗提示、「我的」頁入口與設定側欄分頁；手機與桌機對照、淺色與深色。與使用者來回調整直到確認，確認後移到 `vibe-coding/ui/features/m2-google-login/`，寫 README 條列畫面情境並更新 `vibe-coding/ui/index.md`。
**完成條件**：使用者確認 demo；features 目錄與 index 已更新。

### T2 `social-login` 核心模組與後端
建立 `packages/fitness/social-login/`（命名空間 `Fitness\SocialLogin`），依寫作規範註冊到根目錄 `composer.json`（含 tests autoload-dev）與 `bootstrap/providers.php`。內容：migration `social_accounts`（欄位與兩個唯一索引依規格 §2.1）、`SocialAccount` Model 與 `User` 的關聯、`SocialAccountRepository`（含 Contract）；driver 合約 `Drivers/Contracts/SocialProvider`、abstract 基底、`SocialUser` DTO、`Services/SocialProviderManager`（以別名取得 `fitness.social_login.<provider>`，`available()` 只列出已啟用且已設定的 provider）；設定檔 `config/fitness/social-login.php`（`providers` 走 `SOCIAL_LOGIN_PROVIDERS`）；`Services/SocialLoginService` 處理綁定／登入（預設記住我）／解除綁定，失敗以規格 §3.1 的各 Exception 表示，唯一鍵衝突轉成對應 Exception；路由 redirect／link／callback（共用 callback，以 session 記錄意圖與發起者）、已連結帳號頁、解除綁定，callback 加到 `onboarding_except_routes`；語系 `FitnessSocialLogin::`。回呼結果以 session flash 帶回，`HandleInertiaRequests` 加共享的 `flash`（同步 `SharedData` 型別），登入頁所需的可用 provider 清單由模組提供，不讓 `app/` 依賴模組（實作方式在此 Task 決定並記錄於 handoff）。頁面元件留待 T4。
**完成條件**：程式碼檢視符合寫作規範；`php artisan migrate:fresh` 成功；`route:list` 看得到 `fitness.social_login.*`；未設定任何 driver 時登入頁正常、`available()` 為空。

### T3 `social-login-google` driver
建立 `packages/fitness/social-login-google/`（命名空間 `Fitness\SocialLoginGoogle`），安裝 `laravel/socialite`（只在此模組使用）；實作 `SocialProvider`（scope `openid email profile`、使用 Socialite 預設的 state 驗證（不用 stateless）、把 Socialite 使用者轉成 `SocialUser`），Provider 綁定 `fitness.social_login.google`；設定 `GOOGLE_CLIENT_ID`、`GOOGLE_CLIENT_SECRET`、`GOOGLE_REDIRECT_URI`（預設 `${APP_URL}/auth/google/callback`），沒有 client id 時 `isConfigured()` 為 false；`.env.example` 補上變數；模組 README 附 Google Cloud 憑證建立步驟（規格 §8）。
**完成條件**：未設定憑證時 Google 不出現在 `available()`；填入任意假 client id 後，`/auth/google/redirect` 導向 `accounts.google.com` 且帶正確 redirect_uri 與 scope。

### T4 前端實作
依確認的 demo 實作，拆成以下子步驟，每步完成後 commit 並詢問是否進行下一步。
**完成條件**：`npm run build`、`npm run lint`、`npm run format:check` 通過；390px 手機與桌機寬度檢查畫面；若使用者已建立 Google 憑證，實際走完「帳密登入 → 連結 Google → 登出 → Google 登入 → 解除連結 → Google 登入被拒」。

#### T4-1 已連結帳號頁與入口
`pages/social-login/ConnectedAccounts.vue`（套設定頁 Layout）、模組元件放 `components/social-login/`、型別 `types/social-login.ts`；解除連結確認對話框；「我的」頁加「已連結帳號」一列、設定側欄加分頁。

#### T4-2 登入頁與回呼提示
登入頁加「或」分隔線與 Google 按鈕（無可用 provider 時不顯示）、未綁定等錯誤訊息；共享 `flash` 接到 `AppToast`，綁定成功／解除連結等結果以提示顯示。

### T5 測試
先把測試情境條列給使用者確認（預計涵蓋：未設定時不顯示／不能進入 redirect、綁定成功、Google 帳號已綁定他人、自己已綁定過、登入成功（含記住我）、未綁定拒絕登入、未完成首次設定者 Google 登入後被導向 onboarding、解除綁定只能操作自己的、刪除帳號一併刪除綁定、取消授權／state 錯誤的處理），測試以假 driver 取代 Google、不連外；確認後寫成 `vibe-coding/tests/<日期>-social-login.md` 並更新 `vibe-coding/tests/index.md`，再於 `packages/fitness/social-login/tests/`（與必要時 `social-login-google/tests/`）撰寫並執行。
**完成條件**：使用者確認情境；全部測試（含既有測試）通過、無 skip；Pint、Prettier、ESLint 通過。

## 進度

| Task | 狀態 |
|---|---|
| T1 設計 demo | 完成（2026-11-20） |
| T2 social-login 核心與後端 | 完成（2026-11-20） |
| T3 social-login-google driver | 完成（2026-11-20） |
| T4 前端實作 | 未開始 |
| T5 測試 | 未開始 |
