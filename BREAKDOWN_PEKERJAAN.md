# Breakdown Item Pekerjaan Aplikasi Waste Management System

## 🎯 **SISTEM WASTE MANAGEMENT - FABA OPERATIONS**
*Laravel 12, Inertia.js v2, Vue 3, PostgreSQL Multi-Tenant, Flutter Mobile*

---

## **I. CORE BACKEND INFRASTRUCTURE**

### 1. Multi-Tenancy Architecture
- Setup PostgreSQL schema-based multi-tenancy
- Tenant isolation dengan `SetTenantSchema` middleware
- Tenant-specific migrations (vs public migrations)
- Per-tenant data isolation untuk waste records, FABA, dll

### 2. Authentication & Authorization
- Laravel Fortify headless authentication
- Role-based access control (RBAC) dengan permissions
- Custom permission middleware (`permission`, `role`, `super.admin`)
- 2FA (Two-Factor Authentication) setup
- API token authentication untuk mobile (Bearer token)

### 3. API Layer untuk Mobile (RESTful)
- Versioned API `/api/v1`
- Bearer token authentication dengan `auth.api` middleware
- Tenant-aware API middleware
- Standardized error responses (VALIDATION_ERROR, NOT_FOUND, FORBIDDEN, CONFLICT)
- Pagination & filtering
- Resource transformation (API Resources)

### 4. Background Jobs & Queues
- Redis queue setup
- Async jobs untuk audit logging
- Report generation jobs
- Email notifications queue
- Queue worker monitoring

---

## **II. WASTE MANAGEMENT MODULE**

### 1. Master Data Management
- Waste Categories CRUD
- Waste Types CRUD
- Waste Characteristics CRUD
- Vendors management
- File upload handling

### 2. Waste Records Module
- Waste records CRUD dengan approval workflow
- Status: draft → submitted → approved/rejected
- Permission-based access control (view_own, view_all)
- File attachment support
- Expiry date tracking

### 3. Waste Hauling Module (Pengangkutan Limbah)
- **Catatan**: Transportation telah di-rename menjadi Hauling
- Hauling records management
- Status workflow: pending → dispatched → delivered → cancelled
- Vendor assignment
- Quantity tracking dari waste records (sisa quantity)
- Multiple hauling per waste record
- Approve/reject/cancel actions

### 4. Dashboard & Reports
- Unified dashboard dengan compliance metrics
- Waste production summary
- Hauling status overview (bukan transportation)
- Expiry monitoring
- Risk indicators
- Export Excel/PDF reports

---

## **III. FABA (Fly Ash/Bottom Ash) MODULE**

### 1. FABA Movement Ledger Engine
- Material movement system (Fly Ash, Bottom Ash)
- Movement types: production, utilization_external, utilization_internal, reject, disposal, adjustment
- Stock effect calculation (in/out)
- Period auto-derivation dari transaction_date
- Audit trail untuk semua movements

### 2. Production Entries
- Production recording per material
- Subtype production mapping
- Workshop FABA tracking
- Automatic period grouping
- Stock update otomatis

### 3. Utilization Management
- External utilization dengan vendor selection
- Internal utilization dengan destination selection (FabaInternalDestination)
- Purpose/use-case categorization (FabaPurpose: semen, batako, beton, dll)
- Document tracking
- Stock validation

### 4. Opening Balance & Adjustments
- Opening balance setup per material per period
- Adjustment in/out transactions
- Mandatory reason logging untuk adjustments
- Controlled correction untuk approved periods

### 5. Monthly Closing & Approval
- Monthly period aggregation otomatis
- Closing status workflow: open → submitted → approved/rejected
- Supervisor review & approval
- Period locking setelah approval
- Reopen mechanism dengan authorization
- Snapshot closing (FabaMonthlyClosingSnapshot)

### 6. FABA Recap & Reporting
- Monthly recap (production, utilization, balance)
- Yearly recap dengan trend
- Vendor breakdown reports
- Internal destination breakdown
- Purpose/category summary
- Stock card per material
- TPS balance tracking dengan capacity (FabaTpsCapacity)
- Cumulative accumulation

