<?php

use App\Models\ApiToken;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenant = app(TenantService::class);
    $this->organization = Organization::factory()->create();
    if (! $this->tenant->schemaExists($this->organization->schema_name)) {
        $this->tenant->createSchema($this->organization->schema_name);
    }
    $this->tenant->switchToSchema($this->organization->schema_name);
    $this->tenant->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenant->switchToPublic();
    $slugs = ['faba_production.view', 'faba_production.create', 'faba_approvals.view', 'faba_approvals.submit',
        'faba_approvals.approve', 'faba_approvals.reject', 'faba_approvals.reopen', 'faba_utilization.view',
        'waste_categories.view', 'waste_categories.create', 'waste_categories.edit', 'users.view', 'roles.view', 'faba_reports.export'];
    $role = Role::factory()->create();
    foreach ($slugs as $slug) {
        $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => explode('.', $slug)[0], 'is_active' => true]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }
    $this->maker = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $role->id, 'password' => Hash::make('existing-password')]);
    $this->reviewer = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $role->id]);
});

function mobileImplementationToken(User $user): string
{
    $token = Str::random(40);
    ApiToken::create(['user_id' => $user->id, 'name' => 'test', 'token' => hash('sha256', $token), 'expires_at' => now()->addDay()]);

    return $token;
}

test('maker cannot approve own daily transaction but independent reviewer can', function () {
    $makerToken = mobileImplementationToken($this->maker);
    $result = $this->withToken($makerToken)->postJson('/api/v1/faba/production', [
        'transaction_date' => now()->toDateString(), 'material_type' => 'fly_ash',
        'movement_type' => 'production', 'quantity' => '12.50',
    ])->assertCreated();
    $id = $result->json('data.id');
    $this->withToken($makerToken)->postJson("/api/v1/faba/movements/{$id}/approve")->assertStatus(409);
    $this->withToken(mobileImplementationToken($this->reviewer))->postJson("/api/v1/faba/movements/{$id}/approve")->assertSuccessful();
});

test('submit permission works without approve and another supervisor closes period', function () {
    $this->tenant->switchToSchema($this->organization->schema_name);
    FabaMovement::factory()->create(['material_type' => 'fly_ash', 'movement_type' => 'production', 'stock_effect' => 'in',
        'quantity' => 10, 'transaction_date' => now()->toDateString(), 'period_year' => now()->year, 'period_month' => now()->month,
        'approval_status' => 'approved', 'created_by' => $this->maker->id]);
    $this->tenant->switchToPublic();
    $this->maker->role->permissions()->detach(Permission::whereIn('slug', ['faba_approvals.approve', 'faba_approvals.reject', 'faba_approvals.reopen'])->pluck('id'));
    $reviewRole = Role::factory()->create();
    $reviewRole->permissions()->sync(Permission::whereIn('slug', ['faba_approvals.approve', 'faba_approvals.view'])->pluck('id'));
    $this->reviewer->update(['role_id' => $reviewRole->id]);
    $this->withToken(mobileImplementationToken($this->maker))->postJson('/api/v1/faba/approvals/submit', ['year' => now()->year, 'month' => now()->month])->assertSuccessful();
    $this->withToken(mobileImplementationToken($this->reviewer))->postJson('/api/v1/faba/approvals/'.now()->year.'/'.now()->month.'/approve')->assertSuccessful();
});

test('monthly self approval is rejected even with approval permission', function () {
    $this->tenant->switchToSchema($this->organization->schema_name);
    FabaMonthlyApproval::create(['year' => 2026, 'month' => 1, 'status' => 'submitted', 'submitted_by' => $this->maker->id]);
    $this->tenant->switchToPublic();
    $this->withToken(mobileImplementationToken($this->maker))->postJson('/api/v1/faba/approvals/2026/1/approve')->assertStatus(409);
});

test('master data round trip reuses unique validation correctly', function () {
    $token = mobileImplementationToken($this->maker);
    $result = $this->withToken($token)->postJson('/api/v1/master-data/categories', ['name' => 'Mobile category', 'code' => 'MOB', 'is_active' => true])->assertCreated();
    $id = $result->json('data.id');
    $this->withToken($token)->putJson("/api/v1/master-data/categories/{$id}", ['name' => 'Updated', 'code' => 'MOB', 'is_active' => true])->assertSuccessful();
    $this->withToken($token)->getJson('/api/v1/master-data/categories')->assertJsonPath('data.0.name', 'Updated');
});

test('users directory remains organization scoped', function () {
    $other = User::factory()->create();
    $response = $this->withToken(mobileImplementationToken($this->maker))->getJson('/api/v1/users')->assertSuccessful();
    expect(collect($response->json('data'))->pluck('id')->all())->not->toContain($other->id);
});

