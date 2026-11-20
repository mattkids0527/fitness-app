<script setup lang="ts">
import AppPage from '@/components/AppPage.vue';
import MealCard from '@/components/meal/MealCard.vue';
import MealDaySwitcher from '@/components/meal/MealDaySwitcher.vue';
import MealTotalsCard from '@/components/meal/MealTotalsCard.vue';
import MealTypeIcon from '@/components/meal/MealTypeIcon.vue';
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/composables/useDateFormat';
import { formatKcal } from '@/composables/useNutritionFormat';
import AppLayout from '@/layouts/AppLayout.vue';
import type { MealSummary, MealType, MealTypeOption, NutritionTotals } from '@/types/meal';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, LoaderCircle, Pencil, Plus, Repeat, Star, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    date: string;
    today: string;
    meals: MealSummary[];
    totals: NutritionTotals;
    has_missing_nutrients: boolean;
    calorie_target: number | null;
    meal_types: MealTypeOption[];
    saved_foods_count: number;
}>();

const selected = ref<MealSummary | null>(null);
const actionsOpen = ref(false);
const confirmOpen = ref(false);
const deleting = ref(false);

// 依早餐、午餐、晚餐、點心分組，每組附小計
const groups = computed(() =>
    props.meal_types.map((type) => {
        const meals = props.meals.filter((meal) => meal.meal_type === type.value);

        return { type, meals, kcal: meals.reduce((sum, meal) => sum + meal.kcal, 0) };
    }),
);

/**
 * 餐別的中文名稱。
 */
const typeLabel = (value: MealType) => props.meal_types.find((type) => type.value === value)?.label ?? '';

/**
 * 切換日期；今天不帶參數，網址保持乾淨。
 */
function changeDate(date: string) {
    router.get(route('fitness.meal.index'), date === props.today ? {} : { date });
}

/**
 * 打開某一餐的選單（編輯、再吃一次、刪除）。
 */
function openActions(meal: MealSummary) {
    selected.value = meal;
    actionsOpen.value = true;
}

/**
 * 從選單改開刪除確認。
 */
function askDelete() {
    actionsOpen.value = false;
    confirmOpen.value = true;
}

/**
 * 刪除選定的餐點；成功訊息由後端 flash 經 AppToast 顯示。
 */
function destroy() {
    if (!selected.value) {
        return;
    }

    deleting.value = true;
    router.delete(route('fitness.meal.destroy', { meal: selected.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="飲食" />
    <AppLayout :breadcrumbs="[{ title: '飲食', href: route('fitness.meal.index') }]">
        <AppPage title="飲食">
            <template #actions>
                <Button as-child>
                    <Link :href="route('fitness.meal.create', date === today ? {} : { date })"><Plus class="size-4" /> 新增餐點</Link>
                </Button>
            </template>

            <MealDaySwitcher :date="date" :today="today" @change="changeDate" />
            <div v-if="date !== today" class="mb-1 text-center md:text-left">
                <button type="button" class="h-7 rounded-full border bg-card px-3 text-xs font-semibold hover:bg-muted" @click="changeDate(today)">
                    回到今天
                </button>
            </div>

            <div class="mt-2 grid gap-5 md:grid-cols-[320px_1fr] md:items-start">
                <div>
                    <MealTotalsCard
                        :date="date"
                        :today="today"
                        :totals="totals"
                        :calorie-target="calorie_target"
                        :has-missing-nutrients="has_missing_nutrients"
                    />
                    <Link
                        :href="route('fitness.meal.saved_foods.index')"
                        class="mt-3 flex min-h-[50px] items-center gap-3 rounded-xl border bg-card px-3.5 py-2.5 hover:bg-muted/50"
                    >
                        <Star class="size-5" />
                        <span class="flex-1">常用食物</span>
                        <span class="text-sm text-muted-foreground">{{ saved_foods_count }} 項</span>
                        <ChevronRight class="size-4 text-muted-foreground" />
                    </Link>
                </div>

                <div class="space-y-4">
                    <section v-for="group in groups" :key="group.type.value">
                        <h2 class="mx-1 mb-1.5 flex items-center gap-2 text-sm font-semibold">
                            <MealTypeIcon :type="group.type.value" />
                            {{ group.type.label }}
                            <span v-if="group.meals.length" class="text-[13px] font-medium tabular-nums text-muted-foreground">
                                {{ formatKcal(group.kcal) }} kcal
                            </span>
                            <Link
                                v-if="group.meals.length"
                                :href="route('fitness.meal.create', { date, type: group.type.value })"
                                class="ml-auto flex items-center gap-0.5 p-1 text-[13px] font-semibold text-primary"
                            >
                                <Plus class="size-4" />新增
                            </Link>
                        </h2>
                        <div v-if="group.meals.length" class="space-y-1.5">
                            <MealCard v-for="meal in group.meals" :key="meal.id" :meal="meal" @menu="openActions(meal)" />
                        </div>
                        <Link
                            v-else
                            :href="route('fitness.meal.create', { date, type: group.type.value })"
                            class="flex items-center justify-center gap-1.5 rounded-xl border-[1.5px] border-dashed border-input p-3 text-sm text-muted-foreground hover:bg-muted/50"
                        >
                            <Plus class="size-4" /> 新增{{ group.type.label }}
                        </Link>
                    </section>
                </div>
            </div>

            <ResponsiveDialog
                v-model:open="actionsOpen"
                :title="selected ? `${typeLabel(selected.meal_type)} · ${selected.time}` : ''"
                :description="selected ? `${selected.item_names.join('、')} · ${formatKcal(selected.kcal)} kcal` : undefined"
            >
                <div v-if="selected" class="grid gap-0.5">
                    <Link
                        :href="route('fitness.meal.edit', { meal: selected.id })"
                        class="flex h-12 items-center gap-3 rounded-lg px-2 hover:bg-muted"
                        @click="actionsOpen = false"
                    >
                        <Pencil class="size-5" /> 編輯
                    </Link>
                    <Link
                        :href="route('fitness.meal.create', { copy_from: selected.id })"
                        class="flex h-12 items-center gap-3 rounded-lg px-2 hover:bg-muted"
                        @click="actionsOpen = false"
                    >
                        <Repeat class="size-5" /> 再吃一次
                        <span class="ml-auto text-sm text-muted-foreground">帶入今天</span>
                    </Link>
                    <button type="button" class="flex h-12 items-center gap-3 rounded-lg px-2 text-destructive hover:bg-muted" @click="askDelete">
                        <Trash2 class="size-5" /> 刪除
                    </button>
                </div>
            </ResponsiveDialog>

            <ResponsiveDialog v-model:open="confirmOpen" :title="selected ? `刪除這筆${typeLabel(selected.meal_type)}？` : ''">
                <p v-if="selected" class="text-sm text-muted-foreground">
                    {{ shortDate(selected.date, today) }} {{ selected.time }}，包含 {{ selected.item_names.length }} 個項目，共
                    {{ formatKcal(selected.kcal) }} kcal。刪除後無法復原。
                </p>
                <div class="mt-5 flex gap-2">
                    <Button variant="outline" size="lg" class="h-11 flex-1" @click="confirmOpen = false">取消</Button>
                    <Button variant="destructive" size="lg" class="h-11 flex-1" :disabled="deleting" @click="destroy">
                        <LoaderCircle v-if="deleting" class="size-4 animate-spin" />
                        刪除
                    </Button>
                </div>
            </ResponsiveDialog>
        </AppPage>
    </AppLayout>
</template>
