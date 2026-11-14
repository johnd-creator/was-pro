import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';
import { computed, ref, watch, reactive, effectScope, nextTick } from 'vue';
const source = (path) =>
    readFileSync(
        new URL(`../../resources/js/${path}`, import.meta.url),
        'utf8',
    );

test('dashboard cards are scoped links and critical action opens expired records, not approvals', () => {
    const card = source('components/dashboard/CompactStat.vue');
    assert.match(card, /:is="href \? Link : 'article'"/);
    assert.match(card, /:href="href"/);
    const dashboard = source('pages/Dashboard.vue');
    for (const filter of ['pending', 'backlog', 'expired'])
        assert.ok(dashboard.includes(`recordsHref('${filter}')`));
    assert.match(dashboard, /month: props.filters.month/);
    assert.match(
        dashboard,
        /notificationSummary.pending_waste_approvals_count/,
    );
    assert.doesNotMatch(
        source('components/dashboard/ComplianceHero.vue'),
        /records.pendingApproval/,
    );
});

test('header opens unified inbox and sidebar guide stays above profile', () => {
    const header = source('components/AppSidebarHeader.vue');
    assert.match(header, /notificationIndex.url/);
    assert.match(header, /month: notificationContext.month/);
    assert.match(header, /notificationContext\?\.organization_id/);
    const navigation = source('lib/app-navigation.ts');
    assert.doesNotMatch(navigation, /Notifikasi Operasional/);
    assert.match(navigation, /appFooterNavigationItems[\s\S]*title: 'Panduan'/);
    const sidebar = source('components/AppSidebar.vue');
    assert.match(
        sidebar,
        /<SidebarFooter>[\s\S]*appFooterNavigationItems[\s\S]*<NavUser/,
    );
    const inbox = source('pages/waste-management/notifications/Index.vue');
    assert.match(inbox, /:href="alert.source_url"/);
    assert.ok(
        inbox.indexOf('currentAlerts.length') <
            inbox.indexOf('v-if="!available"'),
    );
});

test('records preserve snapshot for filter navigation and export without legacy filter conflicts', () => {
    const records = source('pages/waste-management/records/Index.vue');
    assert.match(records, /recordSummary.pending/);
    assert.match(records, /props.recordSummary.total > 0/);
    assert.match(records, /parseRecordListState\(page.url, props.filters\)/);
    assert.match(records, /month: committedState.value.month/);
    assert.match(
        records,
        /attention:\s*expiryFilter.value === 'all' \? null : expiryFilter.value/,
    );
    assert.match(records, /:reference-date="filters.as_of"/);
    assert.doesNotMatch(records, /storage_status: expiryFilter/);
    assert.doesNotMatch(records, /masih dalam draf\s+dan belum diajukan/);
});

test('requested login and approval copy are exact', () => {
    const login = source('layouts/auth/AuthSimpleLayout.vue').replace(
        /\s+/g,
        ' ',
    );
    assert.ok(
        login.includes(
            'Kelola Operasional Limbah B3 dan FABA dalam Satu Sistem',
        ),
    );
    for (const file of [
        'components/dashboard/ApprovalList.vue',
        'pages/waste-management/records/PendingApproval.vue',
    ]) {
        assert.doesNotMatch(source(file), /Setujui cepat/);
        assert.match(source(file), /Setuju/);
    }
});

test('dashboard Fly Ash balance uses its own material value instead of the FABA total', () => {
    const dashboard = source('pages/Dashboard.vue');
    assert.match(
        dashboard,
        /label: 'Saldo Fly Ash',\s+value: props.fabaHeroStats.current_fly_ash_balance/,
    );
    assert.doesNotMatch(
        dashboard,
        /label: 'Saldo Fly Ash',\s+value: props.fabaHeroStats.current_balance/,
    );
    const hero = source('components/dashboard/FabaHeroKpiCards.vue');
    assert.match(
        hero,
        /title: 'Saldo Fly Ash',\s+value: props.stats.current_fly_ash_balance/,
    );
    assert.match(hero, /tone: props.stats.current_fly_ash_balance < 0/);
    assert.match(dashboard, /v-for="material in \['Fly Ash', 'Bottom Ash'\]"/);
    assert.match(
        source('pages/waste-management/faba/recaps/Balance.vue'),
        /Saldo TPS FABA/,
    );
});

