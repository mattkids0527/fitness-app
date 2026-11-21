import type { RecordType } from '@/types/exercise';
import { ListOrdered, Timer } from 'lucide-vue-next';
import type { Component } from 'vue';

/** 記錄方式的圖示：填時長為碼錶、逐組為編號清單。 */
export const RECORD_TYPE_ICONS: Record<RecordType, Component> = {
    duration: Timer,
    sets: ListOrdered,
};
