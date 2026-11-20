# M2 Google 登入 畫面

- 確認日期：2026-11-20
- 對應任務：[M2 Google 登入 T1](../../../milestones/M2-google-login.md)
- 開啟方式：瀏覽器打開 `index.html`，上方工具列可切換畫面、Google 是否已設定、連結狀態、Google 回應（授權成功／已被其他帳號連結／取消授權）、手機／桌機、淺色／深色
- 定案設計：
  - Google 按鈕文字用 Google 品牌規範的繁中標準說法「使用 Google 帳戶登入」；白底、四色 G 標誌，深色模式改深底（#131314）
  - 結果提示：成功（已連結 Google、已解除連結 Google）用既有 `AppToast`；失敗用頁內紅色提示框，不自動消失，換頁即清除（flash）
  - 設定頁分頁的選取樣式改為橘色淺底＋橘字（`bg-brand-soft text-primary`），原本的 `bg-muted` 與頁面底色幾乎相同；個人帳號、密碼、外觀分頁一併套用
  - 設定頁標題說明改為「管理帳號、密碼、外觀與已連結帳號」
  - 未設定任何 provider 時入口照常顯示，已連結帳號頁顯示「目前沒有可連結的服務」
  - Google 頭像只在已連結帳號頁顯示，不當站內大頭貼

## 畫面情境

- m2-01/login_google_button：登入頁帳密表單下方加「或」分隔線與「使用 Google 帳戶登入」按鈕
- m2-02/login_google_hidden：未設定 Google（無 client id）時，登入頁不顯示分隔線與 Google 按鈕
- m2-03/login_not_linked：Google 帳號未連結任何帳號，回登入頁顯示「這個 Google 帳號尚未連結」與綁定步驟
- m2-04/login_failed：取消授權或 state 不符，回登入頁顯示「Google 登入沒有完成」
- m2-05/login_success：Google 帳號已連結，登入（記住我）後進今日頁；未完成首次設定者照樣導向首次設定
- m2-06/connected_unlinked：已連結帳號頁（設定第四個分頁），Google 尚未連結，顯示「連結」按鈕
- m2-07/connected_linked：已連結狀態，顯示已連結標籤、Google 頭像、Google email、名稱、連結日期與「解除連結」
- m2-08/link_success_toast：連結成功回到已連結帳號頁，Toast 顯示「已連結 Google」
- m2-09/link_taken：此 Google 帳號已連結到其他帳號，頁內提示並維持未連結
- m2-10/link_failed：連結時取消授權，頁內提示「沒有完成 Google 連結」
- m2-11/unlink_confirm：解除連結確認（手機底部面板、桌機置中對話框），說明仍可用 Email 與密碼登入
- m2-12/unlink_success_toast：解除後回到未連結狀態，Toast 顯示「已解除連結 Google」
- m2-13/connected_unavailable：未設定任何 provider 時，已連結帳號頁顯示「目前沒有可連結的服務」
- m2-14/me_connected_row：「我的」頁設定區塊的「已連結帳號」列可點，右側顯示 Google／未連結
- m2-15/settings_nav_active：設定頁分頁選取樣式改為橘色淺底，手機在上方、1024px 以上在左側
- m2-16/dark_mode：所有畫面的深色版本
