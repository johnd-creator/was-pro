# PRD — FABA Operations, Stock Ledger, and Monthly Closing

**Document version:** v1.0 Draft  
**Date:** 2026-03-24  
**Status:** Draft for discussion  
**Product area:** Operasional Lingkungan / FABA  
**Primary audience:** Product, Engineering, QA, Operasional, Supervisor, Management

---

## 1. Ringkasan

Dokumen ini mendefinisikan kebutuhan produk untuk workflow **Pemanfaatan & Produksi FABA** dengan pendekatan **app-first**, bukan **excel-first**.

Sumber referensi saat ini adalah workbook rekap tahunan yang memuat beberapa domain sekaligus, antara lain:
- Pemanfaatan Fly Ash per mitra
- Pemanfaatan Fly Ash reject
- Produksi Fly Ash
- Produksi Bottom Ash
- Pemanfaatan Bottom Ash
- Rekap total FABA
- Sisa saldo TPS
- Akumulasi TPS
- Ringkasan kategori seperti **Semen dan Batako** serta **Beton**

Tujuan modul ini adalah memindahkan logika tersebut menjadi workflow aplikasi yang berbasis:
- pencatatan transaksi/movement harian
- perhitungan stok dan saldo otomatis
- closing bulanan yang terkontrol
- approval yang bermakna
- laporan yang dapat ditelusuri sampai ke transaksi sumber

Excel tetap dipertahankan sebagai **format output laporan**, bukan sebagai fondasi logika bisnis.

---

## 2. Latar Belakang

Saat ini pelaporan FABA sangat bergantung pada workbook rekap tahunan. Workbook tersebut efektif sebagai alat rekap, tetapi memiliki keterbatasan bila dijadikan basis operasional aplikasi:

1. **Format laporan bercampur dengan logika bisnis**  
   Satu sheet memuat transaksi, total, presentase, saldo TPS, akumulasi, dan ringkasan kategori sekaligus.

2. **Struktur vendor berubah per tahun**  
   Kolom mitra/vendor pada sheet 2024, 2025, dan 2026 tidak identik. Ini menyulitkan jika struktur Excel ditiru 1:1 ke aplikasi.

3. **Tidak ada ledger transaksi yang kuat**  
   Angka total mudah terlihat, tetapi sulit menelusuri kejadian bisnis sumbernya secara konsisten.

4. **Approval belum identik dengan closing operasional**  
   Dalam workflow aplikasi, approval seharusnya berarti periode ditutup, diverifikasi, dan dikunci.

5. **Saldo TPS dan akumulasi masih bergantung pada formula sheet**  
   Sistem aplikasi seharusnya menghitung saldo dengan engine logika, bukan dari layout formula manual.

6. **Koreksi historis sulit diaudit**  
   Perubahan data lama rawan mengubah total bulan berikutnya tanpa histori yang rapi.

---

## 3. Problem Statement

Dibutuhkan modul FABA yang dapat:
- mencatat kejadian operasional secara harian
- mengelompokkan transaksi secara otomatis ke periode bulanan berdasarkan tanggal transaksi
- menghitung produksi, pemanfaatan, saldo TPS, dan akumulasi per material
- mendukung approval sebagai proses **monthly closing**
- menghasilkan laporan resmi yang konsisten dan bisa di-export ke Excel/PDF
- menjaga histori perubahan dan audit trail

---

## 4. Visi Produk

Membangun modul FABA yang menjadikan aplikasi sebagai **source of truth operasional**, sehingga:
- setiap angka laporan berasal dari transaksi yang jelas
- setiap saldo memiliki jejak perhitungan yang bisa diperiksa
- setiap periode yang disetujui terkunci dan terdokumentasi
- laporan bulanan/tahunan dapat dihasilkan ulang kapan saja tanpa merakit formula manual

---

## 5. Goals

### 5.1 Primary Goals
- Menggantikan rekap manual Excel dengan workflow aplikasi.
- Menjadikan setiap angka laporan bisa ditelusuri sampai level transaksi.
- Mengubah approval bulanan menjadi proses closing yang nyata.
- Menyediakan laporan bulanan dan tahunan yang konsisten.

