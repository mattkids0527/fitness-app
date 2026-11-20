<script setup lang="ts">
import FlashAlert from '@/components/FlashAlert.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import ResponsiveDialog from '@/components/ResponsiveDialog.vue';
import ConnectedAccountItem from '@/components/social-login/ConnectedAccountItem.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import type { ConnectedProvider } from '@/types/social-login';
import { Head, router } from '@inertiajs/vue3';
import { Info, Link as LinkIcon, LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    providers: ConnectedProvider[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: '已連結帳號',
        href: '/settings/connected-accounts',
    },
];

const unlinking = ref<ConnectedProvider | null>(null);
const confirmOpen = ref(false);
const processing = ref(false);

/**
 * 開啟解除連結確認。
 */
function askUnlink(provider: ConnectedProvider) {
    unlinking.value = provider;
    confirmOpen.value = true;
}

/**
 * 解除選定平台的連結；成功提示由後端 flash 帶回、AppToast 顯示。
 */
function unlink() {
    if (!unlinking.value) {
        return;
    }

    processing.value = true;
    router.delete(route('fitness.social_login.connected_accounts.destroy', { provider: unlinking.value.key }), {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="已連結帳號" />

        <SettingsLayout>
            <div class="space-y-5">
                <HeadingSmall title="已連結帳號" description="連結後可以直接用該帳號登入，不必輸入密碼。" />

                <FlashAlert />

                <template v-if="providers.length">
                    <div class="divide-y overflow-hidden rounded-xl border bg-card">
                        <ConnectedAccountItem v-for="provider in providers" :key="provider.key" :provider="provider" @unlink="askUnlink" />
                    </div>

                    <div class="flex gap-2.5 rounded-[10px] bg-muted p-3 text-[13px] leading-relaxed text-muted-foreground">
                        <Info class="mt-0.5 size-4 shrink-0" />
                        <p>解除連結後仍可用 Email 與密碼登入。第三方帳號的 Email 不必與本站帳號相同。</p>
                    </div>
                </template>

                <div v-else class="grid place-items-center gap-2 rounded-xl border bg-card px-6 py-10 text-center text-muted-foreground">
                    <span class="grid size-16 place-items-center rounded-[20px] bg-brand-soft text-primary">
                        <LinkIcon class="size-6" />
                    </span>
                    <p>目前沒有可連結的服務</p>
                </div>
            </div>

            <ResponsiveDialog v-model:open="confirmOpen" :title="`解除連結 ${unlinking?.label ?? ''}？`">
                <p v-if="unlinking" class="text-sm text-muted-foreground">
                    解除後就不能用 {{ unlinking.account?.email ?? `這個 ${unlinking.label} 帳號` }} 登入，仍可用 Email
                    與密碼登入，之後也可以重新連結。
                </p>
                <div class="mt-5 flex gap-2">
                    <Button variant="outline" size="lg" class="h-11 flex-1" @click="confirmOpen = false">取消</Button>
                    <Button variant="destructive" size="lg" class="h-11 flex-1" :disabled="processing" @click="unlink">
                        <LoaderCircle v-if="processing" class="size-4 animate-spin" />
                        解除連結
                    </Button>
                </div>
            </ResponsiveDialog>
        </SettingsLayout>
    </AppLayout>
</template>
