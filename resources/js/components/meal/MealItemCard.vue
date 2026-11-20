<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import UnitInput from '@/components/UnitInput.vue';
import { estimateKcal, toNumber } from '@/composables/useMealFood';
import { formatGrams, formatKcal } from '@/composables/useNutritionFormat';
import type { MealFormItem } from '@/types/meal';
import { Calculator, ChevronDown, ChevronUp, Star, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    index: number;
    saved: boolean;
    errors: Partial<Record<string, string>>;
}>();

const item = defineModel<MealFormItem>('item', { required: true });
const emit = defineEmits<{ remove: []; star: [] }>();

const nutrients = [
    { field: 'protein_g', label: '蛋白質' },
    { field: 'carbs_g', label: '碳水' },
    { field: 'fat_g', label: '脂肪' },
    { field: 'fiber_g', label: '纖維' },
] as const;

const estimate = computed(() => estimateKcal(item.value));

// 收合時顯示：份量、重量、三大營養素（都沒填時顯示「營養素未填」）
const meta = computed(() => {
    const parts = [item.value.portion, toNumber(item.value.grams) !== null ? `${formatGrams(toNumber(item.value.grams))} g` : ''];
    const macros = [item.value.protein_g, item.value.carbs_g, item.value.fat_g];
    const macroLine = macros.some((value) => toNumber(value) !== null)
        ? `蛋白質 ${formatGrams(toNumber(item.value.protein_g))} · 碳水 ${formatGrams(toNumber(item.value.carbs_g))} · 脂肪 ${formatGrams(toNumber(item.value.fat_g))}`
        : '營養素未填';

    return [...parts.filter(Boolean), macroLine].join(' · ');
});

/**
 * 這個項目某個欄位的驗證錯誤。
 */
const errorOf = (field: string) => props.errors[`items.${props.index}.${field}`];
</script>

<template>
    <div :class="['rounded-xl border bg-card', item.open ? 'border-primary/50 ring-[3px] ring-primary/10' : '']">
        <div class="flex items-center gap-1 py-1.5 pl-3.5 pr-1">
            <button type="button" class="min-w-0 flex-1 py-1 text-left" :aria-expanded="item.open" @click="item.open = !item.open">
                <div :class="['truncate', item.name.trim() ? 'font-semibold' : 'font-medium text-muted-foreground']">
                    {{ item.name.trim() || '未命名項目' }}
                </div>
                <div class="text-xs text-muted-foreground">{{ meta }}</div>
            </button>
            <span class="mr-0.5 whitespace-nowrap font-bold tabular-nums">
                {{ toNumber(item.kcal) !== null ? formatKcal(Number(item.kcal)) : '—'
                }}<small class="ml-0.5 text-[11px] font-medium text-muted-foreground">kcal</small>
            </span>
            <button
                type="button"
                :class="['grid size-9 place-items-center rounded-lg', saved ? 'text-amber-500' : 'text-muted-foreground hover:bg-muted']"
                :aria-label="saved ? '已在常用食物' : '加入常用食物'"
                :aria-pressed="saved"
                @click="emit('star')"
            >
                <Star :class="['size-4', saved ? 'fill-current' : '']" />
            </button>
            <button
                type="button"
                class="grid size-9 place-items-center rounded-lg text-muted-foreground hover:bg-muted"
                :aria-label="item.open ? '收合' : '展開'"
                @click="item.open = !item.open"
            >
                <component :is="item.open ? ChevronUp : ChevronDown" class="size-4" />
            </button>
        </div>

        <div v-if="item.open" class="space-y-2.5 border-t border-dashed px-3.5 pb-3.5 pt-2.5">
            <div>
                <label :for="`item-${item.key}-name`" class="mb-1 block text-xs text-muted-foreground">名稱</label>
                <Input
                    :id="`item-${item.key}-name`"
                    v-model="item.name"
                    maxlength="100"
                    placeholder="例如：雞胸肉便當"
                    :class="['h-12', errorOf('name') ? 'border-destructive' : '']"
                />
                <InputError class="mt-1" :message="errorOf('name')" />
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div class="min-w-0">
                    <label :for="`item-${item.key}-portion`" class="mb-1 block text-xs text-muted-foreground">份量描述（選填）</label>
                    <Input :id="`item-${item.key}-portion`" v-model="item.portion" maxlength="50" placeholder="例如：1 碗" class="h-12" />
                </div>
                <div class="min-w-0">
                    <label :for="`item-${item.key}-grams`" class="mb-1 block text-xs text-muted-foreground">重量（選填）</label>
                    <UnitInput :id="`item-${item.key}-grams`" v-model="item.grams" unit="g" min="0" :invalid="!!errorOf('grams')" />
                </div>
            </div>
            <div>
                <label :for="`item-${item.key}-kcal`" class="mb-1 block text-xs text-muted-foreground">熱量</label>
                <UnitInput :id="`item-${item.key}-kcal`" v-model="item.kcal" unit="kcal" min="0" :invalid="!!errorOf('kcal')" />
                <InputError class="mt-1" :message="errorOf('kcal') ?? errorOf('grams')" />
                <button
                    v-if="estimate !== null"
                    type="button"
                    class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-brand-soft px-2.5 py-1 text-xs font-semibold text-primary"
                    @click="item.kcal = estimate"
                >
                    <Calculator class="size-3.5" /> 依營養素估算 {{ formatKcal(estimate) }} kcal
                </button>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div v-for="nutrient in nutrients" :key="nutrient.field" class="min-w-0">
                    <label :for="`item-${item.key}-${nutrient.field}`" class="mb-1 block text-xs text-muted-foreground"
                        >{{ nutrient.label }}（選填）</label
                    >
                    <UnitInput
                        :id="`item-${item.key}-${nutrient.field}`"
                        v-model="item[nutrient.field]"
                        unit="g"
                        min="0"
                        :invalid="!!errorOf(nutrient.field)"
                    />
                    <InputError class="mt-1" :message="errorOf(nutrient.field)" />
                </div>
            </div>
            <p class="text-xs text-muted-foreground">不知道的營養素可以留空，加總時以 0 計算。</p>
            <button type="button" class="inline-flex items-center gap-1 text-sm text-destructive" @click="emit('remove')">
                <Trash2 class="size-4" /> 刪除這個項目
            </button>
        </div>
    </div>
</template>
