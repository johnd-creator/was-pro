import assert from 'node:assert/strict';
import fs from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { compileScript, parse, registerTS } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import ts from 'typescript';
import { createSSRApp } from 'vue';

const require = createRequire(import.meta.url);
const root = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '../..',
);
registerTS(() => ts);
function evaluate(source) {
    const output = ts.transpileModule(source, {
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        },
    }).outputText;
    const module = { exports: {} };
    const resolve = (name) =>
        name === '@/lib/faba'
            ? evaluate(
                  fs.readFileSync(
                      path.join(root, 'resources/js/lib/faba.ts'),
                      'utf8',
                  ),
              )
            : require(name);
    new Function('require', 'module', 'exports', output)(
        resolve,
        module,
        module.exports,
    );
    return module.exports;
}
const filename = path.join(
    root,
    'resources/js/components/FabaBalanceReconciliation.vue',
);
const { descriptor } = parse(fs.readFileSync(filename, 'utf8'), { filename });
const compiled = compileScript(descriptor, {
    id: 'faba-reconciliation-test',
    inlineTemplate: true,
    fs: {
        fileExists: fs.existsSync,
        readFile: (file) => fs.readFileSync(file, 'utf8'),
    },
});
const component = evaluate(compiled.content).default;
const summary = {
    opening_fly_ash: 100,
    opening_bottom_ash: 20,
    opening_balance: 120,
    closing_fly_ash: 107,
    closing_bottom_ash: 20,
    closing_balance: 127,
    balance_components: [
        {
            movement_type: 'reject',
            stock_effect: 'in',
            fly_ash: 10,
            bottom_ash: 0,
            total: 10,
        },
        {
            movement_type: 'reject',
            stock_effect: 'out',
            fly_ash: 3,
            bottom_ash: 0,
            total: 3,
        },
    ],
};
test('monthly reconciliation preserves actual stock direction and material totals', async () => {
    const html = await renderToString(
        createSSRApp(component, { summary, periodLabel: 'Januari 2026' }),
    );
    assert.match(html, /Masuk \(\+\)/);
    assert.match(html, /Keluar \(\u2212\)/);
    assert.match(html, /-3,00/);
    assert.match(html, /127,00/);
    assert.doesNotMatch(html, /-0,00/);
    assert.match(html, /Bottom Ash/);
    assert.doesNotMatch(html, /Reset \(/);
});
test('yearly reconciliation separates signed opening resets from production', async () => {
    const html = await renderToString(
        createSSRApp(component, {
            summary: {
                ...summary,
                opening_reset_fly_ash: -9,
                opening_reset_bottom_ash: 0,
                opening_reset_total: -9,
            },
            periodLabel: '2026',
            yearly: true,
        }),
    );
    assert.match(html, /Saldo awal Januari/);
    assert.match(html, /Saldo akhir Desember/);
    assert.match(html, /-9,00/);
    assert.match(html, /Ini bukan produksi atau arus material/);
});
test('legacy payload without component details does not invent a reconciliation', async () => {
    const html = await renderToString(
        createSSRApp(component, {
            summary: { closing_balance: 10 },
            periodLabel: '2025',
        }),
    );
    assert.doesNotMatch(html, /<table/);
});
