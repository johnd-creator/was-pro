# Correction Plan Feature FABA

## 1. Tujuan
Dokumen ini berisi rencana koreksi untuk feature FABA yang sudah dikembangkan, berdasarkan evaluasi terhadap flow, UI, dan kecocokan dengan kebutuhan awal rekap Pemanfaatan & Produksi FABA.

Fokus dokumen ini adalah:
- memastikan implementasi yang sudah dibuat tetap sejalan dengan plan awal
- menutup gap antara UI yang sudah jadi dengan business rule yang dibutuhkan
- menyusun backlog koreksi yang bisa langsung dieksekusi oleh developer atau Codex
- memberi acceptance criteria agar hasil akhir bisa diuji dengan jelas

---

## 2. Ringkasan Evaluasi
Secara umum, feature yang sudah dikembangkan **sudah berada di arah yang benar**.

### Yang sudah sesuai
- modul utama sudah terpisah menjadi: **Dashboard FABA, Produksi, Pemanfaatan, Rekap, Approval, Laporan**
- input transaksi sudah menggunakan **tanggal transaksi**
- pemanfaatan sudah terhubung ke **vendor existing**
- rekap sudah dibedakan antara **Fly Ash (FA)** dan **Bottom Ash (BA)**
- approval sudah dibuat pada level **bulan/tahun**, bukan sebagai master periode terpisah

### Yang masih perlu dikoreksi
- UX approval masih terasa seperti membuat periode manual
- label periode di approval masih berupa **Bulan 1, Bulan 2** dan belum memakai nama bulan sebenarnya
- field **satuan** masih editable, padahal idealnya fixed
- format tanggal masih belum konsisten untuk user Indonesia
- validasi form pemanfaatan belum terlihat dinamis per tipe pemanfaatan
- validasi form produksi belum terlihat dinamis per material dan tipe entri
- rekap masih terlalu ringkas dan belum ada drill-down detail
- belum terlihat rekap tahunan dan rekap per vendor
- histori approval dan audit trail belum terlihat
- logic backend penting seperti **opening balance, carry forward saldo, lock approved period** harus dipastikan sudah ada

---

## 3. Target Hasil Setelah Koreksi
Setelah koreksi selesai, feature FABA harus memenuhi kondisi berikut:

1. User menginput transaksi berdasarkan tanggal, lalu sistem otomatis mengelompokkan ke bulan dan tahun.
2. Approval tidak terasa seperti input periode manual, tetapi review terhadap periode yang memang sudah terbentuk dari data transaksi.
3. Rekap bulanan tidak hanya menampilkan angka total, tetapi juga dapat ditelusuri ke detail pembentuknya.
4. Periode yang sudah approved tidak bisa diedit sembarangan.
5. Saldo awal dan saldo akhir dihitung konsisten berdasarkan opening balance dan carry forward bulan sebelumnya.
6. Semua validasi penting berjalan otomatis sesuai tipe transaksi.
7. Tersedia histori approval dan audit trail yang cukup untuk kebutuhan operasional.

---

## 4. Correction Backlog

## CP-01 — Ubah Approval menjadi Derived Period Review
**Prioritas:** P0  
**Status saat ini:** halaman approval masih meminta user mengisi tahun dan bulan secara manual  
**Target:** approval membaca periode dari transaksi yang sudah ada, bukan terasa seperti membuat periode

### Masalah
Walaupun secara data approval sudah memakai bulan dan tahun, UX saat ini masih terasa manual karena user masih harus memilih atau mengisi periode untuk submit approval.

### Perbaikan
- tampilkan daftar periode yang terbentuk dari data transaksi
- daftar periode minimal berdasarkan kombinasi:
  - `YEAR(transaction_date)`
  - `MONTH(transaction_date)`
- tampilkan status masing-masing periode:
  - draft
  - submitted
  - approved
  - rejected
- action utama di tiap kartu/periode:
  - submit
  - review
  - approve
  - reject
- tombol submit approval sebaiknya menempel pada kartu periode, bukan form input bulan/tahun manual

### Acceptance Criteria
- user tidak perlu membuat periode baru secara manual
- sistem hanya menampilkan periode yang memang memiliki transaksi
- periode yang tampil menggunakan nama bulan dan tahun, misalnya **Maret 2026**
- action submit/review/approve/reject dilakukan langsung dari periode yang tampil

---

## CP-02 — Ubah Label Periode menjadi Nama Bulan yang Jelas
**Prioritas:** P0  
**Status saat ini:** approval menampilkan label seperti `Bulan 1`, `Bulan 2`  
**Target:** gunakan nama periode yang ramah user

### Perbaikan
- ubah label dari format numerik menjadi nama bulan penuh
- contoh:
  - Januari 2026
  - Februari 2026
  - Maret 2026

