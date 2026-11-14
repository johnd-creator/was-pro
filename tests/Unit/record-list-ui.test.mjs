import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';
import { computed, effectScope, nextTick, reactive, ref, watch } from 'vue';
import * as helpers from '../../resources/js/lib/record-list.ts';

function mountList() {
    const source = readFileSync(
        new URL(
            '../../resources/js/pages/waste-management/records/Index.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const script = source
        .match(/<script setup lang="ts">([\s\S]*?)<\/script>/)[1]
        .replace(/import\s[\s\S]*?from\s+['"][^'"]+['"];?/g, '');
    const js = ts.transpileModule(script, {
        compilerOptions: {
            target: ts.ScriptTarget.ES2022,
            module: ts.ModuleKind.ESNext,
        },
    }).outputText;
    const page = reactive({ url: '/records?month=2026-11' });
    const props = reactive({
        filters: { month: '2026-11', attention: null },
        recordSummary: { total: 25 },
        wasteRecords: Array.from({ length: 25 }, (_, i) => ({
            id: String(i),
            record_number: `WR-${i}`,
            date: '2026-11-01',
            created_at: '',
            quantity: i,
        })),
    });
    const listeners = new Map();
    const windows = new Map();
    const timers = new Map();
    const visits = [];
    const unmount = [];
    let timerId = 0;
    const router = {
        on: (event, handler) => {
            listeners.set(event, handler);
            return () => listeners.delete(event);
        },
        get: (url, data, options) => {
            listeners.get('before')?.();
            const visit = { url, data, options, cancelled: false };
            visits.push(visit);
            options.onCancelToken({
                cancel: () => {
                    visit.cancelled = true;
                    options.onFinish();
                },
            });
        },
    };
    const deps = {
        ...helpers,
        ref,
        computed,
        watch,
        router,
        usePage: () => page,
        defineProps: () => props,
        useOrganizationContext: () => ({
            scopedUrl: (url) => `${url}&organization_id=org-a`,
            download: () => {},
        }),
        wasteManagementRoutes: {
            records: { index: { url: () => '/records' } },
        },
        onMounted: (callback) => callback(),
        onBeforeUnmount: (callback) => unmount.push(callback),
        window: {
            addEventListener: (event, handler) => windows.set(event, handler),
            removeEventListener: (event) => windows.delete(event),
        },
        setTimeout: (callback) => {
            timers.set(++timerId, callback);
            return timerId;
        },
        clearTimeout: (id) => timers.delete(id),
    };
    const scope = effectScope();
    const exposed = scope.run(() =>
        new Function(
            ...Object.keys(deps),
            `${js}\nreturn { searchQuery, sortKey, expiryFilter, currentPage, paginatedRecords, isLoading, navigate, scheduleSearch, changeAttention, changePage, resetFilters };`,
        )(...Object.values(deps)),
    );
    return {
        ...exposed,
        props,
        page,
        visits,
        listeners,
        windows,
        timers,
        flush: () => {
            const callbacks = [...timers.values()];
            timers.clear();
            callbacks.forEach((callback) => callback());
        },
        dispose: () => {
            unmount.forEach((callback) => callback());
            scope.stop();
        },
    };
}

test('Vue controls cancel stale requests and debounce once while preserving scope', () => {
    const ui = mountList();
    try {
        ui.searchQuery.value = 'first';
        ui.scheduleSearch();
        ui.flush();
        assert.equal(ui.visits.length, 1);
        ui.searchQuery.value = 'second';
        ui.scheduleSearch();
        assert.equal(ui.visits[0].cancelled, true);
        ui.searchQuery.value = 'final';
        ui.scheduleSearch();
        ui.flush();
        assert.equal(ui.visits.length, 2);
        const latest = ui.visits[1];
        const url = new URL(latest.url, 'https://test.invalid');
        assert.equal(url.searchParams.get('search'), 'final');
        assert.equal(url.searchParams.get('organization_id'), 'org-a');
        assert.equal(url.searchParams.get('page'), '1');
        assert.deepEqual(latest.options.headers, helpers.recordContractHeaders);
        ui.visits[0].options.onFinish();
        assert.equal(
            ui.isLoading.value,
            true,
            'stale finish cannot clear newer loading',
        );
        latest.options.onFinish();
        assert.equal(ui.isLoading.value, false);
    } finally {
        ui.dispose();
    }
});

test('Vue Back/Forward restores controls, cancels pending typing, and handles array/paginator history', async () => {
    const ui = mountList();
    try {
        ui.searchQuery.value = 'unfinished';
        ui.scheduleSearch();
        ui.windows.get('popstate')();
        ui.page.url =
            '/records?month=2026-10&attention=expired&search=WR-24&sort=oldest&page=1';
        ui.props.filters = { month: '2026-10', attention: 'expired' };
        await nextTick();
        ui.listeners.get('navigate')();
        assert.equal(ui.timers.size, 0);
        assert.equal(ui.searchQuery.value, 'WR-24');
        assert.equal(ui.sortKey.value, 'oldest');
        assert.equal(ui.expiryFilter.value, 'expired');
        assert.equal(ui.paginatedRecords.value.length, 1);
        ui.page.url = '/records?month=2026-11&page=2&sort=quantity_high';
        ui.props.filters = { month: '2026-11', attention: null };
        ui.props.wasteRecords = {
            data: [{ id: 'server-page-two' }],
            current_page: 2,
            last_page: 3,
            total: 25,
            from: 11,
            to: 20,
        };
        await nextTick();
        ui.listeners.get('navigate')();
        assert.equal(ui.searchQuery.value, '');
        assert.equal(ui.sortKey.value, 'quantity_high');
        assert.equal(ui.expiryFilter.value, 'all');
        assert.equal(ui.currentPage.value, 2);
        assert.equal(ui.paginatedRecords.value[0].id, 'server-page-two');
        ui.changeAttention('pending');
        assert.match(ui.visits.at(-1).url, /page=1/);
        assert.match(ui.visits.at(-1).url, /attention=pending/);
        ui.resetFilters();
        assert.doesNotMatch(ui.visits.at(-1).url, /attention=|search=/);
    } finally {
        ui.dispose();
    }
    assert.equal(ui.listeners.size, 0);
    assert.equal(ui.windows.size, 0);
});

test('external navigation and unmount cancel pending debounce', () => {
    const ui = mountList();
    ui.searchQuery.value = 'pending';
    ui.scheduleSearch();
    ui.listeners.get('before')();
    ui.flush();
    assert.equal(ui.visits.length, 0);
    ui.scheduleSearch();
    ui.dispose();
    ui.flush();
    assert.equal(ui.visits.length, 0);
});

test('OrganizationLink component scopes the first menu request and negotiates without changing caller intent', async () => {
    const { scopeOrganizationUrl } =
        await import('../../resources/js/lib/organization-context.ts');
    const source = readFileSync(
        new URL(
            '../../resources/js/components/OrganizationLink.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const script = source
        .match(/<script setup lang="ts">([\s\S]*?)<\/script>/)[1]
        .replace(/import\s[\s\S]*?from\s+['"][^'"]+['"];?/g, '');
    const js = ts.transpileModule(script, {
        compilerOptions: { target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const index = '/waste-management/records';
    const origin = 'https://waspro.example';
    const props = reactive({
        href: index,
        method: 'get',
        headers: { 'X-Custom': 'keep' },
    });
    const deps = {
        computed,
        defineOptions: () => {},
        defineProps: () => props,
        useOrganizationContext: () => ({
            scopedUrl: (url) => scopeOrganizationUrl(url, 'org-a', origin),
        }),
        recordNavigationHeaders: helpers.recordNavigationHeaders,
        recordsIndex: { url: () => index },
        window: { location: { origin } },
    };
    const link = new Function(
        ...Object.keys(deps),
        `${js}\nreturn { href, headers };`,
    )(...Object.values(deps));
    assert.equal(link.href.value, `${index}?organization_id=org-a`);
    assert.deepEqual(link.headers.value, {
        'X-Custom': 'keep',
        ...helpers.recordContractHeaders,
    });
    props.href = `${index}?organization_id=explicit`;
    assert.equal(link.href.value, props.href);
    props.headers = { 'x-waspro-records-contract': '1' };
    assert.strictEqual(link.headers.value, props.headers);
    props.headers = { 'X-Custom': 'keep' };
    props.href = `${index}/create`;
    assert.strictEqual(link.headers.value, props.headers);
    props.href = `https://external.example${index}`;
    assert.equal(link.href.value, props.href);
    assert.strictEqual(link.headers.value, props.headers);
    props.href = { url: index, method: 'post' };
    assert.equal(link.href.value.method, 'post');
    assert.strictEqual(link.headers.value, props.headers);
    props.href = index;
    props.method = 'post';
    assert.strictEqual(link.headers.value, props.headers);
    assert.match(source, /v-bind="\$attrs"/);
    assert.match(source, /:method="props.method"/);
    assert.match(source, /:headers="headers"/);
});
