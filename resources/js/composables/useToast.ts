import { ref } from 'vue';

const message = ref<string | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

/**
 * 全站共用的短暫提示訊息（例如「已記錄體重」）。
 */
export function useToast() {
    /**
     * 顯示提示，預設 2.5 秒後消失。
     */
    const show = (text: string, duration = 2500) => {
        message.value = text;
        clearTimeout(timer);
        timer = setTimeout(() => (message.value = null), duration);
    };

    return { message, show };
}