### Acceptance Criteria
- tidak ada lagi label seperti `Bulan 1`
- semua halaman yang menampilkan periode menggunakan format nama bulan + tahun

---

## CP-03 — Jadikan Satuan sebagai Field Tetap / Readonly
**Prioritas:** P1  
**Status saat ini:** field satuan masih bisa diedit  
**Target:** satuan default `ton` dan tidak bisa diubah sembarangan

### Masalah
Jika satuan dibiarkan bebas diedit, akan berisiko terjadi variasi data seperti `ton`, `Ton`, `TON`, `kg`, yang membuat rekap kotor.

### Perbaikan
- ubah field satuan menjadi readonly
- atau hilangkan dari form dan set default `ton` di backend
- jika sistem ke depan memang mendukung multi-satuan, siapkan konversi satuan secara eksplisit, jangan dibiarkan bebas

### Acceptance Criteria
- transaksi baru otomatis tersimpan dengan satuan `ton`
- user tidak dapat menyimpan variasi satuan bebas tanpa rule

---

## CP-04 — Standarisasi Format Tanggal
**Prioritas:** P1  
**Status saat ini:** format tanggal masih campuran `mm/dd/yyyy` dan tampilan seperti `03/19/2026`  
**Target:** format konsisten dan cocok untuk user Indonesia

### Perbaikan
- pilih satu standar tampilan tanggal, disarankan:
  - `dd/mm/yyyy`, atau
  - `yyyy-mm-dd`
- gunakan format yang sama untuk:
  - tanggal transaksi
  - tanggal dokumen
  - filter rekap
  - approval period detail
- pastikan format tampilan konsisten di frontend, tetapi penyimpanan database tetap `date`/`datetime`

### Acceptance Criteria
- semua field tanggal tampil konsisten di seluruh modul FABA
- tidak ada placeholder tanggal campuran
- parsing tanggal valid dan tidak ambigu

---

## CP-05 — Validasi Dinamis pada Form Pemanfaatan
**Prioritas:** P0  
**Status saat ini:** vendor tampak selalu bisa `Tanpa vendor`, belum terlihat rule dinamis  
**Target:** form berubah sesuai tipe pemanfaatan

### Business Rule yang Dibutuhkan
- jika `utilization_type = external`:
  - `vendor_id` wajib
  - `document_number` dipertimbangkan wajib
  - `document_date` dipertimbangkan wajib
  - `attachment` dipertimbangkan wajib atau minimal optional dengan warning
- jika `utilization_type = internal`:
  - `vendor_id` boleh null
  - nomor dokumen dan lampiran bisa optional

### Perbaikan
- tambahkan perubahan field secara dinamis ketika tipe pemanfaatan dipilih
- tambahkan validasi di frontend dan backend
- tampilkan helper text untuk menjelaskan kapan vendor wajib

### Acceptance Criteria
- transaksi external tidak bisa disimpan tanpa vendor
- transaksi internal tetap bisa disimpan tanpa vendor
- validasi backend tetap menolak data salah meskipun frontend dilewati

---

## CP-06 — Batasi Tipe Entri Produksi berdasarkan Material
**Prioritas:** P0  
**Status saat ini:** tipe entri belum terlihat dibatasi berdasarkan material  
**Target:** dropdown tipe entri mengikuti material yang dipilih

### Business Rule yang Dibutuhkan
Contoh rule awal:
- jika material = `fly_ash`, tipe entri yang relevan bisa mencakup:
  - production
  - pok
  - workshop
  - reject
- jika material = `bottom_ash`, tipe entri hanya menampilkan tipe yang relevan untuk bottom ash

### Perbaikan
- material dipilih terlebih dahulu
- dropdown tipe entri terfilter otomatis berdasarkan material
- validasi backend memastikan kombinasi material + entry_type valid

### Acceptance Criteria
- user tidak dapat memilih tipe entri yang tidak valid untuk material tertentu
- backend menolak kombinasi material dan entry type yang tidak sesuai rule

---

## CP-07 — Tambahkan Drill-Down pada Rekap Bulanan
**Prioritas:** P1  
**Status saat ini:** rekap baru menampilkan KPI card ringkas  
**Target:** user bisa menelusuri angka rekap ke detail transaksi pembentuknya

### Perbaikan
Tambahkan halaman detail periode bulanan yang berisi minimal:
- ringkasan total periode
- detail produksi Fly Ash
- detail produksi Bottom Ash
- detail pemanfaatan eksternal per vendor
- detail pemanfaatan internal
- detail sumber saldo awal
- perhitungan saldo akhir

### Rekomendasi UI
- halaman rekap bulanan tetap menampilkan kartu ringkas
- tambahkan tombol `Lihat detail` atau klik kartu/periode
- detail dapat berbentuk tab:
  - ringkasan
  - produksi
  - pemanfaatan
  - approval log

