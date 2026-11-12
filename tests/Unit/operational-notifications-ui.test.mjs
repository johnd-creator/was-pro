import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
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
    assert.match(records, /props.filters.attention/);
    assert.match(records, /month: props.filters.month/);
    assert.match(records, /attention: props.filters.attention/);
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
