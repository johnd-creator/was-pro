# Persiapan kontrak klien Kotlin

Ini panduan integrasi berdasarkan sumber BE03, bukan implementasi aplikasi
Android atau kontrak final. Baca [batas baseline](README.md) dan
[pagination](pagination.md) sebelum membentuk DTO. BE04 belum termasuk.

## Yang bisa disiapkan sekarang

1. Pisahkan DTO per endpoint: backlog hauling adalah waste record dengan
   informasi hauling, sedangkan pending hauling berisi pengajuan hauling.
   Jangan menggunakan satu DTO hanya karena path diawali `waste-haulings`.
2. Bentuk adapter respons yang menerima `meta`/`errors` berupa objek atau
   array kosong. Pisahkan decoding sukses dari decoding error HTTP; sediakan
   fallback bila server/proxy mengirim body di luar envelope JSON.
3. Bedakan tanggal kalender dan timestamp. Jangan menetapkan seluruh field
   waktu ke `Instant`: format approval history tanpa offset belum dapat
   dipetakan ke satu instant secara pasti. Jangan menggeser tanggal
   operasional karena konversi zona waktu perangkat.
4. Pertahankan ID sebagai string dan properti nullable. Toleransi field
   tambahan membantu kompatibilitas; field penting yang hilang tetap perlu
   menghasilkan error terkontrol, bukan data default yang menyesatkan.
5. Paging hanya untuk endpoint yang benar-benar memakai `meta.pagination`.
   Pertahankan filter saat ganti halaman dan deduplikasi ID di tampilan bila
   daftar berubah. Jangan menganggap `meta.count` sebagai total paginator.
6. `PATCH /profile` bukan partial-field update saat ini: Form Request memakai
   `ProfileValidationRules` yang mewajibkan `name` dan `email`. Hindari
   menjanjikan pengiriman hanya salah satu field tanpa perubahan backend.

Sumber untuk poin 1–6: controller dan helper yang dirujuk di dua dokumen
tersebut, `ProfileController::update`,
`app/Http/Requests/Settings/ProfileUpdateRequest.php`, dan
`app/Concerns/ProfileValidationRules.php`.

## Kriteria sebelum kontrak dibekukan

| Area | Bukti/keputusan yang masih diperlukan |
| --- | --- |
| DTO per endpoint | Schema data sukses 42 operasi, bentuk laporan parsial 10 operasi, dan body 19 operasi dari sumber tersedia, beserta contoh sintetis; lihat `payload-coverage.md` untuk bidang yang tetap parsial |
| OpenAPI | 61 operasi terinventarisasi; pemeriksaan meta-schema resmi dan fixture sintetis lulus. DTO laporan/identity lengkap, semantik semua error dan integration contract tests masih diperlukan |
| Pagination | Putusan kompatibilitas untuk koleksi besar; tes data bertanggal sama, batas halaman dan mutasi antarhalaman |
| Error | Penanganan konsisten atau dokumentasi eksplisit untuk exception framework di luar helper; uji body non-JSON |
| Waktu dan angka | Kesepakatan offset timestamp history, presisi jumlah serta pembulatan tampilan; hindari asumsi `Float` untuk akumulasi bisnis |
| Upload | Verifikasi native multipart PUT atau metode alternatif pada stack klien/server sebenarnya sebelum didokumentasikan sebagai didukung |
| Sesi dan keamanan | BE04 diselesaikan dan ditinjau terpisah; jangan menganggap fitur kandidat tersedia pada main |
| Lampiran | Kontrak akses/download dan pengujian terkait tetap ditunda; path tersimpan bukan janji URL yang dapat diunduh |
| QA perangkat | Pengujian perangkat/operator, jaringan terputus, retry dan error pengguna; belum dilakukan |

Offline write belum diaktifkan. Tidak ada jaminan sinkronisasi background,
refresh otomatis, atau retry mutasi umum dalam dokumen ini. Persiapan DTO dan
UI pembacaan dapat berjalan, tetapi integrasi fitur yang masih ditunda harus
menunggu kontrak dan bukti tersendiri. Jangan menyatakan BE05 selesai atau
aplikasi siap produksi berdasarkan paket dokumentasi ini.
