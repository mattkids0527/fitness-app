<script setup lang="ts">
import AppPage from '@/components/AppPage.vue';
import RecordTypeBadge from '@/components/exercise/RecordTypeBadge.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import FlashAlert from '@/components/FlashAlert.vue';
import InputError from '@/components/InputError.vue';
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { RECORD_TYPE_ICONS } from '@/composables/useRecordType';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ArchivedExercise, Exercise, ExerciseCategoryGroup, RecordType, RecordTypeOption } from '@/types/exercise';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    ArchiveRestore,
    ArrowDown,
    ArrowUp,
    ChevronDown,
    ChevronRight,
    ChevronUp,
    Dumbbell,
    EllipsisVertical,
    FolderPlus,
    LoaderCircle,
    Lock,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    categories: ExerciseCategoryGroup[];
    archived: ArchivedExercise[];
    record_types: RecordTypeOption[];
}>();

const showArchived = ref(false);
const restoring = ref<number | null>(null);

// 分類選單與各個確認框都以 id 找回分類，排序或改名後 props 重新載入也能對上
const menuId = ref<number | null>(null);
const menuOpen = ref(false);
const blockedOpen = ref(false);
const deleteOpen = ref(false);
const deleting = ref(false);
const moving = ref(false);
const selected = computed(() => props.categories.find((category) => category.id === menuId.value) ?? null);
const selectedIndex = computed(() => props.categories.findIndex((category) => category.id === menuId.value));
const selectedArchivedCount = computed(() => props.archived.filter((exercise) => exercise.category_id === menuId.value).length);

// 新增分類／改名面板；改名時記錄方式鎖住
const catFormOpen = ref(false);
const renaming = ref(false);
const catForm = useForm({ name: '', record_type: 'duration' as RecordType });

/**
 * 記錄方式的中文名稱。
 */
function typeLabel(type: RecordType): string {
    return props.record_types.find((option) => option.value === type)?.label ?? type;
}

/**
 * MET 一律顯示到小數 1 位。
 */
function metText(met: number): string {
    return met.toFixed(1);
}

/**
 * 項目列的參數摘要：逐組另列每下秒數與組間休息。
 */
function exerciseParams(exercise: Exercise, type: RecordType): string {
    return type === 'sets'
        ? `MET ${metText(exercise.met)} · 每下 ${exercise.sec_per_rep} 秒 · 休息 ${exercise.rest_sec} 秒`
        : `MET ${metText(exercise.met)}`;
}

/**
 * 打開新增分類面板。
 */
function openNewCategory() {
    renaming.value = false;
    catForm.clearErrors();
    catForm.defaults({ name: '', record_type: 'duration' });
    catForm.reset();
    catFormOpen.value = true;
}

/**
 * 從分類選單改開改名面板。
 */
function openRename() {
    if (!selected.value) {
        return;
    }

    renaming.value = true;
    catForm.clearErrors();
    catForm.defaults({ name: selected.value.name, record_type: selected.value.record_type });
    catForm.reset();
    menuOpen.value = false;
    catFormOpen.value = true;
}

/**
 * 建立分類或改名；同名等錯誤留在面板內。
 */
function submitCategory() {
    const options = { preserveScroll: true, onSuccess: () => (catFormOpen.value = false) };

    if (renaming.value && menuId.value !== null) {
        catForm.put(route('fitness.exercise.categories.update', { category: menuId.value }), options);
    } else {
        catForm.post(route('fitness.exercise.categories.store'), options);
    }
}

/**
 * 打開分類選單。
 */
function openMenu(category: ExerciseCategoryGroup) {
    menuId.value = category.id;
    menuOpen.value = true;
}

/**
 * 往上（up）或往下（down）移一格；選單保持開啟，可以連續移動。
 */
function move(direction: 'up' | 'down') {
    if (menuId.value === null || moving.value) {
        return;
    }

    moving.value = true;
    router.post(
        route('fitness.exercise.categories.move', { category: menuId.value }),
        { direction },
        { preserveScroll: true, onFinish: () => (moving.value = false) },
    );
}

/**
 * 從選單按刪除：還有項目時說明不能刪，否則開刪除確認。
 */
function askDelete() {
    menuOpen.value = false;

    if (selected.value?.exercises.length) {
        blockedOpen.value = true;
    } else {
        deleteOpen.value = true;
    }
}

/**
 * 刪除分類；只剩封存項目時它們會變成沒有分類。
 */
function destroyCategory() {
    if (menuId.value === null) {
        return;
    }

    deleting.value = true;
    router.delete(route('fitness.exercise.categories.destroy', { category: menuId.value }), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            deleteOpen.value = false;
        },
    });
}

/**
 * 還原封存的項目；原分類已刪除時由後端放回同記錄方式的第一個分類。
 */
function restore(exercise: ArchivedExercise) {
    restoring.value = exercise.id;
    router.post(route('fitness.exercise.restore', { exercise: exercise.id }), {}, { preserveScroll: true, onFinish: () => (restoring.value = null) });
}
</script>

