export type GuideRole = 'operator' | 'supervisor' | 'admin' | 'super_admin';
export type GuideAction =
    | 'masterTypes'
    | 'fabaMasters'
    | 'stockBalance'
    | 'profile'
    | 'records'
    | 'balance'
    | 'plans'
    | 'haulings'
    | 'production'
    | 'utilization'
    | 'adjustments'
    | 'recaps'
    | 'closing'
    | 'reports'
    | 'vendors'
    | 'audit'
    | 'inbox'
    | 'users'
    | 'roles'
    | 'organizations';
export interface GuideCase {
    id: string;
    title: string;
    category: string;
    summary: string;
    roles: GuideRole[];
    prerequisites: string[];
    steps: string[];
    outcome: string;
    recovery: string;
    action: GuideAction;
}
const everyone: GuideRole[] = [
    'operator',
    'supervisor',
    'admin',
    'super_admin',
];
const reviewers: GuideRole[] = ['supervisor', 'admin', 'super_admin'];
export const guideRoles: { slug: GuideRole; name: string; summary: string }[] =
    [
        {
            slug: 'operator',
            name: 'Operator',
            summary:
                'Template: melihat seluruh catatan B3, membuat serta mengubah/mengajukan catatan sendiri; pengangkutan dan rencana milik sendiri. Menginput FABA, melihat rekap dan mengajukan closing. Tidak menyetujui pengajuan.',
        },
        {
            slug: 'supervisor',
            name: 'Supervisor',
            summary:
                'Template: meninjau catatan B3 dan pengangkutan, mengelola master dasar, input dan review FABA, saldo awal, kapasitas TPS serta closing. Edit catatan B3 tetap milik sendiri. Persetujuan harus oleh orang lain.',
        },
        {
            slug: 'admin',
            name: 'Admin',
            summary:
                'Template: administrasi pengguna dan operasional dalam organisasi sendiri; hak akses dapat ditinjau sesuai permission. Perubahan permission role global hanya oleh akun bertanda Super Admin. Pengelolaan organisasi bukan kewenangan Admin. Hak tambahan tertentu tetap perlu diberikan secara eksplisit.',
        },
        {
            slug: 'super_admin',
            name: 'Super Admin',
            summary:
                'Akun dengan penanda Super Admin dapat mengelola organisasi dan memilih konteks operasional. Nama role saja tidak menggantikan penanda akun. Pemisahan pembuat/pengaju dan pemeriksa tetap berlaku.',
        },
    ];
