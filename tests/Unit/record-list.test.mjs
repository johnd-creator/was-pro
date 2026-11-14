import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import {
    changeRecordListState,
    parseRecordListState,
    recordContractHeaders,
    recordListPage,
    recordListUrl,
} from '../../resources/js/lib/record-list.ts';

const filters = { month: '2026-11', attention: null };
const state = parseRecordListState('/records', filters);
const records = Array.from({ length: 27 }, (_, i) => ({
    id: String(i).padStart(3, '0'),
    record_number: `WR-${i}`,
    date: `2026-11-${String(i + 1).padStart(2, '0')}`,
    created_at: '2026-11-01T00:00:00Z',
    quantity: String(i),
    source: 'Gudang',
    source_name: i === 0 ? 'Sumber khusus' : null,
    waste_type: {
        name: i === 0 ? 'Oli unik' : 'Limbah',
        code: i === 0 ? 'B105D' : 'A1',
        category: i === 0 ? { name: 'Kategori khusus' } : null,
    },
}));

test('version matrix: new UI + old backend preserves every record across local pages', () => {
    const seen = [1, 2, 3].flatMap((page) =>
        recordListPage(records, { ...state, page }).data.map((r) => r.id),
    );
    assert.equal(new Set(seen).size, 27);
    assert.equal(recordListPage(records, { ...state, page: 3 }).from, 21);
    assert.equal(recordListPage(records, { ...state, page: 3 }).to, 27);
    assert.equal(
        records[0].id,
        '000',
        'sorting must not mutate Inertia history props',
    );
});

test('version matrix: new UI + new backend supports initial array and negotiated paginator', () => {
    const full = recordListPage(records, state);
    assert.strictEqual(recordListPage(full, state), full);
    const pageTwo = recordListPage(records, { ...state, page: 2 });
    assert.strictEqual(recordListPage(pageTwo, { ...state, page: 2 }), pageTwo);
    assert.equal(
        pageTwo.data.length,
        10,
        'do not slice page two a second time',
    );
    assert.deepEqual(recordContractHeaders, {
        'X-Waspro-Records-Contract': '2',
    });
});

for (const search of [
    'wr-0',
    'OLI UNIK',
    'b105d',
    'sumber khusus',
    'KATEGORI KHUSUS',
]) {
    test(`legacy full-array search reaches off-page record by ${search}`, () => {
        assert.deepEqual(
            recordListPage(records, { ...state, search }).data.map((r) => r.id),
            ['000'],
        );
    });
}
for (const [sort, expected] of [
    ['oldest', '000'],
    ['latest', '026'],
    ['quantity_low', '000'],
    ['quantity_high', '026'],
]) {
    test(`legacy sorting supports ${sort}`, () => {
        assert.equal(
            recordListPage(records, { ...state, sort }).data[0].id,
            expected,
        );
    });
}

test('empty and shortened results clamp safely', () => {
    assert.deepEqual(recordListPage([], state), {
        data: [],
        total: 0,
        current_page: 1,
        last_page: 1,
        from: null,
        to: null,
    });
    assert.equal(
        recordListPage(records, { ...state, page: 500 }).current_page,
        3,
    );
    assert.equal(
        recordListPage(records, { ...state, search: 'none', page: 9 })
            .current_page,
        1,
    );
});

test('URL controls override omitted legacy props and round trip Back/Forward states', () => {
    const first = {
        ...state,
        month: '2026-10',
        attention: 'critical',
        search: 'oli & air',
        sort: 'quantity_low',
        page: 2,
    };
    const url = recordListUrl('/records?organization_id=org-a', first);
    const parsed = new URL(url, 'https://test.invalid');
    assert.equal(parsed.searchParams.get('organization_id'), 'org-a');
    assert.deepEqual(parseRecordListState(url, filters), first);
    const second = changeRecordListState(first, {
        attention: 'all',
        search: '',
        sort: 'oldest',
    });
    const secondUrl = recordListUrl(url, second);
    assert.equal(
        new URL(secondUrl, 'https://test.invalid').searchParams.has(
            'attention',
        ),
        false,
    );
    assert.equal(
        new URL(secondUrl, 'https://test.invalid').searchParams.has('search'),
        false,
    );
    assert.deepEqual(
        parseRecordListState(url, filters),
        first,
        'Back restores all controls',
    );
    assert.deepEqual(
        parseRecordListState(secondUrl, filters),
        second,
        'Forward restores cleared search and attention',
    );
});

