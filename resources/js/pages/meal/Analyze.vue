<script setup lang="ts">
import AppPage from '@/components/AppPage.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/composables/useDateFormat';
import { currentTime } from '@/composables/useMealFood';
import { useMealPhoto } from '@/composables/useMealPhoto';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type { FlashError, SharedData } from '@/types';
import type { AnalysisQuota, AnalyzeMode } from '@/types/meal';
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertCircle, Camera, Clock, ImageUp, LoaderCircle, Pencil, RefreshCw, Sparkles } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    mode: AnalyzeMode;
    today: string;
    quota: AnalysisQuota;
    hint_max_length: number;
}>();

const page = usePage<SharedData>();
const { show } = useToast();
const { previewUrl, takenAt, setPhoto, photoFile, analysisId, clear } = useMealPhoto();

const hint = ref('');
const analyzing = ref(false);
const error = ref<FlashError | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const nowTime = ref(currentTime());
let cancelToken: { cancel: () => void } | null = null;

const isCamera = computed(() => props.mode === 'camera');
const swapLabel = computed(() => (isCamera.value ? '重拍' : '換一張'));
const outOfQuota = computed(() => props.quota.remaining <= 0 && !analyzing.value);
const notFood = computed(() => error.value?.code === 'no_food');
// 次數用完時由下方的「用完」說明取代，不重複顯示失敗原因
const shownError = computed(() => (error.value && error.value.code !== 'daily_limit' ? error.value : null));
const photoError = computed(() => page.props.errors?.photo ?? page.props.errors?.taken_at);

// 照片標籤：拍照為現在時間；上傳為拍攝時間，讀不到時一樣用現在時間
const timeLabel = computed(() => {
    if (!isCamera.value && takenAt.value) {
        const [date, time] = takenAt.value.split(' ');

        return `拍攝於 ${shortDate(date, props.today)} ${time}`;
    }

    return `現在 ${nowTime.value}`;
});

// 後端導回分析頁時以 flash error 帶回失敗原因（含 code），換照片或重新分析時清掉
watch(
    () => page.props.flash?.error,
    (flash) => {
        if (flash) {
            error.value = flash;
        }
    },
    { immediate: true },
);

/**
 * 開啟相機或相簿（重拍／換一張）。
 */
function pick() {
    fileInput.value?.click();
}

/**
 * 選了新照片：換掉暫存照片，清掉先前的失敗原因。
 */
function onPicked(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    setPhoto(file, props.mode);
    error.value = null;
    nowTime.value = currentTime();
}

/**
 * 送出分析：等照片壓縮完成後以 multipart 上傳；成功時後端導向確認頁，記下這張照片屬於哪一筆分析。
 */
async function start() {
    if (analyzing.value || outOfQuota.value) {
        return;
    }

    analyzing.value = true;
    error.value = null;
    const photo = await photoFile();

    if (!photo) {
        analyzing.value = false;

        return;
    }

    router.post(
        route('fitness.meal.analyze.store'),
        { photo, hint: hint.value.trim() || null, taken_at: takenAt.value },
        {
            forceFormData: true,
            preserveState: true,
            preserveScroll: true,
            onCancelToken: (token) => (cancelToken = token),
            onSuccess: (result) => {
                const id = new URL(result.url, window.location.origin).searchParams.get('analysis');
                analysisId.value = id ? Number(id) : null;
            },
            onCancel: () => show('已取消；已送出的分析仍算一次'),
            onFinish: () => {
                analyzing.value = false;
                cancelToken = null;
            },
        },
    );
}

/**
 * 取消分析中的請求。
 */
function cancel() {
    cancelToken?.cancel();
}

/**
 * 改手動輸入：丟棄照片，進空白的新增餐點表單。
 */
function manual() {
    clear();
    router.visit(route('fitness.meal.create'));
}

// 離開分析頁（往其他頁面）時丟棄照片；送出分析與同頁重新載入不算離開，成功導向確認頁時照片要保留到確認頁
let removeListener: (() => void) | null = null;

onMounted(() => {
    const analyzePath = new URL(route('fitness.meal.analyze'), window.location.origin).pathname;

    removeListener = router.on('before', (event) => {
        const visit = event.detail.visit;

        if (visit.method === 'get' && visit.url.pathname !== analyzePath) {
            clear();
        }
    });
});

onUnmounted(() => removeListener?.());
</script>

