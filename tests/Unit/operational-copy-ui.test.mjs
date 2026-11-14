import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = (path) =>
    readFileSync(
        new URL(
            `../../resources/js/pages/waste-management/${path}`,
            import.meta.url,
        ),
        'utf8',
    );
const copy = (path) => source(path).replace(/\s+/g, ' ');

test('record creator copy explains the automatic saving account without adding a manual PIC', () => {
    assert.match(
        copy('records/Create.vue'),
        /Dicatat oleh akan terisi otomatis dengan akun yang menyimpan catatan ini/,
    );
    assert.match(
        copy('records/Show.vue'),
        /Dicatat oleh terisi otomatis dengan akun yang pertama kali menyimpan catatan/,
    );
    assert.match(source('records/Show.vue'), /wasteRecord\.created_by\?\.name/);
    assert.doesNotMatch(
        source('records/Create.vue'),
        /v-model="[^\"]*(created_by|pic)/i,
    );
});

test('balance explains all anomaly causes as findings rather than waste categories', () => {
    const balance = copy('records/Balance.vue');
    for (const phrase of [
        'bukan kategori limbah',
        'saldo awal atau akhir negatif',
        'tanggal pengangkutan sebelum tanggal catatan',
        'satuan pengangkutan berbeda dari catatan',
        'lebih dari satu temuan',
    ])
        assert.ok(balance.includes(phrase), phrase);
    assert.match(balance, /TableHead\s*>\s*Perlu diperiksa/);
    assert.match(balance, /v-for="anomaly in reference.anomalies"/);
});

test('FABA exports distinguish current draft from approved archive without changing version values', () => {
    const reports = copy('faba/reports/Index.vue');
    assert.match(
        reports,
        /Draf mengikuti data terkini dan bukan arsip persetujuan/,
    );
    assert.match(
        reports,
        /Arsip disetujui berisi data saat persetujuan versi tersebut/,
    );
    assert.match(
        reports,
        /Pilihan sumber hanya mengubah ekspor, bukan ringkasan di atas/,
    );
    assert.match(reports, /<SelectItem value="draft"/);
    assert.match(reports, /:value="String\(version.version\)"/);
    assert.match(
        reports,
        /exportVersion === 'draft' \? undefined : Number\( exportVersion,/,
    );
    assert.match(
        copy('faba/approvals/Review.vue'),
        /Arsip persetujuan tetap tersimpan setelah periode dibuka kembali/,
    );
});

test('FABA opening, closing and approval headings use Indonesian display labels', () => {
    assert.match(copy('faba/recaps/Balance.vue'), /Atur saldo awal/);
    assert.match(copy('faba/recaps/Balance.vue'), /Simpan saldo awal/);
    assert.match(
        copy('faba/recaps/Monthly.vue'),
        /formatFabaStatus\(\s*detail\.snapshot\.status,?\s*\)/,
    );
    assert.match(copy('faba/recaps/Monthly.vue'), /Saldo Awal<\/CardTitle/);
    assert.match(
        copy('faba/approvals/History.vue'),
        /Disetujui oleh<\/TableHead/,
    );
    assert.match(copy('faba/approvals/Review.vue'), /Arsip Penutupan Berversi/);
    assert.match(copy('faba/utilization/Show.vue'), /Status Penutupan/);
    for (const path of [
        'faba/recaps/Balance.vue',
        'faba/recaps/Monthly.vue',
        'faba/approvals/History.vue',
        'faba/approvals/Review.vue',
        'faba/reports/Index.vue',
        'faba/utilization/Show.vue',
    ]) {
        assert.doesNotMatch(
            source(path),
            /Opening Balance|Closing Snapshot|Approved at|Approved By|Draft \/ Data Langsung/,
        );
    }
});

test('hauling and vendor headers are compact while operational records remain available', () => {
    const hauling = source('haulings/Index.vue');
    const vendors = source('master-data/Vendors.vue');
    assert.doesNotMatch(
        hauling,
        /Waste Logistics|Fokus Operasional|Prioritas pengangkutan/i,
    );
    assert.match(hauling, /stats.total_records/);
    assert.match(hauling, /stats.pending_requests/);
    assert.match(hauling, /v-for="record in paginatedRecords"/);
    assert.doesNotMatch(
        vendors,
        /Vendor Registry|<p[^>]*>\s*Transportasi\s*<\/p>/i,
    );
    assert.match(vendors, /props.vendors.length/);
    assert.match(vendors, /openCreateDialog/);
});
