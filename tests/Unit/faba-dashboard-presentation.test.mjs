import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { compile } from '@vue/compiler-dom';
import { parse, compileTemplate } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import * as Vue from 'vue';
import { getFabaDisplayWarnings } from '../../resources/js/lib/faba.ts';
const read = (path) =>
    readFileSync(
        new URL(`../../resources/js/${path}`, import.meta.url),
        'utf8',
    );
const dashboard = read('pages/waste-management/faba/Dashboard.vue');
const chart = read('components/dashboard/FabaTrendChart.vue');
test('FABA dashboard retains all twelve backend months without changing values', () => {
    const expression = dashboard
        .match(/const trendRows = computed\(\(\) =>([\s\S]*?)\n\);/)[1]
        .trim()
        .replace(/,$/, '');
    const trend = Array.from({ length: 12 }, (_, i) => ({
        month: i + 1,
        label: `Bulan ${i + 1}`,
        production: i * 13.25,
        utilization: i * 5.5,
        closing_balance: i - 3,
    }));
    const result = Function('props', `return (${expression});`)({ trend });
    assert.equal(result.length, 12);
    result.forEach((row, index) =>
        Object.keys(trend[index]).forEach((key) =>
            assert.equal(row[key], trend[index][key]),
        ),
    );
    assert.doesNotMatch(dashboard, /integrity-heading|Konsistensi data/);
    assert.match(dashboard, /item.closing_balance < 0/);
    assert.match(
        dashboard,
        /v-if="visibleWarnings.length \|\| stats.negative_periods"/,
    );
    assert.match(dashboard, /role="alert"/);
    assert.match(dashboard, /Periksa rekap dan rekonsiliasi/);
});
test('all FABA chart months remain visible with keyboard-scrollable mobile regions', () => {
    assert.match(chart, /interval: 0/);
    assert.match(chart, /hideOverlap: false/);
    assert.match(chart, /rotate: 45/);
    assert.equal((chart.match(/tabindex="0"/g) ?? []).length, 3);
    assert.equal(
        (chart.match(/Math.max\(420, rows.length \* 66\)/g) ?? []).length,
        3,
    );
    assert.match(chart, /props.data.map/);
    assert.doesNotMatch(chart, /\.slice\(/);
});
test('FABA templates compile after removing presentation panels', () => {
    for (const [filename, source] of [
        ['Dashboard.vue', dashboard],
        ['FabaTrendChart.vue', chart],
    ]) {
        const { descriptor, errors } = parse(source);
        assert.deepEqual(errors, []);
        assert.deepEqual(
            compileTemplate({
                source: descriptor.template.content,
                filename,
                id: filename,
            }).errors,
            [],
        );
    }
});

test('main FABA calendar chart preserves null gaps, zeros and selected-period totals', () => {
    const expression = (name) =>
        chart
            .match(
                new RegExp(
                    `const ${name} = computed\\(\\(\\) =>([\\s\\S]*?)\\n\\);`,
                ),
            )[1]
            .trim()
            .replace(/,$/, '');
    const data = Array.from({ length: 12 }, (_, i) => ({
        label: `Bulan ${i + 1}`,
        year: 2026,
        production: i < 3 ? i * 10 : null,
        utilization: i < 3 ? i : null,
        production_fly_ash: i < 3 ? i * 8 : null,
        utilization_fly_ash: i < 3 ? i : null,
        capacity:
            i < 3
                ? { total: { balance: i * 9 }, fly_ash: { balance: i * 7 } }
                : null,
    }));
    const rows = {
        value: Function(
            'props',
            'selectedMaterial',
            `return (${expression('rows')});`,
        )({ data }, { value: 'total' }),
    };
    assert.equal(rows.value.length, 12);
    assert.equal(rows.value[0].production, 0);
    assert.equal(rows.value[11].production, null);
    assert.equal(rows.value[11].capacity, null);
    const availableRows = {
        value: Function(
            'rows',
            `return (${expression('availableRows')});`,
        )(rows),
    };
    assert.equal(availableRows.value.at(-1).label, 'Bulan 3 2026');
    assert.equal(
        Function('rows', `return (${expression('production')});`)(rows),
        30,
    );
    const fly = Function(
        'props',
        'selectedMaterial',
        `return (${expression('rows')});`,
    )({ data }, { value: 'fly_ash' });
    assert.equal(fly[2].production, 16);
    assert.equal(fly[11].capacity, null);
    assert.doesNotMatch(chart, /enam bulan/i);
    assert.match(chart, /s\.d\. bulan pilihan/);
});

const historicalWarning = {
    code: 'missing_opening_balance',
    message: 'Opening balance historis belum ditentukan untuk periode ini.',
};
const stockWarning = {
    code: 'negative_balance',
    message: 'Saldo akhir periode ini negatif.',
};
const documentWarning = {
    code: 'external_document_incomplete',
    message: 'Dokumen belum lengkap.',
};
const fabaPage = (name) => read('pages/waste-management/faba/' + name + '.vue');
const computedValue = (source, name, bindings) => {
    const expression = source
        .match(
            new RegExp(
                'const ' + name + ' = computed\\(\\(\\) =>([\\s\\S]*?)\\n\\);',
            ),
        )[1]
        .trim()
        .replace(/,$/, '');
    return Function(
        ...Object.keys(bindings),
        'return (' + expression + ');',
    )(...Object.values(bindings));
};

test('FABA display warnings remove only the historical opening-balance code without mutating source data', () => {
    const warnings = Object.freeze([
        Object.freeze(historicalWarning),
        Object.freeze(stockWarning),
        Object.freeze(documentWarning),
        Object.freeze({ code: 'duplicate_warning', message: 'Duplikasi.' }),
        Object.freeze({
            code: 'new_warning',
            message: historicalWarning.message,
        }),
        Object.freeze({
            code: 'utilization_without_stock_source',
            message: 'Sumber stok belum cukup.',
        }),
    ]);
    const result = getFabaDisplayWarnings(warnings);
    assert.deepEqual(result, warnings.slice(1));
    assert.equal(warnings.length, 6);
    assert.equal(result[0], warnings[1]);
    assert.deepEqual(getFabaDisplayWarnings([historicalWarning]), []);
    assert.deepEqual(getFabaDisplayWarnings(undefined), []);
    assert.deepEqual(getFabaDisplayWarnings(null), []);
});

test('FABA dashboard removes the empty warning banner but preserves real alerts', async () => {
    const template = dashboard.match(
        /<div\s+v-if="visibleWarnings.length \|\| stats.negative_periods"[\s\S]*?<\/div>/,
    )[0];
    const render = Function(
        'Vue',
        compile(template.replaceAll('Link', 'WarningLink'), {
            prefixIdentifiers: true,
        }).code,
    )(Vue);
    const display = async (warnings, negativePeriods = 0) => {
        const app = Vue.createSSRApp({
            render,
            setup: () => ({
                visibleWarnings: getFabaDisplayWarnings(warnings),
                stats: { negative_periods: negativePeriods },
                formatNumber: (value) => String(value),
                wasteManagementRoutes: {
                    faba: { recaps: { monthly: { url: () => '/recap' } } },
                },
            }),
        });
        app.component('WarningLink', {
            setup:
                (_, { slots }) =>
                () =>
                    Vue.h('a', slots.default?.()),
        });
        app.component('ArrowRight', { render: () => null });
        return renderToString(app);
    };
    const history = Array.from({ length: 11 }, (_, index) => ({
        ...historicalWarning,
        month: index + 2,
        period_label: 'Bulan ' + (index + 2),
        closing_balance: 100,
    }));
    assert.doesNotMatch(
        await display(history),
        /role="alert"|Opening balance|Periksa rekap/,
    );
    const mixed = await display(
        [
            ...history,
            {
                ...stockWarning,
                month: 2,
                period_label: 'Februari 2026',
                closing_balance: -5,
            },
        ],
        1,
    );
    assert.match(mixed, /role="alert"/);
    assert.match(mixed, /Saldo akhir periode ini negatif/);
    assert.match(mixed, /1 periode memiliki saldo negatif/);
    assert.doesNotMatch(mixed, /Opening balance/);
    assert.match(await display(history, 2), /2 periode memiliki saldo negatif/);
});

test('FABA warning totals and closing queues use visible warnings while preserving balances and permissions', () => {
    const warnings = [historicalWarning, stockWarning, documentWarning];
    const periods = [
        {
            year: 2026,
            month: 2,
            can_submit: false,
            operational_status: 'open',
            recap: { warnings: [historicalWarning], closing_balance: 100 },
        },
        {
            year: 2026,
            month: 3,
            can_submit: true,
            operational_status: 'ready_to_submit',
            recap: { warnings, closing_balance: -10 },
        },
    ];
    const before = structuredClone(periods);
    const displayPeriods = computedValue(
        fabaPage('approvals/Index'),
        'displayPeriods',
        { props: { periods }, getFabaDisplayWarnings },
    );
    assert.deepEqual(
        displayPeriods.map((item) => item.recap.warnings.length),
        [0, 2],
    );
    assert.equal(
        computedValue(fabaPage('approvals/Index'), 'totalWarnings', {
            displayPeriods: { value: displayPeriods },
        }),
        2,
    );
    assert.deepEqual(periods, before);
    for (let i = 0; i < periods.length; i++) {
        assert.equal(displayPeriods[i].can_submit, periods[i].can_submit);
        assert.equal(
            displayPeriods[i].operational_status,
            periods[i].operational_status,
        );
        assert.equal(
            displayPeriods[i].recap.closing_balance,
            periods[i].recap.closing_balance,
        );
    }
    const readyClosingPeriods = periods.map((item) => ({
        ...item,
        warning_count: item.recap.warnings.length,
    }));
    const visibleWarnings = {
        value: getFabaDisplayWarnings(
            periods.flatMap((item) =>
                item.recap.warnings.map((warning) => ({
                    ...warning,
                    month: item.month,
                })),
            ),
        ),
    };
    const queue = computedValue(dashboard, 'displayClosingPeriods', {
        props: { readyClosingPeriods },
        visibleWarnings,
    });
    assert.deepEqual(
        queue.map((item) => item.warning_count),
        [0, 2],
    );
    const months = periods.map((item) => ({
        ...item.recap,
        month: item.month,
    }));
    const displayMonths = computedValue(
        fabaPage('recaps/Yearly'),
        'displayMonths',
        { props: { recap: { months } }, getFabaDisplayWarnings },
    );
    assert.deepEqual(
        displayMonths.map((item) => item.warnings.length),
        [0, 2],
    );
    assert.deepEqual(
        displayMonths.map((item) => item.closing_balance),
        [100, -10],
    );
});

test('FABA warning views react to period changes without changing incoming recap warnings', () => {
    const props = Vue.reactive({ recap: { warnings: [historicalWarning] } });
    const visible = Vue.computed(() =>
        getFabaDisplayWarnings(props.recap.warnings),
    );
    assert.equal(visible.value.length, 0);
    props.recap = { warnings: [historicalWarning, stockWarning] };
    assert.equal(visible.value.length, 1);
    props.recap = { warnings: [] };
    assert.equal(visible.value.length, 0);
    for (const name of [
        'Dashboard',
        'approvals/Index',
        'approvals/Review',
        'recaps/Monthly',
        'recaps/Yearly',
        'reports/Index',
    ]) {
        const source = fabaPage(name);
        assert.match(source, /getFabaDisplayWarnings/);
        const { descriptor, errors } = parse(source);
        assert.deepEqual(errors, []);
        assert.deepEqual(
            compileTemplate({
                source: descriptor.template.content,
                filename: name + '.vue',
                id: name,
            }).errors,
            [],
        );
        if (
            ['approvals/Review', 'recaps/Monthly', 'reports/Index'].includes(
                name,
            )
        ) {
            assert.match(
                descriptor.template.content,
                /v-for="warning in visibleWarnings"/,
            );
            assert.doesNotMatch(
                descriptor.template.content,
                /(?:recap|monthlyRecap)\.warnings/,
            );
        }
    }
    assert.match(
        fabaPage('reports/Index'),
        /getFabaDisplayWarnings\(props.anomalyReport.items\)/,
    );
    assert.doesNotMatch(
        fabaPage('reports/Index').split('<template>')[1],
        /anomalyReport\.items/,
    );
});
