export interface GuideScreenshot {
    src: string;
    alt: string;
    caption: string;
    role: string;
    context: string;
    width: number;
    height: number;
}

const screenshots = [
    [
        'master-b3',
        'Supervisor',
        'Data master · Jenis Limbah',
        'Periksa referensi jenis limbah sebelum digunakan untuk pencatatan B3.',
    ],
    [
        'master-faba',
        'Supervisor dengan hak master FABA',
        'Data master · Tujuan Internal FABA',
        'Kelola referensi tujuan internal dan status aktifnya tanpa menghapus riwayat.',
    ],
    [
        'pantau-rekap',
        'Operator',
        'FABA · Saldo TPS',
        'Contoh ringkasan Saldo TPS: saldo aktif 230 ton, produksi tahun ini 50 ton, pemanfaatan 20 ton, dan arus bersih 30 ton.',
    ],
    [
        'mulai',
        'Operator',
        'Akses · Profil dan organisasi',
        'Pastikan identitas akun dan organisasi sebelum memulai pekerjaan.',
    ],
    [
        'catat-b3',
        'Operator',
        'B3 · Catatan Limbah',
        'Formulir catatan B3 menampilkan tanggal, jenis limbah, jumlah, satuan, dan sumber atau lokasi limbah.',
    ],
    [
        'review-b3',
        'Supervisor',
        'B3 · Review catatan',
        'Formulir review menyediakan catatan persetujuan dan alasan penolakan. Alasan penolakan harus jelas dan minimal 10 karakter.',
    ],
    [
        'rencana',
        'Operator',
        'B3 · Rencana Angkut',
        'Rencanakan pengangkutan dan periksa jumlah yang dialokasikan tanpa mengurangi saldo fisik.',
    ],
    [
        'realisasi',
        'Operator',
        'B3 · Realisasi pengangkutan',
        'Cocokkan realisasi dengan rencana angkut dan sisa alokasinya.',
    ],
    [
        'tutup-rencana',
        'Supervisor',
        'B3 · Penutupan rencana',
        'Contoh rencana memiliki realisasi disetujui 300 kg dan sisa alokasi 500 kg. Isi alasan penutupan untuk melepas sisa alokasi tanpa menghapus realisasi.',
    ],
    [
        'angkut-lama',
        'Operator',
        'B3 · Pengangkutan Limbah',
        'Periksa pencatatan pengangkutan tanpa rencana sesuai alur yang tersedia.',
    ],
    [
        'neraca',
        'Operator',
        'B3 · Neraca operasional',
        'Neraca contoh periode terpilih menunjukkan limbah masuk 1.200 kg, keluar 300 kg, dan saldo akhir 900 kg berdasarkan transaksi yang disetujui.',
    ],
    [
        'produksi-faba',
        'Operator',
        'FABA · Produksi',
        'Formulir produksi FABA menampilkan tanggal transaksi, material, tipe movement, jumlah dalam ton, dan catatan.',
    ],
    [
        'pemanfaatan-faba',
        'Operator',
        'FABA · Pemanfaatan',
        'Contoh formulir memilih Pemanfaatan Eksternal. Periksa tanggal, material, vendor yang wajib dipilih, dan jumlah pemanfaatan.',
    ],
    [
        'koreksi-faba',
        'Operator',
        'FABA · Adjustment / Koreksi',
        'Formulir adjustment menampilkan tanggal, material, tipe koreksi, dan jumlah. Gunakan hanya untuk koreksi nyata, bukan penetapan saldo awal.',
    ],
    [
        'closing',
        'Supervisor',
        'FABA · Closing & Approval',
        'Review periode contoh masih berstatus Draft dan operasional terbuka. Periksa total produksi, pemanfaatan, saldo, dan warning sebelum melanjutkan closing.',
    ],
    [
        'laporan',
        'Operator',
        'FABA · Laporan',
        'Contoh laporan menampilkan rekonsiliasi saldo dan sumber ekspor Draft / Data Langsung. Belum ada versi arsip pada periode ini; periksa peringatan dokumen sebelum ekspor.',
    ],
    [
        'vendor',
        'Supervisor',
        'Data master · Vendors',
        'Periksa vendor dan masa berlaku izin yang tercatat sebelum pengangkutan.',
    ],
    [
        'notifikasi',
        'Operator',
        'Operasional · Notifikasi',
        'Contoh notifikasi catatan limbah disetujui menyediakan Lihat sumber dan Tandai dibaca. Menandai dibaca tidak mengubah keputusan transaksi.',
    ],
    [
        'audit',
        'Supervisor',
        'B3 · Riwayat Audit',
        'Telusuri aktivitas dan perubahan status transaksi melalui riwayat audit B3.',
    ],
    [
        'pengguna',
        'Admin',
        'Administrasi · Pengguna',
        'Periksa akun, organisasi, dan role pengguna sesuai kewenangan administrator.',
    ],
    [
        'organisasi',
        'Super Admin',
        'Administrasi · Organisasi',
        'Periksa organisasi dan kesiapan konteks operasional sebelum digunakan.',
    ],
];

export const guideScreenshots: Record<string, GuideScreenshot> =
    Object.fromEntries(
        screenshots.map(([id, role, context, caption]) => [
            id,
            {
                src: `/images/guide/${id}.jpg`,
                alt: `Tangkapan layar ${context} sebagai ${role}, menggunakan data contoh. ${caption}`,
                caption,
                role,
                context,
                width: 873,
                height: 954,
            },
        ]),
    );
