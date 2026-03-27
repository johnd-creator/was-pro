# FABA Flow

## Ringkasan
Feature FABA di aplikasi ini adalah modul operasional untuk mencatat:
- produksi FABA
- pemanfaatan FABA
- rekap bulanan dan tahunan
- approval bulanan
- opening balance
- audit log
- laporan CSV

Semua data FABA berjalan di domain `waste-management` dengan prefix route `waste-management/faba/*`, dan seluruh data operasionalnya disimpan di schema tenant organisasi aktif.

Prinsip utamanya:
- tidak ada master periode
- periode selalu diturunkan dari `transaction_date`
- approval dilakukan per `bulan + tahun`
- status approval menjadi sumber lock transaksi
- satuan transaksi dipaksa `ton`

---

## Struktur Modul
Menu utama FABA terdiri dari:
- `Produksi`
- `Pemanfaatan`
- `Rekap`
- `Approval`
- `Laporan`

Entitas utamanya:
- `FabaProductionEntry`
- `FabaUtilizationEntry`
- `FabaMonthlyApproval`
- `FabaOpeningBalance`
- `FabaAuditLog`

Service inti:
- `FabaRecapService`

Service ini menjadi pusat logika untuk:
- menentukan periode aktif
- menghitung rekap bulanan
- menghitung rekap tahunan
- menghitung rekap vendor
- menghitung saldo berjalan
- menentukan warning
- menentukan lock period

---

## 1. Alur Input Produksi FABA
User masuk ke menu `Produksi` untuk mencatat produksi atau entri terkait produksi.

Field inti:
- `transaction_date`
- `material_type`
- `entry_type`
- `quantity`
- `unit`
- `note`

Material yang valid:
- `fly_ash`
- `bottom_ash`

Tipe entri produksi:
- `production`
- `pok`
- `workshop`
- `reject`

Aturan khusus material:
- `fly_ash` bisa memakai `production`, `pok`, `workshop`, `reject`
- `bottom_ash` hanya bisa memakai `production`, `workshop`, `reject`

Validasi utama:
- tanggal wajib dan tidak boleh melebihi hari ini
- quantity wajib numeric dan `> 0`
- `unit` hanya boleh `ton`
- kombinasi `material_type + entry_type` harus valid

Saat transaksi disimpan:
- nomor entri dibuat di backend
- `created_by` dan `updated_by` dicatat
- audit log dibuat

Locking:
- jika periode transaksi sudah `submitted` atau `approved`, transaksi tidak boleh dibuat/diubah/dihapus

---

## 2. Alur Input Pemanfaatan FABA
User masuk ke menu `Pemanfaatan` untuk mencatat penggunaan atau penyaluran FABA.

Field inti:
- `transaction_date`
- `material_type`
- `utilization_type`
- `vendor_id`
- `quantity`
- `unit`
- `document_number`
- `document_date`
- `attachment`
- `note`

Tipe pemanfaatan:
- `internal`
- `external`

Aturan utama:
- `unit` tetap `ton`
- `quantity` wajib `> 0`
- untuk `external`, `vendor_id`, `document_number`, dan `document_date` wajib
- untuk `internal`, vendor dan dokumen boleh kosong
- attachment bersifat opsional

Saat transaksi disimpan:
- nomor entri dibuat di backend
- file attachment, jika ada, disimpan sebagai path file
- audit log dibuat

Locking:
- sama seperti produksi, transaksi pemanfaatan tidak bisa diubah jika periodenya `submitted` atau `approved`

---

## 3. Cara Sistem Menentukan Periode
Periode FABA tidak dibuat manual sebagai master data.

Sistem selalu menurunkan periode dari:
- `transaction_date` pada produksi
- `transaction_date` pada pemanfaatan

Contoh:
- transaksi tanggal `2026-03-12` berarti masuk periode `Maret 2026`

Period label dibentuk di service:
- `Januari 2026`
- `Februari 2026`
- dst

Periode yang tampil di approval index atau filter rekap adalah periode yang memang memiliki data transaksi.

Default period:
- jika user tidak memilih bulan/tahun, sistem mengambil periode terbaru yang benar-benar punya data

---

