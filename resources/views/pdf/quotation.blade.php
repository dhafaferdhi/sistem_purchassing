<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cost Estimation - {{ $quotation->document_number }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 20px;
        }
        .header {
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .company-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .doc-title {
            font-size: 14px;
            font-weight: bold;
            color: #555;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            padding: 7px 10px;
            text-align: left;
        }
        .table-bordered th, .table-bordered td {
            border: 1px solid #ddd;
        }
        .table-bordered th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: bold;
            border-radius: 4px;
        }
        .badge-draft { background-color: #e2e8f0; color: #475569; }
        .badge-submitted { background-color: #fef3c7; color: #92400e; }
        .badge-approved { background-color: #dcfce7; color: #166534; }
        .badge-rejected { background-color: #fee2e2; color: #991b1b; }
        .section-header {
            background-color: #1e3a8a;
            color: #fff;
            padding: 6px 10px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 10px;
            border-radius: 3px;
        }
        .grand-total-row {
            background-color: #e0f2fe;
            font-weight: bold;
            font-size: 13px;
            color: #0369a1;
        }
        .signatures {
            margin-top: 30px;
        }
        .sig-box {
            text-align: center;
            width: 30%;
            float: left;
        }
        .sig-line {
            margin-top: 60px;
            border-top: 1px solid #333;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        .clearfix {
            clear: both;
        }
    </style>
</head>
<body>

    <div class="header">
        <table style="margin-bottom: 0;">
            <tr>
                <td style="padding: 0;">
                    <div class="company-title">PT. PURCHASING & MANUFACTURING SYSTEM</div>
                    <div class="doc-title">DOKUMEN ESTIMASI BIAYA & HARGA BELI MATERIAL (COST ESTIMATION)</div>
                </td>
                <td class="text-right" style="padding: 0; vertical-align: top;">
                    <strong>No. Dokumen:</strong> {{ $quotation->document_number }}<br>
                    <strong>Tanggal:</strong> {{ $quotation->created_at->format('d/m/Y H:i') }}<br>
                    <strong>Status:</strong> 
                    <span class="badge badge-{{ $quotation->status }}">
                        {{ strtoupper($quotation->status) }}
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Spesifikasi Komponen -->
    <div class="section-header">1. SPESIFIKASI PRODUK & MATERIAL</div>
    <table class="table-bordered">
        <tr>
            <th width="25%">Kategori Produk</th>
            <td width="25%"><strong>{{ $quotation->category }}</strong></td>
            <th width="25%">Material Spec</th>
            <td width="25%">{{ $quotation->material?->spec ?? '-' }}</td>
        </tr>
        <tr>
            <th>No. Drawing / Gambar</th>
            <td>{{ $quotation->drawing_number ?? '-' }} ({{ $quotation->revision_number ?? 'Rev 0' }})</td>
            <th>Masa Berlaku</th>
            <td>{{ $quotation->validity_until ? $quotation->validity_until->format('d/m/Y') : '-' }}</td>
        </tr>
        <tr>
            @if($quotation->category === 'Fastener')
                <th>Diameter</th>
                <td>{{ $quotation->diameter ?? '-' }} mm</td>
                <th>Weight (Berat)</th>
                <td>{{ number_format($quotation->weight, 4) }} kg</td>
            @else
                <th>Thickness (Ketebalan)</th>
                <td>{{ $quotation->thickness ?? '-' }} mm</td>
                <th>Size Material</th>
                <td>{{ $quotation->size_material ?? '-' }}</td>
            @endif
        </tr>
        @if($quotation->category === 'Presspart')
        <tr>
            <th>Weight (Berat)</th>
            <td>{{ number_format($quotation->weight, 4) }} kg</td>
            <th>Cavity / Scrap</th>
            <td>{{ $quotation->cavity_count ?? 1 }} Cavity @if($quotation->scrap_recovery_value > 0) | Scrap: Rp {{ number_format($quotation->scrap_recovery_value, 2, ',', '.') }} @endif</td>
        </tr>
        @endif
    </table>

    <!-- Snapshot Variabel Pasar -->
    <div class="section-header">2. PARAMETER HARGA & VARIABEL EKONOMI (SNAPSHOT)</div>
    <table class="table-bordered">
        <tr>
            <th width="25%">Basic Rate (USD)</th>
            <td width="25%">$ {{ number_format($quotation->snapshot_basic_rate, 4) }}</td>
            <th width="25%">Exchange Rate (IDR)</th>
            <td width="25%">Rp {{ number_format($quotation->snapshot_exchange_rate, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Fluktuasi JFE</th>
            <td>{{ $quotation->category === 'Presspart' ? number_format($quotation->snapshot_fluktuasi, 4) : 'N/A (Khusus Presspart)' }}</td>
            <th>Harga Dasar Material (Price IDR)</th>
            <td><strong>Rp {{ number_format($quotation->price_idr, 2, ',', '.') }}</strong></td>
        </tr>
    </table>

    <!-- Rincian Biaya Proses Manufaktur -->
    <div class="section-header">3. RINCIAN TAHAPAN PROSES MANUFAKTUR</div>
    <table class="table-bordered">
        <thead>
            <tr>
                <th width="10%" class="text-center">No</th>
                <th width="60%">Nama Proses</th>
                <th width="30%" class="text-right">Biaya Terkunci (Applied Cost)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($quotation->quotationProcesses as $index => $qp)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $qp->process_name }}</td>
                    <td class="text-right">Rp {{ number_format($qp->applied_cost, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center" style="color: #888;">Tidak ada proses manufaktur tambahan.</td>
                </tr>
            @endforelse
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td colspan="2" class="text-right">TOTAL BIAYA PROSES:</td>
                <td class="text-right">Rp {{ number_format($quotation->total_process_cost, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Rekapitulasi Biaya & Grand Total -->
    <div class="section-header">4. REKAPITULASI BIAYA KESELURUHAN (TOTAL COST)</div>
    <table class="table-bordered">
        <tr>
            <th width="60%">Material Cost (Price IDR)</th>
            <td width="40%" class="text-right">Rp {{ number_format($quotation->price_idr, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Total Process Cost</th>
            <td class="text-right">Rp {{ number_format($quotation->total_process_cost, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Profit Margin ({{ number_format($quotation->profit_percentage, 0) }}%)</th>
            <td class="text-right">Rp {{ number_format($quotation->profit, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Biaya Transportasi (Transport)</th>
            <td class="text-right">Rp {{ number_format($quotation->transport, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <th>Biaya Pengepakan (Packing)</th>
            <td class="text-right">Rp {{ number_format($quotation->packing, 2, ',', '.') }}</td>
        </tr>
        <tr class="grand-total-row">
            <th>TOTAL COST (HPP FINAL)</th>
            <td class="text-right">Rp {{ number_format($quotation->total_cost, 2, ',', '.') }}</td>
        </tr>
    </table>

    @if($quotation->notes)
        <div style="margin-top: 10px; font-size: 11px;">
            <strong>Catatan Tambahan:</strong><br>
            <em>{{ $quotation->notes }}</em>
        </div>
    @endif

    <!-- Tanda Tangan -->
    <div class="signatures">
        <div class="sig-box">
            Dibuat Oleh (Estimator),
            <div class="sig-line"></div>
            <strong>{{ $quotation->user?->name ?? 'Staff Estimator' }}</strong>
        </div>
        <div class="sig-box" style="float: right;">
            Disetujui Oleh (Management),
            <div class="sig-line"></div>
            <strong>{{ $quotation->approvedBy?->name ?? 'Management / Approver' }}</strong>
            <br>
            <small>{{ $quotation->approved_at ? $quotation->approved_at->format('d/m/Y') : '(Belum Disetujui)' }}</small>
        </div>
        <div class="clearfix"></div>
    </div>

</body>
</html>

