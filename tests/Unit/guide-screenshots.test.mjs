import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';
import { guideCases } from '../../resources/js/lib/application-guide.ts';
import { guideScreenshots } from '../../resources/js/lib/guide-screenshots.ts';

const componentUrl = new URL(
    '../../resources/js/components/GuideScreenshot.vue',
    import.meta.url,
);
const source = readFileSync(componentUrl, 'utf8');

function jpegDimensions(bytes) {
    assert.equal(
        bytes.readUInt16BE(0),
        0xffd8,
        'Expected JPEG start-of-image marker',
    );
    assert.equal(
        bytes.readUInt16BE(bytes.length - 2),
        0xffd9,
        'Expected JPEG end-of-image marker',
    );
    const frameMarkers = new Set([
        0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce,
        0xcf,
    ]);
    let offset = 2;
    while (offset + 3 < bytes.length) {
        assert.equal(bytes[offset++], 0xff, 'Expected JPEG segment marker');
        while (bytes[offset] === 0xff) offset++;
        const marker = bytes[offset++];
        if (marker === 0xda || marker === 0xd9) break;
        if (marker === 0x01 || (marker >= 0xd0 && marker <= 0xd7)) continue;
        const length = bytes.readUInt16BE(offset);
        assert.ok(
            length >= 2 && offset + length <= bytes.length,
            'Invalid JPEG segment length',
        );
        if (frameMarkers.has(marker)) {
            assert.ok(length >= 8, 'Truncated JPEG frame');
            return {
                height: bytes.readUInt16BE(offset + 3),
                width: bytes.readUInt16BE(offset + 5),
            };
        }
        offset += length;
    }
    assert.fail('JPEG frame dimensions not found');
}

test('every guide case has one distinct screenshot with Indonesian context and sample-data alt text', () => {
    assert.equal(guideCases.length, 21);
    assert.deepEqual(
        Object.keys(guideScreenshots).sort(),
        guideCases.map(({ id }) => id).sort(),
    );
    assert.equal(
        new Set(Object.values(guideScreenshots).map(({ src }) => src)).size,
        guideCases.length,
    );
    for (const { id } of guideCases) {
        const screenshot = guideScreenshots[id];
        assert.equal(screenshot.src, `/images/guide/${id}.jpg`);
        assert.match(screenshot.alt, /data contoh/);
        assert.ok(screenshot.alt.includes(screenshot.context));
        assert.ok(screenshot.alt.includes(screenshot.role));
        assert.ok(screenshot.caption.length > 30);
    }
});

test('all screenshot assets are actual JPEG files with matching dimensions and reasonable payloads', () => {
    for (const screenshot of Object.values(guideScreenshots)) {
        const bytes = readFileSync(
            new URL(`../../public${screenshot.src}`, import.meta.url),
        );
        const dimensions = jpegDimensions(bytes);
        assert.equal(dimensions.width, screenshot.width, screenshot.src);
        assert.equal(dimensions.height, screenshot.height, screenshot.src);
        assert.ok(
            bytes.length < 2_000_000,
            `Keep lazy-loaded guide asset below 2 MB: ${screenshot.src}`,
        );
    }
});

test('screenshot component compiles and preserves accessible non-navigation dialog controls', () => {
    const { descriptor, errors } = parse(source);
    assert.deepEqual(errors, []);
    const script = compileScript(descriptor, { id: 'guide-screenshot' });
    const template = compileTemplate({
        source: descriptor.template.content,
        filename: 'GuideScreenshot.vue',
        id: 'guide-screenshot',
        compilerOptions: { bindingMetadata: script.bindings },
    });
    assert.deepEqual(template.errors, []);
    for (const element of [
        'DialogTrigger',
        'DialogContent',
        'DialogTitle',
        'DialogDescription',
        'DialogClose',
        'figcaption',
    ]) {
        assert.ok(source.includes(`<${element}`));
    }
    assert.match(source, /loading="lazy"/);
    assert.match(source, /decoding="async"/);
    assert.match(source, /:alt="screenshot.alt"/);
    assert.match(source, /aria-label="Tutup gambar"/);
    assert.match(source, /Tekan\s+Escape/);
    assert.match(source, /Data contoh/);
    assert.match(source, /@error="failed = true"/);
    assert.doesNotMatch(source, /<a\s|<Link|window\.open|router\./);
});

test('guide displays the screenshot for the matching filtered case', () => {
    const page = readFileSync(
        new URL('../../resources/js/pages/Guide.vue', import.meta.url),
        'utf8',
    );
    assert.match(page, /v-for="item in results"/);
    assert.match(page, /<GuideScreenshot/);
    assert.match(page, /:screenshot="guideScreenshots\[item.id\]"/);
});
