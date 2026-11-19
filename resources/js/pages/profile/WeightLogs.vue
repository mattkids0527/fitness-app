<script setup lang="ts">
import AppPage from '@/components/AppPage.vue';
import InputError from '@/components/InputError.vue';
import WeightDelta from '@/components/profile/WeightDelta.vue';
import WeightLogForm from '@/components/profile/WeightLogForm.vue';
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/composables/useDateFormat';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { WeightHistory, WeightLogItem } from '@/types/profile';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Info, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    history: WeightHistory;
    page: number;
    today: string;
}>();

const inertiaPage = usePage();
const { show } = useToast();

const deleting = ref<WeightLogItem | null>(null);
const confirmOpen = ref(false);
const processing = ref(false);
const loadingMore = ref(false);
const onlyOne = computed(() => props.history.total <= 1);
const deleteError = computed(() => (inertiaPage.props.errors as Record<string, string>).weight_log);

/**
 * 開啟刪除確認。
 */
function askDelete(log: WeightLogItem) {
    deleting.value = log;
    confirmOpen.value = true;
}

/**
 * 刪除選定的紀錄。
 */
function destroy() {
    if (!deleting.value) {
        return;
    }

    processing.value = true;
    router.delete(route('fitness.profile.weight_logs.destroy', { weightLog: deleting.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            if (!deleteError.value) {
                show('已刪除');
            }
        },
        onFinish: () => {
            processing.value = false;
            confirmOpen.value = false;
        },
    });
}

/**
 * 多載入一批紀錄。
 */
function loadMore() {
    loadingMore.value = true;
    router.get(
        route('fitness.profile.weight_logs.index'),
        { page: props.page + 1 },
        { preserveScroll: true, preserveState: true, only: ['history', 'page'], onFinish: () => (loadingMore.value = false) },
    );
}
</script>

<template>
    <Head title="體重紀錄" />
    <AppLayout
        :breadcrumbs="[
            { title: '我的', href: route('fitness.profile.show') },
            { title: '體重紀錄', href: route('fitness.profile.weight_logs.index') },
        ]"
    >
        <AppPage title="體重紀錄" :back-href="route('fitness.profile.show')" narrow>
            <section class="rounded-xl border bg-card p-4">
                <h2 class="mb-3 font-semibold">新增紀錄</h2>
                <WeightLogForm layout="inline" submit-label="新增" />
            </section>

            <div class="mx-1 mb-1.5 mt-5 flex justify-between text-sm text-muted-foreground">
                <span>歷史紀錄</span>
                <span>共 {{ history.total }} 筆</span>
            </div>
            <InputError class="mb-2" :message="deleteError" />
            <ul class="overflow-hidden rounded-xl border bg-card">
                <li v-for="(log, index) in history.items" :key="log.id" class="flex items-center gap-3 border-b px-3.5 py-3 last:border-b-0">
                    <div class="flex-1">
                        <div class="font-semibold">{{ shortDate(log.measured_on, today) }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ log.measured_on.replaceAll('-', '/') }}{{ index === 0 ? ' · 目前體重' : '' }}
                        </div>
                    </div>
                    <span class="text-lg font-bold tabular-nums">{{ log.weight_kg.toFixed(1) }}</span>
                    <span class="w-12 text-right"><WeightDelta :delta="log.delta_kg" /></span>
                    <button
                        type="button"
                        class="grid size-9 place-items-center rounded-lg text-muted-foreground hover:bg-muted hover:text-destructive disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-muted-foreground"
                        :disabled="onlyOne"
                        :title="onlyOne ? '至少需保留一筆體重紀錄' : '刪除'"
                        :aria-label="`刪除 ${log.measured_on} 的紀錄`"
                        @click="askDelete(log)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </li>
            </ul>

            <div v-if="onlyOne" class="mt-3 flex gap-2.5 rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                <Info class="mt-0.5 size-4 shrink-0" />
                <p>至少需保留一筆體重紀錄，用來計算 BMR 與每日目標。</p>
            </div>

            <Button v-if="history.has_more" variant="outline" class="mt-3 w-full" :disabled="loadingMore" @click="loadMore">
                <LoaderCircle v-if="loadingMore" class="size-4 animate-spin" />
                載入更多
            </Button>

            <ResponsiveDialog v-model:open="confirmOpen" title="刪除體重紀錄？">
                <p v-if="deleting" class="text-sm text-muted-foreground">
                    {{ shortDate(deleting.measured_on, today) }} {{ deleting.weight_kg }} kg，刪除後無法復原。
                    <template v-if="history.items[0]?.id === deleting.id">刪除後會以前一筆作為目前體重，每日目標也會重新計算。</template>
                </p>
                <div class="mt-5 flex gap-2">
                    <Button variant="outline" size="lg" class="h-11 flex-1" @click="confirmOpen = false">取消</Button>
                    <Button variant="destructive" size="lg" class="h-11 flex-1" :disabled="processing" @click="destroy">
                        <LoaderCircle v-if="processing" class="size-4 animate-spin" />
                        刪除
                    </Button>
                </div>
            </ResponsiveDialog>
        </AppPage>
    </AppLayout>
</template>
