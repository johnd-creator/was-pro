import assert from 'node:assert/strict';
import { test } from 'node:test';
import { getWasteStorageState } from '../../resources/js/lib/waste-storage.ts';

const today = new Date(2026, 10, 4, 15, 0, 0);
const cases = [
    ['closed past deadline', '2025-07-29', 0, false, 'completed'],
    ['closed simulated stock', '2025-07-29', 0, true, 'completed'],
    ['positive expired stock', '2026-11-03', 87, false, 'expired'],
    ['partial positive expired stock', '2026-11-03', 0.01, false, 'expired'],
    ['deadline today is not overdue', '2026-11-04', 10, false, 'expiring_soon'],
    ['deadline seven days away', '2026-11-11', 10, false, 'expiring_soon'],
    ['deadline eight days away', '2026-11-12', 10, false, 'fresh'],
    ['closed before deadline', '2026-11-12', 0, false, 'completed'],
    ['missing deadline with stock', null, 10, false, 'na'],
    ['missing deadline without stock', null, 0, false, 'completed'],
    ['invalid calendar date', '2026-02-31', 10, false, 'na'],
    ['legacy calendar-only caller', '2026-11-03', undefined, false, 'expired'],
];

for (const [name, expiryDate, remaining, simulated, expected] of cases) {
    test(name, () => {
        const state = getWasteStorageState(
            expiryDate,
            remaining,
            simulated,
            today,
        );
        assert.equal(state.status, expected);
        if (expected === 'completed') {
            assert.match(state.label, /stok nol/);
            assert.equal(state.label.includes('SIMULASI'), simulated);
            assert.doesNotMatch(state.label, /Melewati/);
        }
    });
}

test('the risk count and filter exclude settled records and retain partial stock', () => {
    const records = [
        { expiry: '2025-01-01', remaining: 0 },
        { expiry: '2026-11-03', remaining: 5 },
        { expiry: '2026-11-03', remaining: 0.01 },
        { expiry: '2026-11-12', remaining: 8 },
    ];
    const states = records.map((record) =>
        getWasteStorageState(record.expiry, record.remaining, false, today),
    );
    assert.equal(
        states.filter((state) => state.status === 'expired').length,
        2,
    );
    assert.equal(
        states.filter((state) => state.status === 'completed').length,
        1,
    );
    assert.equal(records[0].expiry, '2025-01-01');
});
