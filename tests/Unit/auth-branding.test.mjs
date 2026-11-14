import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { compileTemplate, parse } from '@vue/compiler-sfc';
const read = (file) =>
    readFileSync(
        new URL(`../../resources/js/${file}`, import.meta.url),
        'utf8',
    );
test('auth emblem and wordmark grow proportionally without truncation', () => {
    const brand = read('components/AppLogoAuth.vue');
    for (const token of [
        'size-16',
        'sm:size-20',
        'text-3xl',
        'sm:text-4xl',
        'dark:text-slate-100',
    ])
        assert.ok(brand.includes(token), token);
    assert.match(brand, /WASPRO/);
    assert.doesNotMatch(brand, /truncate/);
});
test('PLN is centered in the card without a duplicate account-access caption', () => {
    const layout = read('layouts/auth/AuthSimpleLayout.vue');
    assert.doesNotMatch(layout, /Account Access/);
    assert.match(
        layout,
        /class="flex items-center justify-center"\s+data-testid="auth-pln-brand"/,
    );
    assert.match(layout, /alt="PLN Indonesia Power"/);
    assert.equal((layout.match(/src="\/logo_ip.png"/g) ?? []).length, 1);
    assert.match(layout, /mx-auto mb-5 flex w-fit items-center lg:hidden/);
});
test('login keeps accessible fields, autocomplete, errors, and submit protections', () => {
    const login = read('pages/auth/Login.vue');
    for (const text of [
        'for="email"',
        'for="password"',
        'autocomplete="email"',
        'autocomplete="current-password"',
        'errors.email',
        'errors.password',
        ':disabled="processing"',
    ])
        assert.ok(login.includes(text), text);
    assert.match(login, /v-bind="store.form\(\)"/);
    assert.match(login, /data-test="login-button"/);
    assert.match(login, /h-12 w-full/);
});
test('auth branding and login templates compile', () => {
    for (const file of [
        'components/AppLogoAuth.vue',
        'layouts/auth/AuthSimpleLayout.vue',
        'pages/auth/Login.vue',
    ]) {
        const { descriptor, errors } = parse(read(file), { filename: file });
        assert.deepEqual(errors, [], file);
        assert.deepEqual(
            compileTemplate({
                source: descriptor.template.content,
                filename: file,
                id: 'auth-branding',
            }).errors,
            [],
            file,
        );
    }
});

test('WASPRO login brand has no card background while keeping its dimensions', () => {
    const layout = read('layouts/auth/AuthSimpleLayout.vue');
    const brandLinks = [...layout.matchAll(/<Link\b([\s\S]*?)>\s*<AppLogoAuth\s*\/>\s*<\/Link>/g)];
    assert.equal(brandLinks.length, 2);
    for (const [, attributes] of brandLinks) {
        const classes = attributes.match(/class="([^"]*)"/)[1];
        assert.doesNotMatch(classes, /(?:^|\s)(?:dark:|hover:)?(?:bg-|shadow-|backdrop-)/);
        assert.doesNotMatch(classes, /border-(?:slate|white)/);
    }
    assert.match(brandLinks[0][1], /border border-transparent px-4 py-3/);
});
