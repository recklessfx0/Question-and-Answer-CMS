const assetCache = 'qa-cms-assets-v1';
const shellAsset = /^\/public-assets\/(?:css\/app(?:\.min)?\.css|js\/(?:app(?:\.min)?|shell)\.js)$/;
const offlinePage = `<!doctype html><html lang="en"><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#F7F9F6">
<title>Offline | Q&amp;A</title>
<style>body{margin:0;background:#f7f9f6;color:#202b27;font:1rem/1.6 system-ui,sans-serif}main{max-width:32rem;margin:auto;padding:12vh 1.25rem 2rem}a{color:#296b5d}</style>
<main><h1>You’re offline</h1>
<p>Reconnect to load this page.</p><p><a href="/">Go to questions</a></p></main></html>`;

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((names) => Promise.all(names
        .filter((name) => name.startsWith('qa-cms-assets-') && name !== assetCache)
        .map((name) => caches.delete(name)))));
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => new Response(offlinePage, {
            status: 503,
            headers: {'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store'},
        })));
        return;
    }
    if (!shellAsset.test(url.pathname) || !/^\?v=\d+$/.test(url.search)) return;

    event.respondWith((async () => {
        const cache = await caches.open(assetCache).catch(() => null);
        const cached = await cache?.match(request).catch(() => undefined);
        if (cached) return cached;
        const response = await fetch(request);
        if (cache && response.ok && response.type === 'basic') {
            try {
                await cache.put(request, response.clone());
                const keys = await cache.keys();
                await Promise.all(keys.filter((key) => key.url !== request.url
                    && new URL(key.url).pathname === url.pathname).map((key) => cache.delete(key)));
            } catch {
                // Cache failures should not affect the network response.
            }
        }
        return response;
    })());
});
