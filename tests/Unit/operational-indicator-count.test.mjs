import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';
const source = readFileSync(new URL('../../resources/js/lib/operational-indicator-count.ts', import.meta.url), 'utf8');
const js = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext } }).outputText;
const { operationalIndicatorCount } = await import(`data:text/javascript;base64,${Buffer.from(js).toString('base64')}`);
test('authoritative total includes pending plan once, matching the bell', () => {
    assert.equal(operationalIndicatorCount(10, [2, 6, 1, 0, 0, 1]), 10);
});
test('authoritative zero is retained and invalid or missing total uses safe fallback', () => {
    assert.equal(operationalIndicatorCount(0, [2, 6, 1]), 0);
    assert.equal(operationalIndicatorCount(undefined, [2, 6, 1, 0, 0, 1]), 10);
    assert.equal(operationalIndicatorCount(NaN, [NaN, -2, 0]), 0);
});
test('dashboard supplies shared total and critical label remains driven by expired records', () => {
    const dashboard = readFileSync(new URL('../../resources/js/pages/Dashboard.vue', import.meta.url), 'utf8');
    const hero = readFileSync(new URL('../../resources/js/components/dashboard/ComplianceHero.vue', import.meta.url), 'utf8');
    assert.match(dashboard, /:total-indicators="notificationSummary.total_count"/);
    assert.match(hero, /props.expiredWaste > 0 \? 'Tinjau Catatan Kritis' : 'Lihat Notifikasi'/);
});

test('actual approvals and plans each contribute once including fallback', () => {
    assert.equal(operationalIndicatorCount(3, [0, 0, 0, 0, 0, 1, 2]), 3);
    assert.equal(operationalIndicatorCount(undefined, [0, 0, 0, 0, 0, 1, 2]), 3);
});
