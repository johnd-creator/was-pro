# Plan Feature Rekap Pemanfaatan & Produksi FABA

## 1. Latar Belakang

Saat ini aplikasi sudah memiliki modul **master data mitra/vendor**, sehingga feature baru tidak perlu membuat ulang master partner.
Feature yang akan dibangun fokus pada:

* input data produksi FABA
* input data pemanfaatan FABA
* perhitungan rekap otomatis bulanan dan tahunan
* approval data
* pelaporan dan export

Catatan penting:

* **periode tidak dibuat sebagai master terpisah**
* sistem menggunakan **tanggal input/transaksi**
* aplikasi otomatis mengelompokkan data ke dalam **periode bulan dan tahun** berdasarkan tanggal tersebut

Contoh:

* input tanggal `2026-01-15` → masuk periode **Januari 2026**
* input tanggal `2026-02-03` → masuk periode **Februari 2026**

---

## 2. Tujuan Feature

Membangun feature yang dapat menggantikan proses rekap manual Excel menjadi proses terstruktur di aplikasi Laravel, dengan prinsip:

* input data per transaksi
* rekap dihitung otomatis oleh sistem
* tidak ada formula manual di level user
* histori perubahan dan approval tercatat

---

## 3. Scope Feature

### In Scope

* input produksi Fly Ash
* input produksi Bottom Ash
* input pemanfaatan eksternal
* input pemanfaatan internal
* rekap bulanan otomatis
* rekap tahunan otomatis
* saldo TPS otomatis
* approval per periode bulan
* export laporan

### Out of Scope Tahap Awal

* integrasi ke sistem eksternal
* notifikasi WhatsApp/email
* digital signature
* forecasting / prediksi data

---

## 4. Asumsi Existing System

Aplikasi yang sudah berjalan saat ini sudah memiliki:

* autentikasi user
* master data mitra/vendor
* kemungkinan role user dasar
* framework Laravel aktif

Maka feature baru akan **menggunakan master mitra/vendor existing** dan hanya menambahkan relasi ke modul transaksi pemanfaatan.

---

## 5. Konsep Periode Baru

### 5.1 Prinsip

Sistem **tidak memerlukan tabel master periode**.
Periode ditentukan otomatis dari field tanggal transaksi/input, misalnya:

* `transaction_date`
* `input_date`
* `document_date`

Lalu sistem melakukan derive:

* `periode_bulan`
* `periode_tahun`

### 5.2 Implikasi

Semua rekap dihitung berdasarkan:

* `MONTH(transaction_date)`
* `YEAR(transaction_date)`

### 5.3 Keuntungan

* lebih sederhana
* tidak perlu user membuat periode manual
* mengurangi duplikasi data
* lebih natural sesuai cara kerja input harian/transaksional

### 5.4 Catatan

Walaupun tidak ada master periode, sistem tetap bisa membuat status approval per bulan-tahun dengan pendekatan:

* `month`
* `year`
* `status`

Jadi approval tetap dilakukan pada level:

* Januari 2026
* Februari 2026
* dst

---

## 6. Modul yang Akan Dibangun

### 6.1 Modul Produksi

Digunakan untuk input data produksi FABA.

Submodul:

* Produksi Fly Ash
* Produksi Bottom Ash

### 6.2 Modul Pemanfaatan

Digunakan untuk input data pemanfaatan FABA.

Submodul:

* Pemanfaatan Eksternal
* Pemanfaatan Internal

### 6.3 Modul Rekap

Digunakan untuk menampilkan hasil olahan otomatis dari transaksi.

Submodul:

* Rekap Bulanan
* Rekap Tahunan
* Rekap per Mitra
* Saldo TPS

### 6.4 Modul Approval

Digunakan untuk validasi dan pengesahan data bulanan.

### 6.5 Modul Laporan

Digunakan untuk export data ke:

* Excel
* PDF

---

## 7. Alur Bisnis Utama

### 7.1 Flow Input Data

1. User membuka menu input
2. User mengisi tanggal transaksi
3. User mengisi data produksi atau pemanfaatan
4. Sistem menyimpan transaksi
5. Sistem otomatis membaca bulan dan tahun dari tanggal
6. Data langsung masuk ke kelompok periode yang sesuai
7. Rekap ter-update otomatis

### 7.2 Flow Approval

1. Operator menyelesaikan input transaksi dalam 1 bulan
2. Operator submit periode bulan berjalan
3. Supervisor review data bulan tersebut
4. Jika sesuai → approve
5. Jika tidak sesuai → reject dan beri catatan revisi

### 7.3 Flow Laporan

1. User pilih filter tahun / bulan
2. Sistem tarik data transaksi berdasarkan tanggal
3. Sistem hitung total produksi, pemanfaatan, dan saldo
4. User dapat melihat rekap
5. User dapat export laporan

---

## 8. Rancangan Data Utama

### 8.1 Tidak Menggunakan Master Periode

