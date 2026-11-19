<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <AuthLayout title="驗證 Email" description="請點擊我們寄到你信箱的連結，完成 Email 驗證。">
        <Head title="Email 驗證" />

        <div v-if="status === 'verification-link-sent'" class="mb-4 text-center text-sm font-medium text-green-600">
            新的驗證連結已寄到你註冊時填寫的 Email。
        </div>

        <form @submit.prevent="submit" class="space-y-6 text-center">
            <Button :disabled="form.processing" variant="secondary">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                重新寄送驗證信
            </Button>

            <TextLink :href="route('logout')" method="post" as="button" class="mx-auto block text-sm"> 登出 </TextLink>
        </form>
    </AuthLayout>
</template>