## 4. Cara Rekap Bulanan Bekerja
Rekap bulanan dihitung oleh `FabaRecapService::getMonthlyRecap(year, month)`.

Komponen hitung utama:
- opening balance fly ash
- opening balance bottom ash
- total produksi fly ash
- total produksi bottom ash
- total pemanfaatan fly ash
- total pemanfaatan bottom ash
- closing balance per material
- total produksi
- total pemanfaatan
- opening balance total
- closing balance total

Rumus dasarnya:
- `closing = opening + production - utilization`

Opening balance diambil dengan urutan:
1. kalau ada opening balance eksplisit untuk material pada bulan itu, gunakan nilai itu
2. jika tidak ada, sistem cari carry-forward dari bulan sebelumnya
3. jika belum ada histori sama sekali, opening balance dianggap `0`

Output recap bulanan juga memuat:
- jumlah transaksi produksi
- jumlah transaksi pemanfaatan
- status approval periode
- warnings

Detail rekap bulanan memuat:
- ringkasan recap
- daftar transaksi produksi pembentuk angka
- daftar transaksi pemanfaatan pembentuk angka
- opening balance per material
- audit log periode tersebut

---

## 5. Warning dalam Rekap
Sistem menghasilkan warning secara otomatis di level periode.

Warning yang saat ini dipakai:
- `negative_balance`
  - saldo akhir periode negatif
- `utilization_exceeds_stock`
  - pemanfaatan melebihi stok tersedia
- `utilization_without_stock_source`
  - ada pemanfaatan tanpa produksi maupun opening balance yang cukup
- `missing_opening_balance`
  - opening balance historis belum punya sumber yang jelas
- `external_document_incomplete`
  - transaksi eksternal masih ada yang dokumennya belum lengkap
- `submitted_period_incomplete`
  - periode sudah diajukan, tetapi data eksternalnya masih belum lengkap

Warning ini dipakai di:
- dashboard FABA
- dashboard terpadu
- halaman rekap
- halaman review approval

---

## 6. Cara Approval Bulanan Bekerja
Approval FABA dilakukan per `bulan + tahun`, bukan per transaksi.

Status approval:
- `draft`
- `submitted`
- `approved`
- `rejected`

Arti status:
- `draft`
  - periode belum diajukan
  - transaksi masih bebas diedit
- `submitted`
  - periode sudah diajukan
  - transaksi dalam periode itu terkunci
- `approved`
  - periode disetujui
  - transaksi terkunci
- `rejected`
  - periode ditolak atau dibuka kembali
  - transaksi boleh direvisi lagi

Aturan transisi status:
- `draft -> submitted`
- `submitted -> approved`
- `submitted -> rejected`
- `approved -> rejected` lewat action `reopen`

Aturan submit:
- periode harus punya transaksi produksi atau pemanfaatan
- periode kosong tidak bisa diajukan

Saat submit:
- approval dibuat atau diambil jika sudah ada
- status menjadi `submitted`
- `submitted_by` dan `submitted_at` diisi
- metadata approve/reject dibersihkan
- audit log dibuat

Saat approve:
- hanya bisa dari status `submitted`
- status menjadi `approved`
- `approved_by` dan `approved_at` diisi
- audit log dibuat

Saat reject:
- hanya bisa dari status `submitted`
- status menjadi `rejected`
- `rejected_by`, `rejected_at`, dan `rejection_note` diisi
- audit log dibuat

Saat reopen:
- hanya bisa dari status `approved`
- status kembali menjadi `rejected`
- alasan reopen disimpan ke `rejection_note`
- period dibuka kembali untuk revisi
- audit log dibuat

---

## 7. Locking Transaksi
Locking transaksi ditentukan oleh `FabaRecapService::isPeriodLocked(year, month)`.

Periode dianggap locked jika approval status:
- `submitted`
- `approved`

Dampaknya:
- create transaksi baru ditolak
- edit transaksi ditolak
- delete transaksi ditolak

Periode yang `draft` atau `rejected` tidak locked, sehingga operator masih bisa revisi data.

---

## 8. Rekap Tahunan dan Vendor
### Rekap Tahunan
Rekap tahunan dibentuk dari 12 kali hitung rekap bulanan pada tahun yang dipilih.

