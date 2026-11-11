import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    actualQuantityLimit,
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
