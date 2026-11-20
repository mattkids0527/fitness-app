# 交接：M2 Google 登入

- 更新日期：2026-11-20
- 對應任務：[M2 Google 登入](../milestones/M2-google-login.md)
- 分支：`milestone/M2-google-login`
- 目前進度：T1～T4 完成（T4 的真實 Google 點擊驗收待使用者建立憑證）；下一步 T5 測試，先列情境給使用者確認。

## 下次接手從這裡開始

1. 確認在 `milestone/M2-google-login` 分支；`docker compose up -d` 啟動資料庫。
2. 拉下來後要 `composer install`（新增 `laravel/socialite`）、`composer dump-autoload`（新增 `Fitness\SocialLogin`、`Fitness\SocialLoginGoogle` 與 `autoload.files` 的 helper）並 `php artisan migrate`（新表 `social_accounts`）。
3. 實際點擊驗收前：依 `packages/fitness/social-login-google/README.md` 建立 Google 憑證填入 `.env`；目前本機 `.env` 的 `APP_URL=http://localhost`（沒有 `:8000`），要改成 `http://localhost:8000` 或直接填完整的 `GOOGLE_REDIRECT_URI`，否則 Google 會回 `redirect_uri_mismatch`。

## T1 設計 demo

- 定稿在 `vibe-coding/ui/features/m2-google-login/`，畫面情境與定案設計見該目錄 README。
- 已決：Google 按鈕文字「使用 Google 帳戶登入」；成功用 Toast、失敗用頁內錯誤提示框；設定分頁選取改橘色淺底（既有分頁一併改）；未設定 provider 時入口照常顯示、頁面顯示「目前沒有可連結的服務」。

## T2 `social-login` 核心模組

- 模組：`packages/fitness/social-login/`，內容與路由見模組 README。
- 註冊：根目錄 `composer.json`（psr-4、autoload-dev、`autoload.files` 載入 `helpers.php`）、`bootstrap/providers.php`。
- `fitness.profile.onboarding_except_routes` 加入 `fitness.social_login.callback`。
- `.env.example` 加 `SOCIAL_LOGIN_PROVIDERS=google`。
- **登入頁的可用 provider 清單（本 Task 決定）**：由 social-login 模組的 Provider 以 `Inertia::share('social_login', fn () => ['providers' => social_login()->options()])` 共享給所有頁面。選這個做法是因為 `app/` 的 `HandleInertiaRequests` 與 `AuthenticatedSessionController` 都不用改、不依賴模組；清單只讀設定與容器綁定、不查資料庫，每頁都帶的成本可忽略。前端型別在 T4 放 `types/social-login.ts`。
- **flash**：`HandleInertiaRequests::share()` 加 `flash.success`（字串）與 `flash.error`（`{title, message}`），對應 `SharedData.flash`（`resources/js/types/index.ts` 的 `Flash`、`FlashError`）。前端接 `AppToast` 與錯誤提示框留待 T4-2。
- `User::socialAccounts()` 以 `resolveRelationUsing` 在模組內動態加入，`app/Models/User.php` 未修改。
- 意圖存在 session `social_login.intent`（action、provider、user_id），callback 以 `pull` 取出只用一次；沒有紀錄、provider 不符、綁定回來時已登出或換帳號都視為 `SocialAuthFailedException`。
- 綁定寫入包在 `DB::transaction`（savepoint）裡，PostgreSQL 唯一鍵衝突時只回滾該次寫入，再以檢查轉成對應 Exception。
- 驗證：`migrate:fresh`（測試庫）、rollback 再 migrate 成功；`route:list` 有 5 條 `fitness.social_login.*`；未設定 driver 時 `available()` 為空、登入頁正常、`/auth/google/redirect` 404。另以假 driver 跑過一支暫時的端到端測試（綁定、重複綁定、被他人綁定、登入更新資料、解除、取消授權皆符合預期），已刪除，正式測試於 T5 撰寫。既有 67 tests 通過、Pint 通過。
- `Inertia::render('social-login/ConnectedAccounts')` 的頁面元件 T4-1 建立。

## T3 `social-login-google` driver

