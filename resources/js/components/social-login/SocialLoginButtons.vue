<script setup lang="ts">
import ProviderLogo from '@/components/social-login/ProviderLogo.vue';
import { Button } from '@/components/ui/button';
import type { SharedData } from '@/types';
import type { SocialLoginSharedData } from '@/types/social-login';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<SharedData & SocialLoginSharedData>();

// 已啟用且設定齊全的 provider；沒有時整個區塊不顯示
const providers = computed(() => page.props.social_login?.providers ?? []);
</script>

<template>
    <template v-if="providers.length">
        <div class="flex items-center gap-3 text-xs text-muted-foreground">
            <span class="h-px flex-1 bg-border" />
            或
            <span class="h-px flex-1 bg-border" />
        </div>

        <div class="grid gap-2">
            <!-- 依 Google 品牌規範：白底、四色 G；深色模式改深底。整頁跳轉到第三方授權頁，不用 Inertia Link -->
            <Button
                v-for="provider in providers"
                :key="provider.key"
                variant="outline"
                class="w-full border-[#dadce0] bg-white text-[#1f1f1f] hover:bg-neutral-50 hover:text-[#1f1f1f] dark:border-[#8e918f] dark:bg-[#131314] dark:text-[#e3e3e3] dark:hover:bg-[#1f1f20] dark:hover:text-[#e3e3e3]"
                as-child
            >
                <a :href="route('fitness.social_login.redirect', { provider: provider.key })">
                    <ProviderLogo :provider="provider.key" class="size-[18px]" />
                    使用 {{ provider.label }} 帳戶登入
                </a>
            </Button>
        </div>
    </template>
</template>
