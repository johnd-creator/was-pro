# WASPRO Mobile API additions

Base URL production is https://waspro.ubpbsla.com/api/v1/. This document describes the implementation supplement to the BE03 inventory. It does not establish deployment of these changes on production.

## Authentication and organization context

All endpoints below require Bearer authentication and the existing tenant.schema middleware. Authentication supplies the organization. Clients cannot select another organization's schema through a request parameter. The login endpoint is limited to six attempts per minute per client IP. Password updates validate the current password against the Bearer user and revoke all of that user's API tokens on success.

Existing envelopes and error behavior are retained. Clients accept meta/errors as an object or empty array and handle non-JSON gateway errors. Timestamp history without an offset retains its existing semantics. Operational calendar dates remain YYYY-MM-DD.

## Additional routes

| Method | Path | Permission / payload |
| --- | --- | --- |
| GET | /master-data/{resource} | Corresponding .view permission, page/per_page |
| GET | /master-data/{resource}/{id} | Corresponding .view permission |
| POST | /master-data/{resource} | Corresponding .create permission and existing master Form Request |
| PUT | /master-data/{resource}/{id} | Corresponding .edit permission and existing master Form Request |
| GET | /users | users.view, only users in the authenticated organization |
| GET | /users/{id} | users.view, organization-scoped detail |
| GET | /roles | roles.view, id/name/slug |
| GET | /roles/{id} | roles.view, role and permission descriptions |
| GET | /faba/reports/{report}.{format} | faba_reports.export, existing official report filters |
| GET | /faba/utilization/{id}/attachment | faba_utilization.view, tenant-scoped utilization lookup |
| POST | /faba/utilization/{id} | Multipart update, send _method=PUT |
| GET | /waste-records/export/csv | Waste record viewing permission and existing ownership filtering |
| GET | /faba/production/export/csv | faba_production.view |
| GET | /faba/utilization/export/csv | faba_utilization.view |

Master resources are categories, characteristics, types and vendors. Permissions are waste_categories, waste_characteristics, waste_types and vendors with the appropriate operation suffix.

Master list data is an array with meta.pagination containing current_page, last_page, total, per_page. Detail/mutation data is the serialized model plus allowed_actions and resource_version. Existing validation traits define the fields and limits. Update route parameters are bound models so unique-code validation can ignore the current row.

Reports support monthly, yearly, vendors, internal-destinations, purposes, stock-card, anomalies and analysis-matrix. Format is pdf or xlsx. Responses are binary files with Content-Type and download headers. Filters are year, month, material_type, movement_type, vendor_id, internal_destination_id and purpose_id, interpreted by the existing report service. CSV uses the existing web export handlers.

Attachment responses are private/no-store downloads from the existing storage implementation. The endpoint requires API authentication and does not turn a stored attachment_path into an unprotected download URL. Existing storage disk deployment and web attachment behavior are retained.

## Approval and write contracts

- Monthly submission checks faba_approvals.submit. Reject/reopen check their respective permission.
- The existing transactional model guard enforces separation for both web and API. An actor cannot approve a transaction they created/submitted. Monthly separation checks the period submitter.
- Transaction details expose approve/reject only when allowed by permission, status and period lock.
- Atomic operational transactions and OperationalIdempotency are reused for mobile operations and master writes.
- Send Idempotency-Key with create/update/workflow requests. Preserve the exact payload and attachment during retries after an uncertain result. A changed request with the same key receives a conflict.
- Details expose resource_version. Send it back as _source_version with updates. The version is checked inside the tenant transaction, after idempotent replay is considered. A mismatch returns 409.
- Resource versions use canonical, sorted raw attributes. Clients treat these values as opaque strings.
- Backlog hauling and pending FABA movements support opt-in paginate=true, page and per_page. Without this flag the original array contract is retained. Backlog remaining-quantity filtering happens before pagination.

## Verification and deployment

MobileImplementationTest covers separation of daily/monthly approval, submit without approve, master validation, directory isolation, password revocation, attachment isolation, retry and stale writes. Existing API suites cover the base endpoints. Run only against a separately provisioned disposable PostgreSQL database as specified in TESTING.md.

Before Android organization UAT, deploy the API changes to staging, apply existing required tenant migrations (including operational_requests), verify mail/email changes and storage configuration, and provision an operator plus two independent supervisors. Production approval rules affect web and mobile together and require communicating the changed workflow.

The original OpenAPI remains the BE03 reference inventory. This supplement documents the added endpoints and workflow changes. Formal contract freeze and production UAT require a deployed candidate and representative accounts.
