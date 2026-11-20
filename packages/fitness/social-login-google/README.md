# social-login-google 模組

[social-login](../social-login/README.md) 的 Google driver，以 Laravel Socialite 實作。Socialite 只在本模組使用，核心模組不依賴它。

- 命名空間：`Fitness\SocialLoginGoogle`
- Service Provider：`Fitness\SocialLoginGoogle\SocialLoginGoogleServiceProvider`
- 容器綁定：`fitness.social_login.google` → `Drivers\Google`
- 設定：`config('fitness.social_login_google.*')`（`src/config/fitness/social-login-google.php`）

| 設定 | env | 說明 |
|---|---|---|
| `client_id` | `GOOGLE_CLIENT_ID` | OAuth 用戶端 ID；與密鑰任一沒填時 `isConfigured()` 為 false，登入頁與已連結帳號頁都不顯示 Google |
| `client_secret` | `GOOGLE_CLIENT_SECRET` | OAuth 用戶端密鑰 |
| `redirect` | `GOOGLE_REDIRECT_URI` | 授權後回到的網址，需與 Google Cloud Console 設定完全一致；`.env.example` 預設 `${APP_URL}/auth/google/callback`，留空時改用目前網域的 callback 路由 |
| `scopes` | — | `openid email profile`，只做登入，不呼叫其他 Google API |
| `guzzle` | `GOOGLE_HTTP_TIMEOUT` | 呼叫 Google token／userinfo 的 Guzzle 選項，預設逾時 10 秒 |

## 行為

- 使用 Socialite 預設的 state 驗證（存在 session，不用 stateless），callback 走 `web` middleware。
- 回傳的 `SocialUser`：id 為 Google 的 `sub`（數字字串）、email、name、avatar（頭像網址）。
- 使用者在 Google 按取消（`error=access_denied`）、state 不符、Google 回應錯誤，一律包成 `SocialAuthFailedException`。
- 不保存 access token／refresh token。

## Google OAuth 憑證建立步驟

開發與自動測試不需要（測試用假 driver，沒填憑證時按鈕自動隱藏）；要實際點擊驗收或上線時才需要。

1. 到 [Google Cloud Console](https://console.cloud.google.com/) 建立專案（免費）。
2. 「API 和服務 › OAuth 同意畫面」：使用者類型選「外部」，填應用程式名稱與聯絡信箱；發布狀態維持「測試中」，在「測試使用者」加入要驗收的 Google 帳號（測試中最多 100 位）。
3. 「API 和服務 › 憑證」→「建立憑證 › OAuth 用戶端 ID」→ 應用程式類型「網頁應用程式」→「已授權的重新導向 URI」加入 `http://localhost:8000/auth/google/callback`（localhost 允許 http）。
4. 把用戶端 ID／密鑰填入 `.env`：

   ```dotenv
   GOOGLE_CLIENT_ID=xxxxxxxx.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=xxxxxxxx
   GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
   ```

   `APP_URL` 要與實際開啟的網址一致（`composer dev` 為 `http://localhost:8000`）；否則 Google 會回 `redirect_uri_mismatch`。也可以直接把 `GOOGLE_REDIRECT_URI` 寫成完整網址。
5. 上線時在同一個用戶端另加正式網域的 https 重新導向 URI，並把 `GOOGLE_REDIRECT_URI` 改成正式網址。只用 `openid email profile`（非敏感權限），把同意畫面發布為正式版不需要 Google 審查。

設定後可用 `php artisan tinker --execute 'dump(social_login()->options());'` 確認 Google 出現在可用清單。
