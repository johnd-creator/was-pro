# Product Requirements Document (PRD): Waspro App

## 1. Pendahuluan
Aplikasi Waspro adalah sistem manajemen pengelolaan dan pencatatan limbah secara komprehensif. Menindaklanjuti kebutuhan digitalisasi yang lebih mumpuni, sistem ini akan dirancang ulang menggunakan **API-First Architecture** dengan **Laravel 12** sebagai sistem backend yang kuat, **PostgreSQL** sebagai basis data yang relasional dan tangguh, dan **Flutter** sebagai aplikasi mobile untuk para petugas atau approver di lapangan.

## 2. Tujuan (Goals)
- Memisahkan logic bisnis ke dalam sistem backend REST API/Services (Laravel).
- Menyediakan aplikasi mobile (Flutter) multi-platform (Android & iOS) agar pengguna di Unit Pembangkit/Lapangan dapat memantau, mencatat, dan meng-approve pergerakan limbah dengan mudah tanpa harus membuka laptop/komputer.
- Memaksimalkan PostgreSQL untuk integrasi data tingkat lanjut, skalabilitas, dan konsistensi data.

## 3. Pengguna & Peran (Roles)
1. **Super Admin / Admin Pusat**: Mengelola pengaturan sistem (settings), master data referensi, akun pengguna, sistem peran (role), dan memonitor audit trail.
2. **Admin Unit Pembangkit**: Mengelola data log (penyimpanan & pengangkutan) yang spesifik berada pada otorisasi / ruang lingkup Unit mereka sendiri.
3. **Approver (Manajer/Supervisor)**: Melakukan persetujuan status limbah (approve/reject). Approval bisa berjenjang sesuai dengan *Workflow Settings*.
4. **Operator / Petugas Lapangan**: Dapat menciptakan entri log limbah baru, meng-upload bukti foto dengan cepat menggunakan aplikasi seluler Flutter.

## 4. Fitur Utama

### 4.1 Master Data Management (Web Admin)
- Perusahaan Penghasil & Unit Pembangkit.
- Pengguna Sistem & Peran.
- Katalog Limbah: Jenis Limbah, Karakteristik Limbah, dan Kategori Kegiatan Sumber.

### 4.2 Manajemen Limbah Terpadu
- **Penyimpanan Limbah (TPS)**: Pencatatan awal limbah yang masuk, lengkap dengan informasi tanggal, shift, berat/volume, sumber limbah, dan lokasi blok TPS. Sistem secara otomatis menghitung *Expiry Date* maksimum (contoh: 90 hari, 180 hari, 365 hari tergantung regulasi jenis limbah).
- **Pengangkutan Limbah**: Pencatatan limbah yang keluar dari TPS melalui pihak ketiga (Transportir), lengkap dengan input manifest number dan serah terima dokumen limbah.
- **Workflow Approval Engine**: Persetujuan bertahap (single/multi-layer) atau bulk-approval terhadap status limbah (misal: "Draft" -> "Disetujui Disimpan" -> "Siap Diangkut").

### 4.3 Notifikasi & Real-time Warning
- Notifikasi push (di mobile) dan in-app notification untuk limbah yang mendekati hari batas kadaluarsa di TPS (**Near Expiry Notification**).
- Notifikasi task approval tertunda kepada Manajer/Supervisor.

### 4.4 Pelaporan (Reports & Dashboard)
- Dashboard visual interaktif untuk rekapitulasi limbah per unit atau entitas.
- Custom report limbah tahunan/bulanan, report per kategori limbah, serta export format industri (PDF, Excel, SCV).

### 4.5 Keamanan & Pengaturan Audit
- **Audit Log Terpusat**: Merekam semua aktivitas spesifik (Siapa yang mengubah, log data sebelum, log data sesudah).
- **Settings Konfigurasi System**: Konfigurasi parameter expiry, konfigurasi unggahan dokumen (size dan tipe), parameter workflow approval.

## 5. Non-Functional Requirements & Lingkup Kerja
- **Arsitektur Hibrida**: Laravel diatur untuk menyajikan web portal untuk *Administrative Office* melalui antarmuka web yang aman (contoh Inertia/Vue atau Livewire) disamping juga membuka `/api/v1/...` routes untuk melayani aplikasi **Flutter**.
- **Autentikasi Aman**: Flutter menggunakan arsitektur Token JWT/Sanctum yang divalidasi dan dicabut saat log-out melalui *Bearer token*. Backend memiliki validasi ketat dan Role-based middleware untuk seluruh endpoints REST.
- **Efisiensi Database**: Pada PostgreSQL akan diimplementasikan *indexing* terhadap status, *foreign keys cascading* di mana diperlukan, dan *soft deletes* guna memelihara rekam jejak historis yang tidak boleh sepenuhnya dihapus permanen.
