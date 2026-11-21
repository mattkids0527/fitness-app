/** 分類的記錄方式：填時長／逐組。 */
export type RecordType = 'duration' | 'sets';

/** 記錄方式選項：值、名稱與說明。 */
export interface RecordTypeOption {
    value: RecordType;
    label: string;
    hint: string;
}

/** 分類摘要。 */
export interface ExerciseCategory {
    id: number;
    name: string;
    record_type: RecordType;
}

/** 項目的參數；逐組項目才有每下秒數與組間休息。 */
export interface Exercise {
    id: number;
    name: string;
    category_id: number | null;
    met: number;
    sec_per_rep: number | null;
    rest_sec: number | null;
    description: string | null;
}

/** 管理頁的分類分組：分類與底下未封存的項目。 */
export interface ExerciseCategoryGroup extends ExerciseCategory {
    exercises: Exercise[];
}

/** 管理頁「已封存」列；原分類已刪除時 category_id、category_name 為 null。 */
export interface ArchivedExercise {
    id: number;
    name: string;
    category_id: number | null;
    category_name: string | null;
    record_type: RecordType;
    log_count: number;
}

/** 編輯表單的項目：另帶紀錄筆數，決定刪除時是封存還是直接刪除。 */
export interface ExerciseDetail extends Exercise {
    log_count: number;
}

/** 新增表單的預設值。 */
export interface ExerciseFormDefaults {
    name: string;
    category_id: number | null;
}

/** 即時估算用的體重與記錄日期。 */
export interface ExerciseWeight {
    kg: number;
    date: string;
}

/** 前端即時估算的係數與基準（由後端設定帶入，公式與 ExerciseCalculator 一致）。 */
export interface ExerciseCalculation {
    sets_defaults: { met: number; sec_per_rep: number; rest_sec: number };
    estimate: { duration_min: number; sets: number; reps: number };
}
