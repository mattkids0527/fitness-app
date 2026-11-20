import type { MealSummarySection } from '@/types/meal';
import type { EnergyTargets, WeightLogItem } from '@/types/profile';
import type { WaterSummary } from '@/types/water';

/** profile 模組加入今日頁的區塊。 */
export interface ProfileSummarySection {
    targets: EnergyTargets | null;
    current_weight: WeightLogItem | null;
    weighed_today: boolean;
}

/** 各模組以 section key 加入的當日摘要。 */
export interface DailySummarySections {
    profile?: ProfileSummarySection;
    meal?: MealSummarySection;
    water?: WaterSummary;
}
