<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ ($format ?? 'pdf') === 'excel' ? ($sheet_title ?? $title) : $title }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 12px;
            margin: 24px;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 8px;
        }

        .meta,
        .summary,
        .table-wrap,
        .warnings {
            margin-top: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #e2e8f0;
            font-weight: 700;
        }

        .summary td:first-child,
        .meta td:first-child {
            width: 220px;
            font-weight: 600;
            background: #f8fafc;
        }

        .warning-list {
            margin: 0;
            padding-left: 18px;
        }

        .muted {
            color: #475569;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="muted">Periode: {{ $period_label }} | Generated at: {{ now()->format('Y-m-d H:i:s') }}</p>

    @if (! empty($filters))
        <div class="meta">
            <table>
                <tbody>
                    @foreach ($filters as $label => $value)
                        <tr>
                            <td>{{ $label }}</td>
                            <td>{{ $value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (! empty($summary_rows))
        <div class="summary">
            <table>
                <thead>
                    <tr>
                        <th colspan="2">Ringkasan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary_rows as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>{{ $row['value'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    @foreach ($table_columns as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($table_rows as $row)
                    <tr>
                        @foreach ($row as $value)
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($table_columns) }}">Tidak ada data untuk filter ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (! empty($warnings))
        <div class="warnings">
            <table>
                <thead>
                    <tr>
                        <th>Warnings / Anomalies</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <ul class="warning-list">
                                @foreach ($warnings as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
