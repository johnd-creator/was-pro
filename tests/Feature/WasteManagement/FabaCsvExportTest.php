<?php

use App\Models\FabaInternalDestination;
use App\Models\FabaMovement;
use App\Models\FabaPurpose;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TenantService;
use Illuminate\Support\Facades\Artisan;

test('FABA CSV exports neutralize untrusted text and retain source data', function (string $kind) {
    $organization = Organization::factory()->create();
    $tenants = app(TenantService::class);
    $tenants->createSchema($organization->schema_name);
    $tenants->switchToSchema($organization->schema_name);
    Artisan::call('migrate', ['--path' => 'database/migrations/tenant', '--force' => true]);
    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'role_id' => Role::where('slug', 'operator')->value('id'),
        'name' => '=CREATOR',
    ]);
    $vendor = Vendor::factory()->create(['name' => '+VENDOR']);
    $destination = FabaInternalDestination::factory()->create(['name' => '-DESTINATION']);
    $purpose = FabaPurpose::factory()->create(['name' => '@PURPOSE']);
    $inputs = ['=1+1', '+SUM(1,2)', '-1+1', '@SUM(1,2)', " \t=1+1", "\ttext", "\r\ntext", "\ntext", 'normal, "quoted" 日本語'];
    $entries = [];
    foreach ($inputs as $input) {
        $entries[] = FabaMovement::factory()->create([
            'movement_type' => $kind === 'production' ? FabaMovement::TYPE_PRODUCTION : FabaMovement::TYPE_UTILIZATION_EXTERNAL,
            'stock_effect' => $kind === 'production' ? FabaMovement::STOCK_EFFECT_IN : FabaMovement::STOCK_EFFECT_OUT,
            'approval_status' => FabaMovement::STATUS_DRAFT,
            'material_type' => FabaMovement::MATERIAL_FLY_ASH,
            'transaction_date' => '2026-11-01', 'period_year' => 2026, 'period_month' => 11,
            'quantity' => 12.5, 'unit' => 'ton', 'note' => $input,
            'document_number' => $input, 'document_date' => '2026-11-01',
            'attachment_path' => '=ATTACHMENT', 'created_by' => $user->id,
            'vendor_id' => $vendor->id, 'internal_destination_id' => $destination->id, 'purpose_id' => $purpose->id,
        ]);
    }
    $tenants->switchToPublic();
    $response = $this->actingAs($user)->get(route('waste-management.faba.'.$kind.'.export.csv', ['year' => 2026, 'month' => 11]))->assertOk();
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $response->streamedContent());
    rewind($stream);
    $headers = fgetcsv($stream, escape: '');
    expect($headers)->toHaveCount($kind === 'production' ? 11 : 17);
    $notes = [];
    while (($values = fgetcsv($stream, escape: '')) !== false) {
        $row = array_combine($headers, $values);
        $notes[] = $row['Note'];
        expect($row['Created By'])->toBe("'=CREATOR")
            ->and($row['Quantity'])->toBe('12.5')->and($row['Transaction Date'])->toBe('2026-11-01')
            ->and($row['Unit'])->toBe('ton');
        if ($kind === 'utilization') {
            expect($row['Vendor'])->toBe("'+VENDOR")
                ->and($row['Internal Destination'])->toBe("'-DESTINATION")
                ->and($row['Purpose'])->toBe("'@PURPOSE")
                ->and($row['Document Number'])->toBe($row['Note'])
                ->and($row['Document Date'])->toBe('2026-11-01')
                ->and($row['Attachment Path'])->toBe("'=ATTACHMENT");
        }
    }
    fclose($stream);
    expect($notes)->toHaveCount(count($inputs));
    foreach ($inputs as $index => $input) {
        expect($notes)->toContain($index === count($inputs) - 1 ? $input : "'".$input);
    }
    $tenants->switchToSchema($organization->schema_name);
    foreach ($entries as $index => $entry) {
        expect($entry->fresh()->note)->toBe($inputs[$index])->and($entry->fresh()->document_number)->toBe($inputs[$index]);
    }
    $tenants->switchToPublic();
})->with(['production', 'utilization']);
