# exercise 模組

健身紀錄：使用者自建的分類（各自選記錄方式）與項目、健身紀錄與逐組明細、消耗熱量與訓練量計算，以及完成首次設定時的預設資料。

- 命名空間：`Fitness\Exercise`
- Service Provider：`Fitness\Exercise\ExerciseServiceProvider`
- 設定：`config('fitness.exercise.*')`（`src/config/fitness/exercise.php`）：逐組預設值 `sets_defaults`（MET、每下秒數、組間休息）、最多組數 `max_sets`、「約消耗」估算基準 `estimate`、喝水每小時 ml `water_ml_per_hour`（四捨五入到 `water_round_to_ml`）、預設分類與項目 `defaults`
- 語系：`trans('FitnessExercise::<檔名>.<鍵>')`（`src/lang/zh_TW/`：`options` 記錄方式名稱與說明、`attributes` 欄位名稱、`messages` 成功訊息、`errors` 錯誤訊息）
- 路由（`src/routes/web.php`，名稱前綴 `fitness.exercise.`）：`/exercises` 分類與項目管理頁（`index`）、項目 `create`／`store`／`edit`／`update`／`destroy`／`restore`、分類 `categories.store`／`categories.update`／`categories.move`／`categories.destroy`

## 依賴

- `profile`：`WeightLogRepository::effectiveOnDate()`（計算用體重）、`ProfileCompleted` 事件（建立預設資料）。

## 規則

- **記錄方式**（`Enums\RecordType`）：`duration` 填時長、`sets` 逐組，屬於分類，建立後不能改；項目沿用所屬分類的記錄方式。
- **計算**（`Services\ExerciseCalculator`，前端即時預覽要用相同公式）：
  - 填時長：`kcal = MET × 體重kg × 時數`。
  - 逐組：`估算秒數 = Σ(次數 × 每下秒數) + (組數 − 1) × 組間休息`，`kcal = MET × 體重kg × 估算時數`；次數不是正數的組不計。
  - 訓練量 `Σ(次數 × 重量)`，徒手（重量 null）不計，不影響熱量。
  - `duration_min`、`kcal`、`volume_kg` 四捨五入到小數 1 位；kcal 以未四捨五入的時間計算。
  - 「約消耗」估算：填時長以每 30 分鐘、逐組以 3 組 × 10 下計（`estimate`）。
  - 運動加到喝水建議量：運動時數 × 500 ml，四捨五入到 10 ml。
- **體重**（`Services\BodyWeightResolver`）：紀錄日當天或之前最新一筆；補記到第一筆體重之前的日期時取最早一筆。
- **快照**：`exercise_logs` 存分類名稱、項目名稱、記錄方式、MET、（逐組）每下秒數／組間休息、體重、時長、kcal、訓練量，之後項目改名或改 MET、體重變動都不影響歷史。
- **預設資料**（`Services\DefaultExerciseService`）：設定檔 `defaults` 的 2 個分類、7 個項目複製成使用者自己的資料。使用者完成首次設定時由 listener 建立；上線前已完成設定的使用者由 migration `create_default_exercises_for_existing_users` 補建一次。使用者已有任何分類時不動作，全部刪掉也不會再長回來。
- **分類管理**（`Services\ExerciseCategoryService`）：新增（名稱＋記錄方式，排在最後）、改名、往上／往下移、刪除。名稱不分大小寫不重複（Service 先檢查，唯一索引擋同時送出）。還有未封存的項目時不能刪除（`ExerciseCategoryNotEmptyException`）；只剩封存項目時照常刪除，封存項目的 `category_id` 由 FK 設為 null。
- **項目管理**（`Services\ExerciseService`）：
  - 未封存的項目名稱不分大小寫不重複（`ExerciseNameTakenException`，部分唯一索引擋同時送出），封存後可再建同名項目。
  - 逐組分類的項目必填每下秒數與組間休息；填時長分類的項目這兩欄一律存 null。`Exercise::recordType()` 對沒有分類的封存項目依此判斷記錄方式。
  - 編輯只能移到同記錄方式的分類（`ExerciseCategoryTypeMismatchException`），封存項目不能編輯。
  - 刪除：有紀錄改成封存、沒有紀錄直接刪除。
  - 還原：原分類已刪除時放回同記錄方式的第一個分類；有同名未封存項目（`ExerciseRestoreNameTakenException`）或沒有同記錄方式的分類（`ExerciseRestoreNoCategoryException`）時擋下並以 flash `error` 說明。
- 刪除帳號時，所有健身資料以 FK cascade 一併刪除。

## 資料表

- `exercise_categories`：user_id（cascade）、name（30）、record_type、position；唯一索引 `(user_id, lower(name))`
- `exercises`：user_id（cascade）、category_id（可 null，null on delete）、name（50）、met numeric(4,1)、sec_per_rep、rest_sec（逐組用）、description（300）、archived_at；部分唯一索引 `(user_id, lower(name)) WHERE archived_at IS NULL`
- `exercise_logs`：user_id（cascade）、exercise_id（可 null，null on delete）、performed_at（timestamptz）、category_name、exercise_name、record_type、met、sec_per_rep、rest_sec、body_weight_kg、duration_min、distance_km、kcal、volume_kg、note；索引 `(user_id, performed_at)`、`(user_id, exercise_id, performed_at)`
- `exercise_sets`：exercise_log_id（cascade）、set_no、reps、weight_kg（可 null）

## 對外提供

### Repository 介面

- `Repositories\Contracts\ExerciseCategoryRepository`：是否已有分類、列表、查自己的、某記錄方式的第一個、同名檢查、新增（排在最後）、更新、移動、刪除
- `Repositories\Contracts\ExerciseRepository`：未封存／已封存列表、查自己的（全部／未封存／已封存）、同名檢查（未封存）、分類內未封存數、是否有紀錄、新增、更新、刪除
- `Repositories\Contracts\ExerciseLogRepository`：查自己的紀錄（含逐組）、新增紀錄與逐組

### Services

- `ExerciseCalculator`：`forDuration()`、`forSets()`、`setsSeconds()`、`volume()`、`kcal()`、`estimate()`、`waterMl()`
- `BodyWeightResolver::on()`：某一天適用的體重紀錄
- `DefaultExerciseService::createFor()`：建立預設分類與項目
- `ExerciseCategoryService`：`all()`、`create()`、`rename()`、`move()`、`delete()`
- `ExerciseService`：`active()`、`archived()`、`findActive()`、`create()`、`update()`、`remove()`、`restore()`
- `ExercisePresenter`：分類、項目、管理頁分組與已封存列、記錄方式選項、體重與估算係數轉成前端格式

### 前端

- 頁面：`pages/exercise/Manage.vue`（分類分組、新增分類／改名面板、分類選單、刪除限制、已封存收合區與還原）、`pages/exercise/Form.vue`（新增／編輯項目，即時「約消耗」估算，封存／刪除確認）
- `composables/useExerciseCalc.ts`：與 `ExerciseCalculator` 同公式的前端計算；`composables/useRecordType.ts` 記錄方式圖示；`components/exercise/RecordTypeBadge.vue`；型別 `types/exercise.ts`

### Events

（尚無）

### Listeners

- `CreateDefaultExercises`：監聽 profile 的 `ProfileCompleted`，建立預設分類與項目
