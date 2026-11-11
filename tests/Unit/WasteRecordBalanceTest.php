<?php

use App\Models\WasteHauling;
use App\Models\WasteRecord;
use Illuminate\Database\Eloquent\Collection;

test('storage balance includes only approved haulings', function (array $haulings, float $remaining, string $operationalStatus) {
    $record = new WasteRecord(['quantity' => 100, 'status' => 'approved']);
    $record->setRelation('haulings', new Collection(array_map(
        fn (array $hauling): WasteHauling => new WasteHauling($hauling),
        $haulings,
    )));

    expect($record->getRemainingQuantity())->toBe($remaining)
        ->and($record->getOperationalStatus())->toBe($operationalStatus);
})->with([
    'fully approved' => [[['status' => 'approved', 'quantity' => 100]], 0.0, 'completed'],
    'partial approved' => [[['status' => 'approved', 'quantity' => 30]], 70.0, 'partially_hauled'],
    'pending cannot settle stock' => [[['status' => 'pending_approval', 'quantity' => 100]], 100.0, 'not_hauled'],
    'rejected cannot settle stock' => [[['status' => 'rejected', 'quantity' => 100]], 100.0, 'not_hauled'],
    'cancelled cannot settle stock' => [[['status' => 'cancelled', 'quantity' => 100]], 100.0, 'not_hauled'],
    'mixed approved and pending' => [[['status' => 'approved', 'quantity' => 30], ['status' => 'pending_approval', 'quantity' => 70]], 70.0, 'partially_hauled'],
    'multiple approved simulation entries' => [[['status' => 'approved', 'quantity' => 40, 'hauling_number' => 'SIMULASI-BSLA-01'], ['status' => 'approved', 'quantity' => 60, 'hauling_number' => 'SIMULASI-BSLA-02']], 0.0, 'completed'],
]);
