<script setup lang="ts">
import ProviderLogo from '@/components/social-login/ProviderLogo.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/composables/useInitials';
import type { ConnectedProvider } from '@/types/social-login';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    provider: ConnectedProvider;
}>();

defineEmits<{
    unlink: [provider: ConnectedProvider];
}>();

const { getInitials } = useInitials();

const account = computed(() => props.provider.account);
const displayName = computed(() => account.value?.name || account.value?.email || props.provider.label);

/**
 * 連結時間以裝置時區顯示為 YYYY/MM/DD。
 */
const linkedDate = computed(() => {
    if (!account.value) {
        return '';
    }

    const date = new Date(account.value.linked_at);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}/${pad(date.getMonth() + 1)}/${pad(date.getDate())}`;
});

/**
 * 第三方名稱與連結日期，例如「王小明 · 2026/11/20 連結」。
 */
const accountMeta = computed(() => [account.value?.name, `${linkedDate.value} 連結`].filter(Boolean).join(' · '));
</script>

<template>
    <div>
        <div class="flex items-center gap-3 p-4">
            <span class="grid size-10 shrink-0 place-items-center rounded-[10px] border bg-white">
                <ProviderLogo :provider="provider.key" class="size-5 text-neutral-500" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 font-semibold">
                    {{ provider.label }}
                    <span
                        v-if="account"
                        class="inline-flex items-center gap-1 rounded-full bg-green-600/10 px-2 py-0.5 text-[11px] font-semibold text-green-700 dark:text-green-400"
                    >
                        <Check class="size-3" />已連結
                    </span>
                </div>
                <div class="mt-0.5 truncate text-[13px] text-muted-foreground">{{ account ? '可用來登入' : '尚未連結' }}</div>
            </div>
            <Button
                v-if="account"
                variant="outline"
                size="sm"
                class="text-destructive hover:bg-destructive/5 hover:text-destructive"
                @click="$emit('unlink', provider)"
            >
                解除連結
            </Button>
            <!-- 連結需要整頁跳轉到第三方授權頁，不用 Inertia Link -->
            <Button v-else variant="outline" size="sm" as-child>
                <a :href="route('fitness.social_login.link', { provider: provider.key })">連結</a>
            </Button>
        </div>

        <div v-if="account" class="mx-4 mb-4 flex items-center gap-3 rounded-[10px] bg-muted p-3">
            <Avatar class="size-9 bg-blue-600 text-sm font-semibold text-white">
                <AvatarImage v-if="account.avatar" :src="account.avatar" referrer-policy="no-referrer" alt="" />
                <AvatarFallback>{{ getInitials(displayName) }}</AvatarFallback>
            </Avatar>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold">{{ account.email ?? displayName }}</div>
                <div class="mt-0.5 truncate text-xs text-muted-foreground">{{ accountMeta }}</div>
            </div>
        </div>
    </div>
</template>