test('password validation uses bearer identity and revokes every api token', function () {
    $token = mobileImplementationToken($this->maker);
    mobileImplementationToken($this->maker);
    $this->withToken($token)->putJson('/api/v1/profile/password', [
        'current_password' => 'wrong', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
    ])->assertUnprocessable();
    $this->withToken($token)->putJson('/api/v1/profile/password', [
        'current_password' => 'existing-password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
    ])->assertSuccessful();
    expect(ApiToken::where('user_id', $this->maker->id)->count())->toBe(0);
    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

test('attachment requires a token and is not accessible in another tenant', function () {
    Storage::fake(config('filesystems.default'));
    Storage::disk(config('filesystems.default'))->put('faba/mobile-test.pdf', '%PDF-test');
    $this->tenant->switchToSchema($this->organization->schema_name);
    $movement = FabaMovement::factory()->create(['movement_type' => 'utilization_external', 'attachment_path' => 'faba/mobile-test.pdf', 'created_by' => $this->maker->id]);
    $this->tenant->switchToPublic();
    $path = "/api/v1/faba/utilization/{$movement->id}/attachment";
    $this->getJson($path)->assertUnauthorized();
    $this->withToken(mobileImplementationToken($this->maker))->get($path)->assertSuccessful();
    $otherOrganization = Organization::factory()->create();
    if (! $this->tenant->schemaExists($otherOrganization->schema_name)) {
        $this->tenant->createSchema($otherOrganization->schema_name);
    }
    $this->tenant->switchToSchema($otherOrganization->schema_name);
    $this->tenant->runMigrationsForTenant($otherOrganization->schema_name, 'database/migrations/tenant');
    $this->tenant->switchToPublic();
    $otherUser = User::factory()->create(['organization_id' => $otherOrganization->id, 'role_id' => $this->maker->role_id]);
    $this->withToken(mobileImplementationToken($otherUser))->getJson($path)->assertNotFound();
});

test('creation retry uses same result and stale resource version rejects edit', function () {
    $token = mobileImplementationToken($this->maker);
    $body = ['name' => 'Idempotent category', 'code' => 'IDEMP', 'is_active' => true];
    $first = $this->withToken($token)->withHeader('Idempotency-Key', 'same-category')->postJson('/api/v1/master-data/categories', $body)->assertCreated();
    $second = $this->withToken($token)->withHeader('Idempotency-Key', 'same-category')->postJson('/api/v1/master-data/categories', $body)->assertCreated();
    expect($second->json('data.id'))->toBe($first->json('data.id'));
    $id = $first->json('data.id');
    $this->withHeaders(['Idempotency-Key' => 'update-category'])->putJson("/api/v1/master-data/categories/{$id}", [
        ...$body, 'name' => 'Changed', '_source_version' => $first->json('data.resource_version'),
    ])->assertSuccessful();
    $this->withHeaders(['Idempotency-Key' => 'stale-category'])->putJson("/api/v1/master-data/categories/{$id}", [
        ...$body, 'name' => 'Stale overwrite', '_source_version' => $first->json('data.resource_version'),
    ])->assertStatus(409);
});

test('multipart method override updates external utilization and attachment remains downloadable', function () {
    $this->tenant->switchToSchema($this->organization->schema_name);
    $vendor = \App\Models\Vendor::factory()->create(['is_active' => true]);
    \App\Models\FabaOpeningBalance::factory()->create(['year' => now()->year, 'month' => now()->month, 'material_type' => 'fly_ash', 'quantity' => 100, 'set_by' => $this->maker->id]);
    $this->tenant->switchToPublic();
    $permission = Permission::firstOrCreate(['slug' => 'faba_utilization.create'], ['name' => 'Create utilization', 'module' => 'faba_utilization', 'is_active' => true]);
    $edit = Permission::firstOrCreate(['slug' => 'faba_utilization.edit'], ['name' => 'Edit utilization', 'module' => 'faba_utilization', 'is_active' => true]);
    $this->maker->role->permissions()->syncWithoutDetaching([$permission->id, $edit->id]);
    $token = mobileImplementationToken($this->maker);
    $body = ['transaction_date' => now()->toDateString(), 'material_type' => 'fly_ash', 'movement_type' => 'utilization_external',
        'vendor_id' => $vendor->id, 'quantity' => '1.25', 'document_number' => 'MOBILE-1', 'document_date' => now()->toDateString()];
    $first = $this->withToken($token)->withHeader('Accept', 'application/json')->post('/api/v1/faba/utilization', [
        ...$body, 'attachment' => \Illuminate\Http\UploadedFile::fake()->create('first.pdf', 4, 'application/pdf'),
    ])->assertCreated();
    $id = $first->json('data.id');
    $this->withToken($token)->post("/api/v1/faba/utilization/{$id}", [
        ...$body, '_method' => 'PUT', 'document_number' => 'MOBILE-2',
        'attachment' => \Illuminate\Http\UploadedFile::fake()->create('second.pdf', 4, 'application/pdf'),
    ])->assertSuccessful()->assertJsonPath('data.document_number', 'MOBILE-2');
    $this->withToken($token)->get("/api/v1/faba/utilization/{$id}/attachment")->assertSuccessful();
});

test('password change rolls back if token revocation fails', function () {
    $token = mobileImplementationToken($this->maker);
    \Illuminate\Support\Facades\DB::unprepared("CREATE FUNCTION public.mobile_revocation_failure() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'synthetic token revocation failure'; END \$\$; CREATE TRIGGER mobile_revocation_failure BEFORE DELETE ON public.api_tokens FOR EACH ROW EXECUTE FUNCTION public.mobile_revocation_failure()");
    $this->withToken($token)->putJson('/api/v1/profile/password', [
        'current_password' => 'existing-password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
    ])->assertServerError();
    expect(Hash::check('existing-password', $this->maker->fresh()->password))->toBeTrue();
    expect(ApiToken::where('user_id', $this->maker->id)->count())->toBe(1);
});
