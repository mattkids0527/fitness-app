import { ref } from 'vue';

// 全站共用的開關狀態：側欄、底部 Tab、各頁按鈕都能開啟同一個面板
const quickAddOpen = ref(false);
const weightSheetOpen = ref(false);
const waterSheetOpen = ref(false);

/**
 * 快速新增選單、記錄體重面板與喝水面板的開關。
 */
export function useQuickActions() {
    /**
     * 開啟快速新增選單。
     */
    const openQuickAdd = () => {
        quickAddOpen.value = true;
    };

    /**
     * 開啟記錄體重面板（從快速新增選單開啟時先關掉選單）。
     */
    const openWeightSheet = () => {
        quickAddOpen.value = false;
        weightSheetOpen.value = true;
    };

    /**
     * 開啟喝水面板（從快速新增選單開啟時先關掉選單）。
     */
    const openWaterSheet = () => {
        quickAddOpen.value = false;
        waterSheetOpen.value = true;
    };

    return { quickAddOpen, weightSheetOpen, waterSheetOpen, openQuickAdd, openWeightSheet, openWaterSheet };
}
