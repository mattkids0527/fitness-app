/** 一筆喝水紀錄；time 為使用者時區 H:i。 */
export interface WaterLogItem {
    id: number;
    amount_ml: number;
    time: string;
}

/** 某一天的喝水摘要（共享資料 water 與今日頁 sections.water 格式相同）；沒有體重紀錄時 target_ml 為 null。 */
export interface WaterSummary {
    total_ml: number;
    target_ml: number | null;
    weight_kg: number | null;
    ml_per_kg: number;
    logs: WaterLogItem[];
    quick_amounts: number[];
    min_ml: number;
    max_ml: number;
}
