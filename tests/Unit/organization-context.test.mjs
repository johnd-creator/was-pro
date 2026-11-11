import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { parse } from '@vue/compiler-sfc';
import ts from 'typescript';
import { scopeOrganizationUrl } from '../../resources/js/lib/organization-context.ts';

const origin = 'https://waspro.example';
const organization = 'synthetic-alpha';
for (const [name, input, expected] of [
    [
        'creation',
        '/waste-management/faba/production',
        '/waste-management/faba/production?organization_id=synthetic-alpha',
    ],
    [
        'filtered list',
        '/waste-management/faba/reports?year=2026#totals',
        '/waste-management/faba/reports?year=2026&organization_id=synthetic-alpha#totals',
    ],
    [
        'export',
        '/waste-management/faba/reports/monthly.xlsx?month=11',
        '/waste-management/faba/reports/monthly.xlsx?month=11&organization_id=synthetic-alpha',
    ],
    ['dashboard', '/dashboard', '/dashboard?organization_id=synthetic-alpha'],
    [
        'explicit other tab',
        '/dashboard?organization_id=synthetic-beta',
        '/dashboard?organization_id=synthetic-beta',
    ],
    [
        'explicit global context',
        '/dashboard?organization_id=',
        '/dashboard?organization_id=',
    ],
    [
        'same origin absolute',
        `${origin}/waste-management/records/123`,
        '/waste-management/records/123?organization_id=synthetic-alpha',
    ],
    [
        'external origin',
        'https://other.example/dashboard',
        'https://other.example/dashboard',
    ],
    ['administration global', '/admin/users', '/admin/users'],
    ['profile global', '/settings/profile', '/settings/profile'],
    ['login global', '/login', '/login'],
]) {
    test(`organization URL: ${name}`, () => {
        assert.equal(
            scopeOrganizationUrl(input, organization, origin),
            expected,
        );
    });
}

test('all operational Inertia calls explicitly bind the displayed organization', () => {
    const root = fileURLToPath(new URL('../../resources/js/', import.meta.url));
    const walk = (directory) =>
        readdirSync(directory, { withFileTypes: true }).flatMap((entry) =>
            entry.isDirectory()
                ? walk(path.join(directory, entry.name))
                : [path.join(directory, entry.name)],
        );
    const files = [
        ...walk(path.join(root, 'pages/waste-management')),
        ...walk(path.join(root, 'components/waste-management')),
        path.join(root, 'components/dashboard/ApprovalList.vue'),
    ].filter((file) => file.endsWith('.vue'));
    let count = 0;
    const unscoped = [];
    for (const file of files) {
        const descriptor = parse(readFileSync(file, 'utf8')).descriptor;
        const sources = [descriptor.scriptSetup?.content ?? ''];
        for (const match of (descriptor.template?.content ?? '').matchAll(
            /@(?:click|submit)(?:\.[\w-]+)*="([\s\S]*?)"/g,
        ))
            sources.push(match[1]);
        for (const source of sources) {
            const ast = ts.createSourceFile(
                'source.ts',
                source,
                ts.ScriptTarget.Latest,
                true,
                ts.ScriptKind.TS,
            );
            const visit = (node) => {
                if (
                    ts.isCallExpression(node) &&
                    ts.isPropertyAccessExpression(node.expression) &&
                    ['get', 'post', 'put', 'patch', 'delete'].includes(
                        node.expression.name.text,
                    ) &&
                    node.arguments.length
                ) {
                    const argument = node.arguments[0].getText(ast);
                    if (
                        /wasteManagementRoutes|(?:WasteRecords|WasteHaulings|Categories|Characteristics|Types|Vendors)Controller|["'`]\/waste-management\//.test(
                            argument,
                        )
                    ) {
                        count++;
                        if (!argument.startsWith('scopedUrl('))
                            unscoped.push(
                                `${path.relative(root, file)}: ${argument}`,
                            );
                    }
                }
                ts.forEachChild(node, visit);
            };
            visit(ast);
        }
    }
    assert.ok(
        count >= 60,
        `Expected broad operational request coverage, inspected ${count}`,
    );
    assert.deepEqual(unscoped, []);
});
