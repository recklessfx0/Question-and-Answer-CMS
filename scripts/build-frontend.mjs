import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
for (const path of ['css/app.css', 'js/app.js']) {
    const source = 'public-assets/' + path;
    const output = source.replace(/\.(css|js)$/, '.min.$1');
    const command = `npx --yes esbuild@0.25.12 ${source} --minify --outfile=${output}`;
    const result = spawnSync(command, {cwd: root, shell: true, stdio: 'inherit'});
    if (result.status !== 0) process.exit(result.status || 1);
}