test('dashboard period selector keeps organization scope and resynchronizes after navigation', () => {
    const dashboard = source('pages/Dashboard.vue');
    assert.match(dashboard, /for="dashboard-period"/);
    assert.match(dashboard, /v-model="selectedMonth"/);
    assert.match(dashboard, /v-for="month in availableMonths"/);
    assert.match(dashboard, /@change="changePeriod"/);
    assert.match(
        dashboard,
        /router.get\(\s*scopedUrl\(dashboardRoute.url\(\)\)/,
    );
    assert.match(dashboard, /month: selectedMonth.value/);
    assert.match(dashboard, /watch\(\s*\(\) => props.filters.month/);
    assert.match(
        dashboard,
        /onFinish: \(\) => \{\s*selectedMonth.value = props.filters.month/,
    );
    assert.match(
        dashboard,
        /:disabled="\s*isRefreshing \|\| !availableMonths.length\s*"/,
    );
    assert.match(dashboard, /Saldo Fly Ash memakai periode FABA terbaru/);
});

test('dashboard explains incoming waste and remaining stock in Indonesian', () => {
    const dashboard = source('pages/Dashboard.vue');
    const flow = source('components/dashboard/WasteFlowChart.vue');
    assert.match(dashboard, /Termasuk sisa dari periode sebelumnya/);
    assert.match(flow, /Limbah masuk disetujui/);
    assert.match(flow, /Sisa akhir periode/);
    assert.doesNotMatch(
        flow,
        /Intake approved|Backlog Expired|backlog aktif|Approved/,
    );
});

function mountDashboard() {
    const script = source('pages/Dashboard.vue')
        .split('<script setup lang="ts">')[1]
        .split('</script>')[0];
    const compiled = ts
        .transpileModule(script, {
            compilerOptions: {
                module: ts.ModuleKind.ESNext,
                target: ts.ScriptTarget.ES2022,
            },
        })
        .outputText.replace(/^import[\s\S]*?from ['"][^'"]+['"];\s*/gm, '')
        .replace(/export \{\};?/g, '');
    const props = reactive({
        filters: { month: '2026-11', organization_id: 'org-a' },
        availableMonths: [{ value: '2026-11' }, { value: '2026-10' }],
    });
    const visits = [];
    const dependencies = {
        defineProps: () => props,
        useOrganizationContext: () => ({
            scopedUrl: (url) => url + '?organization_id=org-a',
        }),
        usePermissions: () => ({ hasPermission: () => true }),
        dashboardRoute: { url: () => '/dashboard' },
        router: {
            get: (url, data, options) => {
                visits.push({ url, data, options });
                options.onStart();
            },
            reload: (options) => {
                visits.push({ options });
                options.onStart();
            },
        },
        computed,
        ref,
        watch,
    };
    const scope = effectScope();
    const state = scope.run(() =>
        new Function(
            ...Object.keys(dependencies),
            compiled +
                ';return { selectedMonth, changePeriod, refreshDashboard, isRefreshing, refreshError };',
        )(...Object.values(dependencies)),
    );
    return { ...state, props, visits, stop: () => scope.stop() };
}

test('period changes retain organization, reject repeated/invalid requests, and follow restored history props', async () => {
    const dashboard = mountDashboard();
    try {
        dashboard.changePeriod();
        assert.equal(dashboard.visits.length, 0);
        dashboard.selectedMonth.value = 'unknown';
        dashboard.changePeriod();
        assert.equal(dashboard.visits.length, 0);
        dashboard.selectedMonth.value = '2026-10';
        dashboard.changePeriod();
        assert.equal(dashboard.visits.length, 1);
        assert.equal(
            dashboard.visits[0].url,
            '/dashboard?organization_id=org-a',
        );
        assert.deepEqual(dashboard.visits[0].data, { month: '2026-10' });
        dashboard.changePeriod();
        assert.equal(dashboard.visits.length, 1);
        dashboard.props.filters.month = '2026-10';
        dashboard.visits[0].options.onFinish();
        await nextTick();
        assert.equal(dashboard.selectedMonth.value, '2026-10');
        assert.equal(dashboard.isRefreshing.value, false);
        dashboard.props.filters.month = '2026-11';
        await nextTick();
        assert.equal(dashboard.selectedMonth.value, '2026-11');
    } finally {
        dashboard.stop();
    }
});

test('failed or cancelled period navigation restores selected month and allows retry', () => {
    const dashboard = mountDashboard();
    try {
        dashboard.selectedMonth.value = '2026-10';
        dashboard.changePeriod();
        dashboard.visits[0].options.onError();
        dashboard.visits[0].options.onFinish();
        assert.equal(dashboard.refreshError.value, true);
        assert.equal(dashboard.selectedMonth.value, '2026-11');
        assert.equal(dashboard.isRefreshing.value, false);
        dashboard.selectedMonth.value = '2026-10';
        dashboard.changePeriod();
        assert.equal(dashboard.refreshError.value, false);
        dashboard.visits[1].options.onFinish();
        assert.equal(dashboard.selectedMonth.value, '2026-11');
        assert.equal(dashboard.isRefreshing.value, false);
    } finally {
        dashboard.stop();
    }
});

test('approval time display does not invent an age for a missing submission date', () => {
    const approval = source('components/dashboard/ApprovalList.vue');
    const script = approval
        .split('<script setup lang="ts">')[1]
        .split('</script>')[0];
    const expression = script.match(/function formatTime\([\s\S]*?\n\}/)[0];
    const compiled = ts.transpileModule(expression, {
        compilerOptions: { target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const formatTime = new Function(compiled + ';return formatTime;')();
    assert.equal(formatTime(null), 'Waktu pengajuan belum tercatat');
    assert.equal(formatTime(''), 'Waktu pengajuan belum tercatat');
    assert.equal(formatTime('invalid'), 'Waktu pengajuan belum tercatat');
});

test('dashboard removes the large header and keeps the period control in its section toolbar', () => {
    const dashboard = source('pages/Dashboard.vue');
    assert.doesNotMatch(dashboard, /Ikhtisar operasional/);
    assert.doesNotMatch(dashboard, /Pusat kendali operasional/i);
    assert.match(
        dashboard,
        /aria-label="Bagian dashboard"[\s\S]*id="dashboard-period"/,
    );
    assert.match(dashboard, /for="dashboard-period"/);
    assert.match(dashboard, /@change="changePeriod"/);
    assert.match(dashboard, /@click="refreshDashboard"/);
});

test('B3 navigation groups existing scoped routes and protects hauling approval access', () => {
    const navigation = source('lib/app-navigation.ts');
    const b3 = navigation
        .split("title: 'Limbah B3'")[1]
        .split("title: 'FABA'")[0];
    assert.match(b3, /labelOnly: true/);
    for (const title of [
        'Dashboard Limbah B3',
        'Laporan Limbah B3',
        'Catatan Limbah B3',
        'Neraca Limbah B3',
        'Rencana Angkut',
        'Pengangkutan Limbah B3',
        'Riwayat Audit B3',
    ]) {
        assert.ok(b3.includes(`title: '${title}'`));
    }
    assert.match(b3, /capability: 'hauling_plans_available'/);
    assert.match(
        b3,
        /pendingApproval.url\(\)[\s\S]*permissions: \['waste_hauling.approve', 'waste_hauling.reject'\]/,
    );
    assert.match(b3, /permission: 'operational_audit.view'/);
    assert.match(source('components/NavMain.vue'), /OrganizationLink.vue/);
});

test('records keeps compact actions and filtering after removing promotional panels', () => {
    const records = source('pages/waste-management/records/Index.vue');
    assert.doesNotMatch(
        records,
        /Snapshot|Waste Operations|Aksi Cepat|Aksi cepat operasional|Fokus halaman/,
    );
    assert.match(records, /aria-label="Tindakan catatan limbah"/);
    assert.match(records, /v-if="props.wasteTypes"/);
    assert.match(records, /@click="exportCsv"/);
    assert.match(records, /@click="isCreateDialogOpen = true"/);
    assert.match(records, /@click="changeAttention\('all'\)"/);
    assert.match(records, /flex flex-wrap items-center gap-2/);
});