### 5.2 Secondary Goals
- Mengurangi kesalahan input dan duplikasi dokumen.
- Mempermudah audit histori revisi dan approval.
- Mempermudah penambahan vendor, tujuan internal, dan kategori penggunaan tanpa mengubah struktur sistem.

### 5.3 Non-Goals (V1)
Versi awal tidak mencakup:
- integrasi ERP / SAP / sistem eksternal
- forecasting produksi/pemanfaatan
- notifikasi WhatsApp atau email otomatis
- mobile app native
- digital signature formal

---

## 6. Prinsip Desain Produk

1. **App-first**  
   Sistem dibangun dari transaksi dan business rules, bukan dari susunan sheet.

2. **Ledger-driven**  
   Perubahan stok dicatat sebagai movement yang konsisten.

3. **Auto-period**  
   Periode didapat dari `transaction_date`, bukan dibuat manual oleh user.

4. **Traceable**  
   Semua total dan saldo harus bisa ditelusuri ke transaksi pembentuknya.

5. **Controlled closing**  
   Periode bulanan yang approved harus terkunci.

6. **Excel as reporting layer**  
   Template Excel digunakan untuk export, bukan sebagai mesin logika.

---

## 7. User Persona dan Role

### 7.1 Operator / Admin Operasional
Tanggung jawab:
- input transaksi produksi
- input transaksi pemanfaatan
- upload dokumen pendukung
- review warning transaksi
- submit closing bulanan

### 7.2 Supervisor / Reviewer
Tanggung jawab:
- review ringkasan periode
- memeriksa exception/warning
- approve atau reject closing bulanan
- mengesahkan snapshot periode

### 7.3 Management / Viewer
Tanggung jawab:
- memonitor dashboard
- melihat rekap bulanan dan tahunan
- mengunduh laporan

### 7.4 System
Tanggung jawab:
- derive periode dari tanggal transaksi
- menghitung stok dan saldo
- mendeteksi anomali
- mengunci periode approved
- menyimpan audit trail dan snapshot closing

---

## 8. Observasi Sumber Data Saat Ini

Workbook referensi menunjukkan pola domain yang konsisten, yaitu:
- **Pemanfaatan Fly Ash** per vendor/mitra, termasuk kategori semacam semen, batako, dan beton
- **Pemanfaatan Internal BSLA**
- **Pemanfaatan Fly Ash Reject**
- **Produksi Fly Ash**
- **Produksi Bottom Ash**
- **Pemanfaatan Bottom Ash**
- **Rekap Total FABA**
- **Sisa Saldo TPS** per material dan total
- **Akumulasi TPS** per material dan total
- ringkasan kategori pemanfaatan seperti **Semen dan Batako** serta **Beton**

Implikasi ke desain aplikasi:
- vendor tidak boleh diperlakukan sebagai kolom tetap
- kategori penggunaan harus menjadi atribut data, bukan layout sheet
- saldo TPS harus dihitung oleh engine stok
- rekap akhir harus dibentuk dari movement ledger

---

## 9. Konsep Produk yang Diusulkan

Aplikasi dibangun dalam dua lapisan:

### 9.1 Lapisan UI Operasional
Agar mudah dipakai user, menu tetap dipisah menjadi:
- Produksi
- Pemanfaatan Eksternal
- Pemanfaatan Internal
- Adjustment/Koreksi
- Closing & Approval
- Rekap & Laporan

### 9.2 Lapisan Logika Sistem
Semua input tersebut diterjemahkan ke struktur inti yang sama, yaitu **material movement ledger**.

Setiap transaksi minimal menjawab:
- material apa
- jenis movement apa
- tanggal berapa
- qty berapa
- stock effect apa
- tujuan/vendor/internal destination apa
- dokumen apa
- periode bulan-tahun apa

---

## 10. Core Domain Model

### 10.1 Material
Nilai awal:
- Fly Ash
- Bottom Ash

### 10.2 Movement Type
Minimal V1:
- `opening_balance`
- `production`
- `utilization_external`
- `utilization_internal`
- `reject`
- `disposal_pok`
- `adjustment_in`
- `adjustment_out`

### 10.3 Stock Effect
- `in` -> menambah stok
- `out` -> mengurangi stok

### 10.4 Vendor / Mitra
Menggunakan **master vendor existing** di aplikasi.

### 10.5 Internal Destination
Contoh:
- BSLA
- Workshop FABA
- Operasional internal
- Tujuan internal lain

