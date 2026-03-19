# Architecture Document: Waspro System

## 1. System Overview
Waspro generasi baru dirancang untuk menskalakan model monolithic menjadi sistem **API-Centric / Hybrid Architecture**.
Arsitektur dibagi secara logis menangani dua jenis sisi klien (Client Apps):
1. **Web Admin Dashboard** yang digunakan oleh administrator dan back-office (dibuild di sisi web framework untuk produktivitas).
2. **Flutter Mobile Application** yang digunakan di lapangan (digawangi oleh sistem RESTful Services).

Sistem backend menggunakan standar minimal **Laravel 12** yang berkomunikasi pada RDBMS **PostgreSQL**.

## 2. Definisi Tech Stack
| Tier / Komponen | Teknologi |
| --- | --- |
| **Mobile Client** | Flutter (Dart), BLoC / Riverpod State Management |
| **Web Frontend** | Laravel Blade / Inertia.js (Vue/React) + TailwindCSS |
| **Backend & API** | Laravel 12 (PHP 8.2+) |
| **Database Relasional** | PostgreSQL 15/16 |
| **Cache & Queue System** | Redis |
| **Authentication** | Laravel Sanctum (Stateful Cookie untuk Web, Bearer Tokens untuk Apps) |

## 3. Komponen Arsitektur Utama

### 3.1. Layer Presentasi Klien (Client Layer)
- **Web App**: Diproses oleh Laravel Routes secara session-oriented. Memberikan akses cepat untuk fitur report generation (Excel export yang membebani komputasi dapat didownload dengan mudah).
- **Mobile App**: Mengkonsumsi format JSON menggunakan library networking (HTTP/Dio) di Flutter. App meng-cache data read-only ke SQLite device lokal jika offline-first dipertimbangkan.

### 3.2. Laravel API Gateway & Authorization Layer
- Menerima traffic masuk API di route terproteksi middleware (`auth:sanctum`).
- Menggunakan `FormRequests` untuk validasi parameter JSON.
- Middleware Scope & Policy (`Gate`, `Policy`, dan `unit.access` yang dimiliki aplikasi lama) dipertahankan dan diadaptasi memvalidasi token Flutter yang mengandung data pengguna Unit Pembangkit spesifik.

### 3.3. Layer Logika Aplikasi (Service Layer Pattern)
- Agar business logic tidak repetitif antar Web Controllers dan API Controllers, dibangun class `Services` (Misal: `LimbahApprovalService`, `ExpiryCalculationService`).
- Controller baik Web maupun API hanya meneruskan input Request kepada `Service`, mengumpulkan return data, dan membentuk formatting tampilan sesuai preferensi (Response web / Response JSON).

### 3.4. Layer Basis Data & Penyimpanan (Data Layer)
- Dipergunakan Eloquent ORM. Migrasi skema database yang ketat dirancang khusus untuk kompatibilitas PostgreSQL (Pemanfaatan data type UUID primer bila perlu, indexing yang tepat pada status dan tanggal).
- File pendukung (`upload-settings` asset / dokumentasi manifest) disimpan secara virtual melalui *Laravel Storage System* (Local / S3).

## 4. Infrastruktur & Proses Latar Belakang (Background Jobs)
Beberapa pekerjaan sistem *terputus* secara asinkron (dilayani antrian / queue daemon `Redis`):
- Pengecekan otomatis *Near Expiry* pada scheduler (Laravel Task Scheduling).
- Sinkronisasi Bulk Approval atau pengiriman email report.
- Audit Logging untuk event listener agar tidak menghambat response API terhadap antarmuka user.

## 5. Diagram Topologi Logikal Sistem

```mermaid
flowchart TD
    %% Clients
    Flutter[Flutter Mobile Client\n Operator / Approver]
    Web[Web Dashboard UI\n Admin / Super Admin]

    %% Gateway
    API_Router[Routes API: /api/v1/]
    Web_Router[Routes Web: /web/]

    Flutter -->|HTTP/REST w/ Bearer Token| API_Router
    Web -->|HTTP w/ Session| Web_Router

    %% App Logic
    subgraph Laravel 12 Application Core
        API_Router --> API_Controller[API Controllers]
        Web_Router --> Web_Controller[Web Controllers]

        API_Controller --> AuthMiddle[Sanctum & Permissions Middleware]
        Web_Controller --> AuthMiddle

        AuthMiddle --> SerLayer[Business Services\n(e.g., Approval, Expiry, Report)]
    end

    %% Storage & Background
    SerLayer --> ORM[Eloquent Models]
    ORM -->|CRUD via PDO| Postgres[(PostgreSQL 15+)]
    SerLayer -.->|Push notification job| Redis[(Redis Queue)]
    Redis --> Worker(Laravel Queue Worker\nPush Firebase & Email)
```
