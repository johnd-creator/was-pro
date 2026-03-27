# FABA UAT Checklist

## Ringkasan

Dokumen ini dipakai untuk User Acceptance Test (UAT) operasional modul FABA V1 pada tenant `TWMS`.

Tujuan UAT:
- memastikan workflow operasional FABA siap dipakai
- memastikan approval bulanan benar-benar berfungsi sebagai closing
- memastikan laporan resmi `Excel` dan `PDF` konsisten dengan data aplikasi
- memastikan tidak ada blocker severity tinggi sebelum FABA dinyatakan `V1 clear`

Role yang diuji:
- `Operator` untuk input, edit, delete, adjustment, submit closing
- `Supervisor` untuk review, approve, reject, reopen, dan export

---

## Setup

### Informasi UAT
- URL aplikasi: `http://localhost:8000`
- Tenant: `TWMS`
- Schema tenant: `tenant_twms`
- Tanggal eksekusi: `________________`
- Tester: `________________`
- Status akhir: `PASSED / FAILED / PARTIAL / BLOCKED`

### Akun UAT
- Supervisor: `faba.supervisor.demo@local.test / password`
- Operator: `faba.operator.demo@local.test / password`

### Baseline Sebelum UAT
- [ ] `php artisan faba:seed-demo --tenant=TWMS --schema=tenant_twms --no-interaction`
- [ ] `php artisan test --compact tests/Feature/WasteManagement/FabaModuleTest.php`
- [ ] `npm run types:check`
- [ ] `npm run build`

### Aturan Eksekusi
- Gunakan data tenant `TWMS` hasil seed terbaru.
- Gunakan periode aktif yang memiliki data.
- Catat semua mismatch angka UI vs export.
- CSV tidak diuji karena sudah dipensiunkan dari report layer resmi.

---

## Checklist Skenario

Gunakan format berikut untuk setiap eksekusi:

| ID | Role | Scenario | Expected | Actual | Status | Evidence |
|---|---|---|---|---|---|---|
| UAT-XX | Operator / Supervisor | Ringkasan skenario | Hasil yang diharapkan | Hasil aktual | Pass / Fail / Blocked | Screenshot / file / catatan |

### 1. Smoke Check

#### Operator
- [ ] UAT-01: Buka dashboard utama tanpa error
- [ ] UAT-02: Buka dashboard FABA tanpa error
- [ ] UAT-03: Buka halaman produksi tanpa error
- [ ] UAT-04: Buka halaman pemanfaatan internal tanpa error
- [ ] UAT-05: Buka halaman pemanfaatan eksternal tanpa error
- [ ] UAT-06: Buka halaman adjustment tanpa error
- [ ] UAT-07: Buka recap bulanan tanpa error
- [ ] UAT-08: Buka recap tahunan tanpa error
- [ ] UAT-09: Buka stock card tanpa error
- [ ] UAT-10: Buka approvals index/history/review tanpa error
- [ ] UAT-11: Buka halaman reports tanpa error

#### Supervisor
- [ ] UAT-12: Buka dashboard utama tanpa error
- [ ] UAT-13: Buka dashboard FABA tanpa error
- [ ] UAT-14: Buka approval index/history/review tanpa error
- [ ] UAT-15: Buka recap dan reports tanpa error

Expected umum:
- semua halaman render normal
- tidak ada error 500
- tidak ada toast error
- data seed tampil dan tidak kosong

### 2. Workflow Operator

#### Produksi
- [ ] UAT-16: Buat 1 transaksi produksi `fly_ash`
- [ ] UAT-17: Buat 1 transaksi produksi `bottom_ash`
- [ ] UAT-18: Edit salah satu transaksi produksi
- [ ] UAT-19: Hapus salah satu transaksi produksi yang masih boleh dihapus

#### Pemanfaatan Internal
- [ ] UAT-20: Buat 1 transaksi pemanfaatan internal dengan `internal_destination`
- [ ] UAT-21: Edit transaksi pemanfaatan internal
- [ ] UAT-22: Pastikan saldo material berkurang sesuai quantity

#### Pemanfaatan Eksternal
- [ ] UAT-23: Buat 1 transaksi pemanfaatan eksternal dengan `vendor`
- [ ] UAT-24: Isi `document_number` dan `document_date`
- [ ] UAT-25: Edit transaksi pemanfaatan eksternal
- [ ] UAT-26: Pastikan validasi dokumen aktif jika field wajib dikosongkan

#### Adjustment
- [ ] UAT-27: Buat `adjustment_in`
- [ ] UAT-28: Buat `adjustment_out`
- [ ] UAT-29: Pastikan `adjustment_out` gagal jika stok tidak cukup

Expected umum:
- create/edit/delete berhasil untuk periode terbuka
- display number, movement type, material, quantity, dan reference tampil konsisten
- perubahan stok tercermin di recap dan stock card

