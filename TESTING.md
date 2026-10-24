# Isolated test baseline

Feature tests create and remove tenant schemas. Run them only against a disposable PostgreSQL instance, separate from the running application's database and storage. Never point this suite at the development or production database, including an existing database named `*_test` that contains work you need to keep.

## Database safety

Before any Laravel migration trait runs, `Tests\TestCase::createApplication()` verifies:

- `APP_ENV=testing` and `WASPRO_ALLOW_DESTRUCTIVE_TESTS=true` (an actual Boolean after Laravel environment parsing).
- An explicit PostgreSQL database name ending in `_test`, without a `DB_URL` override.
- The connected server returns the expected database name and the database comment `waspro-disposable-tests`.

The comment is an opt-in marker, not a substitute for isolation. Before creating it, verify the server address, port, data directory, ownership, and that the database was created for this run. Do not mark an existing application database just to make the guard pass.

The CI workflow provisions its own PostgreSQL service and marks only that disposable service database. Local runs should use their own cluster/container on a separate loopback port, with dedicated credentials and a verified data directory. No existing server permissions need changing.

Set `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` explicitly in the test process environment. Set `APP_KEY` to a separate test key, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `MAIL_MAILER=array`, and `QUEUE_CONNECTION=sync`. Do not copy the live `.env`. Do not share `storage/` or generated assets with the live checkout.

## Checks

Use the committed Composer/npm lock files. PHP 8.4 is the tested CI runtime: the currently locked PhpSpreadsheet 1.x does not support PHP 8.5. Enabling 8.5 requires a separately reviewed dependency upgrade.

After preparing the isolated environment and existing dependencies:

```sh
npm run build
npm run types:check
php vendor/bin/pest --compact
composer lint:check
npm run lint:check
npm run format:check
```

The build generates Wayfinder helpers before TypeScript validation. The FABA lookup endpoint keeps the URL `/api/v1/faba/options`, but its internal route name is `api.v1.faba.lookups` to avoid collision between Wayfinder's generated function and its `options` parameter.

ESLint explicitly treats the `@/` alias as internal, so import ordering is consistent both on a clean checkout without generated helpers and after a build.

The pure tests in `tests/Unit` do not boot Laravel or use the database. Database guard regression tests cover missing/invalid opt-in, environment mismatch, connection URL overrides, unmarked databases, and a mismatched actual database.

Run tests serially when sharing a workstation with other active projects. Stop only the disposable PostgreSQL instance after verification; do not stop existing application services.

`phpunit.xml` gives the test process a 512 MiB PHP memory limit for Excel/PDF export checks. This does not change the PHP configuration of a running application.

## Organization provisioning and dashboard regressions

New organizations are provisioned explicitly by a super admin. Organization metadata, fresh UUID schema creation, and the tenant migration batch share one PostgreSQL transaction. Existing schema names are never adopted as a new organization's storage; a failed schema creation or migration does not return a success response. Migration command failures propagate, and both successful and failed operations restore the caller's actual write-connection search path and configuration.

Normal web/API requests do not provision missing schemas. Requests with an assigned organization return HTTP 503 if that organization's record or schema is missing; the dashboard also returns this response when no organization is assigned. Authentication and permissions are unchanged. Existing empty or outdated tenant schemas are not automatically migrated or repaired by a request. They require a separately planned, explicit maintenance operation after assessing their data.

Focused coverage is in `tests/Feature/Api/DashboardTest.php` and `tests/Feature/Admin/OrganizationProvisioningTest.php`. It includes unauthenticated requests, organization isolation, missing schemas, complete provisioning, preservation of existing data/public migration history, schema collisions, failed schema creation, nonzero migration exits, and rollback after both PHP and PostgreSQL SQL errors. Test-only failure migrations live in `tests/Fixtures`; they must only run through the guarded disposable test suite.

The batch transaction requires transaction-compatible PostgreSQL migrations. A future migration that requires execution outside a transaction needs a separately reviewed migration strategy. No existing tenant data, database grants, credentials, or running application settings are changed by this package's local verification.

## Transaction integrity baseline (WAS-BE-03)

