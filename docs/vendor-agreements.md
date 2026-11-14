# Vendor dan MoU

## Ruang lingkup

Master Vendor mendukung peran Pengangkut, Pengelola, dan Pemanfaat secara independen. Satu perusahaan dapat memiliki beberapa peran. Migrasi tidak memberi peran otomatis pada vendor lama: nilai null berarti belum ditetapkan. Peran dapat dilengkapi melalui penyuntingan yang berwenang atau impor terbatas yang telah disetujui. Pengelola bukan sinonim Pemanfaat.

MoU menghubungkan satu pengangkut dengan satu pengelola/pemanfaat. Satu pengangkut dapat mempunyai banyak MoU, dan satu perusahaan boleh berada di kedua sisi. Nomor dokumen unik per pasangan vendor dan klasifikasi B3/Non B3. Tidak ada impor otomatis atau perubahan transaksi terdahulu.

Status dokumen: Draf, Tercatat, Dicabut. Tercatat mewajibkan tanggal awal dan akhir. Masa berlaku ditampilkan terpisah dan inklusif pada tanggal akhir. Status tanggal bukan verifikasi izin atau pengesahan hukum. Tanpa data, tampilkan MoU belum tercatat / tanggal belum lengkap.

## Akses dan integritas

- Baca melalui izin vendors.view; tambah melalui vendors.create; ubah melalui vendors.edit.
- Konteks organisasi mengikuti middleware tenant yang sudah ada; referensi vendor divalidasi di schema tenant aktif.
- Pilihan baru wajib vendor aktif dengan peran sesuai. Pihak historis yang nonaktif boleh dipertahankan ketika mengubah MoU yang sama.
- Vendor dengan referensi MoU tidak boleh dihapus. Tidak tersedia penghapusan MoU; gunakan status Dicabut untuk mempertahankan riwayat.
- Klien vendor lama yang tidak mengirim peran tidak menghapus peran yang tersimpan.

## Status terverifikasi (13 November 2026)

Migrasi vendor/MoU telah diterapkan pada ketiga tenant yang diperiksa: TWMS, TWMSDEMO, dan UBPBSLA. Pemeriksaan baca-saja mengonfirmasi catatan migrasi, tabel `vendor_agreements`, dan ketiga kolom peran vendor. Jangan menjalankan ulang migrasi yang sudah tercatat atau melakukan reset database.

Impor terpisah yang diizinkan untuk UBPBSLA telah menambahkan 14 vendor, melengkapi peran 5 vendor existing, dan mencatat 15 MoU (5 B3, 10 Non B3). Total setelah impor: 29 vendor dan 15 MoU. Verifikasi baca-saja mencocokkan pihak, nomor dokumen, tanggal, klasifikasi, peran, dan provenance ke daftar sumber. Pengulangan dengan sumber yang sama tidak memerlukan pembuatan atau perubahan. Pada tanggal pemeriksaan, 14 MoU berada dalam rentang tanggal dan 1 telah berakhir; angka ini bukan pengesahan legal dan akan berubah mengikuti tanggal.

Dua baris sumber tetap belum diimpor karena identitas belum dipastikan: baris 7, nama Conch Cement Indonesia dibanding Conch Cement existing; baris 11, Putra dibanding Putri Simeuleu Cut. Jangan menggabungkan atau mengimpor identitas tersebut tanpa konfirmasi. Tidak ada reset atau impor transaksi limbah/FABA. Backup lengkap sebelum impor telah diverifikasi dengan decoding archive; belum dilakukan restore rehearsal. Fingerprint setelah impor menunjukkan tenant lain dan tabel operasional tidak berubah.

## Rollout untuk environment atau tenant baru

1. Backup database aktif dan verifikasi backup sesuai prosedur proyek.
2. Jalankan migrasi additive tenant `2026_11_13_095204_add_vendor_roles_and_agreements.php` melalui prosedur migrasi tenant yang berlaku setelah persetujuan operator rollout.
3. Periksa ketiga kolom nullable dan tabel vendor_agreements di setiap schema yang ditargetkan.
4. Build frontend dan periksa halaman Vendor dan MoU pada organisasi yang benar.
5. Uji buat vendor, peran ganda, tambah/ubah MoU, validasi tanggal, hak role dan isolasi organisasi menggunakan data uji yang terpisah.

Pada environment yang belum dimigrasi, daftar dan CRUD vendor lama tetap berfungsi. Panel MoU menampilkan status belum tersedia dan kolom peran yang dikirim klien diabaikan sampai schema tersedia. Ini adalah fallback kompatibilitas, bukan status environment aktif di atas. Migrasi hanya menyiapkan struktur; jangan menjalankan impor data atau reset sebagai bagian otomatis rollout. Status build dan publikasi harus diverifikasi terpisah pada deployment yang dituju.

Rollback migration ditolak apabila ada MoU atau nilai peran tersimpan, termasuk false. Gunakan migrasi korektif atau pemulihan terencana, bukan menghapus histori. Setelah rollout, rollback aplikasi saja tetap kompatibel dengan kolom tambahan.

## Pengujian

`tests/Feature/WasteManagement/VendorAgreementTest.php` memakai database uji disposable. Jangan menjalankan bersamaan dengan pengujian tenant lain. Pengujian mencakup multirole, multipihak, keunikan, tanggal, izin operator, referensi lintas tenant, nonaktif historis, schema lama, kompatibilitas klien dan perlindungan rollback.
