<script setup lang="ts">
import { TriangleAlert } from 'lucide-vue-next';
</script>

<template>
    <div class="mt-3 flex gap-2.5 rounded-lg bg-amber-500/10 p-3 text-sm leading-relaxed text-amber-800 dark:text-amber-400" role="note">
        <TriangleAlert class="mt-0.5 size-4 shrink-0" />
        <p>部分項目沒有填蛋白質、碳水或脂肪，加總時以 0 計算，實際攝取可能更多。</p>
    </div>
</template>