### Acceptance Criteria
- user dapat membuka detail periode tertentu
- user dapat menelusuri total angka ke daftar transaksi pembentuknya
- total pada detail harus sama dengan total pada kartu rekap

---

## CP-08 — Tambahkan Rekap Tahunan dan Rekap per Vendor
**Prioritas:** P1  
**Status saat ini:** baru terlihat rekap bulanan  
**Target:** modul rekap lengkap sesuai plan awal

### Perbaikan
Tambahkan dua halaman/fitur:

#### A. Rekap Tahunan
Menampilkan:
- Januari–Desember
- produksi FA per bulan
- produksi BA per bulan
- pemanfaatan FA per bulan
- pemanfaatan BA per bulan
- total produksi
- total pemanfaatan
- saldo akhir per bulan

#### B. Rekap per Vendor
Menampilkan:
- vendor
- total tonase per tahun/bulan
- jumlah transaksi
- material terkait
- histori transaksi per vendor

### Acceptance Criteria
- user bisa memilih tahun dan melihat tabel 12 bulan
- user bisa memilih vendor dan melihat histori pemanfaatannya
- angka di rekap vendor konsisten dengan data transaksi pemanfaatan eksternal

---

## CP-09 — Tambahkan Histori Approval dan Audit Trail
**Prioritas:** P0  
**Status saat ini:** histori submit/approve/reject belum terlihat  
**Target:** setiap perubahan penting tercatat dan dapat ditelusuri

### Perbaikan
Tambahkan pencatatan minimal untuk event berikut:
- create transaction
- update transaction
- delete transaction jika diizinkan
- submit approval
- approve
- reject
- unlock/reopen period jika ada

### Data yang Perlu Dicatat
- user
- waktu aksi
- modul
- id referensi
- ringkasan perubahan
- alasan reject

### Acceptance Criteria
- setiap action approval memiliki jejak user dan timestamp
- alasan reject tersimpan dan dapat dibaca kembali
- tersedia histori approval per periode

---

## CP-10 — Tingkatkan Dashboard agar Lebih Operasional
**Prioritas:** P2  
**Status saat ini:** dashboard sudah rapi tetapi masih statis  
**Target:** dashboard membantu monitoring operasional harian/bulanan

### Perbaikan
Tambahkan komponen berikut:
- tren produksi vs pemanfaatan 12 bulan terakhir
- daftar periode pending approval
- daftar peringatan saldo negatif
- ringkasan periode terakhir yang approved
- total pemanfaatan per vendor terbesar

### Acceptance Criteria
- dashboard tidak hanya berisi card angka nol atau angka total statis
- user bisa langsung melihat antrian approval dan warning utama dari dashboard

---

## CP-11 — Pastikan Saldo Carry Forward dan Opening Balance Berjalan
**Prioritas:** P0  
**Status saat ini:** belum dapat dipastikan dari UI  
**Target:** saldo awal dan akhir valid secara akuntansi operasional

### Business Rule
- `saldo_awal_bulan_ini = saldo_akhir_bulan_sebelumnya`
- `saldo_akhir = saldo_awal + total_produksi - total_pemanfaatan`
- untuk bulan pertama implementasi sistem, dibutuhkan **opening balance**

### Perbaikan
- tambahkan tabel atau mekanisme opening balance
- hitung carry forward otomatis
- pisahkan saldo minimal untuk:
  - Fly Ash
  - Bottom Ash
  - total FABA jika diperlukan
- jika tidak ada opening balance, beri warning bahwa saldo historis belum final

### Acceptance Criteria
- rekap bulan kedua dan seterusnya otomatis memakai saldo akhir bulan sebelumnya
- sistem mendukung setidaknya satu opening balance awal
- warning muncul jika opening balance belum ditentukan

---

## CP-12 — Lock Period setelah Approved
**Prioritas:** P0  
**Status saat ini:** belum dapat dipastikan dari UI  
**Target:** approval benar-benar bermakna secara kontrol data

### Perbaikan
- saat periode berstatus `approved`, transaksi dalam bulan tersebut tidak dapat diedit, dihapus, atau ditambah tanpa mekanisme reopen
- jika bisnis membutuhkan revisi, gunakan flow:
  - reopen period / unlock approval
  - catat alasan unlock
  - simpan audit trail

### Acceptance Criteria
- user non-authorized tidak bisa mengubah transaksi periode approved
- setiap unlock/reopen tercatat di audit log
- rekap approved tidak berubah diam-diam

---

## CP-13 — Warning Validasi Rekap dan Anomali
**Prioritas:** P1  
**Status saat ini:** dashboard sudah punya card peringatan, tetapi belum dapat dipastikan rule-nya  
**Target:** warning berbasis rule nyata, bukan placeholder