### 10.6 Purpose / Use Case
Contoh:
- semen
- batako
- beton
- konstruksi
- lainnya

### 10.7 Location / Stock Location
Minimal V1:
- TPS
- lokasi internal lain jika diperlukan

### 10.8 Monthly Closing
Entitas untuk mewakili status period closing bulanan.

### 10.9 Closing Snapshot
Ringkasan final periode yang dihasilkan saat periode di-approve.

---

## 11. Scope Produk V1

### 11.1 Modul Produksi
User dapat mencatat produksi per tanggal transaksi.

#### Requirement
- support material: Fly Ash, Bottom Ash
- support subtype produksi sesuai rule bisnis
- sistem otomatis derive bulan/tahun
- transaksi menghasilkan movement stok masuk/keluar sesuai subtype

#### Contoh subtype yang perlu dipetakan
Fly Ash:
- produksi utama
- workshop FABA
- reject
- disposal/POK

Bottom Ash:
- produksi utama
- workshop FABA
- reject/disposal bila relevan

> Catatan: pemetaan subtype final harus dikonfirmasi ke owner bisnis sebelum implementasi final.

### 11.2 Modul Pemanfaatan
User dapat mencatat pemanfaatan berdasarkan tanggal transaksi.

#### Jenis pemanfaatan
- eksternal
- internal
- reject/disposal bila dikelola sebagai transaksi keluar

#### Requirement
- eksternal wajib memilih vendor
- internal wajib memilih internal destination
- purpose/use-case dapat dipilih
- dokumen dapat dilampirkan
- sistem melakukan validasi stok dan duplikasi dasar

### 11.3 Modul Opening Balance
Diperlukan untuk fase implementasi awal atau pergantian tahun agar saldo awal dapat dikontrol.

### 11.4 Modul Adjustment / Koreksi
Digunakan untuk pembetulan data, dengan kontrol ketat terutama jika periode sudah ditutup.

### 11.5 Modul Rekap
Sistem menyajikan:
- total produksi per material
- total pemanfaatan per material
- saldo awal per material
- saldo akhir per material
- total FABA
- akumulasi TPS
- breakdown pemanfaatan per vendor dan per purpose

### 11.6 Modul Closing & Approval
Operator mengajukan penutupan bulan, supervisor mereview dan mengesahkan.

### 11.7 Modul Laporan
Laporan dapat diexport ke:
- Excel
- PDF

---

## 12. Out of Scope V1

- otomatisasi reminder via email/WA
- approval multi-level lebih dari 1 supervisor
- integrasi ke timbangan atau IoT
- sinkronisasi real-time ke Dinas / regulator
- dashboard cost accounting penuh

---

## 13. Alur Produk End-to-End

### 13.1 Input Produksi
1. User buka menu Produksi.
2. User isi tanggal transaksi.
3. User pilih material.
4. User pilih subtype entri.
5. User isi qty dan catatan.
6. User simpan.
7. Sistem:
   - derive periode bulan/tahun dari tanggal transaksi
   - menetapkan stock effect
   - menyimpan movement
   - memperbarui perhitungan operasional

### 13.2 Input Pemanfaatan
1. User buka menu Pemanfaatan.
2. User isi tanggal transaksi.
3. User pilih material.
4. User pilih tipe pemanfaatan.
5. User isi qty.
6. User isi vendor atau internal destination sesuai tipe.
7. User isi dokumen, tanggal dokumen, lampiran jika ada.
8. User simpan.
9. Sistem:
   - derive periode
   - validasi kelengkapan wajib
   - cek stok tersedia
   - cek potensi duplikasi dasar
   - menyimpan movement

### 13.3 Closing Bulanan
1. Sistem mengelompokkan seluruh transaksi ke periode bulanan.
2. User membuka daftar periode yang memiliki transaksi.
3. Sistem menampilkan status periode.
4. Operator memilih periode dan melihat ringkasan closing.
5. Operator submit closing.
6. Supervisor review angka, warning, dan dokumen.
7. Supervisor approve atau reject.
8. Jika approve:
   - snapshot disimpan
   - periode dikunci
9. Jika reject:
   - periode kembali editable
   - alasan reject tercatat

