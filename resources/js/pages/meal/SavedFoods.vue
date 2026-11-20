<script setup lang="ts">
import AppPage from '@/components/AppPage.vue';
import InputError from '@/components/InputError.vue';
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import UnitInput from '@/components/UnitInput.vue';
import { shortDate } from '@/composables/useDateFormat';
import { formatGrams, formatKcal } from '@/composables/useNutritionFormat';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SavedFood } from '@/types/meal';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Info, LoaderCircle, Plus, Star, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    today: string;
    saved_foods: SavedFood[];
}>();

const nutrients = [
    { field: 'protein_g', label: '蛋白質' },
    { field: 'carbs_g', label: '碳水' },
    { field: 'fat_g', label: '脂肪' },
    { field: 'fiber_g', label: '纖維' },
] as const;

const editing = ref<SavedFood | null>(null);
const formOpen = ref(false);
const confirmOpen = ref(false);
const deleting = ref(false);

const form = useForm({
    name: '',
    portion: '',
    grams: '' as number | string,
    kcal: '' as number | string,
    protein_g: '' as number | string,
    carbs_g: '' as number | string,
    fat_g: '' as number | string,
    fiber_g: '' as number | string,
});

/**
 * 打開新增或編輯面板；編輯時帶入原本的數值（null 轉成空字串）。
 */
function openForm(food: SavedFood | null) {
    editing.value = food;
    form.clearErrors();
    form.defaults({
        name: food?.name ?? '',
        portion: food?.portion ?? '',
        grams: food?.grams ?? '',
        kcal: food?.kcal ?? '',
        protein_g: food?.protein_g ?? '',
        carbs_g: food?.carbs_g ?? '',
        fat_g: food?.fat_g ?? '',
        fiber_g: food?.fiber_g ?? '',
    });
    form.reset();
    formOpen.value = true;
}

/**
 * 儲存常用食物；成功訊息由後端 flash 經 AppToast 顯示，同名等錯誤留在面板內。
 */
function submit() {
    const options = { preserveScroll: true, onSuccess: () => (formOpen.value = false) };

    if (editing.value) {
        form.put(route('fitness.meal.saved_foods.update', { savedFood: editing.value.id }), options);
    } else {
        form.post(route('fitness.meal.saved_foods.store'), options);
    }
}

/**
 * 從編輯面板改開刪除確認。
 */
function askDelete() {
    formOpen.value = false;
    confirmOpen.value = true;
}

/**
 * 取消刪除時回到編輯面板。
 */
function cancelDelete() {
    confirmOpen.value = false;
    formOpen.value = true;
}

/**
 * 刪除常用食物；已記錄的餐點不受影響。
 */
