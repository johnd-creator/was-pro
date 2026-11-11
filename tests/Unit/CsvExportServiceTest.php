<?php

use App\Services\CsvExportService;

test('CSV writer neutralizes formula text without changing cell values', function (mixed $input, string $expected) {
    $stream = fopen('php://memory', 'r+');
    CsvExportService::writeRow($stream, [$input, 'sentinel']);
    rewind($stream);
    expect(fgetcsv($stream, escape: ''))->toBe([$expected, 'sentinel']);
    fclose($stream);
})->with([
    ['=1+1', "'=1+1"],
    ['+SUM(1,2)', "'+SUM(1,2)"],
    ['-1+1', "'-1+1"],
    ['@SUM(1,2)', "'@SUM(1,2)"],
    [" \t=1+1", "' \t=1+1"],
    ["\ttext", "'\ttext"],
    ["\r\ntext", "'\r\ntext"],
    ["\ntext", "'\ntext"],
    ["\u{00a0}=1+1", "'\u{00a0}=1+1"],
    ['normal, "quoted" text', 'normal, "quoted" text'],
    ['C:\\folder\\"quote"', 'C:\\folder\\"quote"'],
    ['Limbah 日本語', 'Limbah 日本語'],
    ['2026-11-01', '2026-11-01'],
    ['12.50', '12.50'],
    [-12.5, '-12.5'],
    [12.5, '12.5'],
    [0, '0'],
    [null, ''],
    ['', ''],
]);
