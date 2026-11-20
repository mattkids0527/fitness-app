<script setup lang="ts">
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import ConfidenceBadge from '@/components/meal/ConfidenceBadge.vue';
import MacroValues from '@/components/meal/MacroValues.vue';
import { Input } from '@/components/ui/input';
import UnitInput from '@/components/UnitInput.vue';
import { confidenceLevel } from '@/composables/useConfidence';
import { estimateKcal, toNumber } from '@/composables/useMealFood';
import { NUTRIENTS } from '@/composables/useNutrients';
import { formatGrams, formatKcal } from '@/composables/useNutritionFormat';
import type { MealFormItem } from '@/types/meal';
import { Calculator, ChevronDown, ChevronUp, Flame, Info, Star, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    index: number;
    saved: boolean;
    errors: Partial<Record<string, string>>;
}>();

const item = defineModel<MealFormItem>('item', { required: true });
const emit = defineEmits<{ remove: []; star: [] }>();

const estimate = computed(() => estimateKcal(item.value));

// 照片分析的項目：信心度「低」時外框用橘色，展開後提醒對照照片調整
const lowConfidence = computed(() => item.value.confidence !== null && confidenceLevel(item.value.confidence).low);

// 收合時顯示：份量、重量
const meta = computed(() =>
    [item.value.portion, toNumber(item.value.grams) !== null ? `${formatGrams(toNumber(item.value.grams))} g` : ''].filter(Boolean).join(' · '),
);

// 收合時顯示的三大營養素；都沒填時為 null，改顯示「營養素未填」
const macros = computed(() => {
    const values = {
        protein_g: toNumber(item.value.protein_g),
        carbs_g: toNumber(item.value.carbs_g),
        fat_g: toNumber(item.value.fat_g),
    };

    return Object.values(values).some((value) => value !== null) ? values : null;
});

/**
 * 這個項目某個欄位的驗證錯誤。
 */
const errorOf = (field: string) => props.errors[`items.${props.index}.${field}`];
</script>

<template>
    <div
        :class="['rounded-xl border bg-card', item.open ? 'border-primary/50 ring-[3px] ring-primary/10' : lowConfidence ? 'border-primary/55' : '']"
    >
        <div class="flex items-center gap-1 py-1.5 pl-3.5 pr-1">
            <button type="button" class="min-w-0 flex-1 py-1 text-left" :aria-expanded="item.open" @click="item.open = !item.open">
                <div class="flex min-w-0 items-center gap-1.5">
                    <span :class="['truncate', item.name.trim() ? 'font-semibold' : 'font-medium text-muted-foreground']">
                        {{ item.name.trim() || '未命名項目' }}
                    </span>
                    <ConfidenceBadge v-if="item.confidence !== null" :value="item.confidence" />
                </div>
                <div class="flex min-w-0 items-center gap-2 overflow-hidden whitespace-nowrap text-xs text-muted-foreground">
                    <span v-if="meta" class="truncate">{{ meta }}</span>
                    <MacroValues v-if="macros" :values="macros" />
                    <span v-else>營養素未填</span>
                </div>
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
                <FieldLabel :for="`item-${item.key}-name`" required>名稱</FieldLabel>
                <Input
                    :id="`item-${item.key}-name`"
                    v-model="item.name"
                    aria-required="true"
                    maxlength="100"
                    placeholder="例如：雞胸肉便當"
                    :class="['h-12', errorOf('name') ? 'border-destructive' : '']"
                />
                <InputError class="mt-1" :message="errorOf('name')" />
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div class="min-w-0">
                    <FieldLabel :for="`item-${item.key}-portion`">份量描述</FieldLabel>
                    <Input :id="`item-${item.key}-portion`" v-model="item.portion" maxlength="50" placeholder="例如：1 碗" class="h-12" />
                </div>
                <div class="min-w-0">
                    <FieldLabel :for="`item-${item.key}-grams`">重量</FieldLabel>
                    <UnitInput :id="`item-${item.key}-grams`" v-model="item.grams" unit="g" min="0" :invalid="!!errorOf('grams')" />
                </div>
            </div>
            <div>
                <FieldLabel :for="`item-${item.key}-kcal`" :icon="Flame" required>熱量</FieldLabel>
                <UnitInput :id="`item-${item.key}-kcal`" v-model="item.kcal" unit="kcal" min="0" aria-required="true" :invalid="!!errorOf('kcal')" />
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
                <div v-for="nutrient in NUTRIENTS" :key="nutrient.field" class="min-w-0">
                    <FieldLabel :for="`item-${item.key}-${nutrient.field}`" :icon="nutrient.icon" :icon-class="nutrient.textClass">{{
                        nutrient.label
                    }}</FieldLabel>
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
            <div v-if="lowConfidence" class="flex gap-2 rounded-lg bg-brand-soft p-2.5 text-[13px] leading-relaxed text-foreground">
                <Info class="mt-0.5 size-4 shrink-0 text-primary" />
                <p>這個項目 AI 比較沒把握，份量或名稱可能不準，請對照照片調整。</p>
            </div>
            <button type="button" class="inline-flex items-center gap-1 text-sm text-destructive" @click="emit('remove')">
                <Trash2 class="size-4" /> 刪除這個項目
            </button>
        </div>
    </div>
</template>
