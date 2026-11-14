import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse, compileTemplate } from '@vue/compiler-sfc';
const read = path => readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8');
const dashboard = read('pages/waste-management/faba/Dashboard.vue');
const chart = read('components/dashboard/FabaTrendChart.vue');
test('FABA dashboard retains all twelve backend months without changing values', () => {
    const expression = dashboard.match(/const trendRows = computed\(\(\) =>([\s\S]*?)\n\);/)[1].trim().replace(/,$/, '');
    const trend = Array.from({ length: 12 }, (_, i) => ({ month: i + 1, label: `Bulan ${i + 1}`, production: i * 13.25, utilization: i * 5.5, closing_balance: i - 3 }));
    const result = Function('props', `return (${expression});`)({ trend });
    assert.equal(result.length, 12);
    result.forEach((row, index) => Object.keys(trend[index]).forEach(key => assert.equal(row[key], trend[index][key])));
    assert.doesNotMatch(dashboard, /integrity-heading|Konsistensi data/);
    assert.match(dashboard, /item.closing_balance < 0/);
    assert.match(dashboard, /v-if="warnings.length \|\| stats.negative_periods"/);
    assert.match(dashboard, /role="alert"/);
    assert.match(dashboard, /Periksa rekap dan rekonsiliasi/);
});
test('all FABA chart months remain visible with keyboard-scrollable mobile regions', () => {
    assert.match(chart, /interval: 0/);
    assert.match(chart, /hideOverlap: false/);
    assert.match(chart, /rotate: 45/);
    assert.equal((chart.match(/tabindex="0"/g) ?? []).length, 3);
    assert.equal((chart.match(/Math.max\(420, rows.length \* 66\)/g) ?? []).length, 3);
    assert.match(chart, /props.data.map/);
    assert.doesNotMatch(chart, /\.slice\(/);
});
test('FABA templates compile after removing presentation panels', () => {
    for (const [filename, source] of [['Dashboard.vue', dashboard], ['FabaTrendChart.vue', chart]]) {
        const { descriptor, errors } = parse(source);
        assert.deepEqual(errors, []);
        assert.deepEqual(compileTemplate({ source: descriptor.template.content, filename, id: filename }).errors, []);
    }
});

test('main FABA calendar chart preserves null gaps, zeros and selected-period totals', () => {
    const expression = name => chart.match(new RegExp(`const ${name} = computed\\(\\(\\) =>([\\s\\S]*?)\\n\\);`))[1].trim().replace(/,$/, '');
    const data = Array.from({ length: 12 }, (_, i) => ({
        label: `Bulan ${i + 1}`, year: 2026,
        production: i < 3 ? i * 10 : null, utilization: i < 3 ? i : null,
        production_fly_ash: i < 3 ? i * 8 : null, utilization_fly_ash: i < 3 ? i : null,
        capacity: i < 3 ? { total: { balance: i * 9 }, fly_ash: { balance: i * 7 } } : null,
    }));
    const rows = { value: Function('props', 'selectedMaterial', `return (${expression('rows')});`)({ data }, { value: 'total' }) };
    assert.equal(rows.value.length, 12);
    assert.equal(rows.value[0].production, 0);
    assert.equal(rows.value[11].production, null);
    assert.equal(rows.value[11].capacity, null);
    const availableRows = { value: Function('rows', `return (${expression('availableRows')});`)(rows) };
    assert.equal(availableRows.value.at(-1).label, 'Bulan 3 2026');
    assert.equal(Function('rows', `return (${expression('production')});`)(rows), 30);
    const fly = Function('props', 'selectedMaterial', `return (${expression('rows')});`)({ data }, { value: 'fly_ash' });
    assert.equal(fly[2].production, 16);
    assert.equal(fly[11].capacity, null);
    assert.doesNotMatch(chart, /enam bulan/i);
    assert.match(chart, /s\.d\. bulan pilihan/);
});
