<?php

namespace App\Http\Controllers\Srrv;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\SrrvServiceRequest;
use App\Services\ActivityLogger;
use App\Support\SrrvForms;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

/**
 * The SRRV Assistance Booking Agreement, printed from a retiree file, an ID renewal or a service
 * request and filled from it (and from the retiree file it belongs to). It is signed when the client
 * engages AMEGA, so unlike the PRA forms it prints before the file is approved.
 */
class SrrvAgreementController extends Controller
{
    public function application(SrrvApplication $application): View
    {
        // Printed from the wizard's Service Agreement step, so back goes there.
        return $this->show($application, $application, [['Back to the application', route('srrv.applications.edit', [$application, 'step' => SrrvForms::AGREEMENT_STEP])]]);
    }

    public function renewal(SrrvRenewal $renewal): View
    {
        return $this->show($renewal, $renewal->application, [['Back to the renewal', route('srrv.renewals.show', $renewal)]]);
    }

    public function serviceRequest(SrrvServiceRequest $serviceRequest): View
    {
        return $this->show($serviceRequest, $serviceRequest->application, [['Back to the request', route('srrv.requests.show', $serviceRequest)]]);
    }

    /** @param  list<array{0: string, 1: string}>  $links */
    private function show(SrrvApplication|SrrvRenewal|SrrvServiceRequest $file, ?SrrvApplication $application, array $links): View
    {
        ActivityLogger::log('SRRV', 'PRINT', "Printed the SRRV assistance booking agreement for {$file->reference}");

        return view('srrv.agreement', ['agreement' => self::data($file, $application), 'links' => $links]);
    }

    /**
     * What the agreement shows, from the file and the retiree file behind it.
     *
     * @return array<string, mixed>
     */
    public static function data(SrrvApplication|SrrvRenewal|SrrvServiceRequest $file, ?SrrvApplication $application): array
    {
        $detail = fn (string $key) => ($file instanceof SrrvApplication ? null : $file->detail($key)) ?? $application?->detail($key);
        $date = function (?string $key) use ($detail): ?string {
            $value = $key ? $detail($key) : null;

            return $value ? Carbon::parse($value)->format('F j, Y') : null;
        };
        [$last, $first, $middle] = SrrvForms::nameParts($file, $application);
        $name = trim(collect([$first, $middle, $last])->filter()->implode(' ')) ?: (string) $file->retiree_name;

        // Each dependent with how they are related and when they were born.
        $dependents = collect(SrrvForms::dependents($application))->map(function (string $dependentName, string $who) use ($date): string {
            $born = $date("{$who}_dob");

            return $dependentName.' ('.($who === 'spouse' ? 'Spouse' : 'Child').($born ? ", born {$born}" : '').')';
        });
        $birthDate = $application?->date_of_birth ?? ($file instanceof SrrvApplication ? $file->date_of_birth : null);
        $passport = $detail('passport_no');
        $expires = $date('passport_expires_on');

        // The service the file is for: a new application, another application service, a renewal, or a request.
        $isNew = $file instanceof SrrvApplication && $file->service_type === 'renewal_application';
        $money = fn (string $currency, float $amount) => $amount > 0 ? $currency.' '.number_format($amount, 2) : '';

        // What the client pays AMEGA, as saved on the file, with what the cashier has taken so far. A service request
        // may charge in USD and PHP, so it lists each currency it charges in.
        $charges = match (true) {
            $file instanceof SrrvApplication => [[$file->currency ?: 'PHP', (float) $file->service_fee, (float) $file->amount_paid]],
            $file instanceof SrrvRenewal => [[$file->currency ?: 'PHP', (float) $file->fee_amount, (float) $file->amount_paid]],
            default => array_values(array_filter([['USD', (float) $file->fee_usd, (float) $file->paid_usd], ['PHP', (float) $file->fee_php, (float) $file->paid_php]], fn ($charge) => $charge[1] > 0)) ?: [['PHP', 0.0, 0.0]],
        };
        $amega = collect($charges)->flatMap(fn (array $charge) => [
            ['Service fee', $money($charge[0], $charge[1]), true],
            ['Paid to date', $money($charge[0], $charge[2]), false],
            ['Balance', $money($charge[0], max(0, $charge[1] - $charge[2])), false],
        ])->all();

        // A new application also owes the PRA its processing and annual fees (USD) and the visa deposit (the investment amount).
        $pra = [];
        if ($isNew) {
            $fees = $file->praFees();
            $pra = [
                ['PRA processing and annual fees (estimate)', $money('USD', $fees['annual_fee'] + $fees['processing_fee'])],
                ['Visa deposit (investment amount)', $money($file->investment_currency ?: 'USD', (float) $file->investment_amount)],
            ];
        }

        return [
            'reference' => $file->reference,
            'name' => $name,
            'client' => [
                'Full name' => $name,
                'Date of birth' => $birthDate?->format('F j, Y'),
                'Nationality' => $application?->nationality ?? ($file instanceof SrrvApplication ? $file->nationality : null),
                'Passport no.' => $passport ? $passport.($expires ? " (valid until {$expires})" : '') : null,
                'Mobile / WhatsApp' => $file->retiree_phone ?? $application?->retiree_phone,
                'Email' => $file->retiree_email ?? $application?->retiree_email,
                'Philippine address' => $detail('primary_address'),
                'Overseas address' => $detail('home_address'),
            ],
            'dependents' => $dependents->implode('; '),
            'service' => match (true) {
                $file instanceof SrrvRenewal => 'SRRV ID renewal'.($file->years_paid ? ", {$file->years_paid} year(s)" : ''),
                $file instanceof SrrvServiceRequest => $file->typeLabel(),
                $isNew => SrrvApplication::SERVICE_LABELS['renewal_application'],
                default => SrrvApplication::SERVICE_LABELS[$file->service_type] ?? null,
            },
            'option' => $application?->optionLabel(),
            'applicants' => 1 + $dependents->count(),
            'amega' => $amega,
            'pra' => $pra,
            'agent' => auth()->user()?->name,
        ];
    }
}
