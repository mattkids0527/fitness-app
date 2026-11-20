# M2 Google 登入規格

> 狀態：已定稿（2026-11-20）。任務見 [M2 milestone](../milestones/M2-google-login.md)。依據 [專案總覽規格 v1.0](2026-11-19-project-overview.md) §3.1、§2.4、§4。
> 程式寫法依 [模組化寫作規範](../coding-style.md)（§7 Driver 模式）。
> 「待決」標記的項目需要使用者拍板；「已決」記錄討論結果。

## 1. 目標

已經用帳密登入的使用者，可以在設定頁綁定自己的 Google 帳號；綁定後下次可以直接按「使用 Google 登入」。Google 帳號沒有綁定任何使用者時，不能用來登入，也不會自動建立帳號。

## 2. 已決（來自總覽規格）

- 第一版只做 Google。
- 帳號一定先有 Email + 密碼（由 `user:create` 建立），綁定 Google 是登入後的動作。
- 未綁定的 Google 帳號嘗試登入 → 拒絕，提示「請先以帳號密碼登入，再到設定頁綁定 Google」。說明最後加上「還沒有帳號的話，請聯絡管理員。」（已決，2026-11-21）
- 因為每個帳號都有密碼，Google 帳號遺失時仍可用帳密登入並解除綁定。
- 模組拆成 `social-login`（核心）與 `social-login-google`（driver）。

## 2.1 資料表設計（已決，2026-11-20）

帳號一張表（`users`，不加任何第三方欄位）、第三方連結一張表（`social_accounts`），一對多：一個帳號可綁多個平台，**每個平台各一組**；同一組第三方帳號只能綁一個帳號。日後加 LINE 等平台只需新增 driver 模組，資料表不變。

**`social_accounts`**

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | 主鍵 |
| user_id | bigint，FK → users | 刪除帳號時一併刪除（cascade） |
| provider | varchar(32) | provider 別名：`google`，日後 `line` |
| provider_user_id | varchar(191) | 第三方使用者 id。存字串、不用 uuid 型別（Google 為數字字串 `sub`，LINE 為 `U` 開頭 33 碼） |
| email | varchar，nullable | 第三方回傳的 email（LINE 需另外申請權限，可能沒有） |
| name | varchar，nullable | 顯示用 |
| avatar | varchar，nullable | 顯示用 |
| created_at / updated_at | timestamp | created_at 即連結時間 |

**唯一索引**

| 索引 | 規則 |
|---|---|
| `UNIQUE(provider, provider_user_id)` | 同一組第三方帳號只能綁一個帳號 |
| `UNIQUE(user_id, provider)` | 一個帳號在每個平台只能綁一組 |

- 規則由程式與資料庫雙重把關：程式先檢查並丟出對應 Exception 顯示友善訊息；同時綁定的競態由唯一索引擋下（寫入時捕捉唯一鍵衝突，轉成同一個 Exception）。
- 日後串 LINE 時注意：LINE 的 userId 依 LINE Developers 的 Provider 而不同，需固定使用同一個 Provider，否則既有綁定會失效。

## 3. 模組設計

### 3.1 `social-login`（核心）

| 項目 | 內容 |
|---|---|
| 資料表 | `social_accounts`（見 §2.1） |
| Driver 合約 | `Drivers/Contracts/SocialProvider`：`redirect()`、`user()`（回傳模組自己的 `SocialUser` DTO：id、email、name、avatar）、`label()`、`isConfigured()` |
| Manager | `Services/SocialProviderManager`：以別名取得 `fitness.social_login.<provider>`；`available()` 列出已啟用且已設定的 provider |
| 設定 | `config/fitness/social-login.php`：`providers`（啟用的 provider 別名清單，env `SOCIAL_LOGIN_PROVIDERS=google`） |
| 路由 | `GET /auth/{provider}/redirect`（登入用，guest）、`GET /auth/{provider}/link`（綁定用，auth）、`GET /auth/{provider}/callback`（共用）、`DELETE /settings/connected-accounts/{provider}`（解除綁定）、`GET /settings/connected-accounts`（頁面） |
| 例外 | 每種失敗一個類別：`SocialAccountNotLinkedException`（登入時未綁定）、`SocialAccountAlreadyLinkedException`（此 Google 帳號已綁定其他使用者）、`ProviderAlreadyLinkedException`（自己已綁定過該 provider）、`ProviderNotAvailableException`（未啟用／未設定）、`SocialAuthFailedException`（使用者在 Google 按取消、state 不符等） |

> Google 的 callback 網址只能設定固定一條，所以「登入」和「綁定」共用 callback，進 Google 前把意圖（login / link）與發起的使用者 id 存在 session，回來時依意圖分流。

### 3.2 `social-login-google`（driver）

- 安裝 `laravel/socialite`，只在這個模組使用；核心模組不依賴 Socialite。
- 實作 `SocialProvider`，Provider 綁定到 `fitness.social_login.google`。
- 設定：`GOOGLE_CLIENT_ID`、`GOOGLE_CLIENT_SECRET`、`GOOGLE_REDIRECT_URI`（預設 `${APP_URL}/auth/google/callback`）。沒有 client id 時 `isConfigured()` 為 false，登入頁與設定頁都不顯示 Google 按鈕（本地沒設定也能正常開發與跑測試）。
- 只要 `openid email profile` 權限，不存 access token / refresh token（只做登入，不呼叫 Google API）。

