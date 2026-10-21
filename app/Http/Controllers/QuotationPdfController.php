<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationPdfController extends Controller
{
    public function download(Quotation $quotation)
    {
        $quotation->load(['user', 'material', 'approvedBy', 'quotationProcesses']);

        $pdf = Pdf::loadView('pdf.quotation', compact('quotation'))
            ->setPaper('a4', 'portrait');

        $fileName = 'Quotation-'.str_replace('/', '-', $quotation->document_number).'.pdf';

        return $pdf->stream($fileName);
    }
}
