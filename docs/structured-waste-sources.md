# Structured waste sources

Waste sources are separate from waste type categories. The tenant migration
`2026_11_13_160000_add_structured_waste_sources.php` adds `waste_sources` and a
nullable `waste_records.source_id`. It does not seed sources or modify existing
records, raw `source`, category, quantities, dates or approval state.

## Verified status (13 November 2026)

The additive migration is applied in all three inspected tenants: TWMS,
TWMSDEMO, and UBPBSLA. Read-only checks confirmed its migration record, the
`waste_sources` table, and `waste_records.source_id`. Do not reset the database or
rerun an already recorded migration. No source-data import was performed by this
verification task; schema availability does not imply that source choices have
been populated or historical records classified.

## Rollout for a new environment or tenant

Back up the destination database and verify the backup before applying the
additive migration through the normal tenant migration workflow. Check migration
status first, then verify the table and nullable column in each intended tenant.
Use isolated test data to check permissions, tenant isolation, new selections,
and retention of previously selected inactive sources. Verify the frontend build
and deployment separately. Do not seed, infer spreadsheet mappings, reset data,
or rewrite historical source/category values as part of migration.

For environments not yet migrated, record pages continue to work with no
available source options and source writes are excluded from validation. The
source master shows a not-activated message. This is a compatibility fallback,
not the current status of the inspected environment above.

## Permissions

The source master intentionally reuses `waste_categories.view`,
`waste_categories.create`, and `waste_categories.edit`. Existing category master
administrators gain the corresponding source management action; no user/role is
granted additional permission. Record operators may select existing active
sources but cannot manage the master unless already authorized by those master
permissions. Tenant schema context is enforced by the application middleware.

Sources may be deactivated, not deleted through the UI. A previously selected
inactive source can be retained while editing a record, but cannot be newly
assigned. Historical records without source_id show `Belum tercatat` in the
record UI. Reports can fall back to historical free-text source, without copying
or changing it. No spreadsheet inference or import is included.

## Verification

Focused regression suite: tests/Feature/WasteManagement/StructuredWasteSourcesTest.php.
Use the disposable test database only and coordinate with other test workers.