export const guideCases: GuideCase[] = [
    {
        id: 'master-b3',
        title: 'Menyiapkan kategori, karakteristik dan jenis limbah',
        category: 'Data master',
        roles: reviewers,
        summary:
            'Siapkan referensi dasar sebelum operator mencatat limbah masuk.',
        prerequisites: [
            'Permission view/create/edit untuk master yang dikelola. Hak hapus terpisah; Supervisor standar tidak memiliki hak hapus master dasar.',
        ],
        steps: [
            'Buka Kategori Limbah dan Karakteristik, isi kode/nama serta informasi yang diminta, lalu pastikan referensi yang akan dipakai berstatus aktif.',
            'Buka Jenis Limbah dan pilih kategori serta karakteristik aktif. Isi kode/nama, masa simpan, biaya angkut dan informasi lain sesuai formulir.',
            'Gunakan edit untuk memperbarui master dan status Aktif untuk menonaktifkan referensi yang tidak dipakai lagi.',
            'Periksa pilihan master saat membuat catatan. Tanggal kedaluwarsa catatan berasal dari tanggal masuk dan masa simpan jenis limbah; ini pencatatan operasional, bukan penetapan kepatuhan hukum.',
        ],
        outcome:
            'Catatan menggunakan referensi yang konsisten. Kategori/karakteristik yang terkait jenis dan jenis yang terkait catatan dilindungi dari penghapusan.',
        recovery:
            'Jika referensi tidak muncul, periksa status aktif dan permission. Jangan menghapus transaksi hanya untuk menghapus master yang sudah dipakai; koordinasikan penonaktifan dan peninjauan riwayat.',
        action: 'masterTypes',
    },
    {
        id: 'master-faba',
        title: 'Mengelola tujuan internal dan peruntukan FABA',
        category: 'Data master',
        roles: reviewers,
        summary:
            'Master tambahan dikelola lewat hak khusus tanpa menghapus riwayat.',
        prerequisites: [
            'Hak tambahan faba_master.view untuk membuka; create/edit diperlukan untuk perubahan. Master tersedia pada organisasi aktif.',
        ],
        steps: [
            'Buka Tujuan Internal FABA atau Peruntukan FABA di Data Master.',
            'Untuk referensi baru, isi nama dan slug yang unik serta status Aktif, lalu simpan.',
            'Untuk perubahan, pilih edit dan ubah nama/status sesuai kebutuhan. Slug referensi yang sudah dibuat tidak dapat diganti.',
            'Nonaktifkan referensi yang tidak lagi dipakai. Tidak ada tindakan hapus pada master FABA; transaksi terdahulu tetap tersimpan.',
        ],
        outcome:
            'Referensi aktif dapat digunakan pada transaksi sesuai formulir, sementara hubungan historis tetap dipertahankan.',
        recovery:
            'Jika menu atau tombol tidak tersedia, minta Super Admin memeriksa hak tambahan. Role Admin/Supervisor saja tidak otomatis memberikan hak master FABA.',
        action: 'fabaMasters',
    },
    {
        id: 'pantau-rekap',
        title: 'Membaca dashboard, rekap dan kapasitas TPS',
        category: 'FABA',
        roles: everyone,
        summary:
            'Samakan konteks dan periode sebelum membandingkan ringkasan dengan laporan.',
        prerequisites: [
            'Organisasi aktif dan hak melihat dashboard/rekap sesuai menu. Penetapan saldo awal dan kapasitas TPS memerlukan faba_opening_balance.manage.',
        ],
        steps: [
            'Di Dashboard, pastikan organisasi dan bulan yang dipilih benar. Ringkasan, antrean kerja dan grafik memiliki cakupan masing-masing; baca label sebelum membandingkan angka.',
            'Buka Rekap bulanan atau tahunan dan pilih bulan/tahun yang diperlukan. Telusuri material serta kartu stok untuk memahami pergerakan.',
            'Untuk saldo awal atau kapasitas, buka Saldo TPS FABA pada area Rekap FABA. Hanya pengguna berhak yang dapat menyimpan formulir Set opening balance (saldo awal) dan Kapasitas TPS.',
            'Untuk saldo awal, isi periode, material dan jumlah dalam ton. Untuk kapasitas, pilih material, isi kapasitas ton serta ambang persentase warning/critical; formulir kapasitas tidak memakai periode. Saldo awal adalah penetapan ulang saldo per material, bukan tambahan produksi. Kapasitas TPS adalah kapasitas acuan, bukan saldo fisik atau transaksi masuk.',
        ],
        outcome:
            'Ringkasan dibaca dengan konteks periode yang jelas; saldo dan kapasitas tidak tertukar.',
        recovery:
            'Jika angka berbeda, samakan organisasi, periode, material, status persetujuan dan pilihan versi laporan. Jika perubahan diblokir, periksa penguncian periode/hak akses; jangan memakai saldo awal untuk menutupi selisih.',
        action: 'stockBalance',
    },
    {
        id: 'mulai',
        title: 'Mulai bekerja di organisasi yang benar',
        category: 'Akses & administrasi',
        roles: everyone,
        summary:
            'Kenali konteks organisasi dan bedakan panduan role dari hak akun Anda.',
        prerequisites: [
            'Sudah masuk ke aplikasi. Akun operasional perlu terhubung ke organisasi yang datanya siap.',
            'Jika autentikasi dua faktor (MFA) sudah aktif dan dikonfirmasi, akses mobile/API belum tersedia, termasuk melalui token lama. Gunakan login web dan selesaikan verifikasi dua faktor; jangan menonaktifkan MFA untuk mengakses API.',
            'Perubahan atau reset kata sandi melalui web, administrator, pemulihan akun, atau API mencabut seluruh token API akun tersebut. Login ulang pada mobile/API setelahnya jika akun memenuhi syarat akses.',
        ],
        steps: [
            'Periksa nama organisasi pada bagian atas aplikasi sebelum membuka data atau membuat transaksi.',
            'Super Admin memilih organisasi melalui pemilih organisasi atau Pengaturan Profil. Pemilih di bagian atas membuka Dashboard organisasi tersebut.',
            'Akun biasa memakai organisasi yang ditetapkan administrator. Gunakan filter role di Panduan hanya untuk membaca alur pekerjaan.',
            'Buka menu yang tersedia bagi akun Anda; tombol tindakan mengikuti permission, kepemilikan, status dan kesiapan fitur.',
        ],
        outcome:
            'Transaksi, daftar dan laporan menggunakan organisasi yang benar. Panduan tetap dapat dibaca tanpa organisasi.',
        recovery:
            'Jika organisasi belum ditetapkan atau data belum siap, hubungi administrator. Jangan mencoba ID organisasi lain atau membuat transaksi pengganti.',
        action: 'profile',
    },
    {
        id: 'catat-b3',
        title: 'Mencatat limbah masuk dan mengajukan review',
        category: 'Limbah B3',
        roles: everyone,
        summary: 'Dari catatan draft hingga limbah masuk disetujui.',
        prerequisites: [
            'Hak membuat/mengajukan catatan, jenis limbah tersedia, dan data tanggal, sumber, jumlah serta satuan sudah diperiksa.',
        ],
        steps: [
            'Buka Catatan Limbah, tambah catatan lalu isi data sesuai kejadian dan satuan yang benar.',
            'Simpan draft dan periksa detail sebelum mengajukan. Satuan yang berbeda tidak otomatis dikonversi.',
            'Ajukan catatan milik sendiri untuk review; status berubah menjadi menunggu review.',
            'Minta pemeriksa lain yang berwenang meninjau. Pantau status serta alasan penolakan pada detail.',
        ],
        outcome:
            'Catatan disetujui menjadi dasar saldo masuk dan pengangkutan. Draft atau pengajuan belum disetujui tidak sama dengan saldo masuk disetujui.',
        recovery:
            'Jika ditolak, baca alasan di detail, pilih Kembali ke Draft, perbaiki catatan sendiri lalu Ajukan kembali. Jika sudah terkait rencana angkut, jangan mengubah riwayat sumber; minta administrator/reviewer menilai langkah yang diperbolehkan.',
        action: 'records',
    },
    {
        id: 'review-b3',
        title: 'Meninjau dan menolak catatan B3',
        category: 'Limbah B3',
        roles: reviewers,
        summary:
            'Periksa sumber, kuantitas dan satuan sebelum memberi keputusan.',
        prerequisites: [
            'Permission approve/reject dan pengajuan milik orang lain yang masih menunggu review.',
        ],
        steps: [
            'Buka detail catatan dan cocokkan jenis limbah, tanggal, sumber, jumlah dan satuan dengan dokumen pendukung.',
            'Setujui jika benar. Jika tidak, isi alasan penolakan yang menjelaskan data apa yang harus diperbaiki.',
            'Muat ulang bila status telah berubah; jangan mengulang keputusan berdasarkan layar lama.',
        ],
        outcome:
            'Keputusan tercatat pada status/riwayat. Pembuat atau pengaju tidak dapat menyetujui pengajuan sendiri, termasuk Super Admin.',
        recovery:
            'Jika tombol tidak tersedia, periksa permission, status dan apakah Anda pembuat/pengaju. Mintalah reviewer lain, bukan mengganti identitas lewat filter Panduan.',
        action: 'records',
    },
    {
        id: 'rencana',
        title: 'Merencanakan pengangkutan tanpa mengurangi saldo',
        category: 'Pengangkutan',
        roles: everyone,
        summary:
            'Rencana mengalokasikan kapasitas, bukan mencatat limbah keluar.',
        prerequisites: [
            'Fitur Rencana Angkut aktif dan terpasang pada organisasi, hak pengangkutan yang sesuai, serta catatan limbah disetujui dengan kapasitas tersedia lebih dari nol.',
        ],
        steps: [
            'Buka Rencana Angkut dan pilih catatan sumber yang boleh Anda gunakan.',
            'Isi tanggal rencana dan jumlah dalam satuan sumber; periksa sisa kapasitas lalu ajukan.',
            'Rencana langsung menunggu persetujuan. Rencana pending maupun disetujui mencadangkan kapasitas.',
            'Reviewer lain menyetujui atau menolak rencana. Setelah disetujui, catat realisasi ketika limbah benar-benar keluar.',
        ],
        outcome:
            'Saldo tercatat belum berkurang. Rencana yang ditolak melepas alokasi; rencana yang disetujui siap direalisasikan.',
        recovery:
            'Rencana yang diajukan tidak dapat diedit. Jika rencana masih pending dan salah, minta penolakan lalu buat baru. Rencana disetujui tidak dapat ditolak; selesaikan realisasi pending, lalu reviewer lain menutup rencana dengan alasan sebelum membuat rencana pengganti. Jika menu tidak aktif, tanyakan kesiapan fitur kepada administrator; alur pengangkutan lama tanpa rencana tetap didukung.',
        action: 'plans',
    },
    {
        id: 'realisasi',
        title: 'Mencatat realisasi parsial dari rencana',
        category: 'Pengangkutan',
        roles: everyone,
        summary:
            'Satu rencana dapat direalisasikan beberapa kali sampai alokasinya habis.',
        prerequisites: [
            'Rencana disetujui, hak membuat/mengajukan pengangkutan dan data kejadian tersedia.',
        ],
        steps: [
            'Dari rencana disetujui, buka pencatatan realisasi dan pilih jumlah yang benar-benar keluar.',
            'Gunakan satuan catatan sumber, maksimal dua angka desimal dan jumlah tidak melebihi sisa rencana/kapasitas. Tanggal tidak boleh sebelum limbah masuk atau setelah hari ini menurut zona waktu operasional server.',
            'Jika kolom metadata tersedia, isi vendor, manifest dan bukti bila ada. Data tersebut opsional pada aplikasi; sesuaikan dengan proses organisasi.',
            'Ajukan realisasi untuk diperiksa orang lain. Pending realisasi sudah memakai alokasi rencana, tetapi saldo baru berkurang setelah realisasi disetujui.',
            'Ulangi untuk pengangkutan berikutnya sepanjang masih ada alokasi.',
        ],
        outcome:
            'Hanya realisasi disetujui mengurangi saldo limbah. Persetujuan rencana saja tidak mengurangi saldo.',
        recovery:
            'Jika realisasi pending keliru, gunakan pembatalan yang diizinkan atau minta reviewer menolak, lalu catat ulang. Penolakan/pembatalan mengembalikan alokasi ke rencana. Realisasi terkait rencana tidak dapat diedit; realisasi disetujui tidak boleh diubah.',
        action: 'plans',
    },
    {
        id: 'tutup-rencana',
        title: 'Menutup rencana setelah realisasi selesai',
        category: 'Pengangkutan',
        roles: reviewers,
        summary:
            'Lepaskan alokasi tersisa tanpa menghapus realisasi yang sudah terjadi.',
        prerequisites: [
            'Rencana disetujui, tidak ada realisasi menunggu persetujuan, dan pemeriksa bukan pembuat/pengaju.',
        ],
        steps: [
            'Periksa daftar realisasi dan selesaikan semua keputusan atas realisasi pending terlebih dahulu.',
            'Pada detail rencana, pilih penutupan sisa sesuai hak Anda dan tulis alasan penutupan.',
            'Periksa status rencana dan saldo/alokasi setelah tindakan selesai.',
        ],
        outcome:
            'Rencana yang sudah terealisasi penuh juga dapat ditutup. Alokasi yang masih tersisa dilepas. Riwayat dan realisasi disetujui tetap tersimpan; penutupan tidak membatalkan limbah yang sudah keluar.',
        recovery:
            'Jika ditolak sistem, periksa realisasi pending, status terkini dan pemisahan reviewer. Jangan menghapus riwayat rencana untuk membuka kapasitas.',
        action: 'plans',
    },
    {
        id: 'angkut-lama',
        title: 'Menggunakan pengangkutan tanpa rencana',
        category: 'Pengangkutan',
        roles: everyone,
        summary:
            'Alur lama tetap tersedia; jangan menggandakan realisasi yang sama.',
        prerequisites: [
            'Catatan sumber disetujui, kapasitas tersedia dan permission pengangkutan sesuai tindakan.',
        ],
        steps: [
            'Buka Pengangkutan Limbah dan pilih catatan sumber yang boleh Anda akses.',
            'Isi tanggal, kuantitas dan satuan sesuai kejadian; vendor serta metadata/bukti bersifat opsional jika kolomnya tersedia. Simpan/ajukan mengikuti status dan tombol pada formulir.',
            'Reviewer lain memeriksa pengajuan sebelum persetujuan. Periksa kembali saldo setelah disetujui.',
        ],
        outcome:
            'Pengangkutan tanpa rencana tetap diproses. Reservasi rencana yang sudah ada tetap diperhitungkan, meski fitur rencana sedang dinonaktifkan.',
        recovery:
            'Jika kapasitas tidak cukup, periksa rencana dan pengangkutan pending. Jangan mencatat ulang perjalanan yang sudah dicatat sebagai realisasi rencana.',
        action: 'haulings',
    },
    {
        id: 'neraca',
        title: 'Menjelaskan selisih saldo dan kapasitas tersedia',
        category: 'Limbah B3',
        roles: everyone,
        summary:
            'Saldo tercatat, pending aktual dan reservasi rencana memiliki arti berbeda.',
        prerequisites: ['Hak melihat catatan limbah pada organisasi aktif.'],
        steps: [
            'Buka Neraca Operasional Limbah dan pilih periode/filter yang diperlukan.',
            'Bandingkan limbah masuk disetujui dengan pengangkutan aktual disetujui. Neraca memakai tanggal transaksi dan status saat laporan dibuka, bukan snapshot persetujuan historis. Saldo akhir adalah saldo sebelum periode ditambah masuk dikurangi keluar.',
            'Periksa pending aktual dan alokasi rencana yang belum terpakai untuk memahami kapasitas yang masih dapat diajukan.',
            'Baca jumlah per satuan. Jangan menjumlahkan kg, ton atau liter sebagai satu kuantitas tanpa dasar konversi.',
        ],
        outcome:
            'Saldo tercatat dapat lebih besar daripada kapasitas tersedia karena ada reservasi. Neraca ini bukan pengganti prosedur stock opname fisik.',
        recovery:
            'Telusuri detail sumber dan status sebelum menyimpulkan kehilangan stok. Laporkan referensi transaksi dan periode kepada reviewer bila ada selisih.',
        action: 'balance',
    },
    {
        id: 'produksi-faba',
        title: 'Menginput produksi dan transaksi FABA',
        category: 'FABA',
        roles: everyone,
        summary:
            'Pisahkan material, jenis transaksi dan periode sebelum menyimpan.',
        prerequisites: [
            'Hak input FABA, periode belum terkunci, dan data material/tanggal/kuantitas tersedia.',
        ],
        steps: [
            'Buka Produksi dan pilih jenis transaksi yang benar sesuai formulir, misalnya produksi atau kegiatan workshop.',
            'Isi material Fly Ash atau Bottom Ash, tanggal dan kuantitas dalam ton; lengkapi informasi pendukung.',
            'Simpan untuk menunggu persetujuan harian, lalu minta reviewer lain memeriksa.',
            'Periksa status disetujui pada daftar/detail sebelum menganggap transaksi masuk ke saldo.',
        ],
        outcome:
            'Transaksi baru atau yang diedit menunggu persetujuan; hanya transaksi disetujui dihitung dalam saldo FABA.',
        recovery:
            'Pending/ditolak dapat diperbaiki saat periode belum terkunci dan hak edit tersedia. Edit transaksi ditolak mengajukan kembali. Untuk transaksi disetujui gunakan alur koreksi, bukan mengedit riwayat.',
        action: 'production',
    },
    {
        id: 'pemanfaatan-faba',
        title: 'Mencatat pemanfaatan internal atau eksternal',
        category: 'FABA',
        roles: everyone,
        summary:
            'Gunakan tujuan/peruntukan untuk internal dan vendor untuk eksternal.',
        prerequisites: [
            'Hak pemanfaatan FABA, periode terbuka, saldo material cukup dan master terkait tersedia.',
        ],
        steps: [
            'Buka Pemanfaatan dan pilih internal atau eksternal sesuai kejadian.',
            'Isi material dan jumlah dalam ton. Eksternal memerlukan vendor aktif, nomor/tanggal dokumen serta lampiran JPG, PNG, WEBP atau PDF maksimal 5 MB. Internal memerlukan tujuan internal; peruntukan opsional.',
            'Periksa dokumen dan simpan untuk review. Reviewer lain menyetujui atau menolak.',
            'Lihat rekap material setelah persetujuan; tanggal historis juga diperiksa terhadap saldo berikutnya.',
        ],
        outcome:
            'Pemanfaatan disetujui mengurangi saldo material terkait. Entri pending belum menjadi pengeluaran disetujui.',
        recovery:
            'Jika master tidak ada, minta petugas berhak mengelolanya. Master FABA adalah hak tambahan, bukan otomatis milik semua role. Jika saldo kurang, periksa material/tanggal dan transaksi masuk disetujui.',
        action: 'utilization',
    },
    {
        id: 'koreksi-faba',
        title: 'Melakukan koreksi dan memahami saldo awal',
        category: 'FABA',
        roles: everyone,
        summary: 'Koreksi masuk/keluar berbeda dari penetapan saldo awal.',
        prerequisites: [
            'Permission koreksi atau saldo awal sesuai tindakan, data pendukung dan periode terbuka.',
        ],
        steps: [
            'Untuk koreksi kuantitas, buka Adjustment / Koreksi, pilih arah masuk/keluar dan jelaskan alasannya.',
            'Bila koreksi terkait transaksi disetujui tertentu, gunakan referensi sumber opsional agar hubungan dan snapshot sumber tercatat.',
            'Ajukan untuk persetujuan pihak lain. Pastikan material dan tanggal tidak menimbulkan saldo negatif atau mengubah periode terkunci berikutnya.',
            'Saldo awal dikelola hanya oleh akun berhak melalui Rekap. Saldo awal menetapkan ulang saldo per material pada awal periode, termasuk nilai nol; bukan tambahan produksi.',
            'Periksa rekap dan kartu stok setelah perubahan disetujui.',
        ],
        outcome:
            'Koreksi memiliki jejak transaksi. Saldo awal tidak dihitung sebagai produksi tambahan dan tidak menghapus riwayat.',
        recovery:
            'Jika periode terkunci, ikuti proses reopen oleh pihak berhak. Jangan memakai saldo awal sebagai tombol reset data atau jalan pintas untuk menyembunyikan selisih.',
        action: 'adjustments',
    },
    {
        id: 'closing',
        title: 'Mengajukan, meninjau dan membuka kembali closing',
        category: 'FABA',
        roles: everyone,
        summary:
            'Pengajuan closing sudah mengunci periode, bukan hanya saat disetujui.',
        prerequisites: [
            'Hak closing sesuai tindakan, ada transaksi non-saldo-awal yang disetujui, dan tidak ada transaksi harian pending pada periode.',
        ],
        steps: [
            'Cocokkan rekap bulanan, saldo material dan transaksi harian. Selesaikan semua pengajuan harian terlebih dahulu.',
            'Buka Closing & Approval, pilih periode lalu ajukan. Periode submitted terkunci untuk perubahan.',
            'Reviewer selain pengaju memeriksa dan menyetujui atau menolak dengan alasan. Periode approved tetap terkunci.',
            'Jika perlu koreksi setelah persetujuan, pihak berhak melakukan reopen dengan alasan. Perbaiki data lalu ajukan dan review ulang.',
        ],
        outcome:
            'Persetujuan membentuk snapshot laporan. Penolakan/reopen membuka periode; arsip versi yang sudah disetujui tetap dipertahankan.',
        recovery:
            'Jika pengajuan gagal, periksa transaksi harian pending dan keberadaan transaksi disetujui. Jika tidak dapat reopen, minta pengguna dengan hak reopen; jangan mengubah tanggal untuk menghindari penguncian.',
        action: 'closing',
    },
    {
        id: 'laporan',
        title: 'Mengunduh laporan final atau data langsung',
        category: 'FABA',
        roles: everyone,
        summary:
            'Pilih versi secara sadar agar laporan yang dibagikan tidak tertukar.',
        prerequisites: [
            'Hak ekspor laporan FABA dan organisasi/periode yang benar.',
        ],
        steps: [
            'Buka Laporan, tentukan cakupan/periode dan jenis laporan.',
            'Untuk laporan bulanan final, pilih versi arsip persetujuan secara eksplisit. Periksa periode dan versi pada hasil.',
            'Ekspor bulanan tanpa pilihan versi memakai Draft / Data Langsung yang mengikuti data saat ini, bukan otomatis arsip final.',
            'Gunakan Rekap atau Kartu Stok untuk menelusuri angka dan bandingkan dengan versi final bila ada perubahan setelah reopen.',
        ],
        outcome:
            'Laporan bulanan final berasal dari snapshot versi disetujui yang tetap; draft/live dapat berubah mengikuti transaksi.',
        recovery:
            'Jika versi final tidak tersedia, selesaikan closing atau pilih arsip yang sesuai. Jangan menyebut ekspor data langsung sebagai laporan final hanya karena periode pernah disetujui.',
        action: 'reports',
    },
    {
        id: 'vendor',
        title: 'Memeriksa vendor dan tanggal izin tercatat',
        category: 'Data master',
        roles: everyone,
        summary:
            'Peringatan aplikasi membantu review, bukan verifikasi legalitas otomatis.',
        prerequisites: [
            'Hak melihat vendor; perubahan memerlukan hak create/edit tersendiri.',
        ],
        steps: [
            'Buka Vendors dan periksa identitas serta status vendor yang dipakai transaksi.',
            'Periksa tanggal kedaluwarsa izin yang dicatat dan peringatan yang muncul.',
            'Petugas berhak memperbarui master berdasarkan dokumen yang telah diperiksa organisasi.',
        ],
        outcome:
            'Informasi vendor lebih konsisten. Peringatan hanya berdasarkan tanggal yang tersimpan, bukan pemeriksaan ke penerbit izin.',
        recovery:
            'Jika izin kosong/berakhir atau data meragukan, minta verifikasi petugas terkait sebelum melanjutkan proses internal. Jangan menganggap tidak ada peringatan sebagai jaminan izin valid.',
        action: 'vendors',
    },
    {
        id: 'notifikasi',
        title: 'Menindaklanjuti notifikasi operasional',
        category: 'Pemantauan',
        roles: everyone,
        summary:
            'Tindak lanjuti indikator aktif dan baca riwayat pengajuan dalam satu halaman.',
        prerequisites: [
            'Organisasi aktif dan datanya siap. Indikator mengikuti hak akses, kepemilikan, dan periode; riwayat kejadian tersedia jika fiturnya aktif.',
        ],
        steps: [
            'Klik lonceng di kanan atas dari halaman mana pun untuk membuka Notifikasi Operasional. Periksa tanggal snapshot, lalu pilih indikator yang perlu ditindaklanjuti.',
            'Buka tautan sumber untuk memeriksa status terkini sebelum bertindak.',
            'Pada bagian Riwayat notifikasi, tandai telah dibaca bila selesai meninjau. Indikator aktif hilang setelah sumber masalah ditangani, bukan setelah riwayat ditandai dibaca.',
        ],
        outcome:
            'Notifikasi membantu tindak lanjut di dalam aplikasi; bukan pengiriman email/WhatsApp atau pengingat berkala eksternal.',
        recovery:
            'Jika sumber tidak tampil, periksa organisasi dan akses terkini. Tidak semua kejadian lama memiliki notifikasi; buka daftar transaksi untuk pemeriksaan lengkap.',
        action: 'inbox',
    },
    {
        id: 'audit',
        title: 'Menelusuri riwayat audit B3',
        category: 'Pemantauan',
        roles: reviewers,
        summary: 'Cari jejak perubahan untuk memahami siapa melakukan apa.',
        prerequisites: [
            'Hak tambahan operational_audit.view, hak membaca catatan/pengangkutan sumber dan tabel audit tersedia.',
        ],
        steps: [
            'Buka Riwayat Audit B3 lalu gunakan filter untuk mempersempit waktu/kejadian.',
            'Cocokkan referensi dan perubahan dengan detail transaksi sumber.',
            'Gunakan riwayat untuk menjelaskan keputusan dan koreksi, bukan mengganti data historis.',
        ],
        outcome:
            'Audit hanya menampilkan sumber yang saat ini dapat dibaca; sumber yang sudah dihapus tidak ditampilkan. Jejak aktivitas membantu penelusuran. Hak audit tidak otomatis diberikan ke semua Admin/Supervisor.',
        recovery:
            'Jika menu tidak tersedia, minta administrator memeriksa permission serta kesiapan fitur. Data yang belum tercatat di audit tidak boleh disimpulkan sebagai tidak pernah terjadi.',
        action: 'audit',
    },
    {
        id: 'pengguna',
        title: 'Mengelola pengguna dan hak akses',
        category: 'Akses & administrasi',
        roles: ['admin', 'super_admin'],
        summary:
            'Tetapkan akses sesuai pekerjaan, tanpa mengubah role untuk melewati review.',
        prerequisites: [
            'Hak pengguna atau hak akses sesuai tindakan. Admin bekerja dalam batas organisasinya; perubahan permission role global hanya oleh akun bertanda Super Admin.',
        ],
        steps: [
            'Buka Pengguna, pastikan orang dan organisasi yang akan dikelola benar.',
            'Pilih role yang sesuai kebutuhan. Tinjau permission aktual melalui Hak Akses jika Anda berwenang. Hanya akun bertanda Super Admin dapat mengubah permission role global, yang berdampak lintas organisasi.',
            'Hak master FABA dan audit operasional bersifat tambahan/opt-in. Nama role dalam Panduan hanyalah profil standar, bukan daftar hak aktual.',
            'Minta pengguna memuat ulang halaman setelah perubahan dan periksa menu yang tersedia.',
        ],
        outcome:
            'Akses mengikuti penetapan aktual di aplikasi, bukan pilihan filter role dalam Panduan.',
        recovery:
            'Jika tindakan ditolak, periksa cakupan organisasi dan permission. Jangan memberikan akses lebih luas hanya untuk menyetujui pengajuan sendiri.',
        action: 'users',
    },
    {
        id: 'organisasi',
        title: 'Menyiapkan organisasi untuk operasional',
        category: 'Akses & administrasi',
        roles: ['super_admin'],
        summary:
            'Administrasi global dan data operasional organisasi adalah konteks berbeda.',
        prerequisites: ['Akun bertanda Super Admin.'],
        steps: [
            'Buka Organisasi untuk mengelola metadata dan organisasi baru melalui alur yang tersedia.',
            'Tetapkan pengguna ke organisasi yang benar, lalu pilih konteks organisasi sebelum membuka operasional.',
            'Jika data organisasi lama belum siap, minta pemeliharaan terencana; membuka halaman tidak otomatis memperbaiki atau memigrasikan data.',
        ],
        outcome:
            'Organisasi baru diprovisikan melalui alur administrasi. Data operasional tetap dipisahkan antarorganisasi.',
        recovery:
            'Jika provisioning gagal atau data belum siap, catat pesan kesalahan dan hubungi pengelola teknis. Jangan memakai organisasi lain sebagai tempat sementara untuk data.',
        action: 'organizations',
    },
];

