import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';
import { computed, watch, reactive, effectScope } from 'vue';
import {
    actualQuantityLimit,
    planQuantityError,
    availablePlanRecords,
    initialActualDate,
    haulingActualStatus,
    haulingPlanStatus,
} from '../../resources/js/lib/hauling-plan.ts';

test('plan and realization approval are explicitly distinct', () => {
    assert.equal(haulingPlanStatus('approved'), 'Rencana disetujui');
    assert.equal(haulingActualStatus('approved'), 'Realisasi disetujui');
    assert.equal(
        haulingPlanStatus('pending_approval'),
        'Menunggu persetujuan rencana',
    );
});
test('actual quantity limit uses linked remaining budget and exact record availability', () => {
    const record = { remaining_quantity: 100, available_quantity: 25 };
    assert.equal(actualQuantityLimit(record), 25);
    assert.equal(
        actualQuantityLimit(record, { remaining_plan_quantity: 12.34 }),
        12.34,
    );
    assert.equal(
        actualQuantityLimit(record, { remaining_plan_quantity: 0 }),
        0,
    );
    assert.equal(actualQuantityLimit({ remaining_quantity: 10 }), 10);
    assert.equal(actualQuantityLimit({ remaining_quantity: -1 }), 0);
});

test('linked dates respect explicit server reference without shifting unlinked local dates', () => {
    assert.equal(
        initialActualDate('2026-11-12', 'plan-id', '2026-11-11'),
        '2026-11-11',
    );
    assert.equal(
        initialActualDate('2026-11-10', 'plan-id', '2026-11-11'),
        '2026-11-11',
    );
    assert.equal(
        initialActualDate('2026-11-12', '', '2026-11-11'),
        '2026-11-12',
    );
    assert.equal(initialActualDate('2026-11-12', 'plan-id'), '');
});

test('linked defaults use the server business day across year boundaries', () => {
    assert.equal(
        initialActualDate('2026-12-31', 'plan-id', '2027-01-01'),
        '2027-01-01',
    );
    assert.equal(
        initialActualDate('2027-01-01', 'plan-id', '2026-12-31'),
        '2026-12-31',
    );
    assert.equal(
        initialActualDate('2027-01-01', 'plan-id', '2027-01-01'),
        '2027-01-01',
    );
});

test('unlinked legacy defaults preserve the browser day with or without a server limit', () => {
    assert.equal(
        initialActualDate('2026-12-31', null, '2027-01-01'),
        '2026-12-31',
    );
    assert.equal(
        initialActualDate('2027-01-01', undefined, '2026-12-31'),
        '2027-01-01',
    );
    assert.equal(initialActualDate('2026-11-10'), '2026-11-10');
});

test('linked defaults stay blank when the server business day is unavailable', () => {
    assert.equal(initialActualDate('2026-11-10', 'plan-id'), '');
    assert.equal(initialActualDate('2026-11-10', 'plan-id', ''), '');
});

test('hauling list uses readable bullet separators in priority and source labels', () => {
    const source = readFileSync(
        new URL(
            '../../resources/js/pages/waste-management/haulings/Index.vue',
            import.meta.url,
        ),
        'utf8',
    );
    assert.match(source, /\u2022 Sisa/);
    assert.match(source, /\u2022 \{\{ record\.source \}\}/);
    assert.doesNotMatch(source, /[\u00c2\u00c3\ufffd]/);
});

test('plan selector excludes exhausted allocations and keeps eligible records unchanged', () => {
    const records = [
        { id: 'exhausted', available_quantity: 0 },
        { id: 'reserved', available_quantity: -1 },
        { id: 'eligible', available_quantity: 0.01 },
        { id: 'available', available_quantity: 10 },
    ];
    assert.deepEqual(availablePlanRecords(records), records.slice(2));
    assert.equal(records.length, 4);
    assert.deepEqual(availablePlanRecords(records.slice(0, 2)), []);
    assert.deepEqual(availablePlanRecords([]), []);
    assert.equal(
        availablePlanRecords(records).find(
            (record) => record.id === 'exhausted',
        ),
        undefined,
    );
});

test('plan options, preselection, selected record and empty state share eligible collection', () => {
    const source = readFileSync(
        new URL(
            '../../resources/js/pages/waste-management/hauling-plans/Index.vue',
            import.meta.url,
        ),
        'utf8',
    );
    assert.match(source, /availablePlanRecords\(props.records\)/);
    assert.equal(
        (source.match(/selectableRecords.value.find/g) ?? []).length,
        2,
    );
    assert.match(source, /v-for="record in selectableRecords"/);
    assert.match(source, /v-if="!selectableRecords.length"/);
    assert.match(source, /tersedia untuk alokasi baru/);
});