FABA and waste mutation controllers share an operational transaction. Middleware priority is authentication, tenant selection, operational lock, then route binding. The lock is a PostgreSQL transaction advisory lock derived from the actual database and schema, serializing all operational writes for one tenant. This intentionally coarse baseline includes different periods because backdated movements and opening balances affect later balances. Separate tenants use separate lock identities. Locks release on commit, rollback or disconnect; no persistent grants or system settings are required.

Controller status/stock checks, writes, audits and closing snapshots run under that boundary. Rendered HTTP failures and web error redirects roll back too. Waste transition methods reload scoped rows inside the same lock; soft-deleted stale rows cannot be approved, while explicit restoration is supported. New attachment paths are tracked and removed on rollback; old attachments are retained. Direct query-builder writes, raw SQL, maintenance scripts and historical imports are outside the HTTP command boundary and must explicitly use the coordinator and appropriate domain validation. Do not use these bypasses for application commands.

`FabaLegacyMigrationService` and `FabaDemoDataService` remain explicit migration/bootstrap entry points, not concurrent application commands. Assess them separately and run only during authorized quiescent maintenance or disposable fixture preparation. Genuine unchanged model saves are no-ops even in closed periods; changing an existing FABA row remains guarded. No global validation bypass is introduced for imports or seeders. Restoring a pending/approved hauling is an activation and rechecks capacity, so previously deleted quantities cannot be counted twice. Explicit restore reloads the persisted row inside the lock and discards unsaved stale attributes, as named workflow transitions do. Native explicit force deletion remains supported on soft-deleted models; no HTTP force-delete route is introduced.

Opening balance is a reset per material, including explicit zero, rather than an additional cumulative inflow. The ledger, monthly recap, stock card and newly generated closing snapshots share this rule. Dates have daily accounting precision: approved inflows/outflows on the same day are netted. Backdated commands cannot introduce or worsen a negative daily balance or change a downstream submitted/approved closing balance. Existing negative histories may improve without becoming more negative. Existing historical snapshots are retained as immutable evidence; they are not silently rewritten. New snapshots carry `calculation_version: 2`. If a legacy snapshot differs from canonical balances, an operator must assess and explicitly reopen/reapprove the affected periods through the authorized workflow.

Waste document allocation uses the numeric maximum suffix under the same transaction lock, includes soft-deleted documents, and continues beyond 9999. Existing unique database constraints remain the final safeguard. Supplier document numbers retain duplicate warnings; they are not made globally unique.

API mutation clients may send an optional `Idempotency-Key` containing 1–128 visible ASCII characters without spaces. Keys are scoped to the tenant schema, actor, HTTP method and concrete path. Identical requests replay the successful JSON status/body with `Idempotency-Replayed: true`; different payloads return 409. Uploaded-file fingerprints include original filename and file bytes. Changed user privilege, role/permission or relevant resource ownership fails closed before replay. Status changes alone do not invalidate a legitimate approval replay. Headerless clients remain supported. Receipts contain operational responses, never login tokens, and have no automatic expiry or cleanup in this package.

The tenant migration `2026_10_24_000001_create_operational_requests_table.php` adds idempotency receipts and waste audit logs. Existing tenants require an explicit, separately planned maintenance migration before running this code. Requests do not provision or migrate schemas. Headerless FABA commands do not require the receipt table; keyed requests fail 503 when it is missing. Waste writes require the audit table and fail atomically if it is missing. No live tenant is migrated by local tests or this package's source changes.

`tests/Feature/WasteManagement/TransactionIntegrityTest.php` uses committed disposable fixtures and separate PHP HTTP-kernel workers. Each worker verifies the disposable database identity before its start barrier. Test-only PostgreSQL delay triggers widen the original check/write window without placing a barrier inside an application lock. Worker timestamps assert request overlap; tests assert final stock, status, resource and audit invariants. Workers are bounded to two and cleaned up after completion. The old base `c2dab21d4fef0ef3ca173f53e64614418fdf3b14` reproduced two successful 70-ton approvals against 100 tons (`[200,200]`, expected `[200,409]`, 1 failed test/3 assertions, 3.83 seconds) before source fixes. The same test passes after the shared lock.

Run this file serially using the same physically isolated cluster checks as the baseline. It covers mixed web/API reservations, competing approvals, closing versus input, idempotent retries, numbering rollover, explicit-zero openings, historical stock, rollback failures and tenant isolation. Do not point worker environment variables at an existing application database or share storage with the running checkout.
