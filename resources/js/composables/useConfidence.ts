/** 照片分析信心度的分級：標籤文字、訊號格數，以及是否為需要提醒的「低」。 */
export interface ConfidenceLevel {
    label: '高' | '中' | '低';
    bars: 1 | 2 | 3;
    low: boolean;
}

/**
 * 信心度（0～1）分級：高 ≥ 0.75、中 ≥ 0.5、低 < 0.5。
 */
export function confidenceLevel(value: number): ConfidenceLevel {
    if (value >= 0.75) {
        return { label: '高', bars: 3, low: false };
    }

    if (value >= 0.5) {
        return { label: '中', bars: 2, low: false };
    }

    return { label: '低', bars: 1, low: true };
}
