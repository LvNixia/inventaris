<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm 12mm 16mm 12mm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #111827;
            line-height: 1.4;
        }

        .kop {
            border-bottom: 1.5px solid #111827;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }

        .kop h1 {
            font-size: 14px;
            margin: 0 0 2px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .kop .app { font-size: 10px; color: #4b5563; margin: 0; }

        .meta { width: 100%; margin-bottom: 10px; }
        .meta td { padding: 1px 0; font-size: 8.5px; color: #374151; vertical-align: top; }
        .meta td.label { width: 90px; color: #6b7280; }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data thead th {
            background: #f3f4f6;
            border: .5px solid #d1d5db;
            padding: 4px 5px;
            text-align: left;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: .2px;
        }

        table.data tbody td {
            border: .5px solid #e5e7eb;
            padding: 3px 5px;
            font-size: 8.5px;
        }

        table.data tbody tr:nth-child(even) td { background: #fafafa; }

        .kosong {
            text-align: center;
            padding: 18px;
            color: #6b7280;
            font-style: italic;
        }

        .kaki {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            font-size: 7.5px;
            color: #6b7280;
        }

        .kaki .kanan { float: right; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $title }}</h1>
        <p class="app">{{ config('app.name') }} &mdash; Sistem Inventaris Aset</p>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Dicetak</td>
            <td>{{ $printedAt }}@if ($printedBy) oleh {{ $printedBy }}@endif</td>
        </tr>
        @foreach ($meta as $label => $value)
            <tr>
                <td class="label">{{ $label }}</td>
                <td>{{ $value }}</td>
            </tr>
        @endforeach
        <tr>
            <td class="label">Jumlah baris</td>
            <td>{{ number_format(count($rows), 0, ',', '.') }}</td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="kosong" colspan="{{ count($headings) }}">
                        Tidak ada data yang cocok dengan filter yang dipilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="kaki">
        <span>{{ $title }}</span>
        <span class="kanan">Dicetak {{ $printedAt }}</span>
    </div>
</body>
</html>
