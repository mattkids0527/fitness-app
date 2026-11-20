<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import { Button } from '@/components/ui/button';
import UnitInput from '@/components/UnitInput.vue';
import WaterProgress from '@/components/water/WaterProgress.vue';
import WaterQuickButtons from '@/components/water/WaterQuickButtons.vue';
import { useQuickActions } from '@/composables/useQuickActions';
import { useWaterLog } from '@/composables/useWaterLog';
import type { WaterSummary } from '@/types/water';
import { router, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const { waterSheetOpen } = useQuickActions();
const { processing, record, remove } = useWaterLog();
const page = usePage();

const custom = ref<number | string>('');
const customError = ref<string | null>(null);

// 共享資料 water 是 optional：一般換頁不會載入，打開面板時才以 partial reload 取得
const water = computed(() => page.props.water as WaterSummary | null | undefined);
const serverError = computed(() => (page.props.errors as Record<string, string>).amount_ml);
const description = computed(() =>
    water.value?.weight_kg ? `今天 · 建議量 = 體重 ${water.value.weight_kg} kg × ${water.value.ml_per_kg} ml` : '今天',
);

watch(waterSheetOpen, (open) => {
    if (open) {
        custom.value = '';
        customError.value = null;
        router.reload({ only: ['water'] });
    }
});

/**
 * 記錄自訂的喝水量；先在前端檢查整數與上下限。
 */
function recordCustom() {
    const amount = Number(custom.value);

    if (!water.value || custom.value === '' || !Number.isInteger(amount) || amount < water.value.min_ml || amount > water.value.max_ml) {
        customError.value = `請輸入 ${water.value?.min_ml ?? 10}～${(water.value?.max_ml ?? 2000).toLocaleString('en-US')} ml 的整數。`;

        return;
    }

    customError.value = null;
    record(amount, () => (custom.value = ''));
}
</script>

<template>
    <ResponsiveDialog v-model:open="waterSheetOpen" title="喝水" :description="description">
        <div v-if="!water" class="grid place-items-center py-10 text-muted-foreground">
            <LoaderCircle class="size-6 animate-spin" />
        </div>
        <template v-else>
            <WaterProgress :water="water" />
            <WaterQuickButtons class="mt-3" :amounts="water.quick_amounts" />

            <form class="mt-4" novalidate @submit.prevent="recordCustom">
                <label for="water_custom" class="mb-1.5 block text-sm font-semibold">自訂</label>
                <div class="flex gap-2">
                    <UnitInput
                        id="water_custom"
                        v-model="custom"
                        unit="ml"
                        step="1"
                        :min="String(water.min_ml)"
                        :max="String(water.max_ml)"
                        placeholder="例如 330"
                        :invalid="!!(customError ?? serverError)"
                        class="flex-1"
                    />
                    <Button type="submit" size="lg" class="h-12" :disabled="processing">記錄</Button>
                </div>
                <InputError class="mt-1.5" :message="customError ?? serverError" />
            </form>

            <h3 class="mx-1 mb-1.5 mt-5 text-sm text-muted-foreground">今天的紀錄</h3>
            <ul v-if="water.logs.length" class="overflow-hidden rounded-xl border bg-card">
                <li v-for="log in water.logs" :key="log.id" class="flex items-center gap-3 border-b py-2 pl-3.5 pr-1 last:border-b-0">
                    <span class="flex-1 tabular-nums text-muted-foreground">{{ log.time }}</span>
                    <span class="font-bold tabular-nums">{{ log.amount_ml.toLocaleString('en-US') }} ml</span>
                    <button
                        type="button"
                        class="grid size-9 place-items-center rounded-lg text-muted-foreground hover:bg-muted hover:text-destructive disabled:opacity-30"
                        :aria-label="`刪除 ${log.time} 的 ${log.amount_ml} ml`"
                        :disabled="processing"
                        @click="remove(log.id)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </li>
            </ul>
            <p v-else class="mx-1 text-sm text-muted-foreground">今天還沒有紀錄。</p>
        </template>
    </ResponsiveDialog>
</template>
