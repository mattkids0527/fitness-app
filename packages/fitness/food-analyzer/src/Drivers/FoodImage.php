<?php

namespace Fitness\FoodAnalyzer\Drivers;

use Illuminate\Http\UploadedFile;

/**
 * 要分析的照片，只存在記憶體中：driver 直接拿內容送出，不寫入任何磁碟或資料庫。
 */
class FoodImage
{
    public function __construct(
        public readonly string $contents,
        public readonly string $mimeType,
    ) {}

    /**
     * 從上傳檔讀出內容（只讀 PHP 的上傳暫存檔，不呼叫 store()）。
     */
    public static function fromUploadedFile(UploadedFile $file): self
    {
        return new self((string) $file->get(), (string) $file->getMimeType());
    }

    /**
     * 以 base64 編碼的內容，給以 inline data 送出圖片的 driver 使用。
     */
    public function base64(): string
    {
        return base64_encode($this->contents);
    }
}