## 4. 流程

### 4.1 綁定
1. 設定頁「已連結帳號」按「連結 Google」→ `/auth/google/link` → 導到 Google。
2. 回到 callback：
   - 此 Google 帳號已綁定**其他**使用者 → 失敗，提示「這個 Google 帳號已連結到其他帳號」。
   - 自己已綁定過 Google → 失敗（正常操作不會發生，按鈕會隱藏）。
   - 成功 → 寫入 `social_accounts`，回設定頁並顯示「已連結 Google」。

### 4.2 登入
1. 登入頁「使用 Google 登入」→ `/auth/google/redirect` → Google。
2. 回到 callback：以 `(provider, provider_user_id)` 找綁定紀錄。
   - 找到 → 登入該使用者 → 進今日頁（未完成首次設定的照樣被導到 `/onboarding`）。
   - 找不到 → 回登入頁，顯示「這個 Google 帳號尚未連結，請先以帳號密碼登入，再到設定頁綁定 Google。還沒有帳號的話，請聯絡管理員」。
   - **不**用 email 比對自動綁定（避免帳號被接管）。
3. 每次登入時更新綁定紀錄的 email／name／avatar。

### 4.3 解除綁定
- 設定頁按「解除連結」→ 確認對話框 → 刪除綁定紀錄。

## 5. 畫面（已於 demo 確認，見 [M2 畫面](../ui/features/m2-google-login/README.md)）

- 登入頁：帳密表單下方加分隔線「或」與「使用 Google 帳戶登入」按鈕（Google 品牌規範的繁中標準文字）；未設定 Google 時不顯示。
- 已連結帳號頁：列出 Google（頭像、Google email、連結時間），未連結時顯示「連結」按鈕；未設定任何 provider 時顯示「目前沒有可連結的服務」，入口照常顯示。
- 入口：「我的」頁設定區塊的「已連結帳號」列改為可點；設定頁加一個分頁，分頁選取樣式改為橘色淺底（其他設定分頁一併套用）。
- 結果提示：成功用 Toast；失敗用頁內錯誤提示框（不自動消失）。

## 6. 決策紀錄（2026-11-20 已決）

| # | 問題 | 決定 |
|---|---|---|
| Q1 | 解除連結是否要先輸入密碼確認？ | 不用，確認對話框即可（帳密仍可登入，風險低） |
| Q2 | 用 Google 登入是否預設「記住我」？ | 是。手機 PWA 每次重登很麻煩；帳密登入仍依勾選 |
| Q3 | Google email 與帳號 email 不同時能否綁定？ | 可以，只在連結頁顯示 Google email 供辨識 |
| Q4 | Google 頭像是否拿來當站內大頭貼？ | 不用，只在已連結帳號頁顯示；站內大頭貼另議 |
| Q5 | 已連結帳號頁放哪？ | 放設定（`/settings/connected-accounts`），頁面檔由 social-login 模組擁有（`pages/social-login/ConnectedAccounts.vue`），「我的」頁加入口 |
| Q6 | Google Cloud OAuth 憑證 | 開發與測試不需要（測試用假 driver，未設定時隱藏按鈕）；實際點擊驗收與上線才需要，使用者於驗收前在 Google Cloud Console 建立（見 §8） |
| Q7 | demo 與後端的順序 | demo 先做：畫面少，先確認流程與文案再寫後端 |

## 8. Google OAuth 憑證建立步驟（驗收前）

1. Google Cloud Console 建立專案（免費）。
2. 「OAuth 同意畫面」：使用者類型選「外部」，發布狀態維持「測試中」，把要驗收的 Google 帳號加入「測試使用者」（測試中最多 100 位）。
3. 「憑證」→ 建立「OAuth 用戶端 ID」→ 類型「網頁應用程式」→ 授權的重新導向 URI 加 `http://localhost:8000/auth/google/callback`（localhost 允許 http）。
4. 把用戶端 ID／密鑰填入 `.env` 的 `GOOGLE_CLIENT_ID`、`GOOGLE_CLIENT_SECRET`。
5. 上線時另加正式網域的 https 重新導向 URI；只用 `openid email profile`（非敏感權限），發布為正式版不需 Google 審查。

## 7. 風險與注意

- **iOS 加到主畫面（PWA standalone）**：外部 OAuth 頁可能在另一個瀏覽器環境開啟，登入後 cookie 回不到 PWA。M2 實作後需用實機驗證；若有問題，備案是登入頁在 standalone 模式下提示「請在 Safari 中使用 Google 登入」。（部署前本地無 HTTPS，實機驗證可能要等部署規格。）
- Socialite 的 state 驗證依賴 session，callback 必須走 `web` middleware。
- callback 路由要加到 `fitness.profile.onboarding_except_routes`，避免未完成設定的使用者綁定流程被打斷（實際上未完成設定進不了設定頁，但登入流程會經過 callback）。
- 測試不打真的 Google：以假 driver（測試中綁定 `fitness.social_login.google` 為 fake）或 mock Socialite。
