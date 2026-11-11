<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteRecord;
use App\Services\TenantService;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $tenants = app(TenantService::class);
    $tenants->createSchema($this->org->schema_name);
    $tenants->switchToSchema($this->org->schema_name);
    Artisan::call('migrate', ['--path' => 'database/migrations/tenant', '--force' => true]);
    $role = Role::factory()->create();
    $role->permissions()->sync(Permission::whereIn('slug', ['waste_records.view_own', 'waste_records.reject'])->pluck('id'));
    $this->reviewer = User::factory()->create(['organization_id' => $this->org->id, 'role_id' => $role->id]);
    $this->owner = User::factory()->create(['organization_id' => $this->org->id]);
});

test('reject-only reviewer can see the tenant pending queue but not other owners drafts', function () {
    $pending = WasteRecord::factory()->pendingReview()->create(['created_by' => $this->owner->id]);
    WasteRecord::factory()->draft()->create(['created_by' => $this->owner->id]);

    $this->actingAs($this->reviewer)->get(route('waste-management.records.pending-approval'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/records/PendingApproval')
        ->has('wasteRecords', 1)->where('wasteRecords.0.id', $pending->id));
    $this->get(route('waste-management.records.show', $pending))->assertForbidden();
    $this->get(route('waste-management.records.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('wasteRecords', 0));
});

test('view-only user cannot access the reviewer queue', function () {
    $role = $this->reviewer->role;
    $role->permissions()->sync(Permission::where('slug', 'waste_records.view_own')->pluck('id'));
    $this->actingAs($this->reviewer->fresh())->get(route('waste-management.records.pending-approval'))->assertForbidden();
});

test('record CSV neutralizes spreadsheet text while retaining numeric and date cells', function () {
    $inputs = ['=1+1', '+SUM(1,2)', '-1+1', '@SUM(1,2)', " \t=1+1", "\ttext", "\rtext", "\ntext", 'normal, "quoted" text'];
    foreach ($inputs as $index => $input) {
        WasteRecord::factory()->draft()->create([
            'record_number' => 'CSV-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'created_by' => $this->reviewer->id, 'source' => $input, 'description' => $input,
            'quantity' => 12.5, 'date' => '2026-11-01',
        ]);
    }
    WasteRecord::factory()->draft()->create(['created_by' => $this->owner->id, 'source' => '=HIDDEN']);
    $response = $this->actingAs($this->reviewer)->get(route('waste-management.records.export.csv'))->assertOk();
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $response->streamedContent());
    rewind($stream);
    $header = fgetcsv($stream, escape: '');
    expect($header)->toHaveCount(16);
    $rows = [];
    while (($row = fgetcsv($stream, escape: '')) !== false) {
        $rows[$row[0]] = $row;
    }
    fclose($stream);
    expect($rows)->toHaveCount(count($inputs));
    foreach ($inputs as $index => $input) {
        $row = $rows['CSV-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)];
        $expected = $index === count($inputs) - 1 ? $input : "'".$input;
        expect($row[8])->toBe($expected)->and($row[9])->toBe($expected)
            ->and((float) $row[6])->toBe(12.5)->and($row[1])->toBe('2026-11-01');
    }
});
