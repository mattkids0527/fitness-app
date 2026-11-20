# meal、water（飲食紀錄、常用食物、喝水）測試情境

- 建立日期：2026-11-20
- 對應任務：[M3 飲食紀錄＋喝水 T5](../milestones/M3-meal-water.md)
- 測試程式：`packages/fitness/meal/tests/`、`packages/fitness/water/tests/`
- 原則：時間固定在 2026-11-20 12:40（Asia/Taipei），切日、預設時間都以此為準；資料直接寫入測試庫，不依賴既有資料。
- 情境已於 2026-11-20 由使用者確認。

## meal

- meal-01/test_index_requires_login：未登入打飲食頁導向登入頁。
- meal-02/test_index_shows_day_meals_and_totals：飲食頁列出當天餐點（時間由早到晚）、熱量與營養素加總、缺營養素標記、熱量目標。
- meal-03/test_index_day_boundary_follows_user_timezone：晚上 23:30（使用者時區）吃的算在當天，不會跑到隔天。
- meal-04/test_index_invalid_or_future_date_falls_back_to_today：未來日期、格式錯誤、不存在的日期（2/31）都改成今天。
- meal-05/test_only_confirmed_meals_are_counted：不是 confirmed 的餐點不列入飲食頁、今日頁、最近吃過、複製整餐。
- meal-06/test_create_defaults：今天依現在時間預設時間與餐別；指定日期與餐別時帶入餐別代表時間，並標記已選過餐別。
- meal-07/test_create_copy_from：再吃一次以自己的餐點預填、日期為今天、餐別沿用；複製別人的餐點回 404。
- meal-08/test_store_creates_meal_with_items：新增一餐，項目依順序保存，導回當天並帶成功訊息。
- meal-09/test_store_validation：未來日期、時間或餐別錯誤、沒有項目、名稱或熱量沒填、數值超出範圍、項目超過上限都被擋下。
- meal-10/test_store_touches_only_own_saved_foods：帶入的常用食物更新最近使用時間；帶別人的常用食物 id 不會被更新。
- meal-11/test_edit_excludes_itself_from_copyable_meals：編輯頁帶出該餐內容，「複製整餐」清單不含正在編輯的那一餐。
- meal-12/test_update_replaces_items：更新時間、餐別、備註，項目整批替換。
- meal-13/test_destroy_deletes_meal_and_items：刪除一餐時項目一併刪除，導回當天並帶成功訊息。
- meal-14/test_other_users_meal_returns_404：編輯、更新、刪除別人的餐點一律 404，資料不變。
- meal-15/test_recent_foods_rules：最近吃過依名稱（不分大小寫）取最近一次、只看近 30 天、最多 20 筆、新的在前。
- meal-16/test_copyable_meals_within_14_days：複製整餐只列近 14 天的餐點，新的在前。
- meal-17/test_saved_food_crud：常用食物新增、修改、刪除，列表依最近使用排序、沒用過的排最後。
- meal-18/test_saved_food_name_unique_case_insensitive：名稱不分大小寫、去頭尾空白不能重複（新增與修改都擋）；不同使用者可以同名。
- meal-19/test_other_users_saved_food_returns_404：修改、刪除別人的常用食物回 404。
- meal-20/test_daily_summary_includes_meal_section：今日頁 meal 區塊含加總、缺營養素標記、今日餐點與餐別名稱。
- meal-21/test_deleting_user_cascades_meal_data：刪除帳號時餐點、項目、常用食物一併刪除。
- meal-22/test_nutrition_calculator（單元）：null 當 0 加總；缺蛋白質／碳水／脂肪才標記，只缺纖維不標記。
- meal-23/test_meal_type_for_time（單元）：5、10、14、17、21 點等邊界時間對應的餐別。

## water

- water-01/test_store_records_now：記錄喝水，時間為現在，帶「已記錄 N ml」訊息。
- water-02/test_store_validation：喝水量必須是 10～2000 的整數。
- water-03/test_today_excludes_other_days：昨天 23:30（使用者時區）的紀錄不算進今天。
- water-04/test_destroy_only_own_today：只能刪自己今天的紀錄；昨天的、別人的回 404。
- water-05/test_target_calculation：78.5 kg → 2,750 ml、75 kg → 2,650 ml（四捨五入到 50 ml）；沒有體重紀錄時為 null。
- water-06/test_shared_water_prop_is_optional：一般換頁沒有 water；partial reload（only: water）時才載入。
- water-07/test_daily_summary_includes_water_section：今日頁 water 區塊含總量、建議量、紀錄（新的在前）與按鈕設定。
- water-08/test_deleting_user_cascades_water_logs：刪除帳號時喝水紀錄一併刪除。