export function matchingGuideRole(
    slug: string | null,
    isSuperAdmin: boolean,
): GuideRole | null {
    if (isSuperAdmin) return 'super_admin';
    return guideRoles.some((role) => role.slug === slug)
        ? (slug as GuideRole)
        : null;
}
export function filterGuideCases(
    query: string,
    role: string,
    category: string,
): GuideCase[] {
    const terms = query
        .toLocaleLowerCase('id')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    return guideCases.filter((item) => {
        const content = [
            item.title,
            item.summary,
            item.category,
            ...item.prerequisites,
            ...item.steps,
            item.outcome,
            item.recovery,
        ]
            .join(' ')
            .toLocaleLowerCase('id');
        return (
            (role === 'all' || item.roles.includes(role as GuideRole)) &&
            (category === 'all' || item.category === category) &&
            terms.every((term) => content.includes(term))
        );
    });
}
export interface GuideAccess {
    permissions: string[];
    isSuperAdmin: boolean;
    tenantReady: boolean;
    modules: {
        waste: boolean;
        faba: boolean;
        audit: boolean;
        fabaMasters?: boolean;
    };
    plans: boolean;
    inbox: boolean;
}
export function canOpenGuideAction(
    action: GuideAction,
    access: GuideAccess,
): boolean {
    const can = (...permissions: string[]) =>
        access.isSuperAdmin ||
        permissions.some((permission) =>
            access.permissions.includes(permission),
        );
    if (action === 'profile') return true;
    if (action === 'organizations') return access.isSuperAdmin;
    if (action === 'users')
        return (access.isSuperAdmin || access.tenantReady) && can('users.view');
    if (action === 'roles')
        return (access.isSuperAdmin || access.tenantReady) && can('roles.view');
    if (!access.tenantReady) return false;
    if (action === 'inbox') return true;
    if (action === 'masterTypes')
        return access.modules.waste && can('waste_types.view');
    if (action === 'fabaMasters')
        return access.modules.fabaMasters === true && can('faba_master.view');
    if (action === 'stockBalance')
        return access.modules.faba && can('faba_recaps.view');
    if (action === 'audit')
        return access.modules.audit && can('operational_audit.view');
    if (
        ['records', 'balance', 'plans', 'haulings', 'vendors'].includes(action)
    ) {
        if (!access.modules.waste) return false;
        if (action === 'records' || action === 'balance')
            return can('waste_records.view_all', 'waste_records.view_own');
        if (action === 'plans')
            return (
                access.plans &&
                can(
                    'waste_hauling.view_all',
                    'waste_hauling.view_own',
                    'waste_hauling.approve',
                    'waste_hauling.reject',
                )
            );
        if (action === 'vendors') return can('vendors.view');
        return can('waste_hauling.view_all', 'waste_hauling.view_own');
    }
    const permissions = {
        production: 'faba_production.view',
        utilization: 'faba_utilization.view',
        adjustments: 'faba_adjustments.view',
        recaps: 'faba_recaps.view',
        closing: 'faba_approvals.view',
        reports: 'faba_reports.export',
    };
    return (
        access.modules.faba &&
        can(permissions[action as keyof typeof permissions])
    );
}