### 13.4 Revisi Setelah Approval
Perubahan terhadap periode approved harus melalui salah satu mekanisme berikut:
- `reopen period` dengan otorisasi
- `adjustment` tercatat pada periode koreksi

Keputusan final ditentukan saat desain bisnis finalisasi.

---

## 14. Status Periode

Status minimal:
- `open`
- `ready_to_submit`
- `submitted`
- `approved`
- `rejected`
- `reopened` (opsional, jika digunakan)

### Definisi
- **open**: transaksi masih dapat diinput dan diubah
- **ready_to_submit**: transaksi ada dan validasi minimum terpenuhi
- **submitted**: periode menunggu review supervisor
- **approved**: closing disahkan, periode terkunci
- **rejected**: periode dikembalikan untuk revisi
- **reopened**: periode approved dibuka kembali secara resmi

---

## 15. Business Rules Utama

### 15.1 Periode Otomatis
- Periode **tidak** dibuat sebagai master manual.
- Periode selalu diturunkan dari `transaction_date`.
- Rekap bulanan memakai logika `month(transaction_date)` dan `year(transaction_date)`.

### 15.2 Formula Saldo
Per material:
- `saldo_akhir = saldo_awal + total_inflow - total_outflow`
- `saldo_awal_bulan_berikut = saldo_akhir_bulan_sebelumnya`

### 15.3 Stock Effect per Movement
- `opening_balance` -> in
- `production` -> in
- `utilization_external` -> out
- `utilization_internal` -> out
- `reject` -> out
- `disposal_pok` -> out
- `adjustment_in` -> in
- `adjustment_out` -> out

### 15.4 Vendor Rule
- `utilization_external` wajib memiliki `vendor_id`
- `utilization_internal` tidak boleh memerlukan vendor

### 15.5 Internal Destination Rule
- `utilization_internal` wajib memiliki `internal_destination`

### 15.6 Dokumen Rule
- transaksi eksternal idealnya memiliki nomor dokumen
- jika nomor dokumen tidak wajib, sistem minimal memberi warning saat kosong

### 15.7 Duplicate Prevention
Sistem menampilkan warning jika kombinasi berikut terindikasi duplikat:
- material
- movement_type
- vendor/internal destination
- document_number
- document_date
- qty

### 15.8 Approved Period Lock
- transaksi pada periode approved tidak boleh diedit atau dihapus langsung
- perubahan harus melalui reopen atau adjustment

### 15.9 Opening Balance
- sistem harus mendukung saldo awal per material pada awal implementasi
- tanpa opening balance, akumulasi TPS historis berisiko tidak akurat

### 15.10 Snapshot Closing
Saat periode approved, sistem menyimpan snapshot minimal berisi:
- total inflow/outflow per material
- saldo awal per material
- saldo akhir per material
- total FABA
- warning summary
- approved_by dan approved_at

---

## 16. Functional Requirements per Modul

### 16.1 Dashboard
Dashboard harus menampilkan:
- total produksi periode aktif
- total pemanfaatan periode aktif
- saldo TPS per material
- saldo TPS total
- periode yang masih pending approval
- jumlah warning/anomali
- tren bulanan produksi vs pemanfaatan

### 16.2 Transaksi Produksi
- buat transaksi produksi
- edit/hapus transaksi selama periode belum approved
- filter berdasarkan tanggal, material, subtype, status
- tampilkan histori transaksi

### 16.3 Transaksi Pemanfaatan
- buat transaksi pemanfaatan
- edit/hapus transaksi selama periode belum approved
- filter berdasarkan vendor, material, tipe, tanggal
- upload dan lihat lampiran

### 16.4 Adjustment / Koreksi
- buat transaksi koreksi masuk/keluar
- wajib menyimpan alasan koreksi
- jika menyentuh periode approved, harus mengikuti policy koreksi resmi

### 16.5 Rekap Bulanan
Menampilkan untuk bulan tertentu:
- produksi Fly Ash
- pemanfaatan Fly Ash
- produksi Bottom Ash
- pemanfaatan Bottom Ash
- total produksi FABA
- total pemanfaatan FABA
- saldo awal
- saldo akhir
- akumulasi TPS
- breakdown vendor dan purpose

### 16.6 Rekap Tahunan
Menampilkan Januari–Desember:
- inflow/outflow per material per bulan
- total tahunan
- trend chart

