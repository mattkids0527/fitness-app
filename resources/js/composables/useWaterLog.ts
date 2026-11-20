import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * 記錄／刪除喝水後要重新載入的 props：喝水面板的 water、toast 的 flash，在今日頁再加上 sections（喝水卡）。
 * 其他 props 不重新載入，頁面上正在填的表單不受影響。
 */
function reloadKeys(props: Record<string, unknown>): string[] {
    return 'sections' in props ? ['water', 'sections', 'flash'] : ['water', 'flash'];
}

/**
 * 喝水紀錄的新增與刪除；成功訊息由後端 flash 經 AppToast 顯示。
 */
export function useWaterLog() {
    const page = usePage();
    const processing = ref(false);

    /**
     * 記錄今天喝的水。
     */
    const record = (amountMl: number, onSuccess?: () => void) => {
        processing.value = true;
        router.post(
            route('fitness.water.store'),
            { amount_ml: amountMl },
            { preserveState: true, preserveScroll: true, only: reloadKeys(page.props), onSuccess, onFinish: () => (processing.value = false) },
        );
    };

    /**
     * 刪除今天的一筆紀錄。
     */
    const remove = (waterLogId: number) => {
        processing.value = true;
        router.delete(route('fitness.water.destroy', { waterLog: waterLogId }), {
            preserveState: true,
            preserveScroll: true,
            only: reloadKeys(page.props),
            onFinish: () => (processing.value = false),
        });
    };

    return { processing, record, remove };
}
