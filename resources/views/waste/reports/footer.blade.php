@unless ($compact ?? false)
<p class="small">{{ $report['basis'] }}</p>
@foreach ($report['warnings'] as $warning)<p class="small">Catatan: {{ $warning }}</p>@endforeach
@endunless
<table class="signatures"><tr><td colspan="{{ (int) ($columns / 2) }}">Mengetahui<br>Manager Operasi<br><br><br><br><span data-type="s">{{ $report['metadata']['manager_name'] ?? '(nama dan tanda tangan)' }}</span></td><td colspan="{{ $columns - (int) ($columns / 2) }}" data-type="s">{{ $report['metadata']['location'] ?? '(tempat)' }}, ........................<br>Asman K3 dan Lingkungan<br><br><br><br>{{ $report['metadata']['environment_manager_name'] ?? '(nama dan tanda tangan)' }}</td></tr></table>
