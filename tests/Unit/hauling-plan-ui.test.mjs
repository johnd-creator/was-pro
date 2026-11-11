import assert from 'node:assert/strict';
import test from 'node:test';
import {
    actualQuantityLimit,
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
