# Cakupan payload dan bukti validasi statis

Baseline sumber: `e65c837060beafac9340fc9641e2db8c9cd0b24d` (BE03).
Seluruh contoh pada schema dan request body adalah **data sintetis**; tidak
diambil dari aplikasi, akun, atau database. Tidak ada request dikirim ke server.

## Payload yang dimodelkan

| Bagian | Schema/cakupan |
| --- | --- |
| Waste record | Semua key `serializeWasteRecord`, termasuk nested waste type/actor, status kedaluwarsa, kuantitas turunan, dan hauling history |
| Hauling | Backlog record berbeda dari hauling; detail/history dan pending approval memakai bentuk yang sesuai |
| FABA production/adjustment | Semua key serializer movement, tanggal, angka, state yang dikembalikan, nullable actor, duplicate warning |
| FABA utilization | Semua key serializer response; non-file request fields tersedia sebagai `FabaUtilizationFieldsPartial`, tanpa mengklaim kontrak upload lengkap |
| Workflow payload | Field catatan approval/rejection/reopen, submit year/month, response movement status, serta bentuk metadata periode; tidak menjelaskan atau mengubah kebijakan otorisasi |
| Opening balance/TPS | Field input dari Form Request; response opening balance dan response kapasitas. Nested `capacity_summary` tetap ditandai belum dimodelkan lengkap |
| Pembacaan laporan/options | Tambahan 10 operasi memiliki bentuk data dari sumber: options FABA, index/history/review periode, dashboard, monthly/yearly recap, vendor recap, balance dan stock card. Nested snapshot/audit/raw-model serta sejumlah rangkuman tetap ditandai parsial |

Schema tersebut dipakai oleh **42 operasi sukses**. **19 operasi** memiliki
request body JSON, dengan required fields, tipe, batas panjang/angka dan enum
yang dapat ditelusuri dari sumber. Enam serializer dan 15 Form Request dicek
field-nya oleh `check_contract.py`. Keberadaan key pada serializer dinyatakan
sebagai `required`; field tersebut tetap dapat bernilai null jika schema
mengizinkannya. Objek relasi Eloquent mengizinkan field tambahan; tidak ada
janji bahwa representasi relasi mentah selalu hanya memiliki dua field.

Semua 61 operasi tetap tercakup sebagai rute. Selain 42 operasi di atas,
**10 operasi laporan/options** kini memiliki bentuk data yang dimodelkan
sebagian dari controller/service. **9 operasi identity** (auth, bootstrap,
profile) tetap memakai respons generic/belum lengkap dan tidak dikerjakan
melalui paket operasional ini. Schema per endpoint tidak boleh dianggap
lengkap hanya karena rutenya tercantum. Laporan sengaja menyisakan schema
terbuka untuk beberapa nilai nested, raw model dan payload snapshot historis.

## Aturan yang tidak bisa digantikan schema statis

- Laravel `numeric` menerima angka JSON dan numeric string. Batas pada cabang
  angka dimodelkan; batas nilai string numerik tetap diperiksa server dan
  dijelaskan pada schema. Jangan memakai lolos schema sebagai bukti request
  pasti diterima aplikasi.
- Laravel `date` menerima lebih dari format canonical `YYYY-MM-DD`.
  `before_or_equal:today` bergantung waktu server; tidak diubah menjadi batas
  tanggal permanen dalam spec.
- `exists`, periode/status, stok, sisa hauling, serta relasi antarnilai TPS
  memerlukan data/konteks server. Contoh sintetis tidak membuktikan UUID
  referensi ada atau transaksi dapat dilakukan.
- Server mensyaratkan `critical_threshold >= warning_threshold`; perbandingan
  dua field ini didokumentasikan dalam deskripsi. Schema membatasi angka
  individual, bukan mengimplementasikan logika server.
- Conditional external/internal utilization dijelaskan pada schema non-file.
  Kontrak upload/media type dan akses lampiran tetap ditunda; operasi write
  utilization sengaja belum diberi request body seolah siap dipanggil.
- Bentuk body 422 validasi dicatat untuk operasi Form Request yang sudah
  dimodelkan. Prioritas error izin/status/data serta keseluruhan error per
  endpoint belum diverifikasi melalui HTTP.
- `allowed_actions` dan flag periode hanya didokumentasikan sebagai nilai
  respons server. Tidak ada model policy baru, perubahan izin, atau pengujian
  BE04 yang disisipkan melalui pekerjaan ini.

## Validasi yang sudah dilakukan

`jsonschema` **4.19.2** sudah terpasang. Tidak ada dependency yang diinstal.
Meta-schema resmi OpenAPI 3.0 diambil dari
[OpenAPI Initiative, schema 2024-10-18](https://spec.openapis.org/oas/3.0/schema/2024-10-18).
SHA-256 berkas resmi:

```text
2385f5bbb8c37878daae73baeabe7f34b2f022a4a8c049329ee61f71796f039c
```

Salinan validasi disimpan di workspace tugas, di luar repository. Script
validator memverifikasi fingerprint sebelum membaca schema tersebut dan
tidak mengunduh apa pun. Untuk menjalankan ulang dengan salinan yang sudah ada:

```sh
python -B docs/api/check_contract.py
python -B docs/api/validate_spec.py --meta-schema /path/to/oas-3.0-schema-2024-10-18.json
```

Hasil: meta-schema resmi lulus; **19 contoh schema sintetis** lulus; **8 kasus
negatif** ditolak sesuai schema: field quantity hilang, quantity negatif,
quantity hauling nol, tipe movement tidak valid, note adjustment kosong,
bulan 13, alasan rejection terlalu pendek, dan warning threshold 100.
Contoh memakai adapter `nullable` OpenAPI 3.0 ke union null Draft 4 untuk
pemeriksaan data lokal. Adapter ini bukan engine validasi server.

Pemeriksaan statis tambahan mencakup method/path/operationId, parameter path,
referensi lokal, fingerprint sumber, kesesuaian field request/serializer,
serta tautan dokumentasi. Hasil ini membuktikan struktur spec dan konsistensi
sumber yang diperiksa, **bukan kesesuaian respons aplikasi yang berjalan**.
Validator semantik khusus `openapi-spec-validator`/`openapi-schema-validator`
tidak terpasang; tidak dipasang diam-diam. Meta-schema resmi diperiksa penuh
dengan validator JSON Schema yang tersedia, dilengkapi pemeriksaan lokal.

## Pekerjaan yang masih terpisah

Detail nested laporan/identity yang belum dimodelkan, kontrak semua filter query,
validasi semantik OpenAPI lanjutan, generator SDK Kotlin dan integration
contract tests masih diperlukan sebelum freeze. Keamanan BE04 dan lampiran
tetap ditunda sesuai batas tugas. Offline write, deployment, migrasi database,
serta perubahan akun/layanan tidak dilakukan. Ini belum menyelesaikan BE05
keseluruhan dan tidak menyatakan kesiapan produksi.
