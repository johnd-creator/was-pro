# Kontrak API: catatan implementasi saat ini

Dokumentasi ini berdasarkan `main` pada commit
`e65c837060beafac9340fc9641e2db8c9cd0b24d` (BE03). Ini inventaris sumber,
bukan pembekuan kontrak, spesifikasi OpenAPI lengkap, atau bukti pengujian
runtime baru. Kandidat BE04 belum termasuk. Jangan menganggap fitur yang
masih direncanakan sudah tersedia.

- [Inventaris rute](routes.md): method, path, nama rute, dan handler.
- [Pagination dan kompatibilitas](pagination.md).
- [Persiapan klien Kotlin](kotlin-readiness.md).
- [OpenAPI parsial](openapi.json) dan [cara pemeriksaan statis](specification.md).
- [Cakupan payload, contoh sintetis, dan hasil validasi](payload-coverage.md).

## Alamat dan format respons

Prefix rute adalah `/api/v1`, dari `bootstrap/app.php` dan `routes/api.php`.
Alamat host harus berasal dari konfigurasi lingkungan klien. Dokumen ini
tidak menetapkan alamat deployment.

`app/Http/Controllers/Api/ApiController.php` membentuk respons sukses:

```json
{"success": true, "message": null, "data": null, "meta": []}
```

`data` dapat berupa objek, array, atau null sesuai endpoint. `message` dapat
null. `meta` kosong diserialisasi sebagai `[]`, sedangkan meta berisi field
menjadi objek JSON. Jangan memaksakan semua respons memakai satu bentuk
`data`, atau menganggap meta selalu objek. Contoh di atas adalah ilustrasi
struktur helper, bukan rekaman respons server.

Respons error dari helper:

```json
{"success": false, "message": "Pesan untuk pengguna", "error_code": "VALIDATION_ERROR", "errors": {"quantity": ["Pesan validasi"]}}
```

`errors` kosong adalah `[]`; validasi field menghasilkan objek berisi array
pesan. Pesan berbahasa Indonesia dapat berubah; logika klien sebaiknya memakai
status HTTP dan `error_code` bila tersedia.

## Status dan keterbatasan normalisasi error

| Status | Perilaku yang terlihat dalam sumber |
| --- | --- |
| 200 | Pembacaan dan sebagian besar perubahan; delete sukses tetap memakai body helper, bukan 204 |
| 201 | Pembuatan waste record, hauling, production, utilization, adjustment |
| 401 | Penanganan autentikasi API; kontrak lifecycle token masih menunggu BE04 |
| 403 | Penolakan izin eksplisit dari controller memakai `FORBIDDEN` |
| 404 | Model/path tidak ditemukan memakai `NOT_FOUND` melalui exception renderer |
| 409 | Konflik status/periode/stok memakai `CONFLICT` |
| 422 | Form Request memakai `VALIDATION_ERROR` dan pesan per field |

Sumber: `ApiController.php`, controller API terkait, dan renderer dalam
`bootstrap/app.php`. Tidak semua error framework dinormalisasi: renderer
yang ada tidak menetapkan kontrak seragam untuk setiap 403, 405, 429, atau
500. Klien harus bisa menangani body yang bukan envelope, body kosong, dan
respons non-JSON tanpa gagal parsing. Daftar ini bukan jaminan bahwa setiap
endpoint menghasilkan semua status tersebut. Filter GET yang dikonversi
melalui `Request::integer/string` bukan otomatis validasi Form Request;
jangan menjanjikan 422 untuk semua input query yang tidak valid.

## Tanggal dan identitas

- Perlakukan ID sebagai string buram; jangan mengubah UUID menjadi angka.
- Tanggal operasional seperti `date`, `hauling_date`, dan `transaction_date`
  berbeda dari timestamp. Serializer waste dan movement FABA memakai format
  `Y-m-d` untuk field tersebut. Pertahankan bentuk field per endpoint saat
  menyusun DTO.
- Banyak timestamp memakai `toIso8601String()`. Namun
  `FabaApprovalController::serializeApprovalModel` memakai
  `Y-m-d H:i:s` untuk `submitted_at`, `approved_at`, `rejected_at`; format itu
  tidak memiliki offset zona waktu. Jangan menambahkan `Z` atau menganggapnya
  UTC tanpa kontrak backend yang disepakati.
- Field tanggal/timestamp dapat null. `meta.server_time` tersedia di banyak
  respons, tetapi tidak semua respons.

## Batas dokumen ini

Tidak ada perubahan route, query, validasi, autentikasi, token, otorisasi,
penyimpanan atau akses lampiran dalam paket dokumentasi ini. BE04 tetap
ditunda. Inventaris OpenAPI parsial tersedia; OpenAPI lengkap,
verifikasi DTO per endpoint, runtime contract test,
akses lampiran, serta keputusan freeze masih pekerjaan berikutnya. Tidak ada
dukungan offline write yang diaktifkan atau dijanjikan.