### 3. Traceability dan Rekap

- [ ] UAT-30: Buka recap bulanan untuk periode aktif
- [ ] UAT-31: Verifikasi total produksi
- [ ] UAT-32: Verifikasi total pemanfaatan
- [ ] UAT-33: Verifikasi saldo akhir
- [ ] UAT-34: Verifikasi warning/anomaly bila ada
- [ ] UAT-35: Buka recap tahunan dan pastikan bulan aktif masuk agregasi
- [ ] UAT-36: Buka stock card dan pastikan transaksi baru muncul
- [ ] UAT-37: Verifikasi urutan tanggal stock card benar
- [ ] UAT-38: Verifikasi running balance benar
- [ ] UAT-39: Cocokkan minimal 3 angka antara transaksi, recap bulanan, dan stock card

Expected umum:
- semua angka traceable ke transaksi sumber
- tidak ada mismatch antara transaksi, recap, dan stock ledger

### 4. Closing dan Approval

#### Operator
- [ ] UAT-40: Submit closing periode aktif
- [ ] UAT-41: Pastikan status periode berubah menjadi submitted/locked sesuai policy

#### Supervisor
- [ ] UAT-42: Buka review periode
- [ ] UAT-43: Verifikasi movements, snapshot, breakdown, dan warnings
- [ ] UAT-44: Reject closing
- [ ] UAT-45: Pastikan status kembali terbuka
- [ ] UAT-46: Minta operator submit ulang
- [ ] UAT-47: Approve closing
- [ ] UAT-48: Pastikan snapshot tersimpan
- [ ] UAT-49: Reopen periode
- [ ] UAT-50: Pastikan policy koreksi kembali aktif

Expected umum:
- lifecycle `submit -> reject -> submit -> approve -> reopen` berjalan tanpa error
- approved period benar-benar terkunci untuk edit/delete langsung
- snapshot konsisten pada review dan approval

### 5. Official Report Export

#### Monthly
- [ ] UAT-51: Unduh monthly `xlsx`
- [ ] UAT-52: Unduh monthly `pdf`

#### Yearly
- [ ] UAT-53: Unduh yearly `xlsx`
- [ ] UAT-54: Unduh yearly `pdf`

#### Vendors
- [ ] UAT-55: Unduh vendors `xlsx`
- [ ] UAT-56: Unduh vendors `pdf`

#### Internal Destinations
- [ ] UAT-57: Unduh internal destinations `xlsx`
- [ ] UAT-58: Unduh internal destinations `pdf`

#### Purposes
- [ ] UAT-59: Unduh purposes `xlsx`
- [ ] UAT-60: Unduh purposes `pdf`

#### Stock Card
- [ ] UAT-61: Unduh stock card `xlsx`
- [ ] UAT-62: Unduh stock card `pdf`

#### Anomalies
- [ ] UAT-63: Unduh anomalies `xlsx`
- [ ] UAT-64: Unduh anomalies `pdf`

#### Verifikasi File
- [ ] UAT-65: Semua file berhasil diunduh
- [ ] UAT-66: Semua file dapat dibuka normal
- [ ] UAT-67: Header dan filter periode benar
- [ ] UAT-68: Total produksi sama dengan UI
- [ ] UAT-69: Total pemanfaatan sama dengan UI
- [ ] UAT-70: Saldo akhir sama dengan UI
- [ ] UAT-71: Warning/anomaly utama sama dengan UI

Expected umum:
- semua export resmi terbuka
- semua angka utama konsisten dengan UI dan snapshot

---

## Defect Log

Gunakan tabel ini untuk setiap temuan selama UAT.

| ID | Severity | Role | URL / Page | Period / Filter | Summary | Detail | Evidence | Status |
|---|---|---|---|---|---|---|---|---|
| BUG-XX | blocking / minor / copy-UI | Operator / Supervisor | Halaman terkait | Filter yang dipakai | Ringkasan singkat | Detail mismatch / error | Screenshot / file | Open / Fixed / Retest |

Aturan severity:
- `blocking`: menghentikan flow utama atau membuat hasil tidak dapat dipercaya
- `minor`: flow tetap jalan, tetapi ada bug fungsional ringan atau mismatch terbatas
- `copy-UI only`: hanya label, copy, spacing, atau presentasi kecil

---

## Sign-off

### Ringkasan Hasil
- Total testcase: `71`
- Pass: `_____`
- Fail: `_____`
- Blocked: `_____`

### Keputusan
- [ ] UAT passed
- [ ] UAT blocked
- [ ] Retest required

### Catatan Akhir
- Blocker summary: `____________________________________________________`
- Rekomendasi tindak lanjut: `____________________________________________`
- Sign-off tester: `________________`
- Sign-off product/owner: `________________`
