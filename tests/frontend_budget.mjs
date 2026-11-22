import {readFile} from 'node:fs/promises';
import {gzipSync} from 'node:zlib';

async function measure(path) {
    const file = await readFile(new URL('../public-assets/' + path, import.meta.url));
    const compressed = gzipSync(file, {level: 9}).byteLength;
    console.log(`${path}: ${file.byteLength} bytes raw, ${compressed} bytes gzip`);
    return compressed;
}

const css = await measure('css/app.css');
const javascript = await measure('js/app.js') + await measure('js/shell.js');
await measure('js/service-worker.js');

const shipped = await Promise.all(['css/app.min.css', 'js/app.min.js'].map(async (path) => {
    try {
        return await measure(path);
    } catch (error) {
        if (error.code !== 'ENOENT') throw error;
        return null;
    }
}));

if (css > 20_000 || javascript > 50_000 || (shipped[0] ?? 0) > 20_000 || (shipped[1] ?? 0) > 49_000) {
    console.error('Initial frontend assets exceeded their compressed byte budgets.');
    process.exitCode = 1;
}
