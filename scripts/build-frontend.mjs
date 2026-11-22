import {spawnSync} from 'node:child_process';
import {mkdtempSync, rmSync, writeFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {fileURLToPath} from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
for (const path of ['css/app.css', 'js/app.js']) {
    const source = 'public-assets/' + path;
    const output = source.replace(/\.(css|js)$/, '.min.$1');
    const command = `npx --yes esbuild@0.25.12 ${source} --minify --outfile=${output}`;
    const result = spawnSync(command, {cwd: root, shell: true, stdio: 'inherit'});
    if (result.status !== 0) process.exit(result.status || 1);
}

const mathTemp = mkdtempSync(join(tmpdir(), 'cms-math-'));
try {
    const pack = spawnSync('npm pack katex@0.19.1 --ignore-scripts --silent', {cwd: mathTemp, shell: true, encoding: 'utf8'});
    if (pack.status !== 0) throw new Error(pack.stderr || 'Could not package KaTeX.');
    for (const [entry, output] of [['dist/katex.min.js', 'math.min.js'], ['LICENSE', 'math.LICENSE']]) {
        const extracted = spawnSync('tar', ['-xOf', 'katex-0.19.1.tgz', 'package/' + entry], {cwd: mathTemp});
        if (extracted.status !== 0) throw new Error('Could not extract ' + entry);
        writeFileSync(join(root, 'public-assets/js', output), extracted.stdout);
    }
} finally {
    rmSync(mathTemp, {recursive: true, force: true});
}
