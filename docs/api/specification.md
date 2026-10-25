# Status dan pemeriksaan spesifikasi OpenAPI

`openapi.json` memakai format OpenAPI 3.0.3. Ini **inventaris kontrak parsial**
berdasarkan `main` commit `e65c837060beafac9340fc9641e2db8c9cd0b24d`,
bukan kontrak final untuk menghasilkan SDK Kotlin.

## Cakupan yang tersedia

- 61 operasi pada 47 path; nama rute menjadi `operationId` unik.
- Server relatif `/api/v1`; tidak mencantumkan host lingkungan atau kredensial.
- Parameter placeholder path yang diwajibkan oleh bentuk URL. ID string tidak
  diberi janji validasi UUID baru; parameter year/month mengikuti integer pada
  handler, tanpa mengarang batas nilai.
- Envelope sukses umum, error helper umum, dan metadata enam operasi pagination.
  Schema error merupakan referensi bentuk umum; belum dipasangkan ke setiap
  kombinasi endpoint/status yang belum ditelusuri.
- Schema data sukses untuk 42 operasi dan request body untuk 19 operasi,
  berdasarkan serializer/Form Request; lihat [cakupan payload](payload-coverage.md).
- Bentuk data dari sumber untuk 10 operasi baca laporan/options tambahan;
  nested raw-model/snapshot/rangkuman yang belum lengkap diberi penanda parsial.
- Referensi controller, nama method, baris deklarasi rute, dan fingerprint
  34 berkas sumber. Fingerprint SHA-256 memakai teks UTF-8 dengan newline LF
  agar checkout Windows/Linux dapat dibandingkan.

Schema generic `SuccessEnvelopePartial.data` dan `PaginatedSuccessPartial`
tetap tersedia sebagai fallback; 42 operasi sudah memakai envelope dengan
data operasional yang dimodelkan dan 10 operasi baca memiliki bentuk data
laporan parsial. Jangan menafsirkan schema kosong sebagai
jaminan objek kosong. Operasi non-auth memiliki status sukses helper 200 atau
201 sesuai controller; respons auth hanya diinventarisasi sebagai rute dan
ditandai belum dispesifikasikan lebih lanjut. Setiap operasi memiliki
`x-contract-gaps` dan respons `default` tanpa asumsi media type/body.

Parameter query di spec terbatas pada `page` dan `per_page` untuk enam
endpoint berpaginasi. Filter tambahan yang dibaca controller ada pada
`pagination.md`, belum semuanya dimodelkan di spec. Di luar body/DTO yang
sudah dicakup, request body/media type, field DTO dan daftar error lengkap
masih perlu ditelusuri. Omission request body bukan berarti endpoint menerima
body kosong. Omission metadata security bukan berarti endpoint publik.

Pekerjaan token/sesi/otorisasi BE04 dan keamanan akses lampiran tetap ditunda.
Tidak ada fitur kandidat BE04 yang dimasukkan, diimplementasikan ulang, atau
diuji melalui dokumen ini.

## Menjalankan pemeriksaan statis

Dari root repository, dengan Python 3.9 atau lebih baru yang sudah tersedia:

```sh
python -B docs/api/check_contract.py
```

Script hanya memakai standard library, membaca spec dan sumber PHP sebagai
teks. Script tidak menjalankan PHP/Laravel, tidak membaca `.env`, tidak
mengakses jaringan atau database, dan tidak menulis berkas. Hindari opsi
Python `-O`: pemeriksaan memakai `assert`.

Pemeriksaan mencocokkan method/path terhadap deklarasi sumber, keunikan
operationId/nama rute, keberadaan method handler, parameter path wajib,
referensi schema lokal, penanda celah per operasi, enam operasi pagination,
serta fingerprint sumber. Field enam serializer dan 15 Form Request juga
dibandingkan dengan properti schema. Parser sengaja terbatas pada sintaks deklarasi
eksplisit yang ada pada baseline ini; pola rute baru harus ditinjau dan
parser diperbarui, bukan diabaikan.

`check_contract.py` memeriksa konsistensi sumber. Pemeriksaan meta-schema
resmi dan contoh sintetis dijalankan terpisah dengan `validate_spec.py`;
lihat [perintah dan hasil](payload-coverage.md). Keduanya bukan integration
test. Tidak ada HTTP request, tes database, tes keamanan atau generator klien
yang dijalankan. Perubahan
sumber yang menyebabkan fingerprint berbeda memerlukan peninjauan kontrak;
jangan hanya memperbarui hash untuk membuat pemeriksaan lulus.

## Sebelum pembekuan kontrak

Lengkapi DTO/request/response per operasi dari sumber dan fixture, verifikasi
media type serta status aktual melalui pengujian terisolasi yang diizinkan,
jalankan ulang pemeriksaan meta-schema/konsistensi, dan tinjau hasil generator
Kotlin. Validator semantik OpenAPI khusus belum tersedia di lingkungan ini;
tidak ada dependency baru yang dipasang. Kontrak keamanan dan lampiran
memerlukan penyelesaian terpisah.
Tidak ada dukungan offline write atau kesiapan produksi yang dinyatakan.
