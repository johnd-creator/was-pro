<table class="header"><tr>
<td colspan="{{ $columns < 20 ? 3 : 5 }}" style="width:20%">
@if ($report['pln_branding'])
@if (($format ?? 'pdf') === 'pdf')<img src="{{ public_path('logo_ip.png') }}" style="width:140px" alt="PLN Indonesia Power">@else PLN Indonesia Power @endif
@else <strong>WASPRO</strong><br>Waste Storage and Process Record Online @endif
</td>
<td colspan="{{ $columns < 20 ? 6 : 15 }}" style="text-align:center"><strong>@if ($report['pln_branding'])PT PLN INDONESIA POWER<br>@endif{{ $report['organization'] }}</strong><br>{{ $report['pln_branding'] ? 'IN POWER RECORD DOCUMENTS' : 'DOKUMEN PENCATATAN LIMBAH B3' }}<br><strong>{{ $title }}</strong></td>
<td colspan="{{ $columns < 20 ? 3 : 6 }}" data-type="s">No. Dokumen: {{ $report['metadata']['document_number'] ?? 'Belum diisi' }}<br>Tanggal Terbit: {{ $report['metadata']['document_date'] ?? 'Belum diisi' }}<br>Revisi: {{ $report['metadata']['revision'] ?? 'Belum diisi' }}</td>
</tr></table>
<p><strong>{{ $period }}</strong></p>
<p class="small">{{ $report['scope_label'] }}. Dibuat {{ $report['generated_at'] }} oleh {{ $report['generated_by'] }}. Dokumen ini belum ditandatangani.</p>
