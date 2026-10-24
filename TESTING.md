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

The pure tests in `tests/Unit` do not boot Laravel or use the database. Database guard regression tests cover missing/invalid opt-in, environment mismatch, connection URL overrides, unmarked databases, and a mismatched actual database.

Run tests serially when sharing a workstation with other active projects. Stop only the disposable PostgreSQL instance after verification; do not stop existing application services.

`phpunit.xml` gives the test process a 512 MiB PHP memory limit for Excel/PDF export checks. This does not change the PHP configuration of a running application.
