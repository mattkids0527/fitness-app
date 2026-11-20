# 交接：M2 Google 登入

- 更新日期：2026-11-20
- 對應任務：[M2 Google 登入](../milestones/M2-google-login.md)
- 分支：`milestone/M2-google-login`
- 目前進度：T1、T2 完成；下一步 T3 `social-login-google` driver。

## 下次接手從這裡開始

1. 確認在 `milestone/M2-google-login` 分支；`docker compose up -d` 啟動資料庫。
2. 拉下來後要 `composer dump-autoload`（新增了 `Fitness\SocialLogin` 與 `autoload.files` 的 helper）並 `php artisan migrate`（新表 `social_accounts`）。

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