<template>
    <Head title="分析餐點照片" />
    <AppLayout
        :breadcrumbs="[
            { title: '飲食', href: route('fitness.meal.index') },
            { title: '分析餐點照片', href: '' },
        ]"
    >
        <AppPage title="分析餐點照片" :back-href="route('fitness.meal.index')">
            <input
                ref="fileInput"
                type="file"
                accept="image/*"
                :capture="isCamera ? 'environment' : undefined"
                class="sr-only"
                tabindex="-1"
                aria-hidden="true"
                @change="onPicked"
            />

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 md:items-start md:gap-5">
                <div class="space-y-1.5">
                    <div v-if="previewUrl" class="relative aspect-[4/3] overflow-hidden rounded-xl bg-muted">
                        <img :src="previewUrl" alt="餐點照片" class="size-full object-cover" />
                        <template v-if="analyzing">
                            <div class="absolute inset-0 bg-black/25" />
                            <div class="scan-beam" aria-hidden="true" />
                        </template>
                        <span
                            class="absolute bottom-2.5 left-2.5 inline-flex items-center gap-1 rounded-full bg-black/55 px-2.5 py-1 text-xs font-medium text-white backdrop-blur-sm"
                        >
                            <Clock class="size-3.5" />{{ timeLabel }}
                        </span>
                        <button
                            v-if="!analyzing"
                            type="button"
                            class="absolute bottom-2.5 right-2.5 inline-flex h-8 items-center gap-1 rounded-full bg-white/90 px-3 text-[13px] font-semibold text-neutral-900"
                            @click="pick"
                        >
                            <component :is="isCamera ? Camera : ImageUp" class="size-3.5" />{{ swapLabel }}
                        </button>
                    </div>
                    <button
                        v-else
                        type="button"
                        class="flex aspect-[4/3] w-full flex-col items-center justify-center gap-3 rounded-xl border border-dashed bg-card text-muted-foreground hover:bg-brand-soft"
                        @click="pick"
                    >
                        <span class="grid size-14 place-items-center rounded-2xl bg-brand-soft text-primary">
                            <component :is="isCamera ? Camera : ImageUp" class="size-7" />
                        </span>
                        <span class="font-semibold text-foreground">{{ isCamera ? '拍一張餐點照片' : '選擇餐點照片' }}</span>
                    </button>
                    <InputError :message="photoError" />
                </div>

                <div class="space-y-3">
                    <div v-if="analyzing" class="flex items-center gap-3 rounded-xl border bg-card p-4" role="status">
                        <LoaderCircle class="size-6 shrink-0 animate-spin text-primary" />
                        <div>
                            <p class="font-semibold">分析中，大約 5～20 秒</p>
                            <p class="text-xs text-muted-foreground">請停留在這個畫面，離開會中斷分析。</p>
                        </div>
                    </div>
                    <div
                        v-else-if="shownError || outOfQuota"
                        role="alert"
                        class="flex gap-2.5 rounded-xl border border-destructive/30 bg-destructive/5 p-3.5 text-destructive dark:bg-destructive/15 dark:text-red-400"
                    >
                        <AlertCircle class="mt-0.5 size-5 shrink-0" />
                        <div>
                            <p class="font-semibold">{{ shownError ? shownError.title : `今天的 ${quota.limit} 次已用完` }}</p>
                            <p class="text-sm text-foreground/80">{{ shownError ? shownError.message : '明天再試，或改手動輸入。' }}</p>
                        </div>
                    </div>

                    <section class="space-y-3 rounded-xl border bg-card p-4">
                        <div>
                            <FieldLabel for="analyze_hint">補充說明</FieldLabel>
                            <textarea
                                id="analyze_hint"
                                v-model="hint"
                                :maxlength="hint_max_length"
                                rows="2"
                                :disabled="analyzing"
                                placeholder="例如：飯只吃一半、醬另外放"
                                class="w-full resize-none rounded-lg border border-input bg-card px-3.5 py-2.5 text-base outline-none focus:border-primary focus:ring-2 focus:ring-ring/30 disabled:opacity-60"
                            />
                            <div class="flex justify-between gap-2">
                                <InputError :message="page.props.errors?.hint" />
                                <span class="ml-auto text-xs tabular-nums text-muted-foreground">{{ hint.length }}/{{ hint_max_length }}</span>
                            </div>
                        </div>
                        <span
                            :class="[
                                'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold',
                                quota.remaining <= 0
                                    ? 'bg-destructive/10 text-destructive dark:text-red-400'
                                    : quota.remaining <= 3
                                      ? 'bg-brand-soft text-primary'
                                      : 'bg-muted text-muted-foreground',
                            ]"
                        >
                            <Sparkles class="size-3.5" />
                            {{ quota.remaining <= 0 ? `今天的 ${quota.limit} 次已用完` : `今天還能分析 ${quota.remaining} 次` }}
                        </span>
                    </section>

                    <div
                        class="sticky bottom-[calc(env(safe-area-inset-bottom)+4.75rem)] z-10 -mx-4 flex gap-2 border-t bg-page/95 px-4 py-3 backdrop-blur md:static md:mx-0 md:border-0 md:bg-transparent md:p-0"
                    >
                        <template v-if="analyzing">
                            <Button size="lg" class="h-11 flex-1" disabled>
                                <LoaderCircle class="size-4 animate-spin" />
                                分析中…
                            </Button>
                            <Button variant="ghost" size="lg" class="h-11" @click="cancel">取消</Button>
                        </template>
                        <template v-else-if="outOfQuota">
                            <Button variant="outline" size="lg" class="h-11 flex-1" @click="manual"><Pencil class="size-4" />改手動輸入</Button>
                        </template>
                        <template v-else-if="shownError">
                            <Button v-if="notFood" size="lg" class="h-11 flex-1" @click="pick">
                                <component :is="isCamera ? Camera : ImageUp" class="size-4" />{{ swapLabel }}
                            </Button>
                            <Button v-else size="lg" class="h-11 flex-1" :disabled="!previewUrl" @click="start">
                                <RefreshCw class="size-4" />重新分析
                            </Button>
                            <Button variant="outline" size="lg" class="h-11 flex-1" @click="manual"><Pencil class="size-4" />改手動輸入</Button>
                        </template>
                        <Button v-else size="lg" class="h-11 flex-1" :disabled="!previewUrl" @click="start">
                            <Sparkles class="size-4" />開始分析
                        </Button>
                    </div>
                </div>
            </div>
        </AppPage>
    </AppLayout>
</template>

<style scoped>
/* 分析中：照片上的掃描光帶上下來回 */
.scan-beam {
    position: absolute;
    left: 0;
    right: 0;
    z-index: 1;
    height: 38%;
    background: linear-gradient(to bottom, transparent, hsl(var(--primary) / 0.45) 85%, hsl(var(--primary)) 100%);
    animation: scan 1.8s ease-in-out infinite alternate;
}

@keyframes scan {
    from {
        top: -38%;
    }
    to {
        top: 100%;
        transform: translateY(-100%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .scan-beam {
        animation: none;
        top: 31%;
    }
}
</style>
