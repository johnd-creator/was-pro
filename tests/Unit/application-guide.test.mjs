import assert from 'node:assert/strict';
import test from 'node:test';
import {
    canOpenGuideAction,
    filterGuideCases,
    guideCases,
    matchingGuideRole,
} from '../../resources/js/lib/application-guide.ts';
import { formatFabaStatus } from '../../resources/js/lib/faba.ts';
import { scopeOrganizationUrl } from '../../resources/js/lib/organization-context.ts';

const access = {
    permissions: [],
    isSuperAdmin: false,
    tenantReady: true,
    modules: { waste: true, faba: true, audit: true },
    plans: true,
    inbox: true,
};
test('guide catalogue has distinct cases and complete recovery guidance', () => {
    assert.equal(
        new Set(guideCases.map((item) => item.id)).size,
        guideCases.length,
    );
    for (const item of guideCases) {
        assert.ok(
            item.prerequisites.length &&
                item.steps.length &&
                item.outcome &&
                item.recovery,
        );
        assert.ok(item.roles.length);
    }
});
test('search covers steps and recovery case insensitively with combined filters', () => {
    assert.ok(filterGuideCases('  DITOLAK  ', 'operator', 'FABA').length > 0);
    assert.ok(
        filterGuideCases('snapshot versi', 'all', 'all').some(
            (item) => item.id === 'laporan',
        ),
    );
    assert.deepEqual(filterGuideCases('no-match-xyz', 'all', 'all'), []);
    assert.equal(filterGuideCases('', 'all', 'all').length, guideCases.length);
    assert.ok(
        filterGuideCases('', 'operator', 'all').every((item) =>
            item.roles.includes('operator'),
        ),
    );
});
test('actual role matching never infers privilege from label or unknown role', () => {
    assert.equal(matchingGuideRole('operator', false), 'operator');
    assert.equal(matchingGuideRole('Operator', false), null);
    assert.equal(matchingGuideRole('custom', false), null);
    assert.equal(matchingGuideRole(null, false), null);
    assert.equal(matchingGuideRole('operator', true), 'super_admin');
});
test('guide action links fail closed without actual permission even when reading another role', () => {
    for (const action of [
        'records',
        'plans',
        'production',
        'reports',
        'audit',
        'users',
        'organizations',
    ])
        assert.equal(canOpenGuideAction(action, access), false);
    assert.equal(
        canOpenGuideAction('records', {
            ...access,
            permissions: ['waste_records.view_own'],
        }),
        true,
    );
    assert.equal(
        canOpenGuideAction('plans', {
            ...access,
            permissions: ['waste_hauling.reject'],
        }),
        true,
    );
    assert.equal(
        canOpenGuideAction('haulings', {
            ...access,
            permissions: ['waste_hauling.reject'],
        }),
        false,
    );
    assert.equal(
        canOpenGuideAction('organizations', {
            ...access,
            permissions: ['super.admin'],
        }),
        false,
    );
});
test('super admin still needs tenant and installed enabled features for operational links', () => {
    const admin = { ...access, isSuperAdmin: true };
    assert.equal(
        canOpenGuideAction('plans', { ...admin, plans: false }),
        false,
    );
    assert.equal(
        canOpenGuideAction('inbox', { ...admin, inbox: false }),
        false,
    );
    assert.equal(
        canOpenGuideAction('records', { ...admin, tenantReady: false }),
        false,
    );
    assert.equal(
        canOpenGuideAction('production', {
            ...admin,
            modules: { ...admin.modules, faba: false },
        }),
        false,
    );
    assert.equal(
        canOpenGuideAction('audit', {
            ...admin,
            modules: { ...admin.modules, audit: false },
        }),
        false,
    );
    assert.equal(
        canOpenGuideAction('organizations', { ...admin, tenantReady: false }),
        true,
    );
    assert.equal(
        canOpenGuideAction('profile', { ...access, tenantReady: false }),
        true,
    );
});
test('guide links preserve explicit selected organization and do not scope global administration', () => {
    assert.equal(
        scopeOrganizationUrl('/panduan', 'alpha', 'http://localhost'),
        '/panduan?organization_id=alpha',
    );
    assert.equal(
        scopeOrganizationUrl(
            '/panduan?organization_id=beta',
            'alpha',
            'http://localhost',
        ),
        '/panduan?organization_id=beta',
    );
    assert.equal(
        scopeOrganizationUrl('/admin/roles', 'alpha', 'http://localhost'),
        '/admin/roles',
    );
});

// Operational labels must be readable alongside the guide's closing workflow.
test('closing operational statuses use Indonesian labels', () => {
    assert.equal(formatFabaStatus('ready_to_submit'), 'Siap diajukan');
    assert.equal(formatFabaStatus('submitted'), 'Diajukan');
    assert.equal(formatFabaStatus('open'), 'Masih terbuka');
});

test('optional FABA master links require both installation and actual view permission', () => {
    assert.equal(
        canOpenGuideAction('fabaMasters', {
            ...access,
            permissions: ['faba_master.view'],
        }),
        false,
    );
    assert.equal(
        canOpenGuideAction('fabaMasters', {
            ...access,
            modules: { ...access.modules, fabaMasters: true },
        }),
        false,
    );
    assert.equal(
        canOpenGuideAction('fabaMasters', {
            ...access,
            permissions: ['faba_master.view'],
            modules: { ...access.modules, fabaMasters: true },
        }),
        true,
    );
});