Tidak dibuat tabel `periods` sebagai master.

Sebagai gantinya:

* transaksi menyimpan `transaction_date`
* tabel approval bulanan menyimpan `month` dan `year`

---

## 9. Rancangan Tabel

### 9.1 `production_entries`

Menyimpan data produksi.

Field usulan:

* id
* transaction_date
* material_type
* entry_type
* qty
* note
* created_by
* created_at
* updated_at

Keterangan:

* `transaction_date`: tanggal input/transaksi
* `material_type`: `fly_ash` / `bottom_ash`
* `entry_type`: misalnya `production`, `pok`, `workshop`, `reject`

### 9.2 `utilization_entries`

Menyimpan data pemanfaatan.

Field usulan:

* id
* transaction_date
* material_type
* vendor_id
* utilization_type
* qty
* document_number
* document_date
* attachment_path
* note
* created_by
* created_at
* updated_at

Keterangan:

* `vendor_id` memakai master vendor existing
* `utilization_type`: `external`, `internal`, `workshop`, dll
* untuk pemanfaatan internal, `vendor_id` boleh null

### 9.3 `monthly_approvals`

Menyimpan status approval per bulan.

Field usulan:

* id
* year
* month
* status
* submitted_by
* submitted_at
* approved_by
* approved_at
* rejected_note
* created_at
* updated_at

Status:

* draft
* submitted
* approved
* rejected

Catatan:
Tabel ini bukan master periode, tetapi status proses per bulan-tahun.

### 9.4 `audit_logs`

Menyimpan histori aktivitas.

Field usulan:

* id
* module_name
* ref_id
* action
* description
* created_by
* created_at

---

## 10. Integrasi dengan Master Vendor Existing

### 10.1 Existing

Karena master vendor/mitra sudah ada, maka:

* tidak perlu CRUD vendor baru
* feature hanya perlu mengambil data vendor existing
* relasi pemanfaatan eksternal diarahkan ke tabel vendor saat ini

### 10.2 Kebutuhan

Pastikan tabel vendor existing minimal memiliki:

* id
* nama vendor/mitra
* status aktif

### 10.3 Pemakaian

Pada form pemanfaatan eksternal:

* user pilih vendor dari dropdown existing
* data transaksi menyimpan `vendor_id`

---

## 11. Perhitungan Rekap

### 11.1 Produksi Fly Ash Bulanan

Mengambil total dari `production_entries` dengan kondisi:

* `material_type = fly_ash`
* filter bulan dan tahun dari `transaction_date`

### 11.2 Produksi Bottom Ash Bulanan

Mengambil total dari `production_entries` dengan kondisi:

* `material_type = bottom_ash`
* filter bulan dan tahun dari `transaction_date`

### 11.3 Pemanfaatan Eksternal Bulanan

Mengambil total dari `utilization_entries` dengan kondisi:

* `utilization_type = external`

### 11.4 Pemanfaatan Internal Bulanan

Mengambil total dari `utilization_entries` dengan kondisi:

* `utilization_type = internal`

### 11.5 Total Produksi FABA

```text
total_produksi_faba = total_produksi_fly_ash + total_produksi_bottom_ash
```

### 11.6 Total Pemanfaatan FABA

```text
total_pemanfaatan_faba = total_pemanfaatan_fly_ash + total_pemanfaatan_bottom_ash
```

### 11.7 Saldo Akhir Bulan

```text
saldo_akhir = saldo_awal + total_produksi - total_pemanfaatan
```

### 11.8 Saldo Awal

```text
saldo_awal_bulan_ini = saldo_akhir_bulan_sebelumnya
```

---

## 12. Validasi Bisnis

### 12.1 Validasi Input

* qty wajib angka
* qty tidak boleh negatif
* tanggal transaksi wajib diisi
* vendor wajib diisi untuk pemanfaatan eksternal
* material wajib diisi
* tipe transaksi wajib diisi

### 12.2 Validasi Approval

* bulan yang sudah approved tidak boleh diedit
* submit hanya bisa dilakukan jika data minimal sudah ada
* reject wajib menyertakan catatan

### 12.3 Validasi Rekap

* sistem memberi warning jika pemanfaatan melebihi stok tersedia
* sistem memberi warning jika data produksi kosong tetapi ada pemanfaatan

---

## 13. Menu yang Dibutuhkan

### 13.1 Dashboard

Menampilkan:

* total produksi tahun berjalan
* total pemanfaatan tahun berjalan
* saldo TPS
* grafik bulanan

### 13.2 Transaksi

Submenu:

* Input Produksi Fly Ash
* Input Produksi Bottom Ash
* Input Pemanfaatan Eksternal
* Input Pemanfaatan Internal

### 13.3 Rekap

Submenu:

* Rekap Bulanan
* Rekap Tahunan
* Rekap per Mitra
* Saldo TPS

### 13.4 Approval

Submenu:

* Submit Bulanan
* Review Approval
* Histori Approval

### 13.5 Laporan

