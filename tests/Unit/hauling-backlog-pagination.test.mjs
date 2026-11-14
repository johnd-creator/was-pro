import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';
import { computed, ref, watch, reactive, effectScope, nextTick } from 'vue';
import {
    summarizeWasteQuantities,
    compareWasteQuantities,
} from '../../resources/js/lib/waste-quantity.ts';

const source = readFileSync(
    new URL(
        '../../resources/js/pages/waste-management/haulings/Index.vue',
        import.meta.url,
    ),
    'utf8',
);
const compiled = ts
    .transpileModule(
        source.split('<script setup lang="ts">')[1].split('</script>')[0],
        {
            compilerOptions: {
                module: ts.ModuleKind.ESNext,
                target: ts.ScriptTarget.ES2022,
            },
        },
    )
    .outputText.replace(/^import[\s\S]*?from ['"][^'"]+['"];\s*/gm, '')
    .replace(/export \{\};?/g, '');
const records = Array.from({ length: 12 }, (_, i) => ({
    id: String(i),
    record_number: `REC-${i}`,
    date: '2026-01-01',
    expiry_date: null,
    remaining_quantity: i + 1,
    unit: 'kg',
    source: 'Depot',
    waste_type: { name: 'Oil', code: 'OIL', category: { name: 'B3' } },
    hauling_history: [],
}));
const legacy = () => ({
    records,
    stats: {
        total_records: 12,
        remaining_quantities: [{ unit: 'kg', quantity: 78 }],
        pending_requests: 0,
    },
});
const filters = () => ({ search: '', urgency: 'all', sort: 'priority' });
const capable = () => ({
    ...legacy(),
    backlogContract: 1,
    backlogContracts: [1, 2],
    filters: filters(),
    initialPage: 1,
});
const paginated = () => ({
    ...capable(),
    backlogContract: 2,
    records: records.slice(10),
    pagination: { current_page: 2, last_page: 2, total: 12, from: 11, to: 12 },
    stats: {
        ...legacy().stats,
        expired: 8,
        expiring_soon: 1,
        fresh: 3,
        attention_quantities: [{ unit: 'kg', quantity: 44 }],
    },
});
function harness(initial) {
    const props = reactive(initial),
        requests = [],
        reloads = [],
        mounts = [],
        unmounts = [],
        timers = new Map();
    let timerId = 0;
    const routerListeners = new Map(),
        windowListeners = new Map();
    const listen = (map, name, callback) => {
        if (!map.has(name)) map.set(name, new Set());
        map.get(name).add(callback);
        return () => map.get(name)?.delete(callback);
    };
    const emit = (map, name) =>
        [...(map.get(name) ?? [])].forEach((callback) => callback());
    const dependencies = {
        defineProps: () => props,
        computed,
        ref,
        watch,
        onMounted: (cb) => mounts.push(cb),
        onBeforeUnmount: (cb) => unmounts.push(cb),
        useOrganizationContext: () => ({
            scopedUrl: (url) => url + '&organization_id=tenant-A',
        }),
        wasteManagementRoutes: {
            haulings: {
                index: {
                    url: ({ mergeQuery } = {}) =>
                        '/waste-management/haulings?' +
                        new URLSearchParams(mergeQuery ?? {}).toString(),
                },
            },
        },
        router: {
            on: (name, callback) => listen(routerListeners, name, callback),
            get: (url, data, options) => {
                emit(routerListeners, 'before');
                const request = { url, data, options, cancelled: false };
                requests.push(request);
                options.onCancelToken?.({
                    cancel: () => {
                        request.cancelled = true;
                        options.onFinish?.();
                    },
                });
                options.onStart?.();
            },
            reload: (options) => reloads.push(options),
        },
        window: {
            addEventListener: (name, callback) =>
                listen(windowListeners, name, callback),
            removeEventListener: (name, callback) =>
                windowListeners.get(name)?.delete(callback),
        },
        setTimeout: (cb) => {
            const id = ++timerId;
            timers.set(id, cb);
            return id;
        },
        clearTimeout: (id) => timers.delete(id),
        summarizeWasteQuantities,
        compareWasteQuantities,
    };
    const scope = effectScope();
    const state = scope.run(() =>
        new Function(
            ...Object.keys(dependencies),
            compiled +
                ';return { search, urgencyFilter, sortKey, currentPage, totalPages, paginatedRecords, pageRangeLabel, expiredCount, attentionQuantityLabel, serverPaginated, loading, changePage, resetFilters, handleCreateSuccess };',
        )(...Object.values(dependencies)),
    );
    return {
        ...state,
        props,
        requests,
        reloads,
        externalNavigation: () => emit(routerListeners, 'before'),
        popstate: () => emit(windowListeners, 'popstate'),
        listenerCount: () =>
            [...routerListeners.values(), ...windowListeners.values()].reduce(
                (count, set) => count + set.size,
                0,
            ),
        mount: () => mounts.forEach((cb) => cb()),
        flush: async () => {
            await nextTick();
            const callbacks = [...timers.values()];
            timers.clear();
            callbacks.forEach((cb) => cb());
        },
        apply: async (next) => {
            for (const key of Object.keys(props))
                if (!(key in next)) delete props[key];
            Object.assign(props, next);
            requests.at(-1)?.options.onFinish?.();
            await nextTick();
        },
        stop: () => {
            unmounts.forEach((cb) => cb());
            scope.stop();
        },
    };
}

