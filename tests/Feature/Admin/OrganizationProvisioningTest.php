<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Ramsey\Uuid\Uuid;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_super_admin' => true, 'organization_id' => null]);
    app(TenantService::class)->switchToPublic();
});

test('organization creation provisions all tenant tables and its own migration history', function () {
    $publicMigrations = DB::table('public.migrations')->pluck('migration')->all();

    $this->actingAs($this->admin)->post(route('admin.organizations.store'), ['name' => 'Provisioned Unit', 'code' => 'BE02COMPLETE'])
        ->assertRedirect(route('admin.organizations.index'))
        ->assertSessionHas('success');

    $organization = Organization::where('code', 'BE02COMPLETE')->firstOrFail();
    $tables = app(TenantService::class)->getSchemaTables($organization->schema_name);
    expect($tables)->toContain('migrations', 'waste_categories', 'waste_types', 'waste_records', 'waste_haulings', 'faba_movements', 'faba_monthly_approvals', 'faba_monthly_closing_snapshots', 'faba_tps_capacities');
    expect(DB::table($organization->schema_name.'.migrations')->count())->toBe(count(glob(database_path('migrations/tenant/*.php'))));
    expect(DB::table('public.migrations')->pluck('migration')->all())->toBe($publicMigrations);
    expect(DB::selectOne('SHOW search_path')->search_path)->toBe('public');
});

test('organization provisioning rolls back metadata and schema when migration fails', function () {
    $createdSchema = null;
    $this->partialMock(TenantService::class, function (MockInterface $mock) use (&$createdSchema) {
        $mock->shouldReceive('runMigrationsForTenant')->once()->andReturnUsing(function (string $schema) use (&$createdSchema): void {
            $createdSchema = $schema;
            Schema::create($schema.'.partial_marker', fn ($table) => $table->integer('value'));
            throw new RuntimeException('Internal migration failure detail');
        });
    });

    $this->actingAs($this->admin)->from(route('admin.organizations.index'))
        ->post(route('admin.organizations.store'), ['name' => 'Failed Unit', 'code' => 'BE02ROLLBACK'])
        ->assertRedirect(route('admin.organizations.index'))
        ->assertSessionHas('error', fn (string $message): bool => ! str_contains($message, 'Internal migration failure detail'))
        ->assertSessionMissing('success');

    expect($createdSchema)->not->toBeNull();
    expect(Organization::where('code', 'BE02ROLLBACK')->exists())->toBeFalse();
    expect(app(TenantService::class)->schemaExists($createdSchema))->toBeFalse();
    expect(DB::selectOne('SHOW search_path')->search_path)->toBe('public');
});

test('organization provisioning does not claim success when schema creation fails', function () {
    $this->partialMock(TenantService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createSchema')->once()->andReturn(false);
        $mock->shouldNotReceive('runMigrationsForTenant');
    });

    $this->actingAs($this->admin)->from(route('admin.organizations.index'))
        ->post(route('admin.organizations.store'), ['name' => 'Failed Schema', 'code' => 'BE02SCHEMAFAIL'])
        ->assertSessionHas('error')->assertSessionMissing('success');

    expect(Organization::where('code', 'BE02SCHEMAFAIL')->exists())->toBeFalse();
});

test('non super admins cannot provision organizations', function () {
    $user = User::factory()->create(['is_super_admin' => false, 'organization_id' => null]);
    $this->actingAs($user)->post(route('admin.organizations.store'), ['name' => 'Forbidden', 'code' => 'BE02DENIED'])->assertForbidden();
    expect(Organization::where('code', 'BE02DENIED')->exists())->toBeFalse();
});

test('invalid organization input does not start provisioning', function () {
    $this->partialMock(TenantService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('createSchema');
        $mock->shouldNotReceive('runMigrationsForTenant');
    });
    $this->actingAs($this->admin)->post(route('admin.organizations.store'), ['name' => '', 'code' => ''])
        ->assertSessionHasErrors(['name', 'code']);
});

