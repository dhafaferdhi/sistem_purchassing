<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseOrderPdfController extends Controller
{
    public function download(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'user', 'approvedBy', 'currency', 'items.part']);

        $pdf = Pdf::loadView('pdf.purchase-order', compact('purchaseOrder'))
            ->setPaper('a4', 'portrait');

        $fileName = 'PO-'.str_replace('/', '-', $purchaseOrder->po_number).'.pdf';

        return $pdf->stream($fileName);
    }
}