### 16.7 Rekap per Vendor
Menampilkan:
- total tonase per vendor
- jumlah transaksi
- histori bulanan
- material yang dimanfaatkan
- purpose/use-case bila tersedia

### 16.8 Closing & Approval
- daftar periode otomatis
- detail ringkasan closing
- submit bulanan
- approve/reject
- histori approval
- catatan reject

### 16.9 Laporan
- export bulanan ke Excel/PDF
- export tahunan ke Excel/PDF
- export rekap vendor
- export stock movement / stock card bila dibutuhkan

### 16.10 Audit Trail
Sistem wajib menyimpan log untuk:
- create
- update
- delete
- submit closing
- approve
- reject
- reopen
- adjustment

---

## 17. UX Requirements

### 17.1 Input harus event-based
User tidak mengisi rekap besar. User hanya mencatat kejadian operasional.

### 17.2 Form harus dinamis
Contoh perilaku:
- jika pilih `utilization_external` -> field vendor muncul dan wajib
- jika pilih `utilization_internal` -> vendor disembunyikan, internal destination wajib
- jika pilih material Fly Ash -> subtype Fly Ash saja yang tampil

### 17.3 Satuan harus terkontrol
- default satuan: ton
- tidak disarankan satuan bebas editable jika bisnis memakai satuan tunggal

### 17.4 Approval harus period-driven
User tidak seharusnya membuat periode manual satu per satu. Sistem menampilkan daftar periode yang memang memiliki transaksi.

### 17.5 Rekap harus drill-down
Dari angka total, user harus bisa membuka transaksi pembentuknya.

### 17.6 Format tanggal konsisten
Gunakan format tanggal yang jelas dan konsisten untuk user operasional Indonesia.

---

## 18. Reporting Requirements

### 18.1 Laporan Wajib
- rekap bulanan FA & BA
- rekap tahunan FA & BA
- saldo TPS bulanan
- akumulasi TPS
- daftar pemanfaatan per vendor
- daftar pemanfaatan internal
- histori closing dan approval

### 18.2 Laporan Sangat Disarankan
- stock card per material
- anomaly report
- summary by purpose/use-case
  - semen
  - batako
  - beton
  - lainnya

### 18.3 Output Format
- tampilan UI dapat disederhanakan dan dioptimalkan untuk aplikasi
- format export boleh menyesuaikan kebutuhan laporan resmi selama angka tetap konsisten

---

## 19. Data Requirement Ringkas

### 19.1 Atribut wajib transaksi minimal
- transaction_date
- material
- movement_type
- qty
- unit
- created_by

### 19.2 Atribut kondisional
- vendor_id untuk transaksi eksternal
- internal_destination untuk transaksi internal
- document_number dan document_date untuk transaksi berdokumen
- attachment untuk transaksi yang memerlukan bukti
- purpose/use-case untuk kategorisasi output

### 19.3 Atribut closing
- month
- year
- status
- submitted_by / submitted_at
- approved_by / approved_at
- rejected_note
- snapshot_payload

---

## 20. Non-Functional Requirements

### 20.1 Traceability
Setiap angka di rekap harus bisa ditelusuri kembali ke transaksi sumber.

### 20.2 Data Integrity
Approved period tidak boleh berubah tanpa jejak yang sah.

### 20.3 Performance
Rekap bulanan dan tahunan harus dapat dimuat dengan cepat untuk volume transaksi operasional normal.

### 20.4 Auditability
Semua aksi penting harus tercatat dengan timestamp dan user pelaku.

### 20.5 Maintainability
Penambahan vendor, purpose, dan tujuan internal tidak boleh mengharuskan perubahan struktur database besar.

### 20.6 Export Reliability
Laporan Excel/PDF harus konsisten dengan angka pada UI dan snapshot closing.

---

## 21. KPI Keberhasilan

### 21.1 KPI Operasional
- 100% transaksi FABA operasional dicatat melalui aplikasi
- waktu penyusunan laporan bulanan turun signifikan
- penggunaan workbook manual sebagai alat hitung utama menurun drastis

### 21.2 KPI Kualitas Data
- seluruh angka laporan dapat di-trace ke transaksi
- warning stok negatif dapat terdeteksi otomatis
- duplikasi transaksi berdokumen berkurang