function destroy() {
    if (!editing.value) {
        return;
    }

    deleting.value = true;
    router.delete(route('fitness.meal.saved_foods.destroy', { savedFood: editing.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="常用食物" />
    <AppLayout
        :breadcrumbs="[
            { title: '飲食', href: route('fitness.meal.index') },
            { title: '常用食物', href: route('fitness.meal.saved_foods.index') },
        ]"
    >
        <AppPage title="常用食物" :description="`${saved_foods.length} 項 · 依最近使用排序`" :back-href="route('fitness.meal.index')" narrow>
            <template #actions>
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-full text-primary hover:bg-muted"
                    aria-label="新增常用食物"
                    @click="openForm(null)"
                >
                    <Plus class="size-5" />
                </button>
            </template>

            <div class="flex gap-2.5 rounded-lg bg-muted p-3 text-sm leading-relaxed text-muted-foreground">
                <Info class="mt-0.5 size-4 shrink-0" />
                <p>記錄餐點時按項目旁的 ☆ 也能加入。數值以「一份」為準，帶入時可以選份數。</p>
            </div>

            <ul v-if="saved_foods.length" class="mt-3 overflow-hidden rounded-xl border bg-card">
                <li v-for="food in saved_foods" :key="food.id" class="border-b last:border-b-0">
                    <button type="button" class="flex w-full items-center gap-3 px-3.5 py-3 text-left hover:bg-muted/50" @click="openForm(food)">
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ food.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ food.portion || '1 份' }}<template v-if="food.grams !== null"> · {{ formatGrams(food.grams) }} g</template> ·
                                蛋白質 {{ formatGrams(food.protein_g) }} · 碳水 {{ formatGrams(food.carbs_g) }} · 脂肪 {{ formatGrams(food.fat_g) }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ food.last_used_on ? `最近使用：${shortDate(food.last_used_on, today)}` : '還沒用過' }}
                            </div>
                        </div>
                        <div class="text-right font-bold tabular-nums">
                            {{ formatKcal(food.kcal) }}
                            <small class="block text-[11px] font-medium text-muted-foreground">kcal / 份</small>
                        </div>
                    </button>
                </li>
            </ul>

            <div v-else class="grid place-items-center gap-2 px-6 py-20 text-center text-muted-foreground">
                <span class="grid size-16 place-items-center rounded-2xl bg-brand-soft text-primary"><Star class="size-7" /></span>
                <h2 class="mt-2 font-semibold text-foreground">還沒有常用食物</h2>
                <p class="text-sm">按右上角 ＋ 新增，或在記錄餐點時按 ☆。</p>
            </div>

            <ResponsiveDialog v-model:open="formOpen" :title="editing ? '編輯常用食物' : '新增常用食物'" description="數值以「一份」為準。">
                <form class="space-y-2.5" @submit.prevent="submit">
                    <div>
                        <label for="saved_food_name" class="mb-1 block text-xs text-muted-foreground">名稱</label>
                        <Input
                            id="saved_food_name"
                            v-model="form.name"
                            maxlength="100"
                            placeholder="例如：茶葉蛋"
                            :class="['h-12', form.errors.name ? 'border-destructive' : '']"
                        />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="min-w-0">
                            <label for="saved_food_portion" class="mb-1 block text-xs text-muted-foreground">份量描述（選填）</label>
                            <Input id="saved_food_portion" v-model="form.portion" maxlength="50" placeholder="例如：1 顆" class="h-12" />
                        </div>
                        <div class="min-w-0">
                            <label for="saved_food_grams" class="mb-1 block text-xs text-muted-foreground">重量（選填）</label>
                            <UnitInput id="saved_food_grams" v-model="form.grams" unit="g" min="0" :invalid="!!form.errors.grams" />
                        </div>
                    </div>
                    <InputError :message="form.errors.portion ?? form.errors.grams" />
                    <div>
                        <label for="saved_food_kcal" class="mb-1 block text-xs text-muted-foreground">熱量</label>
                        <UnitInput id="saved_food_kcal" v-model="form.kcal" unit="kcal" min="0" :invalid="!!form.errors.kcal" />
                        <InputError class="mt-1" :message="form.errors.kcal" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div v-for="nutrient in nutrients" :key="nutrient.field" class="min-w-0">
                            <label :for="`saved_food_${nutrient.field}`" class="mb-1 block text-xs text-muted-foreground"
                                >{{ nutrient.label }}（選填）</label
                            >
                            <UnitInput
                                :id="`saved_food_${nutrient.field}`"
                                v-model="form[nutrient.field]"
                                unit="g"
                                min="0"
                                :invalid="!!form.errors[nutrient.field]"
                            />
                            <InputError class="mt-1" :message="form.errors[nutrient.field]" />
                        </div>
                    </div>
                    <div class="flex gap-2 pt-2.5">
                        <Button
                            v-if="editing"
                            type="button"
                            variant="outline"
                            size="lg"
                            class="h-11 px-3 text-destructive hover:text-destructive"
                            aria-label="刪除"
                            @click="askDelete"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                        <Button type="button" variant="outline" size="lg" class="h-11 flex-1" @click="formOpen = false">取消</Button>
                        <Button type="submit" size="lg" class="h-11 flex-1" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                            儲存
                        </Button>
                    </div>
                </form>
            </ResponsiveDialog>

            <ResponsiveDialog v-model:open="confirmOpen" :title="editing ? `刪除「${editing.name}」？` : ''">
                <p class="text-sm text-muted-foreground">只會從常用食物移除，已經記錄的餐點不受影響。</p>
                <div class="mt-5 flex gap-2">
                    <Button variant="outline" size="lg" class="h-11 flex-1" @click="cancelDelete">取消</Button>
                    <Button variant="destructive" size="lg" class="h-11 flex-1" :disabled="deleting" @click="destroy">
                        <LoaderCircle v-if="deleting" class="size-4 animate-spin" />
                        刪除
                    </Button>
                </div>
            </ResponsiveDialog>
        </AppPage>
    </AppLayout>
</template>
