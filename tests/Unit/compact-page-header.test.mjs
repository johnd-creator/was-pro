import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
const root = new URL('../../resources/js/pages/waste-management/', import.meta.url);
const read = (p) => readFileSync(new URL(p, root), 'utf8');
function pages(dir) { return readdirSync(dir, { withFileTypes: true }).flatMap(entry => entry.isDirectory() ? pages(join(dir, entry.name)) : entry.name.endsWith('.vue') ? [join(dir, entry.name)] : []); }
test('operational pages no longer render oversized hero containers', () => {
    for (const file of pages(fileURLToPath(root))) {
        if (file.endsWith('waste-management\\Dashboard.vue') || file.endsWith('waste-management/Dashboard.vue')) continue;
        assert.doesNotMatch(readFileSync(file, 'utf8'), /wm-surface-hero/, file);
    }
});
test('compact heading preserves semantic title and optional contextual description', () => {
    const component = readFileSync(new URL('../../resources/js/components/waste-management/CompactPageHeading.vue', import.meta.url), 'utf8');
    assert.match(component, /<h1/);
    assert.match(component, /text-lg/);
    assert.match(component, /v-if="description"/);
    assert.doesNotMatch(component, /mb-8|text-3xl/);
});
test('dashboard controls and transactional actions survive header simplification', () => {
    const faba = read('faba/Dashboard.vue');
    assert.match(faba, /v-model="filterForm.year"/);
    assert.match(faba, /@submit.prevent="applyFilters"/);
    assert.match(faba, /flex flex-wrap items-center justify-between gap-3/);
    assert.match(read('records/Index.vue'), /@click="isCreateDialogOpen = true"/);
    assert.match(read('records/Index.vue'), /@click="exportCsv"/);
    assert.match(read('faba/production/Index.vue'), /Tambah produksi/);
    assert.match(read('b3/Dashboard.vue'), /v-model="start"/);
    assert.match(read('b3/Dashboard.vue'), /v-model="end"/);
});


test('hauling detail contains a single metadata section after its heading', () => {
    const hauling = read('haulings/Show.vue');
    assert.equal((hauling.match(/v-if="hauling.metadata_available"/g) || []).length, 1);
    assert.equal((hauling.match(/Unduh bukti pengangkutan/g) || []).length, 1);
    const heading = hauling.indexOf('<Heading title="Detail Pengangkutan"');
    const metadata = hauling.indexOf('data-testid="hauling-operational-metadata"');
    assert.ok(heading > 0 && metadata > heading);
    assert.ok(hauling.lastIndexOf('<CardContent', metadata) > heading);
    for (const field of ['vendor_name', 'vehicle_number', 'driver_name', 'document_number', 'destination', 'receiver']) {
        assert.equal((hauling.match(new RegExp(`hauling\\.${field} \\|\\|`, 'g')) || []).length, 1);
    }
    assert.match(hauling, /scopedUrl\(\s*WasteHaulingsController\.attachment/);
});
test('mobile header filters have a separate full-width row and actions cannot shrink', () => {
    const header = readFileSync(new URL('../../resources/js/components/AppSidebarHeader.vue', import.meta.url), 'utf8');
    assert.match(header, /order-3 flex w-full min-w-0 flex-wrap/);
    assert.match(header, /order-2 flex shrink-0 items-center/);
    assert.match(header, /class="contents"/);
    assert.doesNotMatch(header, /sidebar-wrapper:h-12/);
    assert.match(header, /@click="toggleAppearance"/);
    assert.match(header, /@update:model-value="selectOrganization"/);
    assert.match(header, /@click="selectMonth\(month.value\)"/);
});