<template>
    <Head title="分類與項目" />
    <AppLayout
        :breadcrumbs="[
            { title: '健身', href: route('coming_soon.workouts') },
            { title: '分類與項目', href: route('fitness.exercise.index') },
        ]"
    >
        <AppPage title="分類與項目" :back-href="route('coming_soon.workouts')" narrow>
            <template v-if="categories.length" #actions>
                <Button as-child class="h-10">
                    <Link :href="route('fitness.exercise.create')"><Plus class="size-4" />新增項目</Link>
                </Button>
            </template>

            <FlashAlert class="mb-3" />

            <div v-if="!categories.length" class="grid place-items-center gap-2 px-6 py-14 text-center text-muted-foreground">
                <span class="grid size-16 place-items-center rounded-2xl bg-brand-soft text-primary"><Dumbbell class="size-7" /></span>
                <h2 class="mt-2 font-semibold text-foreground">還沒有分類</h2>
                <p class="text-sm">先新增分類並選擇記錄方式，再把項目放進去。</p>
            </div>

            <section v-for="(category, index) in categories" :key="category.id">
                <div :class="['mx-1 mb-1.5 flex items-center gap-2', index === 0 ? 'mt-1' : 'mt-[18px]']">
                    <h2 class="text-[15px] font-bold">{{ category.name }}</h2>
                    <RecordTypeBadge :type="category.record_type" :label="typeLabel(category.record_type)" />
                    <span class="text-[13px] text-muted-foreground">{{ category.exercises.length }} 項</span>
                    <button
                        type="button"
                        class="ml-auto grid size-8 place-items-center rounded-lg text-muted-foreground hover:bg-muted"
                        :aria-label="`${category.name}的選項`"
                        @click="openMenu(category)"
                    >
                        <EllipsisVertical class="size-4" />
                    </button>
                </div>
                <div class="overflow-hidden rounded-xl border bg-card">
                    <Link
                        v-for="exercise in category.exercises"
                        :key="exercise.id"
                        :href="route('fitness.exercise.edit', { exercise: exercise.id })"
                        class="flex min-h-[52px] items-center gap-2.5 border-b py-2 pl-3.5 pr-2.5 hover:bg-muted/50"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ exercise.name }}</div>
                            <div class="text-xs tabular-nums text-muted-foreground">{{ exerciseParams(exercise, category.record_type) }}</div>
                        </div>
                        <ChevronRight class="size-4 text-muted-foreground" />
                    </Link>
                    <Link
                        :href="route('fitness.exercise.create', { category: category.id })"
                        class="flex min-h-11 items-center gap-1.5 px-3.5 text-sm font-semibold text-primary hover:bg-muted/50"
                    >
                        <Plus class="size-4" />新增到「{{ category.name }}」
                    </Link>
                </div>
            </section>

            <button
                type="button"
                class="mt-4 flex h-12 w-full items-center justify-center gap-1.5 rounded-xl border-[1.5px] border-dashed border-input text-sm font-semibold text-muted-foreground hover:bg-muted/50"
                @click="openNewCategory"
            >
                <FolderPlus class="size-4" />新增分類
            </button>

            <template v-if="archived.length">
                <button
                    type="button"
                    class="mt-5 flex w-full items-center gap-1.5 p-1 text-[13px] font-semibold text-muted-foreground"
                    :aria-expanded="showArchived"
                    aria-controls="archived-exercises"
                    @click="showArchived = !showArchived"
                >
                    <Archive class="size-4" />已封存（{{ archived.length }}）
                    <component :is="showArchived ? ChevronUp : ChevronDown" class="size-4" />
                </button>
                <div v-if="showArchived" id="archived-exercises">
                    <p class="mx-1 mb-1.5 text-xs text-muted-foreground">封存的項目不會出現在選擇清單，歷史紀錄照常顯示。</p>
                    <div class="overflow-hidden rounded-xl border bg-card">
                        <div
                            v-for="exercise in archived"
                            :key="exercise.id"
                            class="flex min-h-[52px] items-center gap-2.5 border-b py-2 pl-3.5 pr-2.5 last:border-b-0"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-muted-foreground">{{ exercise.name }}</div>
                                <div class="text-xs tabular-nums text-muted-foreground">
                                    {{ exercise.category_name ?? '沒有分類' }} · {{ exercise.log_count }} 筆紀錄
                                </div>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-8 items-center gap-1 rounded-lg border border-input bg-background px-2.5 text-[13px] font-semibold hover:bg-muted disabled:opacity-50"
                                :disabled="restoring !== null"
                                @click="restore(exercise)"
                            >
                                <LoaderCircle v-if="restoring === exercise.id" class="size-3.5 animate-spin" />
                                <ArchiveRestore v-else class="size-3.5" />還原
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <ResponsiveDialog v-model:open="catFormOpen" :title="renaming ? '分類改名' : '新增分類'">
                <form class="space-y-4" novalidate @submit.prevent="submitCategory">
                    <div>
                        <FieldLabel for="category_name" required>名稱</FieldLabel>
                        <Input
                            id="category_name"
                            v-model="catForm.name"
                            aria-required="true"
                            maxlength="30"
                            placeholder="例如：核心、伸展"
                            :class="['h-12 text-base', catForm.errors.name ? 'border-destructive' : '']"
                        />
                        <InputError class="mt-1" :message="catForm.errors.name" />
                    </div>
                    <div>
                        <span class="mb-1 flex items-center gap-1 text-xs text-muted-foreground"
                            >記錄方式<span class="text-primary" aria-hidden="true">*</span></span
                        >
                        <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="記錄方式">
                            <button
                                v-for="option in record_types"
                                :key="option.value"
                                type="button"
                                role="radio"
                                :aria-checked="catForm.record_type === option.value"
                                :disabled="renaming"
                                :class="[
                                    'grid gap-1.5 rounded-xl border-[1.5px] p-3 text-left',
                                    catForm.record_type === option.value ? 'border-primary bg-brand-soft' : 'border-border bg-card',
                                    renaming && catForm.record_type !== option.value ? 'opacity-45' : '',
                                ]"
                                @click="catForm.record_type = option.value"
                            >
                                <span class="flex items-center gap-1.5 font-bold">
                                    <component
                                        :is="RECORD_TYPE_ICONS[option.value]"
                                        :class="['size-4', catForm.record_type === option.value ? 'text-primary' : 'text-muted-foreground']"
                                        aria-hidden="true"
                                    />
                                    {{ option.label }}
                                    <Lock
                                        v-if="renaming && catForm.record_type === option.value"
                                        class="size-3.5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </span>
                                <small class="text-xs leading-normal text-muted-foreground">{{ option.hint }}</small>
                            </button>
                        </div>
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            {{ renaming ? '記錄方式建立後不能改。' : '建立後不能改。分類底下的項目都用這種方式記錄。' }}
                        </p>
                        <InputError class="mt-1" :message="catForm.errors.record_type" />
                    </div>
                    <Button type="submit" size="lg" class="h-11 w-full" :disabled="catForm.processing">
                        <LoaderCircle v-if="catForm.processing" class="size-4 animate-spin" />
                        {{ renaming ? '儲存' : '建立分類' }}
                    </Button>
                </form>
            </ResponsiveDialog>

            <ResponsiveDialog
                v-model:open="menuOpen"
                :title="selected?.name ?? ''"
                :description="selected ? `${typeLabel(selected.record_type)} · ${selected.exercises.length} 個項目` : undefined"
            >
                <div v-if="selected" class="grid gap-0.5">
                    <button type="button" class="flex h-12 items-center gap-3 rounded-lg px-2 hover:bg-muted" @click="openRename">
                        <Pencil class="size-5" /> 改名
                    </button>
                    <button
                        type="button"
                        class="flex h-12 items-center gap-3 rounded-lg px-2 hover:bg-muted disabled:opacity-35 disabled:hover:bg-transparent"
                        :disabled="selectedIndex <= 0 || moving"
                        @click="move('up')"
                    >
                        <ArrowUp class="size-5" /> 往上移
                    </button>
                    <button
                        type="button"
                        class="flex h-12 items-center gap-3 rounded-lg px-2 hover:bg-muted disabled:opacity-35 disabled:hover:bg-transparent"
                        :disabled="selectedIndex === categories.length - 1 || moving"
                        @click="move('down')"
                    >
                        <ArrowDown class="size-5" /> 往下移
                    </button>
                    <button type="button" class="flex h-12 items-center gap-3 rounded-lg px-2 text-destructive hover:bg-muted" @click="askDelete">
                        <Trash2 class="size-5" /> 刪除分類
                    </button>
                </div>
            </ResponsiveDialog>

            <ResponsiveDialog v-model:open="blockedOpen" :title="selected ? `還不能刪除「${selected.name}」` : ''">
                <p v-if="selected" class="text-sm text-muted-foreground">
                    這個分類還有 {{ selected.exercises.length }} 個項目。先刪除項目，或把項目移到其他「{{
                        typeLabel(selected.record_type)
                    }}」分類，再刪除分類。
                </p>
                <Button size="lg" class="mt-5 h-11 w-full" @click="blockedOpen = false">知道了</Button>
            </ResponsiveDialog>

            <ResponsiveDialog v-model:open="deleteOpen" :title="selected ? `刪除「${selected.name}」？` : ''">
                <p class="text-sm text-muted-foreground">
                    <template v-if="selectedArchivedCount">
                        分類裡只剩 {{ selectedArchivedCount }} 個封存項目，刪除後它們會變成沒有分類；歷史紀錄不受影響。
                    </template>
                    <template v-else>這個分類沒有項目，刪除後無法復原。</template>
                </p>
                <div class="mt-5 flex gap-2">
                    <Button variant="outline" size="lg" class="h-11 flex-1" @click="deleteOpen = false">取消</Button>
                    <Button variant="destructive" size="lg" class="h-11 flex-1" :disabled="deleting" @click="destroyCategory">
                        <LoaderCircle v-if="deleting" class="size-4 animate-spin" />
                        刪除
                    </Button>
                </div>
            </ResponsiveDialog>
        </AppPage>
    </AppLayout>
</template>