test('new frontend retains legacy client filtering and all pages when old backend has no capability', async () => {
    const h = harness(legacy());
    h.mount();
    assert.equal(h.requests.length, 0);
    h.changePage(2);
    assert.equal(h.paginatedRecords.value.length, 2);
    assert.equal(h.currentPage.value, 2);
    h.search.value = 'REC-11';
    await h.flush();
    assert.equal(h.currentPage.value, 1);
    assert.equal(h.paginatedRecords.value[0].id, '11');
    assert.equal(h.requests.length, 0);
    h.stop();
});
test('capable backend opts into pagination with a header and scoped full responses', async () => {
    const h = harness(capable());
    h.mount();
    assert.equal(h.requests.length, 1);
    const req = h.requests[0];
    assert.equal(req.options.headers['X-Waspro-Backlog-Contract'], '2');
    assert.match(req.url, /organization_id=tenant-A/);
    assert.equal(req.options.only, undefined);
    assert.equal(req.options.replace, true);
    await h.apply(paginated());
    assert.equal(h.paginatedRecords.value.length, 2);
    assert.equal(h.currentPage.value, 2);
    assert.equal(h.expiredCount.value, 8);
    assert.equal(h.attentionQuantityLabel.value, '44 kg');
    assert.match(h.pageRangeLabel.value, /11-12 dari 12/);
    h.stop();
});
test('backend rollback clears pagination metadata and returns to complete client pagination', async () => {
    const h = harness(paginated());
    h.changePage(2);
    await h.apply(legacy());
    assert.equal(h.serverPaginated.value, false);
    assert.equal(h.currentPage.value, 2);
    assert.equal(h.paginatedRecords.value.length, 2);
    h.changePage(1);
    assert.equal(h.paginatedRecords.value.length, 10);
    assert.equal(h.requests.length, 1);
    h.stop();
});
test('rapid search cancels in-flight visits and only sends final filters', async () => {
    const h = harness(paginated());
    h.search.value = 'one';
    await h.flush();
    h.search.value = 'two';
    await nextTick();
    assert.equal(h.requests[0].cancelled, true);
    h.search.value = 'final';
    await h.flush();
    assert.equal(h.requests.length, 2);
    assert.match(h.requests[1].url, /search=final/);
    assert.match(h.requests[1].url, /page=1/);
    const next = {
        ...paginated(),
        filters: { ...filters(), search: 'final' },
        pagination: { ...paginated().pagination, current_page: 1 },
    };
    await h.apply(next);
    await h.flush();
    assert.equal(h.requests.length, 2);
    h.stop();
});
test('restored history synchronizes controls without another navigation and unmount cancels pending work', async () => {
    const h = harness(paginated());
    await h.apply({
        ...paginated(),
        filters: { search: 'history', urgency: 'fresh', sort: 'latest' },
    });
    await h.flush();
    assert.equal(h.search.value, 'history');
    assert.equal(h.requests.length, 0);
    h.search.value = 'pending';
    await nextTick();
    h.stop();
    await h.flush();
    assert.equal(h.requests.length, 0);
});
test('successful realization reload keeps the version handshake and permits backend fallback', () => {
    const h = harness(paginated());
    h.handleCreateSuccess();
    assert.equal(h.reloads[0].headers['X-Waspro-Backlog-Contract'], '2');
    assert.equal(h.reloads[0].only, undefined);
    h.stop();
    const old = harness(legacy());
    old.handleCreateSuccess();
    assert.deepEqual(old.reloads[0].headers, {});
    old.stop();
});

test('resetting to the displayed filters cancels an older search instead of accepting stale results', async () => {
    const h = harness(paginated());
    h.search.value = 'in-flight';
    await h.flush();
    assert.equal(h.requests.length, 1);
    h.resetFilters();
    await h.flush();
    assert.equal(h.requests[0].cancelled, true);
    assert.equal(h.requests.length, 1);
    assert.equal(h.search.value, '');
    h.stop();
});

test('leaving Pengangkutan cancels delayed filters and in-flight visits before unmount', async () => {
    const h = harness(paginated());
    h.mount();
    h.search.value = 'pending';
    await nextTick();
    h.externalNavigation();
    await h.flush();
    assert.equal(h.requests.length, 0);
    h.search.value = 'in-flight';
    await h.flush();
    assert.equal(h.requests.length, 1);
    assert.equal(h.loading.value, true);
    h.externalNavigation();
    assert.equal(h.requests[0].cancelled, true);
    assert.equal(h.loading.value, false);
    h.search.value = 'back';
    await nextTick();
    h.popstate();
    await h.flush();
    assert.equal(h.requests.length, 1);
    h.stop();
    assert.equal(h.listenerCount(), 0);
});