### 7. FABA Dashboard
- Production vs utilization charts
- Current TPS balance vs capacity
- Pending approvals count
- Monthly trend indicators
- Warning/anomaly detection

---

## **IV. WEB FRONTEND (INERTIA.JS + VUE 3)**

### 1. App Shell & Navigation
- Responsive sidebar dengan green environmental theme
- Permission-based menu filtering
- Dark/light mode support
- AppHeader dengan user info
- Breadcrumb navigation

### 2. UI Component Library
- Reka UI components (shadcn-like patterns)
- Design tokens system
- Status indicators (critical, warning, success, info)
- Form components dengan validation
- Data tables dengan sorting/filtering

### 3. Waste Management Pages
- Waste records list/detail/create/edit
- **Waste Hauling pages** (Index, Show, Create, PendingApproval)
- Master data management pages (Types, Categories, Characteristics, Vendors)
- Approval workflows UI
- File upload interfaces

### 4. FABA Module Pages
- Production entries interface
- Utilization forms (internal/external)
- Adjustment forms
- Monthly closing dashboard
- Approval review UI
- Recap summaries dengan drill-down
- Stock card view
- Balance monitoring dengan TPS capacity

### 5. Dashboard Pages
- Unified waste management dashboard
- FABA operations dashboard
- Charts & visualizations
- Real-time metrics

### 6. Settings & Admin
- User profile management
- Appearance settings (theme)
- Password change
- 2FA management
- Organization management (admin)
- User management (admin)
- Role & permissions (admin)

---

## **V. FLUTTER MOBILE APP**

### 1. Project Setup & Architecture
- Flutter project initialization
- BLoC/Riverpod state management setup
- Dependency injection setup
- API client dengan Dio + Bearer token interceptor
- Secure token storage
- Navigation setup (bottom nav: Dashboard, Waste, FABA, Approvals, Profile)

### 2. Authentication Module
- Login screen dengan form validation
- Token storage & management
- Auto-login dengan stored token
- Logout functionality
- Session expiry handling (401 force relogin)
- API error handling (401, 403, 404)

### 3. App Bootstrap & Context
- User profile loading (`GET /api/v1/auth/me`)
- Organization context
- Role & permissions caching
- Allowed actions caching
- Module access based on permissions

### 4. Mobile Dashboard
- Dashboard summary cards
- Pending approvals count
- Quick stats (waste records, waste haulings, FABA)
- Recent activity feed
- Refresh mechanism

### 5. Waste Records Module
- Waste records list dengan pagination
- Filter & search
- Create record form
- Edit draft/rejected records
- Detail view dengan actions
- Submit approval flow
- Status tracking

### 6. Waste Hauling Module (Pengangkutan)
- **Catatan**: API endpoint `/api/v1/waste-haulings`
- Hauling list dengan status filter
- Create hauling form
- Hauling options API (waste records yang available)
- Dispatch action
- Deliver action
- Cancel action
- Vendor selection
- Waste record selection (approved only dengan sisa quantity)
- History view per waste record

### 7. FABA Production Module
- Production list/detail/create
- Material selection (Fly Ash/Bottom Ash)
- Subtype selection
- Quantity & date input
- Period auto-display
- Edit/delete sesuai permission

### 8. FABA Utilization Module
- Utilization list/detail/create
- External vs internal toggle
- Vendor selection (external)
- Internal destination selection (FabaInternalDestination)
- Purpose/use-case selection (FabaPurpose)
- Document attachment
- Stock validation

### 9. FABA Adjustments Module
- Adjustment list/detail/create
- Adjustment in/out selection
- Reason input (mandatory)
- Material & quantity
- Period handling

### 10. FABA Recap & Dashboard
- Monthly recap summary
- Yearly overview
- Balance view dengan TPS capacity
- Stock card
- Dashboard metrics
- Filter by period

### 11. Approvals Module
- Pending approvals queue (waste records, waste haulings, FABA movements)
- Detail review context
- Approve action
- Reject action dengan reason
- Reopen action (jika allowed)
- Status filtering

