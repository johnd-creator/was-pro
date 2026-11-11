<?php

use App\Http\Requests\WasteManagement\FabaUtilizationMovementRequest;
use App\Http\Requests\WasteManagement\WasteRecordRequest;
use App\Models\FabaInternalDestination;
use App\Models\FabaMovement;
use App\Models\FabaPurpose;
use App\Models\Organization;
use App\Models\Vendor;
use App\Models\WasteRecord;
use App\Models\WasteType;
use App\Services\TenantService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenantService = app(TenantService::class);
    $suffix = Str::lower(Str::random(8));
    $this->organization = Organization::factory()->create([
        'code' => 'ACTIVE'.$suffix,
        'schema_name' => 'tenant_active_'.$suffix,
    ]);
    $this->tenantService->createSchema($this->organization->schema_name);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $this->tenantService->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
});

function activeMasterRequest(string $class, string $field, string $id, ?string $recordId = null): FormRequest
{
    $method = $recordId ? 'PUT' : 'POST';
    $request = $class::create('/validation', $method, [
        $field => $id,
        'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL,
    ]);
    $route = new Route([$method], '/validation', fn () => null);
    $route->bind($request);
    if ($recordId) {
        $route->setParameter($class === WasteRecordRequest::class ? 'wasteRecord' : 'utilization', $recordId);
    }
    $request->setRouteResolver(fn () => $route);

    return $request;
}

function activeMasterPasses(FormRequest $request, string $field): bool
{
    return Validator::make($request->all(), [$field => $request->rules()[$field]])->passes();
}

test('master selections require active current tenant rows but retain unchanged inactive history', function (string $model, string $field, string $requestClass) {
    $master = $model::factory()->create(['is_active' => true]);
    expect(activeMasterPasses(activeMasterRequest($requestClass, $field, $master->id), $field))->toBeTrue();

    $record = $requestClass === WasteRecordRequest::class
        ? WasteRecord::factory()->draft()->create([$field => $master->id])
        : FabaMovement::factory()->create([
            'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL,
            'approval_status' => FabaMovement::STATUS_PENDING_APPROVAL,
            $field => $master->id,
        ]);

    $master->update(['is_active' => false]);
    expect(activeMasterPasses(activeMasterRequest($requestClass, $field, $master->id), $field))->toBeFalse()
        ->and(activeMasterPasses(activeMasterRequest($requestClass, $field, $master->id, $record->id), $field))->toBeTrue();

    $other = $model::factory()->create(['is_active' => false]);
    expect(activeMasterPasses(activeMasterRequest($requestClass, $field, $other->id, $record->id), $field))->toBeFalse()
        ->and(activeMasterPasses(activeMasterRequest($requestClass, $field, (string) Str::uuid()), $field))->toBeFalse();

    if (in_array($model, [Vendor::class, WasteType::class], true)) {
        $master->delete();
        expect(activeMasterPasses(activeMasterRequest($requestClass, $field, $master->id, $record->id), $field))->toBeFalse();
    }
})->with([
    'waste type' => [WasteType::class, 'waste_type_id', WasteRecordRequest::class],
    'vendor' => [Vendor::class, 'vendor_id', FabaUtilizationMovementRequest::class],
    'destination' => [FabaInternalDestination::class, 'internal_destination_id', FabaUtilizationMovementRequest::class],
    'purpose' => [FabaPurpose::class, 'purpose_id', FabaUtilizationMovementRequest::class],
]);

test('B3 quantity zero optional source and exact units remain unchanged', function () {
    $master = WasteType::factory()->create(['is_active' => true]);
    $request = WasteRecordRequest::create('/validation', 'POST', [
        'date' => now()->toDateString(),
        'waste_type_id' => $master->id,
        'quantity' => 0,
        'unit' => 'liter',
    ]);

    expect(Validator::make($request->all(), $request->rules())->passes())->toBeTrue();
});
