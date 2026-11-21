# exercise 模組

健身紀錄：使用者自建的分類（各自選記錄方式）與項目、健身紀錄與逐組明細、消耗熱量與訓練量計算，以及完成首次設定時的預設資料。

- 命名空間：`Fitness\Exercise`
- Service Provider：`Fitness\Exercise\ExerciseServiceProvider`
- 設定：`config('fitness.exercise.*')`（`src/config/fitness/exercise.php`）：逐組預設值 `sets_defaults`（MET、每下秒數、組間休息）、最多組數 `max_sets`、「約消耗」估算基準 `estimate`、喝水每小時 ml `water_ml_per_hour`（四捨五入到 `water_round_to_ml`）、預設分類與項目 `defaults`
- 語系：`trans('FitnessExercise::<檔名>.<鍵>')`（`src/lang/zh_TW/`：`options` 記錄方式名稱）

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
- 刪除帳號時，所有健身資料以 FK cascade 一併刪除。

## 資料表

- `exercise_categories`：user_id（cascade）、name（30）、record_type、position；唯一索引 `(user_id, lower(name))`
- `exercises`：user_id（cascade）、category_id（可 null，null on delete）、name（50）、met numeric(4,1)、sec_per_rep、rest_sec（逐組用）、description（300）、archived_at；部分唯一索引 `(user_id, lower(name)) WHERE archived_at IS NULL`
- `exercise_logs`：user_id（cascade）、exercise_id（可 null，null on delete）、performed_at（timestamptz）、category_name、exercise_name、record_type、met、sec_per_rep、rest_sec、body_weight_kg、duration_min、distance_km、kcal、volume_kg、note；索引 `(user_id, performed_at)`、`(user_id, exercise_id, performed_at)`
- `exercise_sets`：exercise_log_id（cascade）、set_no、reps、weight_kg（可 null）

## 對外提供

### Repository 介面

- `Repositories\Contracts\ExerciseCategoryRepository`：是否已有分類、新增（排在最後）
- `Repositories\Contracts\ExerciseRepository`：查自己的項目、新增
- `Repositories\Contracts\ExerciseLogRepository`：查自己的紀錄（含逐組）、新增紀錄與逐組

### Services

- `ExerciseCalculator`：`forDuration()`、`forSets()`、`setsSeconds()`、`volume()`、`kcal()`、`estimate()`、`waterMl()`
- `BodyWeightResolver::on()`：某一天適用的體重紀錄
- `DefaultExerciseService::createFor()`：建立預設分類與項目

### Events

（尚無）

### Listeners

- `CreateDefaultExercises`：監聽 profile 的 `ProfileCompleted`，建立預設分類與項目