test('tenant migration failures roll back the batch and restore the actual caller search path', function (string $fixturePath, string $exceptionClass) {
    $tenants = app(TenantService::class);
    $suffix = Str::lower(Str::random(8));
    $source = 'tenant_source_'.$suffix;
    $target = 'tenant_target_'.$suffix;
    $tenants->createSchema($source);
    $tenants->createSchema($target);
    Schema::create($target.'.preserved', fn ($table) => $table->string('value'));
    DB::table($target.'.preserved')->insert(['value' => 'keep-existing-data']);
    $tenants->switchToSchema($source);
    $beforePath = DB::selectOne('SHOW search_path')->search_path;
    $beforeConfig = config('database.connections.pgsql');
    $beforeLevel = DB::transactionLevel();

    // A different service instance must preserve the real connection state too.
    expect(fn () => (new TenantService)->runMigrationsForTenant($target, $fixturePath))
        ->toThrow($exceptionClass);

    expect(DB::selectOne('SHOW search_path')->search_path)->toBe($beforePath);
    expect(config('database.connections.pgsql'))->toBe($beforeConfig);
    expect(DB::transactionLevel())->toBe($beforeLevel);
    expect(Schema::hasTable($target.'.provisioning_probe'))->toBeFalse();
    expect(Schema::hasTable($target.'.migrations'))->toBeFalse();
    expect(DB::table($target.'.preserved')->value('value'))->toBe('keep-existing-data');
})->with([
    'application exception' => ['tests/Fixtures/tenant-migration-failure', RuntimeException::class],
    'aborted PostgreSQL transaction' => ['tests/Fixtures/tenant-sql-failure', \Illuminate\Database\QueryException::class],
]);

test('nonzero migration exit is reported as a failure', function () {
    $tenants = app(TenantService::class);
    $schema = 'tenant_exit_'.Str::lower(Str::random(8));
    $tenants->createSchema($schema);
    Artisan::shouldReceive('call')->once()->with('migrate', Mockery::type('array'))->andReturn(1);

    expect(fn () => $tenants->runMigrationsForTenant($schema, 'database/migrations/tenant'))->toThrow(RuntimeException::class);
    expect(DB::selectOne('SHOW search_path')->search_path)->toBe('public');
});

test('migration target must already exist and cannot fall back to public', function () {
    $before = DB::table('public.migrations')->pluck('migration')->all();
    expect(fn () => app(TenantService::class)->runMigrationsForTenant('tenant_missing_'.Str::lower(Str::random(8)), 'database/migrations/tenant'))
        ->toThrow(RuntimeException::class);
    expect(DB::table('public.migrations')->pluck('migration')->all())->toBe($before);
    expect(Schema::hasTable('public.waste_records'))->toBeFalse();
});

test('provisioning refuses an existing schema without changing its data', function () {
    $uuid = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    $schema = 'org_'.str_replace('-', '', $uuid);
    $existing = Organization::factory()->create(['schema_name' => $schema]);
    $tenants = app(TenantService::class);
    $tenants->createSchema($schema);
    Schema::create($schema.'.preserved', fn ($table) => $table->string('value'));
    DB::table($schema.'.preserved')->insert(['value' => 'existing-tenant']);
    Str::createUuidsUsing(fn () => Uuid::fromString($uuid));

    try {
        expect(fn () => $tenants->provisionOrganization(['name' => 'Collision', 'code' => 'BE02COLLISION']))
            ->toThrow(RuntimeException::class, 'Refusing to provision an existing tenant schema.');
    } finally {
        Str::createUuidsNormally();
    }

    expect(Organization::find($existing->id)?->schema_name)->toBe($schema);
    expect(Organization::where('code', 'BE02COLLISION')->exists())->toBeFalse();
    expect(DB::table($schema.'.preserved')->value('value'))->toBe('existing-tenant');
});