### 21.3 KPI Governance
- semua periode closing memiliki jejak submit dan approve
- revisi periode terekam dengan baik
- tidak ada perubahan silent pada approved period

---

## 22. Acceptance Criteria V1

### 22.1 Produksi
- user dapat input transaksi produksi FA/BA
- sistem otomatis mengelompokkan ke bulan dan tahun berdasarkan tanggal transaksi

### 22.2 Pemanfaatan
- user dapat input pemanfaatan eksternal dan internal
- vendor existing dapat dipilih untuk transaksi eksternal

### 22.3 Rekap
- sistem dapat menghitung total produksi/pemanfaatan per bulan
- sistem dapat menghitung saldo awal dan saldo akhir per material
- sistem dapat menampilkan total FABA dan akumulasi TPS

### 22.4 Approval
- user dapat submit periode bulanan
- supervisor dapat approve/reject
- periode approved terkunci
- snapshot closing tersimpan

### 22.5 Audit
- sistem menyimpan histori create/update/submit/approve/reject

### 22.6 Laporan
- user dapat export laporan bulanan dan tahunan
- angka export konsisten dengan UI/snapshot

---

## 23. Rekomendasi Arsitektur Produk

### 23.1 Core Engine
Produk sebaiknya dibangun di atas empat engine utama:
- **Validation Engine**
- **Movement Ledger Engine**
- **Closing Engine**
- **Reporting Engine**

### 23.2 Kenapa bukan tabel rekap?
Karena tabel rekap akan rapuh saat:
- vendor berubah
- kategori bertambah
- koreksi historis terjadi
- kebutuhan laporan berkembang

### 23.3 Kenapa ledger lebih aman?
Karena ledger memungkinkan:
- audit yang baik
- drill-down transaksi
- saldo yang konsisten
- snapshot closing yang stabil

---

## 24. Implementasi Bertahap yang Disarankan

### Phase 1 — Core Capture
- material movement
- produksi
- pemanfaatan
- vendor existing integration
- opening balance

### Phase 2 — Rekap & Dashboard
- rekap bulanan
- rekap tahunan
- saldo TPS
- akumulasi
- dashboard KPI

### Phase 3 — Closing & Approval
- monthly closing
- approve/reject
- snapshot
- lock period
- audit trail

### Phase 4 — Reporting & Improvement
- export Excel/PDF
- stock card
- anomaly report
- per vendor / per purpose reporting

---

## 25. Open Questions yang Harus Diputuskan

Sebelum development penuh, keputusan bisnis berikut wajib difinalkan:

1. Apakah `reject` dan `disposal/POK` adalah kategori yang sama atau berbeda?
2. Apakah `workshop FABA` dianggap produksi, pemanfaatan internal, atau kategori tersendiri?
3. Saldo TPS dihitung per material saja, atau juga per lokasi?
4. Apakah opening balance awal implementasi akan diinput manual?
5. Jika periode approved perlu dikoreksi, apakah policy-nya `reopen` atau `adjustment only`?
6. Dokumen apa yang benar-benar wajib untuk transaksi eksternal?
7. Apakah `purpose/use-case` wajib diisi atau opsional?
8. Apakah export resmi harus mengikuti layout workbook saat ini 100%, atau cukup menjaga konsistensi angka?

---

## 26. Keputusan Produk yang Direkomendasikan

Jika PRD ini disetujui, tiga keputusan inti yang direkomendasikan adalah:

### Keputusan 1
**Bangun sistem berbasis movement ledger, bukan tabel rekap.**

### Keputusan 2
**Definisikan approval sebagai monthly closing, bukan sekadar status form.**

### Keputusan 3
**Jadikan Excel sebagai format keluaran, bukan sumber logika utama.**

---

## 27. Kesimpulan

Workflow FABA seharusnya dibangun sebagai **sistem transaksi + stok + closing + laporan**.

Workbook saat ini tetap penting sebagai referensi struktur pelaporan, tetapi aplikasi tidak seharusnya meniru sheet secara literal. Dengan pendekatan pada PRD ini, sistem akan:
- lebih fleksibel saat vendor berubah
- lebih aman saat ada koreksi historis
- lebih mudah diaudit
- lebih kuat untuk approval dan closing
- lebih siap menjadi source of truth operasional