test('plan quantity validation rejects invalid or over-allocated quantities and preserves valid full balances', () => {
    const record = { available_quantity: 2457, unit: 'kg' };
    for (const value of [
        '',
        ' ',
        0,
        -1,
        '1.001',
        'NaN',
        Infinity,
        '1e3',
        '2,457',
        '10000000000',
        2457.01,
    ]) {
        assert.notEqual(planQuantityError(value, record), '', String(value));
    }
    for (const value of [0.01, '0.10', 2457, '2457.00', '100.23']) {
        assert.equal(planQuantityError(value, record), '', String(value));
    }
    assert.match(planQuantityError(2457.01, record), /2.457 kg/);
    assert.notEqual(
        planQuantityError(0.01, { available_quantity: 0, unit: 'ton' }),
        '',
    );
    assert.notEqual(
        planQuantityError(1, { available_quantity: NaN, unit: 'kg' }),
        '',
    );
    assert.notEqual(planQuantityError(1), '');
    assert.equal(
        planQuantityError('0.29', { available_quantity: 0.29, unit: 'ton' }),
        '',
    );
    assert.notEqual(
        planQuantityError('0.30', { available_quantity: 0.29, unit: 'ton' }),
        '',
    );
});

test('plan submit blocks invalid entries, accepts exact headroom, and rechecks changed batch or availability', () => {
    const records = reactive([
        {
            id: 'one',
            waste_type_id: 'a',
            available_quantity: 20,
            unit: 'kg',
            waste_type: null,
        },
        {
            id: 'two',
            waste_type_id: 'a',
            available_quantity: 5,
            unit: 'kg',
            waste_type: null,
        },
    ]);
    const picker = mountPicker(records, '?waste_record=one');
    try {
        assert.equal(picker.canSubmit.value, false);
        for (const value of ['', 0, -2, '0.001', 20.01]) {
            picker.form.quantity = value;
            assert.equal(picker.canSubmit.value, false);
            picker.submit();
        }
        assert.equal(picker.submissions.length, 0);
        picker.form.quantity = 20;
        assert.equal(picker.canSubmit.value, true);
        picker.submit();
        assert.equal(picker.submissions.length, 1);
        picker.form.processing = true;
        picker.submit();
        assert.equal(picker.submissions.length, 1);
        picker.form.processing = false;
        records[0].available_quantity = 19;
        assert.equal(picker.canSubmit.value, false);
        picker.submit();
        assert.equal(picker.submissions.length, 1);
        picker.form.waste_record_id = 'two';
        assert.equal(picker.form.quantity, '');
        assert.equal(picker.canSubmit.value, false);
        picker.form.quantity = 5;
        assert.equal(picker.canSubmit.value, true);
    } finally {
        picker.stop();
    }
});

function mountPicker(records, query = '') {
    const source = readFileSync(
        new URL(
            '../../resources/js/pages/waste-management/hauling-plans/Index.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const script = source
        .split('<script setup lang="ts">')[1]
        .split('</script>')[0];
    const compiled = ts
        .transpileModule(script, {
            compilerOptions: {
                module: ts.ModuleKind.ESNext,
                target: ts.ScriptTarget.ES2022,
            },
        })
        .outputText.replace(/^import[\s\S]*?from ['"][^'"]+['"];\s*/gm, '')
        .replace(/export \{\};?/g, '');
    const submissions = [];
    const dependencies = {
        defineProps: () => ({ records, plans: [], plansEnabled: true }),
        usePage: () => ({ url: '/hauling-plans' + query, props: {} }),
        useForm: (initial) =>
            reactive({
                ...initial,
                processing: false,
                clearErrors() {},
                post() {
                    submissions.push({ ...this });
                },
            }),
        useOrganizationContext: () => ({ scopedUrl: (url) => url }),
        usePermissions: () => ({ hasPermission: () => true }),
        formatLocalDate: () => '2026-11-12',
        store: { url: () => '/hauling-plans' },
        computed,
        watch,
        availablePlanRecords,
        planQuantityError,
    };
    const scope = effectScope();
    const state = scope.run(() =>
        new Function(
            ...Object.keys(dependencies),
            compiled +
                ';return { form, selectedRecord, quantityError, canSubmit, submit };',
        )(...Object.values(dependencies)),
    );
    return { ...state, submissions, stop: () => scope.stop() };
}
