import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { compileTemplate, parse } from '@vue/compiler-sfc';
import { twMerge } from 'tailwind-merge';

const root = fileURLToPath(new URL('../../resources/js/', import.meta.url));
const read = (relative) => readFileSync(join(root, relative), 'utf8');
const pages = (dir) =>
    readdirSync(dir, { withFileTypes: true }).flatMap((entry) =>
        entry.isDirectory()
            ? pages(join(dir, entry.name))
            : entry.name.endsWith('.vue')
              ? [join(dir, entry.name)]
              : [],
    );

test('operational page gutters stay at 16px instead of expanding at desktop breakpoints', () => {
    const operationalPages = pages(join(root, 'pages/waste-management'));
    for (const file of operationalPages) {
        const source = readFileSync(file, 'utf8');
        assert.doesNotMatch(source, /lg:px-8|lg:py-8/, file);
    }
    assert.match(read('pages/Dashboard.vue'), /max-w-\[1680px\] space-y-4 p-4/);
    assert.match(
        read('pages/waste-management/faba/Dashboard.vue'),
        /max-w-\[1600px\] space-y-4 p-4/,
    );
});

test('compact card spacing is inherited only by authenticated application content', () => {
    const layout = read('layouts/app/AppSidebarLayout.vue');
    assert.match(layout, /\[--card-gap:0\.75rem\]/);
    assert.match(layout, /\[--card-padding:1rem\]/);
    assert.match(
        read('components/ui/card/Card.vue'),
        /gap-\[var\(--card-gap,1\.5rem\)\]/,
    );
    assert.match(
        read('components/ui/card/Card.vue'),
        /py-\[var\(--card-padding,1\.5rem\)\]/,
    );
    for (const part of ['CardHeader', 'CardContent', 'CardFooter']) {
        assert.match(
            read(`components/ui/card/${part}.vue`),
            /px-\[var\(--card-padding,1\.5rem\)\]/,
        );
    }
    for (const file of pages(join(root, 'layouts/auth'))) {
        assert.doesNotMatch(
            readFileSync(file, 'utf8'),
            /--card-gap|--card-padding/,
        );
    }
});

test('explicit flush and custom card spacing still overrides inherited defaults', () => {
    assert.equal(
        twMerge(
            'gap-[var(--card-gap,1.5rem)] py-[var(--card-padding,1.5rem)]',
            'gap-0 py-0',
        ),
        'gap-0 py-0',
    );
    assert.equal(twMerge('px-[var(--card-padding,1.5rem)]', 'px-0'), 'px-0');
    assert.equal(
        twMerge(
            '[.border-b]:pb-[var(--card-padding,1.5rem)]',
            '[.border-b]:pb-0',
        ),
        '[.border-b]:pb-0',
    );
});

test('compact presentation preserves touch controls, focus affordances, and feedback', () => {
    const dashboard = read('pages/Dashboard.vue');
    assert.match(dashboard, /min-h-11/);
    assert.match(dashboard, /focus-visible:outline-2/);
    assert.match(dashboard, /aria-live="polite"/);
    assert.match(dashboard, /@click="refreshDashboard"/);
    assert.match(dashboard, /@change="changePeriod"/);
    assert.match(read('components/ui/input/Input.vue'), /h-9 w-full min-w-0/);
    assert.match(read('components/ui/input/Input.vue'), /text-base/);
    assert.match(
        read('components/ui/button/index.ts'),
        /focus-visible:ring-\[3px\]/,
    );
    assert.match(read('components/dashboard/TaskListPanel.vue'), /min-h-11/);
    assert.match(read('components/dashboard/FabaTrendChart.vue'), /min-h-11/);
});

test('record spacing retains create, export, storage-risk filtering, and labelled controls', () => {
    const source = read('pages/waste-management/records/Index.vue');
    assert.match(source, /aria-label="Tindakan catatan limbah"/);
    assert.match(source, /@click="isCreateDialogOpen = true"/);
    assert.match(source, /@click="exportCsv"/);
    assert.match(source, /changeAttention\('expiring_soon'\)/);
    assert.match(source, /v-model="searchQuery"/);
    assert.match(source, /@update:model-value="changeAttention"/);
    assert.doesNotMatch(source, /<div class="p-3 lg:p-4">/);
});

test('affected templates compile across dashboard, lists, forms, and shared cards', () => {
    const files = [
        ...pages(join(root, 'pages/waste-management')),
        ...pages(join(root, 'components/ui/card')),
        ...[
            'pages/Dashboard.vue',
            'components/dashboard/CompactStat.vue',
            'components/dashboard/WasteFlowChart.vue',
            'components/dashboard/FabaTrendChart.vue',
            'components/dashboard/TaskListPanel.vue',
            'layouts/app/AppSidebarLayout.vue',
        ].map((file) => join(root, file)),
    ];
    for (const file of files) {
        const { descriptor, errors } = parse(readFileSync(file, 'utf8'), {
            filename: file,
        });
        assert.deepEqual(errors, [], file);
        const compiled = compileTemplate({
            source: descriptor.template.content,
            filename: file,
            id: 'compact-spacing-regression',
        });
        assert.deepEqual(compiled.errors, [], file);
    }
});

test('form cards do not stack large content padding on top of the card padding', () => {
    for (const file of [
        'records/Create.vue',
        'faba/adjustments/Create.vue',
        'faba/adjustments/Edit.vue',
        'faba/production/Create.vue',
        'faba/production/Edit.vue',
        'faba/utilization/Create.vue',
        'faba/utilization/Edit.vue',
    ]) {
        const source = read(`pages/waste-management/${file}`);
        assert.match(source, /<CardContent class="space-y-4 px-4 py-0">/, file);
        assert.doesNotMatch(
            source,
            /<CardContent class="(?:space-y-6 )?p-[68]">/,
            file,
        );
        assert.doesNotMatch(source, /<form[^>]*space-y-[68]/, file);
    }
});
