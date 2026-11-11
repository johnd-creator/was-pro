import assert from 'node:assert/strict';
import test from 'node:test';
import { vendorExpiryStatus } from '../../resources/js/lib/vendor-license.ts';
test('expiry date is inclusive independent of clock time and timezone', () => {
 assert.equal(vendorExpiryStatus('2026-11-11T00:00:00.000000Z', '2026-11-11').status, 'not_expired');
 assert.equal(vendorExpiryStatus('2026-11-11', '2026-11-12').status, 'expired');
 assert.equal(vendorExpiryStatus('2026-11-11', '2026-11-10').status, 'not_expired');
});
test('missing dates are informational unknown, never valid or verified', () => {
 assert.equal(vendorExpiryStatus(null, '2026-11-11').status, 'unknown');
 assert.equal(vendorExpiryStatus(undefined, '2026-11-11').text, 'Tanggal belum dicatat');
 assert.equal(vendorExpiryStatus('2026-11-11', '').status, 'unknown');
 assert.equal(vendorExpiryStatus('2026-11-11', '2026-11-11').text, 'Belum kedaluwarsa');
});