test('successful tenant migration preserves existing records and the caller context', function () {
    $tenants = app(TenantService::class);
    $suffix = Str::lower(Str::random(8));
    $target = 'tenant_preserved_'.$suffix;
    $source = 'tenant_caller_'.$suffix;
    $tenants->createSchema($target);
    $tenants->runMigrationsForTenant($target, 'database/migrations/tenant');
    $tenants->switchToSchema($target);
    $record = \App\Models\WasteRecord::factory()->create(['quantity' => 31.25, 'created_by' => $this->admin->id]);
    $tenants->createSchema($source);
    $tenants->switchToSchema($source);
    $path = DB::selectOne('SHOW search_path')->search_path;
    $publicMigrations = DB::table('public.migrations')->pluck('migration')->all();

    (new TenantService)->runMigrationsForTenant($target, 'database/migrations/tenant');

    expect(DB::selectOne('SHOW search_path')->search_path)->toBe($path);
    expect(DB::table($target.'.waste_records')->count())->toBe(1);
    expect((float) DB::table($target.'.waste_records')->where('id', $record->id)->value('quantity'))->toBe(31.25);
    expect(DB::table('public.migrations')->pluck('migration')->all())->toBe($publicMigrations);
});

test('organization provisioning preserves the existing caller tenant and its data', function () {
    $tenants = app(TenantService::class);
    $existing = Organization::factory()->create(['schema_name' => 'tenant_admin_'.Str::lower(Str::random(8))]);
    $tenants->createSchema($existing->schema_name);
    $tenants->runMigrationsForTenant($existing->schema_name, 'database/migrations/tenant');
    $this->admin->update(['organization_id' => $existing->id]);
    $tenants->switchToSchema($existing->schema_name);
    $record = \App\Models\WasteRecord::factory()->create(['quantity' => 11.5, 'created_by' => $this->admin->id]);
    $expectedPath = DB::selectOne('SHOW search_path')->search_path;
    $tenants->switchToPublic();

    $this->actingAs($this->admin)->post(route('admin.organizations.store'), ['name' => 'Another Unit', 'code' => 'BE02FROMTENANT'])
        ->assertSessionHas('success')->assertSessionMissing('error');

    expect(DB::selectOne('SHOW search_path')->search_path)->toBe($expectedPath);
    expect(DB::table($existing->schema_name.'.waste_records')->count())->toBe(1);
    expect((float) DB::table($existing->schema_name.'.waste_records')->where('id', $record->id)->value('quantity'))->toBe(11.5);
    $newSchema = DB::table('public.organizations')->where('code', 'BE02FROMTENANT')->value('schema_name');
    expect($newSchema)->not->toBe($existing->schema_name);
    expect(Schema::hasTable($newSchema.'.faba_movements'))->toBeTrue();
});

test('provisioning cannot reuse a schema that appears after the initial existence check', function () {
    $uuid = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
    $schema = 'org_'.str_replace('-', '', $uuid);
    app(TenantService::class)->createSchema($schema);
    Schema::create($schema.'.preserved', fn ($table) => $table->string('value'));
    DB::table($schema.'.preserved')->insert(['value' => 'do-not-adopt-this-schema']);
    $this->partialMock(TenantService::class, function (MockInterface $mock) {
        $mock->shouldReceive('schemaExists')->once()->andReturn(false);
    });
    Str::createUuidsUsing(fn () => Uuid::fromString($uuid));

    try {
        expect(fn () => app(TenantService::class)->provisionOrganization(['name' => 'Race', 'code' => 'BE02RACE']))
            ->toThrow(RuntimeException::class, 'Tenant schema creation failed.');
    } finally {
        Str::createUuidsNormally();
    }

    expect(Organization::where('code', 'BE02RACE')->exists())->toBeFalse();
    expect(DB::table($schema.'.preserved')->value('value'))->toBe('do-not-adopt-this-schema');
});