### 12. Profile Module
- Profile view
- Edit profile
- Change password
- Logout

### 13. Offline Support & Caching
- SQLite local cache setup
- Read-only data caching (lookup, permissions, dashboard)
- Queue mechanism untuk offline write (opsional)
- Sync conflict resolution

---

## **VI. TESTING & QUALITY ASSURANCE**

### 1. Backend Testing (Pest 4)
- Feature tests untuk semua CRUD operations
- API contract testing (MobileAuthTest, WasteRecordsApiTest, WasteHaulingsApiTest, FabaMovementsApiTest, FabaRecapsAndApprovalsApiTest)
- Authentication & authorization tests
- Multi-tenancy isolation tests
- FABA ledger calculation tests
- Approval workflow tests

### 2. Frontend Testing
- Component unit tests
- Page integration tests
- Form validation tests
- Navigation tests

### 3. Mobile Testing
- Widget tests
- Integration tests
- E2E tests dengan mock API
- Permission-based UI tests

### 4. Manual Testing & QA
- UAT waste management workflows (termasuk hauling)
- UAT FABA closing & approval
- Cross-browser testing
- Mobile device testing (Android priority)
- Performance testing

---

## **VII. DEPLOYMENT & DEVOPS**

### 1. Infrastructure Setup
- PostgreSQL database setup (multi-schema)
- Redis cache & queue setup
- Server configuration
- Environment management
- SSL certificates

### 2. CI/CD Pipeline
- GitHub Actions workflow
- Automated testing (lint, format, types:check, test)
- Automated deployment
- Database migration automation

### 3. Monitoring & Logging
- Laravel Pail untuk log monitoring
- Error tracking (Sentry/Bugsnag)
- Queue monitoring
- Performance monitoring

---

## **VIII. DOCUMENTATION**

### 1. Technical Documentation
- API documentation (endpoint `/api/v1` lengkap)
- Database schema documentation
- Architecture documentation
- Deployment guides

### 2. User Documentation
- User manual web app (bahasa Indonesia)
- User manual mobile app (bahasa Indonesia)
- SOP FABA closing & approval
- Training materials

---

## **SCOPE PENGEMBANGAN FLUTTER MOBILE APP**

### Architecture Approach
- **Online-first**: Semua aksi utama memerlukan koneksi internet
- **Permission-driven UI**: UI membaca permission slugs dan allowed actions dari backend
- **Bearer Token Auth**: Terpisah dari web session auth

### Release Phases

#### Release 1 - Core Waste Management
- **Authentication**: Login, logout, session management
- **Bootstrap**: User profile, organization, role, permissions
- **Dashboard**: Summary cards, pending approvals, quick stats
- **Waste Records**: List, create, edit, submit, approve/reject
- **Waste Haulings**: List, create, dispatch, deliver, cancel
- **Profile**: View, edit, change password, logout

#### Release 2 - FABA Module
- **FABA Production**: List, create, edit, delete
- **FABA Utilization**: List, create, edit, delete (internal/external)
- **FABA Adjustments**: List, create, edit, delete
- **FABA Recaps**: Monthly, yearly, balance, stock card
- **FABA Approvals**: Submit, approve, reject, reopen periods
- **FABA Dashboard**: Metrics, trends, warnings

#### Release 3 - Enhancement & Polish
- **Offline Support**: Local caching, offline queue
- **Notifications**: Push notification support
- **Reporting**: Export interaction di mobile
- **Performance**: Optimization, lazy loading

### API Dependencies
Backend sudah menyediakan API endpoints:
- Auth: `/api/v1/auth/login`, `/api/v1/auth/logout`, `/api/v1/auth/me`
- Bootstrap: `/api/v1/me`, `/api/v1/dashboard`, `/api/v1/lookups`
- Waste Records: `/api/v1/waste-records/*`
- Waste Haulings: `/api/v1/waste-haulings/*`
- FABA Production: `/api/v1/faba/production/*`
- FABA Utilization: `/api/v1/faba/utilization/*`
- FABA Adjustments: `/api/v1/faba/adjustments/*`
- FABA Recaps: `/api/v1/faba/recaps/*`
- FABA Approvals: `/api/v1/faba/approvals/*`
- Profile: `/api/v1/profile/*`

