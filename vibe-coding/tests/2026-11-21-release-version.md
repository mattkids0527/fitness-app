# 版本分頁（設定頁「版本」）測試情境

- 建立日期：2026-11-21
- 對應任務：[D2 發版版號 T1](../milestones/D2-release-versioning.md)
- 測試程式：`tests/Feature/Settings/VersionTest.php`（ver-06 為之後追加的「我的」頁版本列；v1.3.2 驗收後拿掉 commit，ver-02、ver-05 隨之調整）
- 原則：容器改綁定暫存的 `version.json`，不碰專案根目錄的檔案。
- 情境已於 2026-11-21 由使用者確認。

- ver-01/test_version_page_requires_login：未登入導向登入頁。
- ver-02/test_version_page_shows_deployed_version：有 `version.json` 時只帶出版號與更新日期（以台北時間換算日期，UTC 11/20 17:30 → 2026-11-21），不帶 commit。
- ver-03/test_version_page_without_version_file：沒有 `version.json` 時 `version` 為 null（畫面顯示「未標示版本」）。
- ver-04/test_invalid_version_file_is_ignored：不是 JSON、版號少了 `v`、缺少版號，都視為沒有版本資訊。
- ver-05/test_invalid_time_is_omitted：版號正確但部署時間無法解析時，版號照常顯示，更新日期為 null。
- ver-06/test_me_page_shows_app_version：「我的」頁的 `appVersion` 沒有 `version.json` 時為 null，有時為版號。
