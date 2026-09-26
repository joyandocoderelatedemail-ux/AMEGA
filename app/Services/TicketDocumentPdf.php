<?php

namespace App\Services;

use App\Models\BookingAgreement;
use App\Models\TicketBooking;
use App\Support\BookingAgreementSheet;
use App\Support\DataPrivacyConsent;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders a ticket's paperwork as PDFs, for attaching to the client's emails.
 */
class TicketDocumentPdf
{
    private const LOGO_PATH = 'newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png';

    /**
     * Width the letterhead logo is scaled down to. The source is 2680px wide,
     * which on its own makes each PDF over a megabyte.
     */
    private const LOGO_WIDTH = 600;

    private static ?string $logo = null;

    public static function bookingAgreement(BookingAgreement $agreement): string
    {
        return Pdf::loadView('ticketing.agreements.pdf', [
            'agreement' => $agreement,
            'logo' => self::logo(),
        ] + BookingAgreementSheet::data($agreement))
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public static function dataPrivacyConsent(TicketBooking $ticket): string
    {
        return Pdf::loadView('consent.data-privacy-pdf', [
            'consent' => DataPrivacyConsent::forTicket($ticket),
            'handlingAgent' => $ticket->issuedBy?->name,
            'logo' => self::logo(),
        ])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    /**
     * The letterhead logo inlined, so the PDF never has to fetch a URL.
     */
    private static function logo(): ?string
    {
        $path = public_path(self::LOGO_PATH);

        if (! is_file($path)) {
            return null;
        }

        return self::$logo ??= 'data:image/png;base64,'.base64_encode(self::scaledPng($path));
    }

    /**
     * The PNG at LOGO_WIDTH, keeping its transparency; the original bytes
     * when GD is unavailable.
     */
    private static function scaledPng(string $path): string
    {
        $original = (string) file_get_contents($path);
        $source = function_exists('imagecreatefromstring') ? @imagecreatefromstring($original) : false;

        if ($source === false || imagesx($source) <= self::LOGO_WIDTH) {
            return $original;
        }

        $height = (int) round(imagesy($source) * self::LOGO_WIDTH / imagesx($source));
        $scaled = imagecreatetruecolor(self::LOGO_WIDTH, $height);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagecopyresampled($scaled, $source, 0, 0, 0, 0, self::LOGO_WIDTH, $height, imagesx($source), imagesy($source));

        ob_start();
        imagepng($scaled, null, 9);

        return (string) ob_get_clean();
    }
}
