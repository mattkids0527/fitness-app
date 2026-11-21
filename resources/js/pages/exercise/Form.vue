<script setup lang="ts">
import AppPage from '@/components/AppPage.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import UnitInput from '@/components/UnitInput.vue';
import { estimateKcal } from '@/composables/useExerciseCalc';
import { formatKcal } from '@/composables/useNutritionFormat';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    ExerciseCalculation,
    ExerciseCategory,
    ExerciseDetail,
    ExerciseFormDefaults,
    ExerciseWeight,
    RecordType,
    RecordTypeOption,
} from '@/types/exercise';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Archive, ChevronDown, Flame, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    categories: ExerciseCategory[];
    record_types: RecordTypeOption[];
    weight: ExerciseWeight | null;
    calculation: ExerciseCalculation;
    exercise: ExerciseDetail | null;
    defaults: ExerciseFormDefaults | null;
}>();

const isEdit = props.exercise !== null;
const pageTitle = isEdit ? '編輯項目' : '新增項目';
const setsDefaults = props.calculation.sets_defaults;

/**
 * 分類的記錄方式；找不到分類時為 null。
 */
function typeOf(categoryId: number | null): RecordType | null {
    return props.categories.find((category) => category.id === categoryId)?.record_type ?? null;
}

const initialCategoryId = props.exercise?.category_id ?? props.defaults?.category_id ?? null;
const initialType = typeOf(initialCategoryId);

const form = useForm({
    name: props.exercise?.name ?? props.defaults?.name ?? '',
    category_id: initialCategoryId,
    // 新增逐組項目時帶入預設 MET；填時長的 MET 差異大，留給使用者填
    met: (props.exercise?.met.toFixed(1) ?? (initialType === 'sets' ? setsDefaults.met.toFixed(1) : '')) as number | string,
    sec_per_rep: (props.exercise?.sec_per_rep ?? setsDefaults.sec_per_rep) as number | string,
    rest_sec: (props.exercise?.rest_sec ?? setsDefaults.rest_sec) as number | string,
    description: props.exercise?.description ?? '',
});

const type = computed(() => typeOf(form.category_id));
const typeLabel = (value: RecordType | null) => props.record_types.find((option) => option.value === value)?.label ?? '';

// 編輯時只能移到同記錄方式的分類，避免同一項目新舊紀錄算法不同
const categoryOptions = computed(() => (isEdit ? props.categories.filter((category) => category.record_type === initialType) : props.categories));
const selectedCategory = computed(() => props.categories.find((category) => category.id === form.category_id) ?? null);
const categoryHelp = computed(() => {
    if (isEdit) {
        return `只能移到同為「${typeLabel(initialType)}」的分類。`;
    }

    return selectedCategory.value ? `記錄方式跟著分類：${selectedCategory.value.name}是「${typeLabel(selectedCategory.value.record_type)}」。` : '';
});

/**
 * 換分類時，若改成逐組且 MET 還沒填，帶入逐組預設 MET。
 */
function onCategoryChange() {
    if (type.value === 'sets' && String(form.met).trim() === '') {
        form.met = setsDefaults.met.toFixed(1);
    }
}

// 「約消耗」即時估算（規格 Q10）：公式與後端 ExerciseCalculator::estimate() 一致
const estimate = computed(() => {
    const met = Number(form.met);

    if (!props.weight || !type.value || !(met > 0)) {
        return null;
    }

    const kcal = estimateKcal(type.value, met, props.weight.kg, props.calculation, Number(form.sec_per_rep) || 0, Number(form.rest_sec) || 0);
    const basis = props.calculation.estimate;
    const lead = type.value === 'sets' ? `${basis.sets} 組 × ${basis.reps} 下` : `每 ${basis.duration_min} 分鐘`;

    return { kcal, lead };
});

/**
 * 儲存項目；成功後由後端導回管理頁，同名等錯誤留在表單。
 */
