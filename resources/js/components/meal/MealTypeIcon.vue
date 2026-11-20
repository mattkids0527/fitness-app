<script setup lang="ts">
import type { MealType } from '@/types/meal';
import { Cookie, Moon, Sun, Sunrise } from 'lucide-vue-next';

defineProps<{
    type: MealType;
}>();

const icons = { breakfast: Sunrise, lunch: Sun, dinner: Moon, snack: Cookie };
</script>

<template>
    <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-brand-soft text-primary">
        <component :is="icons[type]" class="size-4" />
    </span>
</template>
