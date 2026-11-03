<?php

use App\Services\FabaRecapService;

test('capacity summary classifies configured thresholds and exposes physical capacity', function (float $balance, ?float $capacity, string $status, bool $available) {
    $service = new class($balance, $capacity) extends FabaRecapService
    {
        public function __construct(private float $balance, private ?float $capacity) {}

        public function getMonthlyRecap(int $year, int $month): array
        {
            return ['closing_fly_ash' => $this->balance, 'closing_bottom_ash' => 0];
        }

        public function getTpsCapacitySettings(): array
        {
            return [
                'thresholds' => ['warning' => 70, 'critical' => 90],
                'materials' => [
                    'fly_ash' => ['capacity' => $this->capacity, 'warning_threshold' => 70, 'critical_threshold' => 90],
                    'bottom_ash' => ['capacity' => 100, 'warning_threshold' => 60, 'critical_threshold' => 85],
                ],
            ];
        }
    };

    $summary = $service->getTpsCapacitySummary(2026, 3);
    $flyAsh = $summary['materials'][0];
    expect($flyAsh['available'])->toBe($available)
        ->and($flyAsh['status'])->toBe($status)
        ->and($flyAsh['remaining_capacity'])->toEqual(max(0, max(0, $capacity) - max(0, $balance)))
        ->and($flyAsh['excess_quantity'])->toEqual(max(0, $balance - max(0, $capacity)))
        ->and($summary['total']['capacity'])->toEqual(max(0, $capacity) + 100)
        ->and($summary['total']['available'])->toBe($available)
        ->and($summary['materials'][1]['utilization_percentage'])->toEqual(0);
})->with([
    'normal' => [69.0, 100.0, 'normal', true],
    'warning boundary' => [70.0, 100.0, 'warning', true],
    'critical boundary' => [90.0, 100.0, 'critical', true],
    '196 percent' => [196.0, 100.0, 'critical', true],
    'missing capacity' => [20.0, null, 'normal', false],
    'negative integrity balance' => [-20.0, 100.0, 'normal', true],
    'zero capacity' => [20.0, 0.0, 'normal', false],
    'invalid capacity' => [20.0, -10.0, 'normal', false],
]);
