# daily-summary 模組測試情境

- 建立日期：2026-11-19
- 對應任務：[M1 T6](../milestones/M1-account-foundation.md)
- 測試程式：`packages/fitness/daily-summary/tests/`
- 備註：使用者要求 M1 一路做完，情境未逐條事先確認，寫完後請使用者事後檢視增減。

## Feature

- D-F01/test_guests_are_redirected_to_login：未登入進今日頁導向登入頁。
- D-F02/test_today_page_includes_profile_section：今日頁帶入 profile 區塊（每日目標、目前體重），今天已量時 weighed_today 為 true。
- D-F03/test_weighed_today_is_false_when_latest_log_is_older：最新體重不是今天時 weighed_today 為 false。
- D-F04/test_today_follows_user_timezone：「今天」依使用者時區切日（台北已過午夜、UTC 仍是前一天）。
- D-F05/test_other_modules_can_add_sections：其他模組監聽 DailySummaryCollecting 加入的區塊會出現在頁面 props。