Output utamanya:
- total produksi tahunan
- total pemanfaatan tahunan
- saldo akhir tahun
- trend bulanan:
  - produksi
  - pemanfaatan
  - saldo akhir

### Rekap Vendor
Rekap vendor hanya membaca transaksi pemanfaatan `external`.

Output utamanya:
- total quantity per vendor
- jumlah transaksi per vendor
- material yang pernah ditransaksikan
- histori transaksi vendor
- monthly history vendor

---

## 9. Opening Balance
Opening balance adalah titik awal saldo per material untuk bulan tertentu.

Disimpan per:
- `year`
- `month`
- `material_type`

Contoh:
- opening balance `fly_ash` Januari 2026
- opening balance `bottom_ash` Januari 2026

Fungsinya:
- menjadi sumber saldo awal jika histori lama tidak cukup
- menghindari perhitungan carry-forward yang kosong

Hak akses:
- perubahan opening balance dipisahkan dengan permission `faba_opening_balance.manage`

Saat opening balance disimpan:
- nilai dibulatkan 2 desimal
- `set_by` dan `set_at` dicatat
- audit log dibuat

---

## 10. Audit Log
Setiap aksi penting dicatat ke `FabaAuditLog`.

Contoh event yang dicatat:
- create/update/delete produksi
- create/update/delete pemanfaatan
- submit approval
- approve
- reject
- reopen
- set opening balance

Audit log ditampilkan pada:
- review approval per periode
- history approval

Tujuannya:
- memberi jejak siapa melakukan apa
- membantu review dan investigasi perubahan data

---

## 11. Dashboard FABA
Dashboard FABA menggunakan hasil rekap untuk menampilkan:
- total produksi tahun berjalan
- total pemanfaatan tahun berjalan
- saldo saat ini
- trend bulanan
- pending approval
- warning aktif
- periode approved terakhir
- top vendor

Dashboard tidak menghitung angka sendiri dari nol; sebagian besar membaca hasil service recap yang sama agar konsisten dengan halaman rekap dan approval.

---

## 12. Laporan
Halaman `Laporan FABA` menyediakan export CSV untuk:
- transaksi produksi
- transaksi pemanfaatan
- rekap bulanan
- rekap tahunan

Aturan export:
- mengikuti filter aktif
- memakai source data yang sama dengan recap
- rekap bulanan memuat opening balance dan closing balance

---

## 13. Permission yang Terlibat
Permission FABA dipisahkan per area:
- `faba_dashboard.view`
- `faba_production.view`
- `faba_production.create`
- `faba_production.edit`
- `faba_production.delete`
- `faba_utilization.view`
- `faba_utilization.create`
- `faba_utilization.edit`
- `faba_utilization.delete`
- `faba_recaps.view`
- `faba_opening_balance.manage`
- `faba_approvals.view`
- `faba_approvals.submit`
- `faba_approvals.approve`
- `faba_approvals.reject`
- `faba_approvals.reopen`
- `faba_reports.export`

Jadi akses user ke setiap menu dan action ditentukan dari permission ini, bukan hanya nama role.

---

## 14. Flow Operasional Singkat
Flow normal feature FABA saat dipakai harian:

1. Operator input transaksi produksi
2. Operator input transaksi pemanfaatan
3. Sistem menghitung recap otomatis berdasarkan `transaction_date`
4. Operator cek rekap bulanan dan warning
5. Jika sudah siap, operator submit periode bulanan
6. Supervisor/reviewer membuka review approval
7. Reviewer approve atau reject
8. Jika approved, periode terkunci
9. Jika perlu revisi setelah approved, reviewer bisa `reopen`
10. User dapat melihat dashboard dan export laporan dari data yang sudah tercatat

---

## 15. Hal yang Paling Penting Dipahami
- FABA adalah modul berbasis transaksi, bukan berbasis master periode
- semua angka rekap berasal dari produksi + pemanfaatan + opening balance
- status approval mengontrol lock data
- `submitted` sudah mengunci transaksi
- `approved` mengunci penuh
- `rejected` membuka kembali
- `reopen` tidak membuat status baru; ia mengembalikan periode ke kondisi revisi
- semua angka dashboard, rekap, approval, dan laporan harus konsisten karena membaca logika service yang sama
