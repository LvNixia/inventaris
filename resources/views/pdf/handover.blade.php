<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Serah Terima</title>
    <style>
        @page {
            margin: 100px 50px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #000;
        }
        header {
            position: fixed;
            top: -60px;
            left: 0px;
            right: 0px;
            height: 50px;
        }
        footer {
            position: fixed;
            bottom: -60px;
            left: 0px;
            right: 0px;
            height: 50px;
            text-align: right;
            font-size: 10px;
        }
        .pagenum:before {
            content: counter(page);
        }
        .header-content {
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin-top: 20px;
            text-decoration: underline;
        }
        .subtitle {
            text-align: center;
            margin-bottom: 30px;
        }
        .parties {
            margin-bottom: 20px;
        }
        .party-table {
            width: 100%;
            margin-left: 20px;
        }
        .party-table td {
            padding: 2px;
            vertical-align: top;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 6px;
        }
        .items-table th {
            background-color: #f2f2f2;
        }
        .items-table tr {
            page-break-inside: avoid;
        }
        .items-table thead {
            display: table-header-group;
        }
        .signatures {
            width: 100%;
            margin-top: 50px;
            text-align: center;
            page-break-inside: avoid;
        }
        .signatures td {
            width: 33%;
            vertical-align: top;
        }
        .signature-box {
            height: 80px;
        }
        .watermark {
            position: absolute;
            top: 30%;
            left: 10%;
            font-size: 80px;
            color: rgba(255, 0, 0, 0.2);
            transform: rotate(-45deg);
            z-index: -1;
        }
    </style>
</head>
<body>

    <header>
        <div class="header-content">
            INDOSURTA<br>
            Surveying & Mapping Equipment<br>
            Sales, Service, Rental, & Calibration
        </div>
    </header>

    <footer>
        Hal. <span class="pagenum"></span>
    </footer>

    <main>
        @if($isDraft)
            <div class="watermark">DRAFT</div>
        @elseif($doc->status === 'cancelled')
            <div class="watermark">DIBATALKAN</div>
        @endif

        <div class="title">SURAT SERAH TERIMA BARANG</div>
        <div class="subtitle">
            Nomor: {{ $isDraft ? 'DRAFT — belum bernomor' : $doc->document_number }}
            @if($doc->status === 'cancelled')
                <br><span style="color:red">Dibatalkan pada: {{ $doc->cancelled_at?->format('d/m/Y') }}<br>Alasan: {{ $doc->cancel_reason }}</span>
            @endif
        </div>

        @php
            \Carbon\Carbon::setLocale('id');
            $date = \Carbon\Carbon::parse($doc->document_date);
        @endphp
        
        <p>Pada hari ini, <strong>{{ $date->isoFormat('dddd') }}</strong> tanggal <strong>{{ $date->isoFormat('D') }}</strong> bulan <strong>{{ $date->isoFormat('MMMM') }}</strong> tahun <strong>{{ $date->isoFormat('Y') }}</strong>, telah dilakukan serah terima barang antara:</p>

        <div class="parties">
            <strong>1. Pihak Pertama (Yang Menyerahkan)</strong>
            <table class="party-table">
                <tr><td width="100">Nama</td><td width="10">:</td><td>{{ $doc->first_party_name ?? $doc->firstParty->name }}</td></tr>
                <tr><td>Jabatan</td><td>:</td><td>{{ $doc->first_party_position ?? $doc->firstParty->position?->name ?? '-' }}</td></tr>
                <tr><td>Departemen</td><td>:</td><td>{{ $doc->first_party_division ?? $doc->firstParty->division?->name ?? '-' }}</td></tr>
            </table>
        </div>

        <div class="parties">
            <strong>2. Pihak Kedua (Yang Menerima)</strong>
            <table class="party-table">
                <tr><td width="100">Nama</td><td width="10">:</td><td>{{ $doc->second_party_name ?? $doc->secondParty->name }}</td></tr>
                <tr><td>Jabatan</td><td>:</td><td>{{ $doc->second_party_position ?? $doc->secondParty->position?->name ?? '-' }}</td></tr>
                <tr><td>Departemen</td><td>:</td><td>{{ $doc->second_party_division ?? $doc->secondParty->division?->name ?? '-' }}</td></tr>
            </table>
        </div>

        <p>Dengan ini Pihak Pertama menyerahkan kepada Pihak Kedua barang-barang sebagai berikut:</p>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="30">No</th>
                    <th>Nama Barang</th>
                    <th>Nomor Seri</th>
                    <th>Spesifikasi</th>
                    <th width="40">Jumlah</th>
                    <th>Kondisi</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($doc->items as $index => $item)
                    <tr>
                        <td style="text-align: center;">{{ $index + 1 }}</td>
                        <td>{{ $item->item_name ?? $item->asset->brand?->name . ' ' . $item->asset->model }}</td>
                        <td>{{ $item->serial_number ?? $item->asset->serial_number ?? $item->asset->imei_1 }}</td>
                        <td>
                            @php
                                $specs = $item->specifications ?? $item->asset->specifications;
                            @endphp
                            @if($specs && is_array($specs))
                                <ul style="margin: 0; padding-left: 15px;">
                                @foreach($specs as $key => $val)
                                    <li>{{ $key }}: {{ $val }}</li>
                                @endforeach
                                </ul>
                            @endif
                        </td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                        <td>{{ $item->condition ?? $item->asset->condition?->name }}</td>
                        <td>
                            {{ $item->remarks }}
                            @if($item->user_employee_id)
                                <br><i>Dipakai oleh: {{ $item->userEmployee?->name }}</i>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p>Barang-barang tersebut telah diperiksa bersama dan dinyatakan dalam kondisi baik. Setelah penandatanganan surat ini, seluruh tanggung jawab atas barang tersebut beralih kepada Pihak Kedua.</p>
        <p>Demikian surat serah terima barang ini dibuat untuk dipergunakan sebagaimana mestinya.</p>

        <table class="signatures">
            <tr>
                <td>
                    Pihak Pertama<br>
                    <div class="signature-box"></div>
                    ({{ $doc->first_party_name ?? $doc->firstParty->name }})<br>
                    {{ $doc->first_party_position ?? $doc->firstParty->position?->name ?? '-' }}
                </td>
                <td>
                    @if($doc->witness_id)
                        Mengetahui<br>
                        <div class="signature-box"></div>
                        ({{ $doc->witness_name ?? $doc->witness->name }})<br>
                        {{ $doc->witness_position ?? $doc->witness->position?->name ?? '-' }}
                    @endif
                </td>
                <td>
                    Pihak Kedua<br>
                    <div class="signature-box"></div>
                    ({{ $doc->second_party_name ?? $doc->secondParty->name }})<br>
                    {{ $doc->second_party_position ?? $doc->secondParty->position?->name ?? '-' }}
                </td>
            </tr>
        </table>
    </main>

</body>
</html>
