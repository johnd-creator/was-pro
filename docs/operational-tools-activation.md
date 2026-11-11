# FABA master tools, vendor expiry information, and B3 audit viewer

These source changes do not migrate live tenants, register live permissions, assign roles, deploy assets, restart services, or change application configuration.

## Explicit activation still required

1. Review and deploy the tested source and a separately built matching asset bundle in an authorized deployment. The existing active public/build was not overwritten during development. Existing vendor payloads and transportations_count alias remain compatible; new pages are not available in the old asset bundle.
2. Assess each tenant schema before an authorized maintenance migration. Existing master tables are reused; there is no new phase-10 tenant migration. The audit viewer needs the existing operational_audit_logs table from 2026_10_24_000001_create_operational_requests_table.php. A tenant-wide migration also applies ALL other pending migrations, including earlier repair phases. Never assume it applies only this feature. Missing audit storage returns 503; requests do not provision it.
3. In an explicitly authorized maintenance session, register the four permissions using OperationalToolsPermissionsSeeder. This dedicated seeder only registers permissions and does not attach any role. It is not called by normal DatabaseSeeder.
4. Selectively assign faba_master.view, faba_master.create, faba_master.edit, and/or operational_audit.view to approved roles through existing administration. No operator, supervisor, admin, or role named super_admin receives these new grants automatically from RolePermissionsSeeder. Explicit existing grants are preserved. The existing user is_super_admin bypass is unchanged.
5. Audit access also requires waste_records.view_own/view_all or waste_hauling.view_own/view_all. It does not grant resource visibility on its own. Cross-tenant records and unknown/deleted records are excluded before counts and pagination. Soft-deleted records remain excluded to avoid expanding existing view permissions. Deleted-record forensic access would need a separately designed permission and workflow.

## Behavior and limitations

- FABA destination/purpose codes are permanent after creation because recap segments use slugs. Names, descriptions and active status can change. No delete endpoint is introduced, preserving referenced history.
- Only active masters can be newly selected. Editing retains the current inactive selection without allowing a different inactive selection. The edit UI includes current inactive references.
- Vendor expiry information compares calendar dates against the selected transaction/hauling day, inclusive of the expiry day. The master list uses the server's current calendar date. Unknown expiry is not reported as valid. No warning horizon or transaction blocker is added; wording describes recorded expiry, not verified legal status.
- Existing vendor-create date validation remains unchanged. Editing can preserve an unchanged historical expiry while changing contact details or active status. Changing the expiry retains the existing update validation.
- Existing audit entries are not rewritten. Newly recorded changes add source, description, notes, expiry and decision fields. Old entries may lack these fields. The viewer uses an explicit field allowlist and never exposes attachment_path or arbitrary nested fields, even from legacy records.
- Audit listing is read-only, 25 events per page, filtered by module/action/reference/actor/date. It exposes only events for currently visible source records.
- Development verification runs solely against the isolated disposable PostgreSQL cluster and isolated storage/cache. No live role, tenant, migration, asset, or configuration changes have been applied.
