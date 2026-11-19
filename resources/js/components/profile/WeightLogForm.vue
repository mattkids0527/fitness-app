<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import UnitInput from '@/components/UnitInput.vue';
import { localToday } from '@/composables/useDateFormat';
import { useToast } from '@/composables/useToast';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        layout?: 'stacked' | 'inline';
        submitLabel?: string;
    }>(),
    { layout: 'stacked', submitLabel: '儲存' },
);

const emit = defineEmits<{ saved: [] }>();
const { show } = useToast();
const today = localToday();

const form = useForm({
    weight_kg: '' as string | number,
    measured_on: today,
});

/**
 * 新增體重紀錄；成功後清空表單並提示。
 */
function submit() {
    const weight = Number(form.weight_kg);

    form.clearErrors();

    if (!(weight >= 20 && weight <= 300)) {
        form.setError('weight_kg', '體重需介於 20 到 300 公斤。');

        return;
    }

    form.post(route('fitness.profile.weight_logs.store'), {
        preserveScroll: true,
        onSuccess: () => {
            show(`已記錄體重 ${Math.round(weight * 10) / 10} kg`);
            form.reset();
            emit('saved');
        },
    });
}
</script>

<template>
    <form @submit.prevent="submit">
        <div :class="layout === 'inline' ? 'grid grid-cols-2 gap-2' : 'space-y-4'">
            <div>
                <Label v-if="layout === 'stacked'" for="weight_log_kg" class="mb-2 block font-semibold">體重</Label>
                <UnitInput
                    id="weight_log_kg"
                    v-model="form.weight_kg"
                    unit="kg"
                    placeholder="體重"
                    :invalid="!!form.errors.weight_kg"
                    aria-label="體重"
                />
            </div>
            <div>
                <Label v-if="layout === 'stacked'" for="weight_log_date" class="mb-2 block font-semibold">量測日期</Label>
                <UnitInput
                    id="weight_log_date"
                    v-model="form.measured_on"
                    type="date"
                    :max="today"
                    :invalid="!!form.errors.measured_on"
                    aria-label="量測日期"
                />
            </div>
        </div>
        <InputError class="mt-1.5" :message="form.errors.weight_kg" />
        <InputError class="mt-1.5" :message="form.errors.measured_on" />
        <Button type="submit" size="lg" class="mt-4 h-11 w-full" :disabled="form.processing">
            <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
            {{ submitLabel }}
        </Button>
    </form>
</template>