Submenu:

* Export Excel
* Export PDF

---

## 14. Rancangan Halaman

### 14.1 Halaman Input Produksi

Field:

* tanggal transaksi
* material
* tipe entry
* qty
* catatan

### 14.2 Halaman Input Pemanfaatan

Field:

* tanggal transaksi
* material
* tipe pemanfaatan
* vendor
* qty
* nomor dokumen
* tanggal dokumen
* lampiran
* catatan

### 14.3 Halaman Rekap Bulanan

Filter:

* tahun
* bulan

Output:

* total produksi Fly Ash
* total produksi Bottom Ash
* total pemanfaatan Fly Ash
* total pemanfaatan Bottom Ash
* total produksi FABA
* total pemanfaatan FABA
* saldo awal
* saldo akhir

### 14.4 Halaman Rekap Tahunan

Filter:

* tahun

Output:

* tabel Januari–Desember
* total tahunan
* grafik tren

### 14.5 Halaman Rekap per Mitra

Filter:

* tahun
* vendor

Output:

* total tonase
* jumlah transaksi
* histori per bulan

### 14.6 Halaman Approval Bulanan

Filter:

* bulan
* tahun

Output:

* ringkasan transaksi
* ringkasan total
* status approval
* tombol submit / approve / reject

---

## 15. Service Layer yang Dibutuhkan

### 15.1 ProductionService

Fungsi:

* simpan transaksi produksi
* update transaksi produksi
* validasi transaksi produksi

### 15.2 UtilizationService

Fungsi:

* simpan transaksi pemanfaatan
* update transaksi pemanfaatan
* validasi transaksi pemanfaatan

### 15.3 RecapService

Fungsi:

* hitung rekap bulanan
* hitung rekap tahunan
* hitung saldo
* hitung rekap per vendor

### 15.4 ApprovalService

Fungsi:

* submit bulan
* approve bulan
* reject bulan
* lock data approved

### 15.5 ExportService

Fungsi:

* export Excel
* export PDF

---

## 16. Query Logic Periode Otomatis

### 16.1 Kelompok Bulanan

Contoh query:

```sql
SELECT *
FROM production_entries
WHERE MONTH(transaction_date) = 1
AND YEAR(transaction_date) = 2026;
```

### 16.2 Rekap Bulanan

Contoh agregasi:

```sql
SELECT
    YEAR(transaction_date) as year,
    MONTH(transaction_date) as month,
    material_type,
    SUM(qty) as total_qty
FROM production_entries
GROUP BY YEAR(transaction_date), MONTH(transaction_date), material_type;
```

### 16.3 Approval Check

Untuk cek apakah bulan sudah approved:

```text
cek tabel monthly_approvals berdasarkan month + year
```

---

## 17. Tahapan Implementasi

### Phase 1 - Core Transaction

Target:

* tabel produksi
* tabel pemanfaatan
* input form transaksi
* relasi ke vendor existing

Output:

* transaksi dasar sudah berjalan

### Phase 2 - Rekap Engine

Target:

* rekap bulanan
* rekap tahunan
* saldo TPS otomatis

Output:

* dashboard rekap sudah bisa dipakai

### Phase 3 - Approval

Target:

* submit bulanan
* approve/reject
* lock data approved

Output:

* alur validasi data sudah aman

### Phase 4 - Export & Audit

Target:

* export Excel
* export PDF
* audit log

Output:

* feature siap operasional

---

## 18. Risiko dan Antisipasi

### Risiko 1

Format bisnis Excel lama tidak 100% sama dengan struktur transaksi.

**Antisipasi:**
buat mapping formula Excel ke rule sistem sebelum development.

### Risiko 2

Ada beberapa jenis qty yang secara bisnis perlu masuk atau tidak masuk ke total tertentu.

**Antisipasi:**
definisikan rule per `entry_type` secara tertulis sebelum coding.

### Risiko 3

User terbiasa input rekap langsung, bukan transaksi detail.

**Antisipasi:**
buat UI sederhana dan mirip proses kerja harian.

### Risiko 4

Saldo historis awal belum ada di sistem.

**Antisipasi:**
siapkan opening balance per material saat implementasi awal.

---

## 19. Kebutuhan Tambahan yang Disarankan

* opening balance per material
* import data historis dari Excel
* filter by vendor
* upload dokumen pendukung
* histori revisi
* summary dashboard

---

## 20. Kesimpulan

Feature ini akan dibangun dengan pendekatan **transaksi harian / input bertanggal**, bukan master periode manual.
Sistem akan otomatis membaca bulan dan tahun dari tanggal transaksi, lalu mengelompokkan data menjadi rekap bulanan dan tahunan.

Dengan pendekatan ini:

* aplikasi lebih sederhana
* user tidak perlu membuat periode manual
* data lebih fleksibel
* rekap tetap bisa dikontrol melalui approval bulanan
* master vendor existing tetap dipakai tanpa perubahan besar
