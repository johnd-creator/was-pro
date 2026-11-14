<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Penyimpanan Limbah B3</title><style>
@page { margin: 18px; } body { font-family: DejaVu Sans,sans-serif; font-size:8px; color:#111; } table { border-collapse:collapse; width:100%; } td,th { border:1px solid #333; padding:5px; vertical-align:top; } th { background:#e8eef2; text-align:center; } .header td { font-size:9px; vertical-align:middle; } .small { font-size:7px; } .number { text-align:right; } .signatures td { border:0; text-align:center; padding-top:15px; } thead { display:table-header-group; } tr { page-break-inside:avoid; } .carry { background:#f3f4f6; }
</style></head><body>
@include('waste.reports.header', ['columns'=>12, 'title'=>'LEMBAR KEGIATAN PENYIMPANAN LIMBAH B3', 'period'=>'Periode '.$report['start_date'].' s.d. '.$report['end_date']])
<p class="small">Kg dikonversi ke ton. Satuan lainnya dipertahankan dan dicantumkan per baris. Baris saldo awal adalah sisa sebelum periode; bukan penerimaan baru. Rencana angkut belum dihitung sebagai pengeluaran.</p>
@foreach (array_chunk($report['entries'], ($format ?? 'pdf') === 'pdf' ? 20 : max(1, count($report['entries']))) ?: [[]] as $entries)
<table><thead><tr><th colspan="7">MASUKNYA LIMBAH B3 KE TPS</th><th colspan="4">KELUARNYA LIMBAH B3 DARI TPS</th><th>SISA</th></tr>
<tr><th>No.</th><th>Jenis Limbah B3 Masuk</th><th>Kode Limbah</th><th>Tanggal Masuk</th><th>Sumber Limbah B3</th><th>Jumlah Masuk</th><th>Maksimal Penyimpanan</th><th>Tanggal Keluar</th><th>Jumlah Keluar</th><th>Tujuan Penyerahan</th><th>Bukti No. Dokumen</th><th>Sisa Limbah B3 di TPS</th></tr>
<tr>@foreach (range('A','L') as $letter)<th>{{ $letter }}</th>@endforeach</tr></thead><tbody>
@forelse ($entries as $entry)<tr class="{{ $entry['kind']==='Saldo awal periode' ? 'carry' : '' }}">
<td>{{ ($loop->parent->index * 20) + $loop->iteration }}</td><td data-type="s">{{ $entry['waste_type'] }}<br><span class="small">{{ $entry['record_number'] }} / {{ $entry['kind'] }} / {{ $entry['unit'] }}</span></td><td data-type="s">{{ $entry['code'] }}</td><td data-type="s">{{ $entry['date'] }}</td><td data-type="s">{{ $entry['source'] }}</td>
<td class="number" data-type="{{ $entry['incoming'] === null ? 's' : 'n' }}" data-format="0.0000">{{ $entry['incoming'] === null ? '-' : (($format ?? 'pdf') === 'excel' ? $entry['incoming'] : number_format($entry['incoming'],4,',','.')) }}</td><td data-type="s">{{ $entry['expiry_date'] ?? 'Tidak tercatat' }}</td><td data-type="s">{{ $entry['outgoing_date'] ?? '-' }}</td><td class="number" data-type="{{ $entry['outgoing'] === null ? 's' : 'n' }}" data-format="0.0000">{{ $entry['outgoing'] === null ? '-' : (($format ?? 'pdf') === 'excel' ? $entry['outgoing'] : number_format($entry['outgoing'],4,',','.')) }}</td><td data-type="s">{{ $entry['destination'] ?? '-' }}</td><td data-type="s">{{ $entry['document_number'] ?? '-' }}</td><td class="number" data-type="n" data-format="0.0000">{{ ($format ?? 'pdf') === 'excel' ? $entry['remaining'] : number_format($entry['remaining'],4,',','.') }}</td>
</tr>@empty<tr><td colspan="12">Tidak ada catatan disetujui atau saldo awal dalam periode ini.</td></tr>@endforelse
</tbody></table>
@endforeach
@include('waste.reports.footer', ['columns'=>12])
</body></html>
