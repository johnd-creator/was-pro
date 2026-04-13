# Perbaikan UX Dashboard Kepatuhan

## Ringkasan
Dashboard ini sudah memiliki fondasi yang cukup rapi, tetapi secara visual masih terasa **top-heavy** karena hero card merah terlalu besar dan terlalu dominan. Akibatnya, perhatian pengguna terlalu banyak tersedot ke area alert, sementara KPI utama, tren operasional, dan area kerja keputusan justru terasa sekunder.

Arah perbaikannya bukan membongkar total desain, tetapi **mereposisi hierarki visual** agar dashboard terasa lebih **enterprise, operasional, tegas, dan nyaman dipakai jangka panjang**.

## Penilaian Maturitas Saat Ini
**Functional but inconsistent**

### Kenapa
- Struktur section sudah jelas: hero, snapshot, trend monitor, composition, workflow.
- Card dan chart container relatif rapi dan readable.
- Informasi inti sebenarnya sudah lengkap.

### Masalah utama
- Hero terlalu besar, terlalu merah, dan terlalu dekoratif.
- Semantic color merah digunakan terlalu agresif.
- Ritme antar section belum seimbang.
- Beberapa area terasa seperti kumpulan widget cantik, belum seperti satu sistem dashboard enterprise yang solid.
- Workflow section bagian bawah terlalu tinggi dan kurang actionable.

---

## Masalah UX Utama

### 1. Hero terlalu besar dan mengganggu scanning
Hero merah memakan terlalu banyak viewport pertama. Untuk dashboard operasional, pengguna seharusnya bisa langsung membaca:
- status penting
- KPI utama
- tren utama
- antrian keputusan

Saat hero terlalu dominan, alur baca jadi tertahan.

### 2. Severity terlalu dilebihkan
Merah penuh sebagai latar besar memberi rasa “selalu darurat”. Ini berisiko membuat fatigue visual dan menurunkan kualitas scan jangka panjang.

### 3. Hierarki visual tidak seimbang
Bagian atas terlalu berat, sedangkan section bawah lebih datar. Halaman jadi tidak punya ritme visual yang dewasa.

### 4. Sistem visual belum sepenuhnya konsisten
Snapshot card, chart card, hero, dan queue panel masih terasa seperti style yang bagus sendiri-sendiri, tetapi belum sepenuhnya satu bahasa visual.

### 5. Workflow section belum terasa seperti pusat aksi
Queue dan daftar keputusan belum cukup padat, belum cukup actionable, dan masih terlalu banyak area kosong.

---

## Arah Visual yang Direkomendasikan
**Operational Enterprise**

### Karakter
- tenang
- tegas
- profesional
- data-first
- alert-aware, bukan alert-dominated

### Prinsip
- status kritis tetap jelas
- KPI dan tren tetap jadi pusat scanning
- warna semantik dipakai disiplin
- layout lebih firm, bukan dekoratif
- dashboard harus nyaman dipakai dalam sesi panjang

---

## Perbaikan Utama yang Harus Dilakukan

## 1. Refactor Hero Menjadi Compact Critical Summary
### Masalah sekarang
- terlalu tinggi
- terlalu merah
- terlalu banyak area dekoratif
- value informasinya tidak sebanding dengan ukurannya

### Solusi
Ubah hero menjadi **compact critical summary bar**.

### Struktur baru
#### Baris 1
- badge status: `Kepatuhan Kritis`
- title: `Tinjau Risiko Kepatuhan`
- deskripsi singkat
- CTA utama: `Tinjau Catatan Kritis`

#### Baris 2
4 indikator kritis dalam bentuk compact summary cards:
- Melewati Batas Simpan
- Mendekati Batas Simpan
- Pending Approval
- Peringatan FABA

### Aturan visual
- turunkan tinggi hero sekitar 35–45%
- hilangkan konsep full red billboard
- gunakan surface netral terang
- pakai merah hanya untuk:
  - badge kritis
  - angka kritis
  - border alert
  - icon alert
  - CTA kritis

### Outcome
- alert tetap jelas
- halaman lebih cepat discan
- snapshot KPI kembali naik nilainya
- dashboard terasa lebih enterprise

---

## 2. Tambahkan Page Header yang Lebih Formal
### Tujuan
Agar halaman terasa sebagai produk enterprise yang matang, bukan hanya kumpulan widget.

### Struktur
- title: `Dashboard Kepatuhan & Operasional`
- subtitle singkat
- area kanan untuk:
  - period filter
  - unit/site selector
  - refresh/export bila perlu

### Dampak
- memperjelas konteks halaman
- memisahkan identitas halaman dari alert state
- memperbaiki struktur top section

---

## 3. Rapikan Snapshot KPI Cards
### Yang sudah bagus
- secara grid sudah enak dilihat
- angka utama cukup kuat
- variasi topik data sudah berguna

### Yang harus dibenahi
- samakan ritme internal semua card
- samakan posisi badge, title, value, unit, dan meta text
- kurangi variasi warna pastel yang terlalu banyak intensitas berbeda
- fokus pada satu angka utama dan satu keterangan pendukung

