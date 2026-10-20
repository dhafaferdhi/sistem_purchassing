<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - {{ $purchaseOrder->po_number }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #333;
            margin: 15px;
        }
        .header {
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .company-title {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .doc-title {
            font-size: 14px;
            font-weight: bold;
            color: #334155;
            margin-top: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        th, td {
            padding: 6px 8px;
            text-align: left;
        }
        .table-bordered th, .table-bordered td {
            border: 1px solid #cbd5e1;
        }
        .table-bordered th {
            background-color: #f1f5f9;
            color: #1e293b;
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
            padding: 2px 7px;
            font-size: 9px;
            font-weight: bold;
            border-radius: 4px;
        }
        .badge-draft { background-color: #e2e8f0; color: #475569; }
        .badge-approved { background-color: #dcfce7; color: #166534; }
        .badge-sent { background-color: #fef3c7; color: #92400e; }
        .badge-completed { background-color: #dbeafe; color: #1e40af; }
        .badge-cancelled { background-color: #fee2e2; color: #991b1b; }
        .section-header {
            background-color: #1e3a8a;
            color: #fff;
            padding: 5px 8px;
            font-weight: bold;
            font-size: 11px;
            margin-top: 12px;
            margin-bottom: 8px;
            border-radius: 3px;
        }
        .grand-total-row {
            background-color: #ecfdf5;
            font-weight: bold;
            font-size: 12px;
            color: #065f46;
        }
        .signatures {
            margin-top: 25px;
        }
        .sig-box {
            text-align: center;
            width: 32%;
            float: left;
        }
        .sig-line {
            margin-top: 55px;
            border-top: 1px solid #333;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        .clearfix {
            clear: both;
        }
        .footer-note {
            font-size: 9px;
            color: #64748b;
            margin-top: 15px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 6px;
        }
    </style>
</head>
<body>

    <div class="header">
        <table style="margin-bottom: 0;">
            <tr>
                <td style="width: 60%; padding: 0;">
                    <div class="company-title">PT PUD MANUFACTURING INDONESIA</div>
                    <div style="color: #64748b; font-size: 10px; margin-top: 2px;">
                        Kawasan Industri MM2100, Cikarang Barat, Bekasi - Jawa Barat<br>
                        Telp: (021) 8980-0000 | Email: purchasing@pud-mfg.co.id
                    </div>
                </td>
                <td style="width: 40%; text-align: right; padding: 0;">
                    <div class="doc-title">PURCHASE ORDER (PO)</div>
                    <div style="font-size: 13px; font-weight: bold; color: #1e3a8a; margin-top: 2px;">{{ $purchaseOrder->po_number }}</div>
                    <div style="margin-top: 4px;">
                        Status:
                        <span class="badge badge-{{ strtolower(str_replace(' ', '', $purchaseOrder->status)) }}">
                            {{ strtoupper($purchaseOrder->status) }}
                        </span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Info PO & Vendor --}}
    <table class="table-bordered" style="margin-bottom: 12px;">
        <tr>
            <td style="width: 50%; vertical-align: top; background-color: #f8fafc;">
                <div style="font-weight: bold; color: #1e3a8a; margin-bottom: 4px; text-transform: uppercase;">KEPADA (VENDOR / SUPPLIER):</div>
                <div style="font-size: 12px; font-weight: bold;">{{ $purchaseOrder->supplier?->name }}</div>
                <div style="font-size: 10px; color: #475569; margin-top: 2px;">Kode Vendor: {{ $purchaseOrder->supplier?->code }}</div>
                <div style="font-size: 10px; color: #475569; margin-top: 2px;">Alamat: {{ $purchaseOrder->supplier?->address ?? 'Kawasan Industri Mitra Karawang' }}</div>
                <div style="font-size: 10px; color: #475569;">Kontak / Phone: {{ $purchaseOrder->supplier?->phone ?? '-' }}</div>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <table style="width: 100%; margin: 0; padding: 0;">
                    <tr>
                        <td style="padding: 2px 4px; width: 45%; color: #64748b;">Tanggal Order:</td>
                        <td style="padding: 2px 4px; font-weight: bold;">{{ $purchaseOrder->order_date ? $purchaseOrder->order_date->format('d/m/Y') : '-' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 4px; color: #64748b;">Target Pengiriman:</td>
                        <td style="padding: 2px 4px; font-weight: bold;">{{ $purchaseOrder->delivery_date ? $purchaseOrder->delivery_date->format('d/m/Y') : 'Sesuai Kesepakatan' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 4px; color: #64748b;">Termin Pembayaran:</td>
                        <td style="padding: 2px 4px; font-weight: bold;">{{ $purchaseOrder->payment_terms }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 4px; color: #64748b;">Mata Uang & Kurs:</td>
                        <td style="padding: 2px 4px; font-weight: bold;">{{ $purchaseOrder->currency?->code ?? 'IDR' }} (Rp {{ number_format($purchaseOrder->exchange_rate, 0, ',', '.') }})</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 4px; color: #64748b;">Dibuat Oleh:</td>
                        <td style="padding: 2px 4px; font-weight: bold;">{{ $purchaseOrder->user?->name ?? 'Purchasing Staff' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-header">DAFTAR BARANG YANG DIPESAN (LINE ITEMS)</div>
    <table class="table-bordered">
        <thead>
            <tr>
                <th style="width: 5%;" class="text-center">No</th>
                <th style="width: 18%;">Kode Item</th>
                <th style="width: 18%;">Kode Item Vendor</th>
                <th style="width: 25%;">Deskripsi & Spesifikasi</th>
                <th style="width: 8%;" class="text-center">Qty</th>
                <th style="width: 8%;" class="text-center">Satuan</th>
                <th style="width: 18%;" class="text-right">Harga Satuan (IDR)</th>
                <th style="width: 20%;" class="text-right">Total Harga (IDR)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($purchaseOrder->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td style="font-family: monospace; font-weight: bold;">{{ $item->item_code }}</td>
                    <td style="font-family: monospace; color: #64748b;">{{ $item->vendor_item_code ?: '-' }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="text-center font-bold">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $item->unit }}</td>
                    <td class="text-right">Rp {{ number_format($item->unit_price_idr, 2, ',', '.') }}</td>
                    <td class="text-right font-bold">Rp {{ number_format($item->total_price_idr, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="color: #94a3b8; padding: 15px;">Tidak ada item dalam Purchase Order ini.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" class="text-right" style="font-weight: bold; background-color: #f8fafc;">Subtotal:</td>
                <td class="text-right" style="font-weight: bold; background-color: #f8fafc;">
                    Rp {{ number_format($purchaseOrder->subtotal_idr, 2, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td colspan="7" class="text-right" style="font-weight: bold; background-color: #f8fafc;">
                    PPN ({{ number_format($purchaseOrder->tax_percentage, 1) }}%):
                </td>
                <td class="text-right" style="font-weight: bold; background-color: #f8fafc;">
                    Rp {{ number_format($purchaseOrder->tax_amount_idr, 2, ',', '.') }}
                </td>
            </tr>
            <tr class="grand-total-row">
                <td colspan="7" class="text-right" style="text-transform: uppercase;">GRAND TOTAL PEMBELIAN:</td>
                <td class="text-right">
                    Rp {{ number_format($purchaseOrder->total_amount_idr, 2, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Instruksi & Catatan --}}
    @if($purchaseOrder->notes)
        <table class="table-bordered">
            <tr>
                <td style="background-color: #fffbeb; border-color: #fef3c7;">
                    <div style="font-weight: bold; color: #b45309; margin-bottom: 2px;">Instruksi Khusus & Catatan Pengiriman:</div>
                    <div style="color: #78350f;">{{ $purchaseOrder->notes }}</div>
                </td>
            </tr>
        </table>
    @endif

    {{-- Kotak Tanda Tangan --}}
    <div class="signatures">
        <div class="sig-box">
            <div>Dibuat Oleh,</div>
            <div style="color: #64748b; font-size: 10px;">Purchasing Staff</div>
            <div class="sig-line"></div>
            <div style="font-weight: bold; margin-top: 5px;">{{ $purchaseOrder->user?->name ?? 'Staff Purchasing' }}</div>
        </div>
        <div class="sig-box">
            <div>Disetujui Oleh,</div>
            <div style="color: #64748b; font-size: 10px;">Procurement Manager</div>
            <div class="sig-line"></div>
            <div style="font-weight: bold; margin-top: 5px;">{{ $purchaseOrder->approvedBy?->name ?? 'Management / Approver' }}</div>
        </div>
        <div class="sig-box">
            <div>Diterima & Dikonfirmasi,</div>
            <div style="color: #64748b; font-size: 10px;">Vendor Acceptance</div>
            <div class="sig-line"></div>
            <div style="font-weight: bold; margin-top: 5px;">{{ $purchaseOrder->supplier?->name }}</div>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="footer-note">
        * Surat Pesanan (PO) ini sah dan mengikat antara kedua belah pihak. Harap konfirmasi dan kirimkan salinan bertanda tangan ke purchasing@pud-mfg.co.id maksimal 2x24 jam setelah PO diterima.
    </div>

</body>
</html>

