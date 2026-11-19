/*
 * Fitness service worker：讓 App 可以「加到主畫面」並在離線時顯示離線頁。
 * - 頁面（含個人資料）一律走網路，不快取；離線時改顯示 /offline.html。
 * - /build/ 底下的檔案檔名含 hash、內容不會變，第一次載入後快取起來。
 * 修改此檔的快取策略時，記得調整 CACHE_VERSION，舊快取會在 activate 時清掉。
 */
const CACHE_VERSION = 'v1';
const STATIC_CACHE = `fitness-static-${CACHE_VERSION}`;
const PRECACHE = ['/offline.html', '/favicon.svg', '/icons/icon-192.png'];

// 安裝時先快取離線頁需要的檔案
self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)));
    self.skipWaiting();
});

// 啟用時清掉舊版本的快取
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('fitness-') && key !== STATIC_CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // 整頁載入：走網路，失敗時顯示離線頁（Inertia 的 XHR 請求不在此列，失敗時由前端處理）
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));

        return;
    }

    // 建置後的靜態檔：快取優先
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                        }

                        return response;
                    }),
            ),
        );
    }
});
