# Inventaris rute API BE03

Baseline `e65c837060beafac9340fc9641e2db8c9cd0b24d`. Ada 61 deklarasi method/path pada `routes/api.php`; tabel berasal dari pembacaan statis grup prefix dan handler. Ini tidak menjalankan Laravel atau membuktikan respons HTTP. GET/HEAD implisit Laravel tidak dihitung sebagai dua deklarasi. Semua path memakai prefix `/api/v1`.

Nama dan handler dicatat agar bisa ditelusuri ke sumber. Daftar ini bukan deskripsi lengkap body, middleware, izin atau lifecycle sesi; kontrak keamanan BE04 tetap ditunda. Tidak ada endpoint kandidat BE04 yang dimasukkan. Lihat [catatan kontrak](README.md).

| Method | Path | Nama rute | Handler | Sumber |
| --- | --- | --- | --- | --- |
| POST | `/api/v1/auth/login` | `api.v1.auth.login` | `AuthController::login` | [L19](../../routes/api.php#L19) |
| POST | `/api/v1/auth/logout` | `api.v1.auth.logout` | `AuthController::logout` | [L22](../../routes/api.php#L22) |
| GET | `/api/v1/auth/me` | `api.v1.auth.me` | `AuthController::me` | [L23](../../routes/api.php#L23) |
| GET | `/api/v1/me` | `api.v1.me` | `BootstrapController::me` | [L28](../../routes/api.php#L28) |
| GET | `/api/v1/dashboard` | `api.v1.dashboard` | `BootstrapController::dashboard` | [L29](../../routes/api.php#L29) |
| GET | `/api/v1/lookups` | `api.v1.lookups` | `BootstrapController::lookups` | [L30](../../routes/api.php#L30) |
| GET | `/api/v1/profile` | `api.v1.profile.show` | `ProfileController::show` | [L33](../../routes/api.php#L33) |
| PATCH | `/api/v1/profile` | `api.v1.profile.update` | `ProfileController::update` | [L34](../../routes/api.php#L34) |
| PUT | `/api/v1/profile/password` | `api.v1.profile.password.update` | `ProfileController::updatePassword` | [L35](../../routes/api.php#L35) |
| GET | `/api/v1/waste-records` | `api.v1.waste-records.index` | `WasteRecordController::index` | [L39](../../routes/api.php#L39) |
| POST | `/api/v1/waste-records` | `api.v1.waste-records.store` | `WasteRecordController::store` | [L40](../../routes/api.php#L40) |
| GET | `/api/v1/waste-records/pending-approval` | `api.v1.waste-records.pending-approval` | `WasteRecordController::pendingApproval` | [L41](../../routes/api.php#L41) |
| GET | `/api/v1/waste-records/{wasteRecord}` | `api.v1.waste-records.show` | `WasteRecordController::show` | [L42](../../routes/api.php#L42) |
| PUT | `/api/v1/waste-records/{wasteRecord}` | `api.v1.waste-records.update` | `WasteRecordController::update` | [L43](../../routes/api.php#L43) |
| DELETE | `/api/v1/waste-records/{wasteRecord}` | `api.v1.waste-records.destroy` | `WasteRecordController::destroy` | [L44](../../routes/api.php#L44) |
| POST | `/api/v1/waste-records/{wasteRecord}/submit` | `api.v1.waste-records.submit` | `WasteRecordController::submit` | [L45](../../routes/api.php#L45) |
| POST | `/api/v1/waste-records/{wasteRecord}/approve` | `api.v1.waste-records.approve` | `WasteRecordController::approve` | [L46](../../routes/api.php#L46) |
| POST | `/api/v1/waste-records/{wasteRecord}/reject` | `api.v1.waste-records.reject` | `WasteRecordController::reject` | [L47](../../routes/api.php#L47) |
| POST | `/api/v1/waste-records/{wasteRecord}/return-to-draft` | `api.v1.waste-records.return-to-draft` | `WasteRecordController::returnToDraft` | [L48](../../routes/api.php#L48) |
| GET | `/api/v1/waste-haulings` | `api.v1.waste-haulings.index` | `WasteHaulingController::index` | [L52](../../routes/api.php#L52) |
| POST | `/api/v1/waste-haulings` | `api.v1.waste-haulings.store` | `WasteHaulingController::store` | [L53](../../routes/api.php#L53) |
| GET | `/api/v1/waste-haulings/pending-approval` | `api.v1.waste-haulings.pending-approval` | `WasteHaulingController::pendingApproval` | [L54](../../routes/api.php#L54) |
| GET | `/api/v1/waste-haulings/history/{wasteRecord}` | `api.v1.waste-haulings.history` | `WasteHaulingController::history` | [L55](../../routes/api.php#L55) |
| GET | `/api/v1/waste-haulings/{wasteHauling}` | `api.v1.waste-haulings.show` | `WasteHaulingController::show` | [L56](../../routes/api.php#L56) |
| POST | `/api/v1/waste-haulings/{wasteHauling}/approve` | `api.v1.waste-haulings.approve` | `WasteHaulingController::approve` | [L57](../../routes/api.php#L57) |
| POST | `/api/v1/waste-haulings/{wasteHauling}/reject` | `api.v1.waste-haulings.reject` | `WasteHaulingController::reject` | [L58](../../routes/api.php#L58) |
| POST | `/api/v1/waste-haulings/{wasteHauling}/cancel` | `api.v1.waste-haulings.cancel` | `WasteHaulingController::cancel` | [L59](../../routes/api.php#L59) |
| GET | `/api/v1/faba/options` | `api.v1.faba.lookups` | `FabaOptionsController::__invoke` | [L63](../../routes/api.php#L63) |
| GET | `/api/v1/faba/dashboard` | `api.v1.faba.dashboard` | `FabaRecapController::dashboard` | [L64](../../routes/api.php#L64) |
| GET | `/api/v1/faba/production` | `api.v1.faba.production.index` | `FabaProductionController::index` | [L67](../../routes/api.php#L67) |
| POST | `/api/v1/faba/production` | `api.v1.faba.production.store` | `FabaProductionController::store` | [L68](../../routes/api.php#L68) |
| GET | `/api/v1/faba/production/{production}` | `api.v1.faba.production.show` | `FabaProductionController::show` | [L69](../../routes/api.php#L69) |
| PUT | `/api/v1/faba/production/{production}` | `api.v1.faba.production.update` | `FabaProductionController::update` | [L70](../../routes/api.php#L70) |
| DELETE | `/api/v1/faba/production/{production}` | `api.v1.faba.production.destroy` | `FabaProductionController::destroy` | [L71](../../routes/api.php#L71) |
| GET | `/api/v1/faba/utilization` | `api.v1.faba.utilization.index` | `FabaUtilizationController::index` | [L75](../../routes/api.php#L75) |
| POST | `/api/v1/faba/utilization` | `api.v1.faba.utilization.store` | `FabaUtilizationController::store` | [L76](../../routes/api.php#L76) |
| GET | `/api/v1/faba/utilization/{utilization}` | `api.v1.faba.utilization.show` | `FabaUtilizationController::show` | [L77](../../routes/api.php#L77) |
| PUT | `/api/v1/faba/utilization/{utilization}` | `api.v1.faba.utilization.update` | `FabaUtilizationController::update` | [L78](../../routes/api.php#L78) |
| DELETE | `/api/v1/faba/utilization/{utilization}` | `api.v1.faba.utilization.destroy` | `FabaUtilizationController::destroy` | [L79](../../routes/api.php#L79) |
| GET | `/api/v1/faba/adjustments` | `api.v1.faba.adjustments.index` | `FabaAdjustmentController::index` | [L83](../../routes/api.php#L83) |
| POST | `/api/v1/faba/adjustments` | `api.v1.faba.adjustments.store` | `FabaAdjustmentController::store` | [L84](../../routes/api.php#L84) |
| GET | `/api/v1/faba/adjustments/{adjustment}` | `api.v1.faba.adjustments.show` | `FabaAdjustmentController::show` | [L85](../../routes/api.php#L85) |
| PUT | `/api/v1/faba/adjustments/{adjustment}` | `api.v1.faba.adjustments.update` | `FabaAdjustmentController::update` | [L86](../../routes/api.php#L86) |
| DELETE | `/api/v1/faba/adjustments/{adjustment}` | `api.v1.faba.adjustments.destroy` | `FabaAdjustmentController::destroy` | [L87](../../routes/api.php#L87) |
| GET | `/api/v1/faba/recaps/monthly` | `api.v1.faba.recaps.monthly` | `FabaRecapController::monthly` | [L91](../../routes/api.php#L91) |
| GET | `/api/v1/faba/recaps/yearly` | `api.v1.faba.recaps.yearly` | `FabaRecapController::yearly` | [L92](../../routes/api.php#L92) |
| GET | `/api/v1/faba/recaps/vendors` | `api.v1.faba.recaps.vendors` | `FabaRecapController::vendors` | [L93](../../routes/api.php#L93) |
| GET | `/api/v1/faba/recaps/balance` | `api.v1.faba.recaps.balance` | `FabaRecapController::balance` | [L94](../../routes/api.php#L94) |
| GET | `/api/v1/faba/recaps/stock-card` | `api.v1.faba.recaps.stock-card` | `FabaRecapController::stockCard` | [L95](../../routes/api.php#L95) |
| POST | `/api/v1/faba/recaps/opening-balance` | `api.v1.faba.recaps.opening-balance.store` | `FabaRecapController::storeOpeningBalance` | [L96](../../routes/api.php#L96) |
| POST | `/api/v1/faba/recaps/tps-capacity` | `api.v1.faba.recaps.tps-capacity.store` | `FabaRecapController::storeTpsCapacity` | [L97](../../routes/api.php#L97) |
| GET | `/api/v1/faba/approvals` | `api.v1.faba.approvals.index` | `FabaApprovalController::index` | [L101](../../routes/api.php#L101) |
| GET | `/api/v1/faba/approvals/history` | `api.v1.faba.approvals.history` | `FabaApprovalController::history` | [L102](../../routes/api.php#L102) |
| POST | `/api/v1/faba/approvals/submit` | `api.v1.faba.approvals.submit` | `FabaApprovalController::submit` | [L103](../../routes/api.php#L103) |
| GET | `/api/v1/faba/approvals/{year}/{month}` | `api.v1.faba.approvals.review` | `FabaApprovalController::review` | [L104](../../routes/api.php#L104) |
| POST | `/api/v1/faba/approvals/{year}/{month}/approve` | `api.v1.faba.approvals.approve` | `FabaApprovalController::approve` | [L105](../../routes/api.php#L105) |
| POST | `/api/v1/faba/approvals/{year}/{month}/reject` | `api.v1.faba.approvals.reject` | `FabaApprovalController::reject` | [L106](../../routes/api.php#L106) |
| POST | `/api/v1/faba/approvals/{year}/{month}/reopen` | `api.v1.faba.approvals.reopen` | `FabaApprovalController::reopen` | [L107](../../routes/api.php#L107) |
| GET | `/api/v1/faba/movements/pending-approval` | `api.v1.faba.movements.pending-approval` | `FabaMovementApprovalController::pending` | [L111](../../routes/api.php#L111) |
| POST | `/api/v1/faba/movements/{movement}/approve` | `api.v1.faba.movements.approve` | `FabaMovementApprovalController::approve` | [L112](../../routes/api.php#L112) |
| POST | `/api/v1/faba/movements/{movement}/reject` | `api.v1.faba.movements.reject` | `FabaMovementApprovalController::reject` | [L113](../../routes/api.php#L113) |

Pemeriksaan lokal: jumlah 61 dibandingkan deklarasi sumber; tidak ada pasangan method/path atau nama duplikat; setiap controller dan method handler ditemukan di sumber. Tidak ada request jaringan ke aplikasi, akses database, bootstrap Laravel, atau pengujian fitur keamanan dalam pemeriksaan ini.
