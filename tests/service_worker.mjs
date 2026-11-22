import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {runInNewContext} from 'node:vm';

const origin = 'https://cms.test';
const listeners = new Map();
const stored = new Map();
const requests = [];
let offline = false;

runInNewContext(await readFile(new URL('../public-assets/js/service-worker.js', import.meta.url), 'utf8'), {
    URL,
    Response,
    self: {location: {origin}, addEventListener: (name, listener) => listeners.set(name, listener)},
    fetch: async (request) => {
        requests.push(request.url);
        if (offline) throw new TypeError('Network unavailable');
        const response = new Response('network response');
        Object.defineProperty(response, 'type', {value: 'basic'});
        return response;
    },
    caches: {
        open: async () => ({
            match: async (request) => stored.get(request.url),
            put: async (request, response) => stored.set(request.url, response),
            keys: async () => [...stored.keys()].map((url) => ({url})),
            delete: async (request) => stored.delete(request.url),
        }),
    },
});

function request(path, mode = 'navigate', method = 'GET') {
    let response;
    listeners.get('fetch')({
        request: {method, mode, url: origin + path},
        respondWith: (promise) => { response = promise; },
    });
    return response;
}

assert.equal((await request('/admin')).status, 200);
assert.equal(stored.size, 0, 'Authenticated navigation must stay out of the asset cache.');
assert.equal(request('/login', 'navigate', 'POST'), undefined, 'POST must reach the network directly.');
await request('/public-assets/css/app.min.css?v=1', 'cors');
assert.equal(stored.size, 1);
const countBeforeCacheHit = requests.length;
await request('/public-assets/css/app.min.css?v=1', 'cors');
assert.equal(requests.length, countBeforeCacheHit);
await request('/public-assets/css/app.min.css?v=2', 'cors');
assert.equal(stored.size, 1, 'A new asset version replaces the previous one.');
offline = true;
const fallback = await request('/question/example');
assert.equal(fallback.status, 503);
assert.match(await fallback.text(), /You’re offline/);
assert.equal(stored.size, 1, 'Neither discussion HTML nor the offline response belongs in the asset cache.');
console.log('Service worker navigation, offline fallback, and selective asset caching passed.');
