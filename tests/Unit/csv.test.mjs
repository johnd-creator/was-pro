import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { encodeCsvRow } from '../../resources/js/lib/csv.ts';

test('CSV protects formula strings and quotes delimiters, quotes and newlines', () => {
    for (const value of [
        '=1+1',
        '+SUM(1,2)',
        '-1+1',
        '@SUM(1,2)',
        ' \t=1+1',
        '\ttext',
        '\r\ntext',
        '\ntext',
        '\u00a0=1+1',
    ]) {
        const safe = `'${value}`;
        const expected = /[,"\r\n]/.test(safe)
            ? `"${safe.replaceAll('"', '""')}"`
            : safe;
        assert.equal(encodeCsvRow([value]), expected);
    }
    assert.equal(
        encodeCsvRow([
            'normal, "quoted" 日本語',
            'line\nbreak',
            -12.5,
            0,
            '2026-11-01',
            '12.50',
            null,
        ]),
        '"normal, ""quoted"" 日本語","line\nbreak",-12.5,0,2026-11-01,12.50,',
    );
});

test('both dashboard exporters use the shared CSV row encoder', () => {
    for (const component of ['CategoryChart', 'StatusDistribution']) {
        const source = readFileSync(
            new URL(
                `../../resources/js/components/dashboard/${component}.vue`,
                import.meta.url,
            ),
            'utf8',
        );
        assert.match(source, /import \{ encodeCsvRow \} from '@\/lib\/csv'/);
        assert.match(source, /\[headers, \.\.\.rows\]\.map\(encodeCsvRow\)/);
        assert.doesNotMatch(source, /row\.join\(','\)/);
    }
});
