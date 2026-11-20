# 版本分頁（設定頁「版本」）測試情境

- 建立日期：2026-11-21
- 對應任務：[D2 發版版號 T1](../milestones/D2-release-versioning.md)
- 測試程式：`tests/Feature/Settings/VersionTest.php`
- 原則：容器改綁定暫存的 `version.json`，不碰專案根目錄的檔案。
- 情境已於 2026-11-21 由使用者確認。

- ver-01/test_version_page_requires_login：未登入導向登入頁。
- ver-02/test_version_page_shows_deployed_version：有 `version.json` 時帶出版號、7 碼小寫 commit、台北時間的部署時間。
- ver-03/test_version_page_without_version_file：沒有 `version.json` 時 `version` 為 null（畫面顯示「未標示版本」）。
- ver-04/test_invalid_version_file_is_ignored：不是 JSON、版號少了 `v`、缺少版號，都視為沒有版本資訊。
- ver-05/test_invalid_commit_and_time_are_omitted：版號正確但 commit 不是 sha、時間無法解析時，版號照常顯示，另兩欄為 null。
