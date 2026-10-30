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
        return $this->show($application, $application, [['Back to the file', route('srrv.applications.show', $application)]]);
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

        $dependents = SrrvForms::dependents($application);
        $relations = collect($dependents)->map(function (string $dependentName, string $who) use ($date): string {
            $relation = $who === 'spouse' ? 'Spouse' : 'Child';
            $born = $date("{$who}_dob");

            return $born ? "{$relation}, born {$born}" : $relation;
        });
        $birthDate = $application?->date_of_birth ?? ($file instanceof SrrvApplication ? $file->date_of_birth : null);
        $gender = ['M' => 'Male', 'F' => 'Female'][$detail('gender')] ?? null;

        // The service the file is for: a new application, a dependent, a renewal, or another request.
        $isNew = $file instanceof SrrvApplication && $file->service_type === 'renewal_application';
        $other = match (true) {
            $file instanceof SrrvServiceRequest => $file->typeLabel(),
            $file instanceof SrrvApplication && ! $isNew => SrrvApplication::SERVICE_LABELS[$file->service_type] ?? null,
            default => null,
        };

        // The PRA checklist, ticked as the documents came in (new applications only; the others have their own lists).
        $documents = [];
        $received = $outstanding = [];
        if ($file instanceof SrrvApplication) {
            $checklist = collect($file->documentChecklist())->keyBy('type');
            $has = fn (string ...$types) => collect($types)->every(fn ($type) => $checklist->get($type)['received'] ?? false);
            $documents = [
                'Passport copy / passport details' => $has('passport'),
                'Application forms and photographs, if required' => $has('application_form', 'id_photos'),
                'Civil status / dependent documents, if applicable' => $checklist->has('relationship_proof') && $has('relationship_proof'),
                'Police clearances or other clearances, if required' => $has('police_clearance'),
                'Medical documents, if required' => $has('medical_certificate'),
                'Proof of deposit / financial documents, if applicable to the selected option' => $has('visa_deposit'),
            ];
            $received = $checklist->where('received', true)->pluck('label')->all();
            $outstanding = $checklist->where('required', true)->where('received', false)->pluck('label')->all();
        } else {
            $documents = array_fill_keys([
                'Passport copy / passport details', 'Application forms and photographs, if required', 'Civil status / dependent documents, if applicable',
                'Police clearances or other clearances, if required', 'Medical documents, if required', 'Proof of deposit / financial documents, if applicable to the selected option',
            ], false);
        }

        // Fees in the file's own currency: AMEGA's fee, and for a new application the PRA's annual and processing fees (USD).
        [$currency, $serviceFee] = match (true) {
            $file instanceof SrrvApplication => [$file->currency ?: 'PHP', (float) $file->service_fee],
            $file instanceof SrrvRenewal => [$file->currency ?: 'PHP', (float) $file->fee_amount],
            default => (float) $file->fee_php > 0 || (float) $file->fee_usd <= 0 ? ['PHP', (float) $file->fee_php] : ['USD', (float) $file->fee_usd],
        };
        $praFee = null;
        if ($isNew && $currency === 'USD') {
            $fees = $file->praFees();
            $praFee = $fees['annual_fee'] + $fees['processing_fee'];
        }
        $money = fn (?float $amount) => $amount ? number_format($amount, 2) : '';

        return [
            'reference' => $file->reference,
            'name' => $name,
            'client' => [
                'Principal Applicant Full Name' => $name,
                'Date of Birth' => $birthDate?->format('F j, Y'),
                'Nationality' => $application?->nationality ?? ($file instanceof SrrvApplication ? $file->nationality : null),
                'Sex (if required)' => $gender,
                'Passport Number' => $detail('passport_no'),
                'Passport Expiry Date' => $date('passport_expires_on'),
                'Philippine Address (if any)' => $detail('primary_address'),
                'Overseas Address' => $detail('home_address'),
                'Mobile / WhatsApp Number' => $file->retiree_phone ?? $application?->retiree_phone,
                'Email Address' => $file->retiree_email ?? $application?->retiree_email,
                'Spouse / Dependent Name(s)' => implode(', ', $dependents),
                'Relationship / Date of Birth' => $relations->implode('; '),
                'Preferred Contact Method' => null,
                'Preferred Contact Time / Time Zone' => null,
            ],
            'requested' => [
                'Initial SRRV application' => $isNew,
                'Dependent application' => $isNew && $dependents !== [],
                'Renewal / extension assistance' => $file instanceof SrrvRenewal,
                'Document preparation' => false,
                'PRA appointment coordination' => false,
            ],
            'other' => $other,
            'option' => $application?->optionLabel(),
            'applicants' => $application ? 1 + count($dependents) : 1,
            'scope' => match (true) {
                $file instanceof SrrvRenewal => 'SRRV ID renewal'.($file->years_paid ? ", {$file->years_paid} year(s)" : ''),
                $isNew => SrrvApplication::SERVICE_LABELS['renewal_application'].($application ? ' ('.$application->optionLabel().')' : ''),
                default => $other,
            },
            'currency' => $currency === 'USD' ? 'USD' : 'PHP',
            'service_fee' => $money($serviceFee),
            'pra_fee' => $money($praFee),
            'total' => $money($serviceFee + (float) $praFee),
            'documents' => $documents,
            'received' => implode(', ', $received),
            'outstanding' => implode(', ', $outstanding),
            'agent' => auth()->user()?->name,
        ];
    }
}
