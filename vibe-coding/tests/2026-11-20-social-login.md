# social-login（Google 登入、綁定、解除綁定）測試情境

- 建立日期：2026-11-20
- 對應任務：[M2 Google 登入 T5](../milestones/M2-google-login.md)
- 測試程式：`packages/fitness/social-login/tests/`、`packages/fitness/social-login-google/tests/`
- 原則：不連外。核心模組以假 driver（`Tests\Fakes\FakeSocialProvider`）取代 Google；Google driver 以 Guzzle MockHandler 模擬 token／userinfo 回應。
- 情境已於 2026-11-20 由使用者確認。

## social-login 核心

- sl-01/test_manager_without_driver：沒有 driver 時 `available()` 為空，`get()` 丟 404 例外。
- sl-02/test_manager_excludes_unconfigured_or_disabled：設定不齊全、或不在 `providers` 設定中的 provider 不列出。
- sl-03/test_login_page_hides_google：沒有可用 provider 時登入頁共享清單為空，redirect、link 路由 404。
- sl-04/test_login_page_shares_providers：有 driver 時登入頁共享 `[{key: google, label: Google}]`。
- sl-05/test_redirect_guest_only：redirect 導向第三方並記錄登入意圖；已登入者被擋。
- sl-06/test_link_requires_auth：未登入打 link 會導向登入頁。
- sl-07/test_link_success：綁定成功寫入資料、回已連結帳號頁並帶成功訊息。
- sl-08/test_link_account_taken：此 Google 帳號已綁在別人身上時顯示錯誤、不寫入。
- sl-09/test_link_provider_already_linked：自己已綁過 Google 時顯示錯誤。
- sl-10/test_link_unique_violation：同時綁定造成唯一鍵衝突時，轉成「已連結到其他帳號」。
- sl-11/test_login_success_remember：已綁定者 Google 登入成功、進今日頁、記住我，並更新 email／名稱／頭像。
- sl-12/test_login_not_linked_rejected：未綁定時回登入頁顯示錯誤，不登入、不建帳號；同 email 的使用者也不會被登入。
- sl-13/test_login_not_onboarded：未完成首次設定者 Google 登入後被導向首次設定頁。
- sl-14/test_callback_auth_failed：取消授權或 state 不符時，登入、綁定各回對應頁面並顯示「沒有完成」。
- sl-15/test_callback_without_intent：沒有出發前的紀錄或 provider 對不上時視為授權失敗。
- sl-16/test_link_callback_user_changed：綁定回來時已登出或換了帳號，視為授權失敗、不寫入。
- sl-17/test_connected_accounts_page：頁面資料列出 provider 與自己的綁定資訊，不含他人。
- sl-18/test_unlink_own_only：解除只刪自己的綁定、他人不受影響，帶成功訊息。
- sl-19/test_delete_user_cascades：刪除帳號時一併刪除綁定紀錄。

## social-login-google driver

- slg-01/test_not_configured_without_credentials：client id 或密鑰缺一個就不算設定齊全。
- slg-02/test_redirect_url：導向 `accounts.google.com`，帶正確 redirect_uri、`openid email profile` 與 state。
- slg-03/test_cancel_access_denied：Google 帶 `error=access_denied` 回來時轉成授權失敗。
- slg-04/test_invalid_state：state 不符時轉成授權失敗。
- slg-05/test_maps_google_user：模擬 Google token 與 userinfo 回應，轉成 id、email、名稱、頭像正確。
