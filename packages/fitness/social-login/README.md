# social-login 模組

第三方帳號登入的核心：`social_accounts` 綁定表、driver 合約與 Manager、綁定／登入／解除綁定流程、已連結帳號頁。不依賴任何第三方套件；實際的平台（Google 等）由 driver 模組提供，例如 `social-login-google`。

- 命名空間：`Fitness\SocialLogin`
- Service Provider：`Fitness\SocialLogin\SocialLoginServiceProvider`
- 設定：`config('fitness.social_login.*')`（`src/config/fitness/social-login.php`）：`providers` 啟用的 provider 別名（env `SOCIAL_LOGIN_PROVIDERS`，預設 `google`，逗號分隔）
- 語系：`trans('FitnessSocialLogin::<檔名>.<鍵>')`（`src/lang/zh_TW/`：`messages` 成功提示、`errors` 失敗提示的 title／message）
- 路由名稱前綴：`fitness.social_login.`（`src/routes/web.php`）
- Helper：`social_login()` 取得 `SocialProviderManager`

## 規則

- 帳號一定先有 Email + 密碼；綁定是登入後的動作。
- 每個帳號每個平台只能綁一組、同一組第三方帳號只能綁一個帳號（程式先檢查，唯一索引擋競態）。
- 未綁定的第三方帳號不能登入，不以 email 比對、不建立帳號。
- 以第三方帳號登入一律「記住我」，並以第三方最新資料更新 email／名稱／頭像。

## 資料表

- `social_accounts`：user_id（cascade）、provider、provider_user_id、email、name、avatar、timestamps（created_at 即連結時間）；唯一索引 `(provider, provider_user_id)`、`(user_id, provider)`。
- `User` 的 `socialAccounts()` 關聯由 Provider 以 `resolveRelationUsing` 動態加入，`app/Models/User` 不依賴本模組。

## 路由

| 方法 | 路徑 | 名稱 | 行為 |
|---|---|---|---|
| GET | `/auth/{provider}/redirect` | `fitness.social_login.redirect` | guest；記錄意圖 login 後導向第三方 |
| GET | `/auth/{provider}/link` | `fitness.social_login.link` | auth；記錄意圖 link 與發起者後導向第三方 |
| GET | `/auth/{provider}/callback` | `fitness.social_login.callback` | 共用；依 session 意圖完成登入或綁定（已列入 `fitness.profile.onboarding_except_routes`） |
| GET | `/settings/connected-accounts` | `fitness.social_login.connected_accounts.index` | `social-login/ConnectedAccounts` |
| DELETE | `/settings/connected-accounts/{provider}` | `fitness.social_login.connected_accounts.destroy` | 解除自己的綁定 |

- `{provider}` 未啟用、沒有 driver 或設定不齊全時一律 404（`ProviderNotAvailableException`）。
- redirect／link 以 `Inertia::location()` 回應，從 Inertia 請求發起也會整頁跳轉。
- 結果以 session flash 帶回：成功 `success`（字串），失敗 `error`（`{title, message}`）；綁定且仍登入時回已連結帳號頁，其餘回登入頁。

## Inertia 共享資料

- `social_login.providers`：`[{key, label}]`，已啟用、有 driver 且設定齊全的 provider，登入頁據此顯示按鈕。由本模組的 Provider 以 `Inertia::share()` 加入，`app/` 不需知道本模組。

## 對外提供

### Driver 合約（driver 模組實作）

- `Drivers\Contracts\SocialProvider`：`redirect()`、`user()`（回傳 `SocialUser`，失敗丟 `SocialAuthFailedException`）、`label()`、`isConfigured()`
- `Drivers\SocialProvider`：abstract 基底，`isConfigured()` 預設 true、`authFailed()` 把第三方例外包成模組例外
- `Drivers\SocialUser`：id、email、name、avatar
- driver 模組在自己的 Provider 綁定 `fitness.social_login.<provider>`

### Services

- `SocialProviderManager`：`get($provider)`（不可用丟 404 例外）、`available()`、`options()`
- `SocialLoginService`：`login()`、`link()`、`unlink()`、`connectedAccounts()`

### Repository 介面

- `Repositories\Contracts\SocialAccountRepository`

### Exceptions

- `SocialAccountNotLinkedException`：登入時這組第三方帳號未綁定
- `SocialAccountAlreadyLinkedException`：這組第三方帳號已綁定其他使用者
- `ProviderAlreadyLinkedException`：自己已綁定過該平台
- `SocialAuthFailedException`：取消授權、state 不符、沒有發起紀錄、回來時已登出或換了帳號
- `ProviderNotAvailableException`：provider 不可用（404）

### Events / Listeners

（尚無）