- 模組：`packages/fitness/social-login-google/`，`Drivers\Google` 繼承核心的 abstract `SocialProvider`，Provider 綁定 `fitness.social_login.google`；憑證建立步驟見模組 README。
- `laravel/socialite ^5.33` 安裝在根目錄 `composer.json`（只有本模組使用），模組 `composer.json` 也列為依賴。
- Socialite provider 以 `Socialite::buildProvider()` 依模組設定建立（不用 `config/services.php`），每次呼叫重建以取得當下 request；scope 明確設為 `openid email profile`，使用預設 state 驗證。
- `isConfigured()`：client id 與密鑰都有填才為 true（規格只寫 client id；沒有密鑰時 callback 必定失敗，所以一起檢查）。
- `GOOGLE_REDIRECT_URI` 沒設定時改用 `route('fitness.social_login.callback', 'google')`（目前網域）；`.env.example` 依規格預設 `${APP_URL}/auth/google/callback`。
- 取消授權（`error=access_denied`）、state 不符與 Socialite／HTTP 例外皆包成 `SocialAuthFailedException`。
- 驗證：未填憑證時 `available()` 為空、`/auth/google/redirect` 404；填假 client id 後 302 到 `accounts.google.com/o/oauth2/auth`，帶正確 `redirect_uri`、`scope=openid email profile`、`state`；未設 `GOOGLE_REDIRECT_URI` 時 redirect_uri 為目前網域的 callback；`?error=access_denied` 與錯誤 state 的 callback 皆導回登入頁。既有 67 tests、Pint 通過。

## T4-1 已連結帳號頁與入口

- `pages/social-login/ConnectedAccounts.vue`：套 AppLayout + 設定 Layout；列出可用 provider（`ConnectedAccountItem`），沒有可用 provider 時顯示「目前沒有可連結的服務」；解除連結以 `ResponsiveDialog` 確認後 `router.delete`。
- `components/social-login/`：`ProviderLogo`（Google 四色 G，其他平台暫用連結圖示，T4-2 登入按鈕共用）、`ConnectedAccountItem`（頭像用 `referrer-policy="no-referrer"`，避免 Google 頭像因 referrer 被擋；連結按鈕用一般 `<a>` 整頁跳轉）。
- `types/social-login.ts`：`SocialProviderOption`、`SocialLoginSharedData`、`LinkedSocialAccount`、`ConnectedProvider`。
- `layouts/settings/Layout.vue`：加「已連結帳號」分頁、說明改為「管理帳號、密碼、外觀與已連結帳號」、選取樣式改 `bg-brand-soft text-primary`（其他分頁一併套用）。
- `pages/profile/Show.vue`：「已連結帳號」列改為連到已連結帳號頁。**與 demo 不同**：右側不顯示「Google／未連結」——那需要每頁多一次查詢或讓 profile 依賴 social-login，而且同區塊的「外觀」列也只有箭頭，保持一致。
- 驗證：`npm run build`、`lint`、`format:check` 通過；以暫時帳號在 390px（淺色）與 1280px（深色）檢查已連結、解除確認面板、解除後未連結、我的頁入口，暫時帳號已刪除。

## T4-2 登入頁與回呼提示

- `components/social-login/SocialLoginButtons.vue`：讀共享的 `social_login.providers`，顯示「或」分隔線與「使用 {label} 帳戶登入」按鈕（Google 品牌樣式，深色模式深底）；沒有可用 provider 時整塊不顯示。以一般 `<a>` 整頁跳轉。
- `components/FlashAlert.vue`（跨模組共用）：顯示 `flash.error` 的標題與說明；用在登入頁與已連結帳號頁。
- `components/AppToast.vue`：監看 `page.props.flash`，有 `success` 就顯示 Toast（每次回應都是新的 flash 物件，同樣文字連續出現也會顯示）。登入頁（AuthLayout）沒有 AppToast，成功登入不需要提示。
- 驗證：以假憑證起 server，Playwright 攔截 accounts.google.com 後模擬 `?error=access_denied` 回來——登入頁與已連結帳號頁都顯示對應錯誤提示框、重新整理後消失；解除連結後顯示「已解除連結 Google」Toast；未設定憑證時登入頁沒有分隔線與 Google 按鈕。390px 淺色、1280px 深色檢查過。build、lint、format:check、Pint、67 tests 通過。
- **尚未做**：實際用 Google 帳號走完「帳密登入 → 連結 Google → 登出 → Google 登入 → 解除連結 → Google 登入被拒」，需使用者先依 `social-login-google` README 建立憑證並修正 `APP_URL`（見上方「下次接手」第 3 點）。
