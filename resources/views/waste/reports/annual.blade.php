<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Neraca Limbah B3 Tahun {{ $report['year'] }}</title><style>
@page { margin:16px; } body { font-family:DejaVu Sans,sans-serif; font-size:7px; color:#111; } table { border-collapse:collapse; width:100%; } td,th { border:1px solid #333; padding:3px; vertical-align:middle; } th { text-align:center; background:#e4eaf0; } .header td { font-size:9px; } .small { font-size:7px; } .number { text-align:right; } .incoming { background:#fff7c2; } .utilized { background:#daf2d6; } .outgoing { background:#ffe6bc; } .signatures td { border:0; text-align:center; padding-top:15px; } thead { display:table-header-group; } tr { page-break-inside:avoid; }
</style></head><body>
@include('waste.reports.header', ['columns'=>26, 'title'=>'NERACA LIMBAH BAHAN BERBAHAYA & BERACUN (B3) TAHUN '.$report['year'], 'period'=>'UNIT: '.$report['organization']])
<table><thead><tr><th rowspan="2">No.</th><th rowspan="2">Kode LB3</th><th rowspan="2">Jenis Limbah</th><th rowspan="2">Sumber</th><th rowspan="2">Sat.</th><th rowspan="2">Sisa Tahun Lalu ({{ $report['year']-1 }})</th><th rowspan="2">Aktivitas</th><th colspan="12">Jumlah per Bulan</th><th colspan="4">TOTAL</th><th colspan="2">BALANCING</th><th rowspan="2">Keterangan</th></tr><tr>
@foreach (['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'] as $month)<th>{{ $month }}</th>@endforeach
<th>MASUK</th><th>MANFAATKAN</th><th>KELUAR</th><th>SIMPAN</th><th>Tercatat / Tersedia</th><th>%</th></tr></thead><tbody>
@forelse ($report['annual_rows'] as $row)
@foreach (['incoming'=>'Masuk','utilized'=>'Manfaatkan','outgoing'=>'Keluar'] as $key=>$label)
<tr class="{{ $key }}">
@if ($loop->first)<td rowspan="3">{{ $loop->parent->iteration }}</td><td rowspan="3" data-type="s">{{ $row['code'] }}</td><td rowspan="3" data-type="s">{{ $row['waste_type'] }}</td><td rowspan="3" data-type="s">{{ $row['sources'] }}</td><td rowspan="3" data-type="s">{{ $row['unit'] }}</td><td rowspan="3" class="number" data-type="n" data-format="0.0000">{{ ($format ?? 'pdf')==='pdf' ? number_format($row['opening'],4,',','.') : $row['opening'] }}</td>@endif
<td>{{ $label }}</td>@foreach ($row['months'] as $month)<td class="number" data-type="{{ $key==='utilized' ? 's' : 'n' }}" data-format="0.0000">{{ $key==='utilized' ? '-' : (($format ?? 'pdf')==='pdf' ? number_format($month[$key],4,',','.') : $month[$key]) }}</td>@endforeach
@if ($loop->first)<td rowspan="3" class="number" data-type="n" data-format="0.0000">{{ ($format ?? 'pdf')==='pdf' ? number_format($row['incoming'],4,',','.') : $row['incoming'] }}</td><td rowspan="3" data-type="s" title="Pemanfaatan belum dipilah; bukan nol">-</td><td rowspan="3" class="number" data-type="n" data-format="0.0000">{{ ($format ?? 'pdf')==='pdf' ? number_format($row['outgoing'],4,',','.') : $row['outgoing'] }}</td><td rowspan="3" class="number" data-type="n" data-format="0.0000">{{ ($format ?? 'pdf')==='pdf' ? number_format($row['closing'],4,',','.') : $row['closing'] }}</td><td rowspan="3" data-type="s">{{ number_format($row['accounted'],4,',','.') }} / {{ number_format($row['available'],4,',','.') }}</td><td rowspan="3" data-type="s">{{ $row['balance_percent'] === null ? 'Tidak dihitung' : number_format($row['balance_percent'],2,',','.').'%' }}</td><td rowspan="3" data-type="s">{{ $row['anomaly_count'] ? $row['anomaly_count'].' anomali perlu diperiksa.' : '-' }}</td>@endif
</tr>@endforeach
@empty<tr><td colspan="26">Tidak ada transaksi atau saldo awal disetujui untuk tahun ini.</td></tr>@endforelse
</tbody></table>
<p class="small">Manfaatkan: - = data belum dipilah, bukan nol.</p>
@include('waste.reports.footer', ['columns'=>26, 'compact'=>true])
</body></html>
