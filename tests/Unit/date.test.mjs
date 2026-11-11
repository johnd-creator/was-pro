import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { formatLocalDate } from '../../resources/js/lib/date.ts';

const moduleUrl = new URL('../../resources/js/lib/date.ts', import.meta.url)
    .href;
const cases = [
    ['WIB morning', 'Asia/Jakarta', '2026-11-09T17:05:00Z', '2026-11-10'],
    ['WITA morning', 'Asia/Makassar', '2026-11-09T16:05:00Z', '2026-11-10'],
    ['WIT morning', 'Asia/Jayapura', '2026-11-09T15:05:00Z', '2026-11-10'],
    ['WIB year rollover', 'Asia/Jakarta', '2026-12-31T17:05:00Z', '2027-01-01'],
    [
        'WIB month rollover',
        'Asia/Jakarta',
        '2026-10-31T17:05:00Z',
        '2026-11-01',
    ],
    ['WIB leap day', 'Asia/Jakarta', '2028-02-28T17:05:00Z', '2028-02-29'],
    ['UTC date', 'UTC', '2026-11-10T12:00:00Z', '2026-11-10'],
    [
        'western local evening',
        'America/Los_Angeles',
        '2026-11-10T01:05:00Z',
        '2026-11-09',
    ],
];

for (const [name, timezone, instant, expected] of cases) {
    test(name, () => {
        const result = spawnSync(
            process.execPath,
            [
                '--experimental-strip-types',
                '--input-type=module',
                '-e',
                `import { formatLocalDate } from ${JSON.stringify(moduleUrl)}; console.log(formatLocalDate(new Date(${JSON.stringify(instant)})));`,
            ],
            { encoding: 'utf8', env: { ...process.env, TZ: timezone } },
        );
        assert.equal(result.status, 0, result.stderr);
        assert.equal(result.stdout.trim(), expected);
    });
}

test('date defaults to the current local calendar day', () => {
    const date = new Date();
    const expected = [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
    assert.equal(formatLocalDate(), expected);
});

for (const file of [
    'components/waste-management/CreateWasteRecordForm.vue',
    'components/waste-management/CreateWasteHaulingForm.vue',
    'pages/waste-management/haulings/Create.vue',
    'pages/waste-management/records/Create.vue',
    'pages/waste-management/faba/adjustments/Create.vue',
    'pages/waste-management/faba/production/Create.vue',
    'pages/waste-management/faba/utilization/Create.vue',
]) {
    test(`create form uses the shared local calendar date: ${file}`, () => {
        let source = readFileSync(
            new URL(`../../resources/js/${file}`, import.meta.url),
            'utf8',
        );
        if (file === 'pages/waste-management/haulings/Create.vue') {
            assert.match(source, /import CreateWasteHaulingForm from '@\/components\/waste-management\/CreateWasteHaulingForm.vue';/);
            assert.match(source, /<CreateWasteHaulingForm v-bind="props"/);
            source = readFileSync(new URL('../../resources/js/components/waste-management/CreateWasteHaulingForm.vue', import.meta.url), 'utf8');
        }
        assert.match(
            source,
            /import \{ formatLocalDate \} from '@\/lib\/date';/,
        );
        if (file.includes('HaulingForm') || file.includes('haulings/')) {
            assert.match(source, /hauling_date: initialActualDate\(\s*formatLocalDate\(\),\s*props.plansEnabled \? props.selectedPlanId : null,\s*props.actualDateLimit/);
        } else {
            assert.match(source, /(?:hauling_date|transaction_date|date): formatLocalDate\(\)/);
        }
        assert.doesNotMatch(
            source,
            /new Date\(\)\.toISOString\(\)\.split\('T'\)\[0\]/,
        );
    });
}
