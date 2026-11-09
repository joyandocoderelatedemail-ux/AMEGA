<?php

namespace App\Services;

use App\Models\ImmigrationQuotation;
use Barryvdh\DomPDF\Facade\Pdf;

/** The Immigration quotation on AMEGA's letterhead, for printing or emailing to the client. */
class ImmigrationQuotationPdf
{
    public static function render(ImmigrationQuotation $quotation): string
    {
        return Pdf::loadView('admin.immigration.quotations.pdf', [
            'quotation' => $quotation->loadMissing(['items', 'createdBy']),
            'logo' => TicketDocumentPdf::logo(),
        ])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public static function filename(ImmigrationQuotation $quotation): string
    {
        return 'Quotation-'.$quotation->quote_number.'.pdf';
    }
}
