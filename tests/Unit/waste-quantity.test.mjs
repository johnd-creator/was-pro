import assert from 'node:assert/strict';
import test from 'node:test';
import {
    compareWasteQuantities,
    summarizeWasteQuantities,
} from '../../resources/js/lib/waste-quantity.ts';

test('summaries keep mass and volume units distinct without conversion', () => {
    assert.deepEqual(
        summarizeWasteQuantities([
            { unit: 'kg', remaining_quantity: 100 },
            { unit: 'liter', remaining_quantity: 20 },
            { unit: 'kg', remaining_quantity: 25 },
            { unit: 'ton', remaining_quantity: 2 },
        ]),
        [
            { unit: 'kg', quantity: 125 },
            { unit: 'liter', quantity: 20 },
            { unit: 'ton', quantity: 2 },
        ],
    );
});

test('summaries round within each unit and support empty records', () => {
    assert.deepEqual(summarizeWasteQuantities([]), []);
    assert.deepEqual(
        summarizeWasteQuantities([
            { unit: 'kg', remaining_quantity: 0.1 },
            { unit: 'kg', remaining_quantity: 0.2 },
        ]),
        [{ unit: 'kg', quantity: 0.3 }],
    );
});

test('quantity ordering compares values only within the same unit', () => {
    const records = [
        { unit: 'liter', remaining_quantity: 100 },
        { unit: 'kg', remaining_quantity: 10 },
        { unit: 'kg', remaining_quantity: 20 },
    ];
    assert.deepEqual(records.sort(compareWasteQuantities), [
        { unit: 'kg', remaining_quantity: 20 },
        { unit: 'kg', remaining_quantity: 10 },
        { unit: 'liter', remaining_quantity: 100 },
    ]);
});