function submit() {
    form.transform((data) => ({
        ...data,
        sec_per_rep: type.value === 'sets' ? data.sec_per_rep : null,
        rest_sec: type.value === 'sets' ? data.rest_sec : null,
    }));

    if (props.exercise) {
        form.put(route('fitness.exercise.update', { exercise: props.exercise.id }));
    } else {
        form.post(route('fitness.exercise.store'));
    }
}

// 刪除：有紀錄的改成封存，沒有紀錄的直接刪除
const confirmOpen = ref(false);
const removing = ref(false);
const willArchive = computed(() => (props.exercise?.log_count ?? 0) > 0);

/**
 * 封存或刪除項目，完成後由後端導回管理頁。
 */
function remove() {
    if (!props.exercise) {
        return;
    }

    removing.value = true;
    router.delete(route('fitness.exercise.destroy', { exercise: props.exercise.id }), {
        onFinish: () => {
            removing.value = false;
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="pageTitle" />
    <AppLayout
        :breadcrumbs="[
            { title: '分類與項目', href: route('fitness.exercise.index') },
            { title: pageTitle, href: '' },
        ]"
    >
        <AppPage :title="pageTitle" :back-href="route('fitness.exercise.index')" narrow>
            <form class="space-y-3" novalidate @submit.prevent="submit">
                <section class="space-y-3.5 rounded-xl border bg-card p-4">
                    <div>
                        <FieldLabel for="exercise_name" required>名稱</FieldLabel>
                        <Input
                            id="exercise_name"
                            v-model="form.name"
                            aria-required="true"
                            maxlength="50"
                            placeholder="例如：啞鈴臥推"
                            :class="['h-12 text-base', form.errors.name ? 'border-destructive' : '']"
                        />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <div>
                        <FieldLabel for="exercise_category" required>分類</FieldLabel>
                        <div
                            :class="[
                                'relative flex h-12 items-center rounded-lg border bg-card focus-within:ring-2 focus-within:ring-ring/30',
                                form.errors.category_id ? 'border-destructive' : 'border-input focus-within:border-primary',
                            ]"
                        >
                            <select
                                id="exercise_category"
                                v-model="form.category_id"
                                aria-required="true"
                                class="h-full w-full appearance-none border-0 bg-transparent pl-3.5 pr-10 text-base outline-none focus:ring-0"
                                @change="onCategoryChange"
                            >
                                <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                                    {{ category.name }}（{{ typeLabel(category.record_type) }}）
                                </option>
                            </select>
                            <ChevronDown class="pointer-events-none absolute right-3.5 size-4 text-muted-foreground" />
                        </div>
                        <p v-if="categoryHelp" class="mt-1.5 text-xs text-muted-foreground">{{ categoryHelp }}</p>
                        <InputError class="mt-1" :message="form.errors.category_id" />
                    </div>

                    <div>
                        <FieldLabel for="exercise_met" required>MET</FieldLabel>
                        <UnitInput
                            id="exercise_met"
                            v-model="form.met"
                            step="0.1"
                            min="0.5"
                            max="30"
                            placeholder="例如 7.0"
                            aria-required="true"
                            :invalid="!!form.errors.met"
                        />
                        <p class="mt-1.5 text-xs text-muted-foreground">運動強度係數。參考：快走 4.3、慢跑 8～10、飛輪 7、重訓 3.5～6。</p>
                        <InputError class="mt-1" :message="form.errors.met" />
                    </div>

                    <div v-if="type === 'sets'">
                        <div class="grid grid-cols-2 gap-2.5">
                            <div class="min-w-0">
                                <FieldLabel for="exercise_sec_per_rep" required>每下秒數</FieldLabel>
                                <UnitInput
                                    id="exercise_sec_per_rep"
                                    v-model="form.sec_per_rep"
                                    unit="秒"
                                    step="1"
                                    min="1"
                                    max="60"
                                    inputmode="numeric"
                                    aria-required="true"
                                    :invalid="!!form.errors.sec_per_rep"
                                />
                            </div>
                            <div class="min-w-0">
                                <FieldLabel for="exercise_rest_sec" required>組間休息</FieldLabel>
                                <UnitInput
                                    id="exercise_rest_sec"
                                    v-model="form.rest_sec"
                                    unit="秒"
                                    step="1"
                                    min="0"
                                    max="600"
                                    inputmode="numeric"
                                    aria-required="true"
                                    :invalid="!!form.errors.rest_sec"
                                />
                            </div>
                        </div>
                        <InputError class="mt-1" :message="form.errors.sec_per_rep ?? form.errors.rest_sec" />
                        <p class="mt-1.5 text-xs text-muted-foreground">用來估算重訓時間：次數 × 每下秒數 ＋ 組間休息。</p>
                    </div>

                    <div>
                        <FieldLabel for="exercise_description">說明</FieldLabel>
                        <textarea
                            id="exercise_description"
                            v-model="form.description"
                            maxlength="300"
                            rows="2"
                            placeholder="器材怎麼用、練哪裡"
                            class="w-full resize-none rounded-lg border border-input bg-card px-3.5 py-2.5 text-base outline-none focus:border-primary focus:ring-2 focus:ring-ring/30"
                        />
                        <div class="text-right text-xs tabular-nums text-muted-foreground">{{ form.description.length }}/300</div>
                        <InputError :message="form.errors.description" />
                    </div>
                </section>

                <div v-if="estimate && weight" class="flex items-center gap-2.5 rounded-[10px] bg-brand-soft p-3 text-sm">
                    <Flame class="size-5 shrink-0 text-primary" aria-hidden="true" />
                    <div>
                        以你目前體重 {{ weight.kg.toFixed(1) }} kg，{{ estimate.lead }}約
                        <b class="tabular-nums">{{ formatKcal(estimate.kcal) }}</b> kcal
                        <small class="block text-xs text-muted-foreground">MET × 體重 × 時數；實際以每筆紀錄計算。</small>
                    </div>
                </div>

                <button
                    v-if="exercise"
                    type="button"
                    class="mx-1 mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-destructive hover:underline"
                    @click="confirmOpen = true"
                >
                    <component :is="willArchive ? Archive : Trash2" class="size-4" />{{ willArchive ? '封存項目' : '刪除項目' }}
                </button>

                <div
                    class="sticky bottom-[calc(env(safe-area-inset-bottom)+4.75rem)] z-10 -mx-4 border-t bg-page/95 px-4 py-3 backdrop-blur md:static md:mx-0 md:border-0 md:bg-transparent md:px-0"
                >
                    <Button type="submit" size="lg" class="h-11 w-full" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        儲存
                    </Button>
                </div>
            </form>

            <ResponsiveDialog v-model:open="confirmOpen" :title="exercise ? `${willArchive ? '封存' : '刪除'}「${exercise.name}」？` : ''">
                <p v-if="exercise" class="text-sm text-muted-foreground">
                    <template v-if="willArchive">
                        這個項目有 {{ exercise.log_count }} 筆紀錄，所以改成封存：不會出現在選擇清單，歷史紀錄照常顯示，之後可以在「已封存」還原。
                    </template>
                    <template v-else>這個項目還沒有紀錄，刪除後無法復原。</template>
                </p>
                <div class="mt-5 flex gap-2">
                    <Button variant="outline" size="lg" class="h-11 flex-1" @click="confirmOpen = false">取消</Button>
                    <Button :variant="willArchive ? 'default' : 'destructive'" size="lg" class="h-11 flex-1" :disabled="removing" @click="remove">
                        <LoaderCircle v-if="removing" class="size-4 animate-spin" />
                        {{ willArchive ? '封存' : '刪除' }}
                    </Button>
                </div>
            </ResponsiveDialog>
        </AppPage>
    </AppLayout>
</template>
