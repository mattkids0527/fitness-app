import type { AnalyzeMode } from '@/types/meal';
import { ref } from 'vue';

// 照片只留在瀏覽器記憶體：分析頁、確認頁共用同一份，換頁不會遺失；重新整理或放棄後就消失
const previewUrl = ref<string | null>(null);
const takenAt = ref<string | null>(null);
const preparing = ref(false);
const analysisId = ref<number | null>(null);
let prepared: Promise<File> | null = null;

const MAX_EDGE = 1600;

/**
 * Date 轉成裝置本地時間的 Y-m-d H:i。
 */
function formatLocal(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/**
 * 讀照片 EXIF 的拍攝時間（DateTimeOriginal）；讀不到或不合理時回傳 null，由後端改用現在時間。
 */
async function readTakenAt(file: File): Promise<string | null> {
    try {
        const { default: exifr } = await import('exifr');
        const exif = await exifr.parse(file, ['DateTimeOriginal']);
        const date = exif?.DateTimeOriginal;

        return date instanceof Date && !Number.isNaN(date.getTime()) ? formatLocal(date) : null;
    } catch {
        return null;
    }
}

/**
 * 壓縮成長邊 1600px 的 JPEG；壓縮失敗時送原檔，由後端驗證類型與大小。
 * 不用套件的 Web Worker（它會從 CDN 載入程式），在主執行緒壓縮。
 */
async function compress(file: File): Promise<File> {
    try {
        const { default: imageCompression } = await import('browser-image-compression');
        const blob = await imageCompression(file, {
            maxWidthOrHeight: MAX_EDGE,
            maxSizeMB: 1,
            initialQuality: 0.82,
            fileType: 'image/jpeg',
            useWebWorker: false,
        });

        return new File([blob], 'meal.jpg', { type: 'image/jpeg' });
    } catch {
        return file;
    }
}

/**
 * 照片分析用的照片暫存：選照片時讀拍攝時間（上傳）並在背景壓縮，送出時取壓縮後的檔案。
 */
export function useMealPhoto() {
    /**
     * 放棄目前的照片，釋放預覽網址。
     */
    const clear = () => {
        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value);
        }

        previewUrl.value = null;
        takenAt.value = null;
        preparing.value = false;
        analysisId.value = null;
        prepared = null;
    };

    /**
     * 換成新選的照片：立即顯示預覽，上傳的照片讀拍攝時間（壓縮會去掉 EXIF，要先讀），壓縮在背景進行。
     */
    const setPhoto = (file: File, source: AnalyzeMode) => {
        clear();
        previewUrl.value = URL.createObjectURL(file);
        preparing.value = true;

        const url = previewUrl.value;
        // 等待期間又換了照片時，不覆蓋新照片的狀態
        const isCurrent = () => previewUrl.value === url;

        prepared = (async () => {
            const time = source === 'upload' ? await readTakenAt(file) : null;

            if (isCurrent()) {
                takenAt.value = time;
            }

            const compressed = await compress(file);

            if (isCurrent()) {
                preparing.value = false;
            }

            return compressed;
        })();
    };

    /**
     * 取得要送出的照片（等壓縮完成）；沒有照片時回傳 null。
     */
    const photoFile = async (): Promise<File | null> => (prepared ? await prepared : null);

    return { previewUrl, takenAt, preparing, analysisId, setPhoto, photoFile, clear };
}