### Rule Minimum
- warning jika pemanfaatan > stok tersedia
- warning jika ada pemanfaatan tetapi tidak ada produksi dan tidak ada saldo awal yang cukup
- warning jika transaksi external tidak memiliki dokumen pendukung sesuai rule
- warning jika periode sudah submitted tetapi masih ada data tidak lengkap

### Acceptance Criteria
- setiap warning memiliki source rule yang jelas
- warning bisa ditelusuri ke daftar transaksi atau periode yang bermasalah

---

## 5. Struktur Pekerjaan yang Disarankan

### Sprint 1 — Business Rule Critical Fix
Fokus:
- CP-01 Approval derived period review
- CP-02 Label nama bulan
- CP-05 Validasi dinamis pemanfaatan
- CP-06 Validasi dinamis produksi
- CP-11 Carry forward saldo + opening balance
- CP-12 Lock approved period

### Sprint 2 — Rekap dan Traceability
Fokus:
- CP-07 Drill-down rekap bulanan
- CP-08 Rekap tahunan dan rekap per vendor
- CP-09 Histori approval dan audit trail
- CP-13 Warning anomali berbasis rule

### Sprint 3 — UX Polishing
Fokus:
- CP-03 Satuan readonly
- CP-04 Format tanggal konsisten
- CP-10 Dashboard operasional

---

## 6. Rekomendasi Teknis Implementasi

### Backend
- gunakan service layer untuk perhitungan rekap dan approval
- pastikan semua validasi inti ada di backend, bukan hanya di frontend
- buat helper untuk derive periode dari `transaction_date`
- buat service untuk saldo bulanan agar tidak tersebar di banyak controller

### Frontend
- gunakan dependent dropdown pada material dan tipe entri
- gunakan conditional form rendering pada pemanfaatan external vs internal
- tambahkan badge status periode:
  - Draft
  - Submitted
  - Approved
  - Rejected

### Database
Tambahan yang kemungkinan dibutuhkan:
- `monthly_approvals`
- `opening_balances`
- `audit_logs`
- optional: snapshot/summary table jika performa rekap nanti berat

---

## 7. QA Checklist

### Input Produksi
- [ ] user dapat membuat transaksi produksi Fly Ash
- [ ] user dapat membuat transaksi produksi Bottom Ash
- [ ] tipe entri mengikuti material yang dipilih
- [ ] satuan tersimpan konsisten sebagai `ton`

### Input Pemanfaatan
- [ ] external wajib pilih vendor
- [ ] internal boleh tanpa vendor
- [ ] nomor dokumen dan lampiran mengikuti rule yang ditentukan
- [ ] transaksi dengan data tidak valid ditolak backend

### Rekap
- [ ] total produksi FA sesuai transaksi bulan terkait
- [ ] total produksi BA sesuai transaksi bulan terkait
- [ ] total pemanfaatan sesuai transaksi bulan terkait
- [ ] saldo awal bulan kedua = saldo akhir bulan pertama
- [ ] detail rekap dapat menjelaskan total pada kartu ringkas

### Approval
- [ ] periode approval diambil dari data transaksi
- [ ] submit approval tidak memerlukan membuat periode manual
- [ ] periode approved tidak bisa diedit
- [ ] reject menyimpan alasan
- [ ] histori approval bisa dibaca kembali

### Dashboard
- [ ] warning negatif muncul bila rumus stok negatif
- [ ] pending approval menunjukkan periode yang benar
- [ ] angka dashboard konsisten dengan rekap

---

## 8. Definition of Done
Correction plan dianggap selesai jika:
- seluruh item prioritas P0 selesai dan lolos QA
- perhitungan saldo sudah tervalidasi dengan data sampel nyata
- approval sudah berbasis periode hasil derive dari transaksi
- period approved terkunci
- histori approval dan audit trail tersedia
- rekap bulanan dapat ditelusuri ke transaksi pembentuknya
- format UI utama sudah konsisten dan lebih operasional

---

## 9. Kesimpulan
Feature FABA yang ada saat ini **sudah benar secara arah**, tetapi masih membutuhkan beberapa koreksi penting agar benar-benar siap menggantikan proses Excel dan aman dipakai operasional.

Koreksi paling kritis ada pada:
- approval yang harus terasa derived dari transaksi, bukan periode manual
- validasi dinamis berdasarkan tipe transaksi
- saldo, opening balance, dan carry forward
- lock period setelah approved
- histori approval dan audit trail

Jika item prioritas tinggi di dokumen ini diselesaikan, maka feature FABA akan jauh lebih stabil, terkontrol, dan sesuai dengan kebutuhan bisnis yang sudah direncanakan.
