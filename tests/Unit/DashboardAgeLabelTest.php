<?php

use App\Services\UnifiedDashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

test('dashboard task age is human readable without fractional hours', function (?int $secondsAgo, string $expected) {
    $now = CarbonImmutable::parse('2026-11-13 12:00:00', 'UTC');
    Carbon::setTestNow($now);

    try {
        $reflection = new ReflectionClass(UnifiedDashboardService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $submittedAt = $secondsAgo === null ? null : $now->subSeconds($secondsAgo)->toIso8601String();

        expect($reflection->getMethod('formatAgeLabel')->invoke($service, $submittedAt))->toBe($expected);
    } finally {
        Carbon::setTestNow();
    }
})->with([
    'missing timestamp' => [null, 'Perlu perhatian'],
    'just submitted' => [0, '< 1 jam'],
    'two minutes' => [120, '< 1 jam'],
    'fifty nine minutes' => [3599, '< 1 jam'],
    'one hour' => [3600, '1 jam'],
    'one and a half hours' => [5400, '1 jam'],
    'almost one day' => [86399, '23 jam'],
    'one day' => [86400, '1 hari'],
    'two and a half days' => [216000, '2 hari'],
    'future timestamp' => [-120, '< 1 jam'],
]);
