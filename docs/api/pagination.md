# Pagination dan kompatibilitas

Baseline: commit BE03 yang disebut pada [catatan kontrak](README.md).
Tabel ini berasal dari controller, bukan hasil pengukuran database atau
pengujian HTTP baru. Semua path relatif terhadap `/api/v1` dan memakai GET.

## Endpoint berpaginasi

| Path | Filter tambahan yang dibaca | Urutan dari sumber |
| --- | --- | --- |
| `/waste-records` | `status`, `search` | `date DESC`, `created_at DESC` |
| `/waste-records/pending-approval` | Tidak ada filter tambahan pada method ini | `submitted_at DESC` |
| `/waste-haulings/pending-approval` | Tidak ada filter tambahan pada method ini | `submitted_at DESC` |
| `/faba/production` | `material_type`, `movement_type`, `year`, `month` | `transaction_date DESC`, `created_at DESC` |
| `/faba/utilization` | `material_type`, `movement_type`, `year`, `month` | `transaction_date DESC`, `created_at DESC` |
| `/faba/adjustments` | `material_type`, `movement_type`, `year`, `month` | `transaction_date DESC`, `created_at DESC` |

Keenam endpoint memakai paginator Laravel berbasis `page`. Helper
`resolvePerPage` pada masing-masing controller mengonversi `per_page` ke
integer, default 15, lalu membatasi 1 sampai 100. Ini perilaku coercion dan
clamping, bukan janji validasi query ketat.

Data halaman berupa array pada `data`; metadata adalah:

```json
{"pagination": {"current_page": 1, "per_page": 15, "total": 30, "last_page": 2}}
```

Contoh metadata bersifat ilustratif. Jangan mengandalkan field Laravel lain
seperti `links`, `next_page_url`, `from`, atau `to`: helper `paginationMeta`
hanya mengembalikan empat field di atas. Pertahankan filter ketika meminta
halaman berikutnya; berhenti ketika `current_page >= last_page`.

## Koleksi tanpa pagination halaman

| Path | Bentuk dan batas saat ini |
| --- | --- |
| `/waste-haulings` | Array backlog waste record approved dengan remaining quantity positif; `meta.count`; mengambil record lalu memfilter di memori |
| `/waste-haulings/history/{wasteRecord}` | Objek `record` dan array `haulings`; haulings diurutkan `created_at` menurun |
| `/faba/movements/pending-approval` | Array movement; `transaction_date DESC`, `submitted_at DESC`; memakai `get()` |
| `/faba/approvals` | Array periode dari service, filter `year`; tidak memakai paginator |
| `/faba/approvals/history` | Objek `approvals` tanpa pagination serta `audit_logs` terbaru yang dibatasi 100 |

Lookup, options, dashboard, recap, dan detail periode juga tidak mengikuti
protokol halaman enam endpoint di atas; itu bukan janji semua koleksinya
lengkap atau tidak terbatas. Khusus `audit_logs` history, batas 100 berarti
respons bukan arsip lengkap.

## Risiko dan rancangan berikutnya (belum diimplementasikan)

Urutan pagination belum memiliki tie-breaker ID unik. Record dengan tanggal
dan waktu sama dapat bergeser antarhalaman; perubahan data selama traversal
juga dapat menimbulkan duplikasi atau kehilangan item dengan offset paging.
Klien dapat menggabungkan berdasarkan ID dan menawarkan refresh, tetapi itu
tidak menjamin snapshot konsisten.

Jika pagination ditambahkan ke koleksi lama, gunakan kontrak opt-in atau
versi baru yang eksplisit, pertahankan bentuk respons lama bagi klien lama,
dan tambahkan urutan deterministik. Jangan diam-diam memotong array menjadi
halaman pertama atau mengubah objek history menjadi array. Backlog hauling
memerlukan penanganan filter remaining quantity sebelum pagination; memaginasi
record terlebih dahulu lalu memfilter dapat menghasilkan total dan halaman
yang salah. Pembatasan maupun optimasi ini belum dijalankan dalam paket ini.

Sumber: `WasteRecordController::{index,pendingApproval,resolvePerPage,paginationMeta}`,
`WasteHaulingController::{index,history,pendingApproval,resolvePerPage,paginationMeta}`,
`FabaProductionController`, `FabaUtilizationController`,
`FabaAdjustmentController` (method `index`, `resolvePerPage`, `paginationMeta`),
`FabaApprovalController::{index,history}`,
`FabaMovementApprovalController::pending` di `app/Http/Controllers/Api/`.