---

## **CATATAN PENTING**

### Fitur yang SUDAH Tersedia (Based on Codebase Analysis)
1. ✅ Multi-tenancy dengan PostgreSQL schema-based
2. ✅ Authentication & authorization system lengkap
3. ✅ API layer untuk mobile (`/api/v1`)
4. ✅ Waste Records module dengan approval workflow
5. ✅ **Waste Hauling module** (pengganti transportation)
6. ✅ FABA Movement Ledger Engine
7. ✅ FABA Production, Utilization, Adjustments
8. ✅ FABA Monthly Closing & Approval
9. ✅ FABA Recaps & Reports
10. ✅ Web frontend dengan Inertia.js + Vue 3
11. ✅ Mobile API endpoints lengkap
12. ✅ Testing infrastructure (Pest 4)

### Complexity Factors
- Multi-tenancy architecture menambah kompleksitas signifikan
- FABA ledger engine adalah modul paling kompleks (stock calculation, period locking)
- Flutter mobile membutuhkan state management yang robust
- **Waste Hauling** menggantikan transportation dengan workflow yang lebih spesifik

### Dependencies
- PostgreSQL 15+ (untuk schema-based multi-tenancy)
- Redis (untuk queue & caching)
- Laravel 12, PHP 8.4+
- Flutter 3.24+, Dart 3.5+
- Node.js 18+ (untuk Vite build)

### Deliverables
- Source code backend (Laravel 12)
- Source code web frontend (Vue 3 + Inertia.js v2)
- Source code mobile app (Flutter)
- Database migrations (public + tenant)
- Test suites (backend, frontend, mobile)
- API documentation
- User manuals (bahasa Indonesia)
- Deployment scripts

### Out of Scope
- Integrasi ERP/SAP eksternal
- Digital signature formal
- Push notification infrastructure (hanya preparation)
- Offline-first architecture penuh (hanya basic caching)
- iOS development (hanya Android fase 1-2)
- Infrastructure hosting cost
- Server maintenance & monitoring cost

---

## **REKOMENDASI PHASING**

### Phase 1 - Foundation
- Core backend + multi-tenancy enhancement
- Waste Management module lengkap (Records + Hauling)
- Web admin dashboard optimization
- API layer hardening
- Testing & deployment infrastructure

### Phase 2 - FABA Enhancement
- FABA module optimization & bug fixes
- Advanced reporting & export
- Performance tuning
- Documentation completion

### Phase 3 - Mobile Development
- Flutter mobile Release 1 (Waste Management)
- Flutter mobile Release 2 (FABA Module)
- Flutter mobile Release 3 (Polish & Enhancement)

### Phase 4 - Handover & Training
- User training
- Admin training
- Technical handover
- Post-launch support

---

## **BREAKDOWN PERIODE PENGEMBANGAN**

```
Phase 1: Foundation & Enhancement (3 bulan)
├── Backend optimization & testing
├── Web frontend polish
├── Waste Management module finalization
└── API hardening

Phase 2: FABA & Reporting (2 bulan)
├── FABA module optimization
├── Advanced reporting
├── Performance tuning
└── Documentation

Phase 3: Flutter Mobile - Release 1 (2 bulan)
├── Auth & Bootstrap
├── Dashboard
├── Waste Records
├── Waste Haulings
└── Profile

Phase 4: Flutter Mobile - Release 2 (2 bulan)
├── FABA Production
├── FABA Utilization
├── FABA Adjustments
├── FABA Recaps
└── FABA Approvals

Phase 5: Polish & Handover (1 bulan)
├── Mobile enhancement
├── Training
├── Documentation finalization
└── Technical handover
```

**Total Timeline: ± 10-12 bulan** (dengan asumsi sistem backend dan web sudah 80% complete)