test('changing search, sorting or attention resets page while pagination preserves filters', () => {
    const original = { ...state, page: 3, attention: 'expired', search: 'oli' };
    for (const changes of [
        { search: 'air' },
        { sort: 'oldest' },
        { attention: 'pending' },
    ]) {
        const next = changeRecordListState(original, changes);
        assert.equal(next.page, 1);
        assert.equal(next.month, original.month);
    }
    assert.deepEqual(changeRecordListState(original, { page: 2 }), {
        ...original,
        page: 2,
    });
});

test('invalid query values normalize safely and all attention values survive', () => {
    for (const value of ['-1', '0', 'no', '1.5', 'Infinity']) {
        assert.equal(
            parseRecordListState(
                `/records?page=${value}&sort=no&attention=no`,
                filters,
            ).page,
            1,
        );
    }
    for (const attention of [
        'expired',
        'expiring_soon',
        'critical',
        'backlog',
        'pending',
        'fresh',
        'completed',
    ]) {
        assert.equal(
            parseRecordListState(`/records?attention=${attention}`, filters)
                .attention,
            attention,
        );
    }
});

test('Vue wiring keeps negotiation, cancellable debounce, history synchronization and loading affordance', () => {
    const source = readFileSync(
        new URL(
            '../../resources/js/pages/waste-management/records/Index.vue',
            import.meta.url,
        ),
        'utf8',
    );
    assert.match(
        source,
        /wasteRecords: WasteRecord\[\] \| RecordPaginator<WasteRecord>/,
    );
    assert.match(source, /headers: recordContractHeaders/);
    assert.match(
        source,
        /watch\(\s*\(\) => \[page\.url, props\.filters, props\.wasteRecords\],\s*syncControls,?\s*\)/,
    );
    assert.match(source, /router\.on\('navigate'/);
    assert.match(source, /addEventListener\('popstate', cancelPending\)/);
    assert.match(
        source,
        /function scheduleSearch\(\): void \{\s*cancelPending\(\)/,
    );
    assert.match(source, /onCancelToken/);
    assert.match(source, /generation === visitGeneration/);
    assert.match(source, /removeBeforeListener\(\)/);
    assert.match(source, /removeNavigateListener\(\)/);
    assert.match(source, /:aria-busy="isLoading"/);
    assert.match(source, /role="status"/);
    assert.doesNotMatch(source, /replace: true/);
    assert.match(source, /@update:model-value="\(\) => navigate\(\)"/);
    assert.match(source, /navigate\(1, value\)/);
});

test('sidebar negotiation selects only same-origin exact record index GETs and preserves headers', async () => {
    const { recordNavigationHeaders } =
        await import('../../resources/js/lib/record-list.ts');
    const index = '/waste-management/records';
    const origin = 'https://waspro.example';
    const caller = { 'X-Custom': 'keep' };
    for (const href of [
        index,
        `${index}?month=2026-11&organization_id=a#table`,
        `${origin}${index}`,
        { url: index, method: 'get' },
    ]) {
        assert.deepEqual(
            recordNavigationHeaders(href, index, origin, 'get', caller),
            { ...caller, ...recordContractHeaders },
        );
    }
    for (const href of [
        '/waste-management/records/create',
        '/waste-management/records/uuid',
        '/waste-management/records/export/csv',
        '/waste-management/records/',
        '/dashboard',
        '/waste-management/haulings?organization_id=a',
        '/waste-management/faba/dashboard?organization_id=a',
        `https://outside.example${index}`,
        `//outside.example${index}`,
        'mailto:test@example.com',
        undefined,
    ]) {
        assert.strictEqual(
            recordNavigationHeaders(href, index, origin, 'get', caller),
            caller,
        );
    }
    for (const method of ['post', 'put', 'delete', 'head']) {
        assert.strictEqual(
            recordNavigationHeaders(index, index, origin, method, caller),
            caller,
        );
        assert.strictEqual(
            recordNavigationHeaders(
                { url: index, method },
                index,
                origin,
                'get',
                caller,
            ),
            caller,
        );
    }
    for (const name of [
        'X-Waspro-Records-Contract',
        'x-waspro-records-contract',
        'X-WASPRO-RECORDS-CONTRACT',
    ]) {
        const custom = { ...caller, [name]: '1' };
        assert.strictEqual(
            recordNavigationHeaders(index, index, origin, 'get', custom),
            custom,
        );
    }
    assert.deepEqual(
        caller,
        { 'X-Custom': 'keep' },
        'caller headers remain unmodified',
    );
});
