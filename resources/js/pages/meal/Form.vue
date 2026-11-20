<script setup lang="ts">
import AppPage from '@/components/AppPage.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import FoodPicker from '@/components/meal/FoodPicker.vue';
import MealItemCard from '@/components/meal/MealItemCard.vue';
import ServingsDialog from '@/components/meal/ServingsDialog.vue';
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Button } from '@/components/ui/button';
import UnitInput from '@/components/UnitInput.vue';
import { shortDate } from '@/composables/useDateFormat';
import { currentTime, fromFormItem, mealTypeForTime, toFormItem, toNumber } from '@/composables/useMealFood';
import { formatKcal } from '@/composables/useNutritionFormat';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    FoodValues,
    MealDetail,
    MealFormDefaults,
    MealFormItem,
    MealFormLimits,
    MealType,
    MealTypeOption,
    RecentFood,
    SavedFood,
} from '@/types/meal';
import { Head, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Repeat, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

const props = defineProps<{
    today: string;
    meal_types: MealTypeOption[];
    saved_foods: SavedFood[];
    recent_foods: RecentFood[];
    copyable_meals: MealDetail[];
    limits: MealFormLimits;
    meal: MealDetail | null;
    defaults: MealFormDefaults | null;
}>();

const { show } = useToast();
const isEdit = props.meal !== null;
const initial = (props.meal ?? props.defaults) as MealDetail | MealFormDefaults;

const form = useForm({
    date: initial.date,
    time: initial.time,
    meal_type: initial.meal_type as MealType | null,
    note: initial.note ?? '',
    items: initial.items.map((food) => toFormItem(food)),
});

// 使用者手動選過餐別後，改時間不再自動切換；改過時間後，改日期不再帶入預設時間
const typeChosen = ref(isEdit || (props.defaults?.type_chosen ?? false));
const timeTouched = ref(isEdit);

const picking = ref<{ food: FoodValues; savedFoodId: number | null } | null>(null);
const servingsOpen = ref(false);
const confirmDeleteOpen = ref(false);
const deleting = ref(false);

const backHref = computed(() => route('fitness.meal.index', initial.date === props.today ? {} : { date: initial.date }));
const totalKcal = computed(() => form.items.reduce((sum, item) => sum + (toNumber(item.kcal) ?? 0), 0));
const savedNames = computed(() => new Set(props.saved_foods.map((food) => food.name.trim().toLowerCase())));
const errors = computed(() => form.errors as Partial<Record<string, string>>);
const typeLabel = (value: MealType) => props.meal_types.find((type) => type.value === value)?.label ?? '';

/**
 * 改時間後，沒手動選過餐別時依時間自動選。
 */
function onTimeChange() {
    timeTouched.value = true;

    if (!typeChosen.value && form.time) {
        form.meal_type = mealTypeForTime(form.time, props.meal_types);
    }
}

/**
 * 改日期後，沒改過時間時：今天帶入現在時間、其他日期帶入餐別的代表時間。
 */
function onDateChange() {
    if (timeTouched.value || !form.meal_type) {
        return;
    }

    const type = props.meal_types.find((option) => option.value === form.meal_type);
    form.time = form.date === props.today ? currentTime() : (type?.default_time ?? form.time);
}

/**
 * 還能不能再加 count 個項目；超過上限時提示。
 */
function hasRoomFor(count: number): boolean {
    if (form.items.length + count <= props.limits.max_items) {
        return true;
    }

    show(`一餐最多 ${props.limits.max_items} 個項目`);

    return false;
}

/**
 * 從常用食物或最近吃過選了一項，接著選份數。
 */
function pickFood(food: FoodValues, savedFoodId: number | null) {
    picking.value = { food, savedFoodId };
    servingsOpen.value = true;
}

/**
 * 以選定份數加入項目；來自常用食物時記下 id，儲存時更新最近使用時間。
 */
function addPicked(food: FoodValues, servings: number) {
    if (!picking.value || !hasRoomFor(1)) {
        return;
    }

    form.items.push(toFormItem(food, picking.value.savedFoodId));
    form.clearErrors('items');
    servingsOpen.value = false;
    show(`已加入${food.name}${servings === 1 ? '' : ` ×${servings}`}`);
}

/**
 * 複製整餐：把那一餐的所有項目加入。
 */
function copyMeal(meal: MealDetail) {
    if (!hasRoomFor(meal.items.length)) {
        return;
    }

    meal.items.forEach((food) => form.items.push(toFormItem(food)));
    form.clearErrors('items');
    show(`已加入 ${meal.items.length} 個項目`);
}

/**
 * 手動新增一個空白項目，展開並把游標放到名稱欄。
 */
async function addManual() {
    if (!hasRoomFor(1)) {
        return;
    }

    form.items.forEach((item) => (item.open = false));
    const item = toFormItem(null, null, true);
    form.items.push(item);
    form.clearErrors('items');
    await nextTick();
    document.getElementById(`item-${item.key}-name`)?.focus();
}

/**
 * 刪除一個項目；項目位置改變，舊的欄位錯誤一併清掉。
 */
function removeItem(index: number) {
    form.items.splice(index, 1);
    form.clearErrors();
}

/**
 * 把項目加入常用食物（以這個項目的數值作為一份）；只重新載入常用食物清單，表單內容保留。
 */
function star(item: MealFormItem) {
    if (!item.name.trim()) {
        show('先填名稱才能加入常用食物');

        return;
    }

    if (savedNames.value.has(item.name.trim().toLowerCase())) {
        show('已經在常用食物裡');

        return;
    }

    if (toNumber(item.kcal) === null) {
        show('先填熱量才能加入常用食物');

        return;
    }

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { saved_food_id, ...food } = fromFormItem(item);

    router.post(route('fitness.meal.saved_foods.store'), food, {
        preserveState: true,
        preserveScroll: true,
        only: ['saved_foods', 'flash'],
        onError: (error) => show(Object.values(error)[0] ?? '加入常用食物失敗'),
    });
}

/**
 * 儲存：項目只送後端需要的欄位；驗證失敗時展開有錯誤的項目。
 */
function submit() {
    form.transform((data) => ({
        ...data,
        items: data.items.map((item) => fromFormItem(item)),
    }));

    const options = {
        preserveScroll: true,
        onError: (errors: Record<string, string>) => {
            form.items.forEach((item, index) => {
                if (Object.keys(errors).some((key) => key.startsWith(`items.${index}.`))) {
                    item.open = true;
                }
            });
        },
    };

    if (isEdit && props.meal) {
        form.put(route('fitness.meal.update', { meal: props.meal.id }), options);
    } else {
        form.post(route('fitness.meal.store'), options);
    }
}

/**
 * 刪除這一餐；成功訊息由後端 flash 經 AppToast 顯示。
 */
function destroy() {
    if (!props.meal) {
        return;
    }

    deleting.value = true;
    router.delete(route('fitness.meal.destroy', { meal: props.meal.id }), {
        onFinish: () => {
            deleting.value = false;
            confirmDeleteOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="isEdit ? '編輯餐點' : '新增餐點'" />
    <AppLayout
        :breadcrumbs="[
            { title: '飲食', href: route('fitness.meal.index') },
            { title: isEdit ? '編輯餐點' : '新增餐點', href: '' },
        ]"
    >
        <AppPage :title="isEdit ? '編輯餐點' : '新增餐點'" :back-href="backHref">
            <template v-if="isEdit" #actions>
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-full text-destructive hover:bg-muted"
                    aria-label="刪除這一餐"
                    @click="confirmDeleteOpen = true"
                >
                    <Trash2 class="size-5" />
                </button>
            </template>

            <div v-if="defaults?.copied_from" class="mb-3 flex gap-2.5 rounded-lg bg-muted p-3 text-sm leading-relaxed text-muted-foreground">
                <Repeat class="mt-0.5 size-4 shrink-0" />
                <p>
                    已帶入{{ shortDate(defaults.copied_from.date, today) }}的{{
                        typeLabel(defaults.copied_from.meal_type)
                    }}內容，可以修改，按「儲存」才會建立。
                </p>
            </div>

            <form class="grid gap-3 md:grid-cols-2 md:items-start md:gap-5" novalidate @submit.prevent="submit">
                <div class="space-y-3">
                    <section class="space-y-3 rounded-xl border bg-card p-4">
                        <div class="grid grid-cols-2 gap-2.5">
                            <div class="min-w-0">
                                <label for="meal_date" class="mb-1 block text-xs text-muted-foreground">日期</label>
                                <UnitInput
                                    id="meal_date"
                                    v-model="form.date"
                                    type="date"
                                    :max="today"
                                    :invalid="!!form.errors.date"
                                    @change="onDateChange"
                                />
                            </div>
                            <div class="min-w-0">
                                <label for="meal_time" class="mb-1 block text-xs text-muted-foreground">時間</label>
                                <UnitInput id="meal_time" v-model="form.time" type="time" :invalid="!!form.errors.time" @change="onTimeChange" />
                            </div>
                        </div>
                        <InputError :message="form.errors.date ?? form.errors.time" />
                        <div>
                            <span class="mb-1 block text-xs text-muted-foreground">餐別</span>
                            <SegmentedControl v-model="form.meal_type" :options="meal_types" @update:model-value="typeChosen = true" />
                            <p v-if="!typeChosen" class="mt-1.5 text-xs text-muted-foreground">依時間自動選擇，可以手動改。</p>
                            <InputError class="mt-1.5" :message="form.errors.meal_type" />
                        </div>
                    </section>

                    <section class="rounded-xl border bg-card p-4">
                        <h2 class="mb-3 font-semibold">快速帶入</h2>
                        <FoodPicker
                            :today="today"
                            :meal-types="meal_types"
                            :saved-foods="saved_foods"
                            :recent-foods="recent_foods"
                            :copyable-meals="copyable_meals"
                            @pick="pickFood"
                            @copy="copyMeal"
                        />
                    </section>
                </div>

                <div class="space-y-3">
                    <section class="rounded-xl border bg-card p-4">
                        <h2 class="mb-3 flex items-center gap-2 font-semibold">
                            這一餐的項目
                            <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold text-muted-foreground">{{
                                form.items.length
                            }}</span>
                        </h2>
                        <InputError class="mb-2" :message="form.errors.items" />
                        <p v-if="!form.items.length" class="text-sm text-muted-foreground">還沒有項目。從「快速帶入」選擇，或手動新增。</p>
                        <div class="space-y-2">
                            <MealItemCard
                                v-for="(item, index) in form.items"
                                :key="item.key"
                                v-model:item="form.items[index]"
                                :index="index"
                                :errors="errors"
                                :saved="savedNames.has(item.name.trim().toLowerCase())"
                                @remove="removeItem(index)"
                                @star="star(item)"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="lg"
                            class="mt-3 h-11 w-full"
                            :disabled="form.items.length >= limits.max_items"
                            @click="addManual"
                        >
                            <Plus class="size-4" /> 手動新增項目
                        </Button>
                    </section>

                    <section class="rounded-xl border bg-card p-4">
                        <FieldLabel for="meal_note">備註</FieldLabel>
                        <textarea
                            id="meal_note"
                            v-model="form.note"
                            maxlength="500"
                            rows="2"
                            placeholder="例如：外食、聚餐"
                            class="w-full resize-none rounded-lg border border-input bg-card px-3.5 py-2.5 text-base outline-none focus:border-primary focus:ring-2 focus:ring-ring/30"
                        />
                        <InputError class="mt-1" :message="form.errors.note" />
                    </section>
                </div>

                <div
                    class="sticky bottom-[calc(env(safe-area-inset-bottom)+4.75rem)] z-10 -mx-4 flex items-center gap-3 border-t bg-page/95 px-4 py-3 backdrop-blur md:static md:col-span-2 md:mx-0 md:border-0 md:bg-transparent md:px-0"
                >
                    <div class="min-w-0">
                        <div class="text-xs text-muted-foreground">本餐合計</div>
                        <div class="text-lg font-extrabold tabular-nums">{{ formatKcal(totalKcal) }} kcal</div>
                    </div>
                    <Button type="submit" size="lg" class="ml-auto h-11 min-w-36" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        儲存
                    </Button>
                </div>
            </form>

            <ServingsDialog v-model:open="servingsOpen" :food="picking?.food ?? null" :servings="limits.servings" @add="addPicked" />

            <ResponsiveDialog v-model:open="confirmDeleteOpen" :title="meal ? `刪除這筆${typeLabel(meal.meal_type)}？` : ''">
                <p v-if="meal" class="text-sm text-muted-foreground">
                    {{ shortDate(meal.date, today) }} {{ meal.time }}，包含 {{ meal.items.length }} 個項目，共
                    {{ formatKcal(meal.kcal) }} kcal。刪除後無法復原。
                </p>
                <div class="mt-5 flex gap-2">
                    <Button variant="outline" size="lg" class="h-11 flex-1" @click="confirmDeleteOpen = false">取消</Button>
                    <Button variant="destructive" size="lg" class="h-11 flex-1" :disabled="deleting" @click="destroy">
                        <LoaderCircle v-if="deleting" class="size-4 animate-spin" />
                        刪除
                    </Button>
                </div>
            </ResponsiveDialog>
        </AppPage>
    </AppLayout>
</template>