### Standar baru
Setiap KPI card berisi:
- icon kecil
- label
- angka utama
- unit kecil
- supporting note 1 baris

### Tujuan
Agar row KPI terasa seperti satu family component yang konsisten.

---

## 4. Upgrade Trend Monitor Menjadi Lebih Enterprise
### Yang dipertahankan
- layout 2 card per row
- summary angka di atas chart
- komparasi dua variabel

### Yang ditingkatkan
- header chart lebih ringkas
- summary badges lebih tegas dan kurang dekoratif
- grid dan axis lebih subtle
- legend lebih disiplin
- warna chart tidak terlalu saturated
- perjelas hierarchy antara chart title, chart summary, dan supporting note

### Tujuan
Membuat area trend terasa seperti alat analisis operasional, bukan presentasi.

---

## 5. Perbaiki Composition Section
### Masalah sekarang
- donut chart terasa agak terlalu dekoratif
- visual masih sedikit “soft SaaS”
- ruang kanan belum dipakai maksimal

### Solusi
#### Opsi A
Tetap pakai donut tetapi:
- kecilkan chart
- perbesar legend dan angka
- pertegas persentase dan total

#### Opsi B
Ubah ke stacked horizontal bar bila ingin lebih enterprise dan cepat discan.

### Rekomendasi
Untuk dashboard operasional, stacked bar sering lebih kuat daripada donut besar.

---

## 6. Ubah Workflow Menjadi Actionable Work Queue
### Masalah sekarang
- terlalu tinggi
- terlalu banyak whitespace
- daftar belum terasa actionable
- belum mencerminkan pusat keputusan operasional

### Solusi
Buat dua panel queue yang lebih padat:
- Catatan Limbah Butuh Review
- Approval FABA Menunggu Keputusan

### Setiap item minimal berisi
- ID / nomor referensi
- judul
- metadata penting
- umur / SLA
- status badge
- quick action

### Tujuan
Agar area bawah halaman benar-benar terasa sebagai ruang kerja keputusan, bukan hanya wadah daftar.

---

## Perbaikan Design Tokens

## Color
### Aturan baru
- neutral: default surface
- blue: info / produksi / data umum
- green: valid / processed / aman
- amber: warning / mendekati batas
- red: critical only

### Larangan
Merah tidak dipakai untuk container besar penuh kecuali benar-benar emergency-first scenario.

---

## Typography
### Masalah sekarang
- terlalu banyak small uppercase label berpotensi menambah noise
- hierarchy belum sepenuhnya disiplin

### Arah perbaikan
- page title: jelas dan tegas
- section title: medium-bold
- KPI number: dominan
- micro label: dibatasi
- uppercase hanya untuk label tertentu yang benar-benar perlu

---

## Spacing
### Yang perlu diperbaiki
- hero terlalu boros vertical space
- workflow section terlalu lega
- beberapa section masih terlalu presentational

### Arah
Gunakan mode **comfortable-compact enterprise**:
- tidak terlalu rapat
- tidak terlalu airy
- cocok untuk operator dan supervisor

---

## Radius, Border, dan Shadow
### Rekomendasi
- kurangi radius agar lebih firm
- gunakan border netral yang jelas
- kurangi shadow lembut berlebihan
- prioritaskan clarity of edge dibanding dekorasi

### Tujuan
Agar UI terasa lebih dewasa, lebih tegas, dan lebih trustable.

---

## Theme Direction
### Recommended direction
**Operational Enterprise**

### Kenapa
Karena konteks halaman ini adalah:
- monitoring
- compliance
- approval
- operasional harian
- ritme kerja jangka panjang

UI harus terasa:
- stabil
- tegas
- rapi
- bisa dipercaya
- tidak melelahkan

---

## Prioritas Implementasi

## Immediate fixes
1. kecilkan hero 35–45%
2. ubah hero menjadi compact critical summary
3. hilangkan full red background besar
4. rapikan CTA dan indikator kritis
5. padatkan workflow section

## Short-term cleanup
1. standarisasi KPI card
2. standarisasi chart card
3. disiplinkan semantic colors
4. rapikan border, radius, dan shadow
5. sederhanakan micro-label

## Medium-term rollout
1. definisikan token warna semantik
2. definisikan token spacing dan radius
3. bangun standard component:
   - page header
   - critical summary bar
   - KPI card
   - chart card
   - queue item
   - status badge
4. rapikan shared component di Vue / Blade / Inertia

---

## Hasil Akhir yang Dituju
Setelah perbaikan, dashboard harus terasa:
- lebih enterprise
- lebih tenang
- lebih cepat discan
- lebih profesional
- lebih actionable
- lebih nyaman untuk penggunaan jangka panjang

---

# Checklist Eksekusi Cepat
- [ ] Ubah hero besar menjadi compact summary bar
- [ ] Kurangi penggunaan merah sebagai background utama
- [ ] Naikkan prioritas visual KPI row
- [ ] Rapikan konsistensi seluruh card
- [ ] Tingkatkan ketegasan chart container
- [ ] Ubah workflow menjadi actionable queue
- [ ] Standarkan semantic colors
- [ ] Standarkan radius, border, shadow, dan spacing
