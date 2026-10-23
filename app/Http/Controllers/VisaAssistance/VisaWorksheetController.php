<?php

namespace App\Http\Controllers\VisaAssistance;

use App\Http\Controllers\Controller;
use App\Models\VisaApplicant;
use App\Models\VisaApplication;
use App\Services\ActivityLogger;
use App\Support\FormSpec;
use App\Support\VisaWorksheets;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * An applicant's country worksheet: the details the embassy form asks beyond the
 * applicant record, entered at the counter and printed on AMEGA's worksheet.
 */
class VisaWorksheetController extends Controller
{
    public function edit(VisaApplication $application, VisaApplicant $applicant): View
    {
        $worksheet = $this->worksheet($application, $applicant);

        return view('visa-assistance.worksheets.edit', [
            'application' => $application,
            'applicant' => $applicant,
            'worksheet' => $worksheet,
        ]);
    }

    public function update(Request $request, VisaApplication $application, VisaApplicant $applicant): RedirectResponse
    {
        $worksheet = $this->worksheet($application, $applicant);
        $validated = $request->validate(VisaWorksheets::rules($worksheet));

        $applicant->update(['form_details' => FormSpec::clean($validated['form'] ?? null)]);

        ActivityLogger::log('Visa Assistance', 'WORKSHEET', "Saved the {$worksheet} worksheet of {$applicant->full_name} on {$application->reference}");

        if ($request->input('then') === 'print') {
            return redirect()->route('visa.worksheets.print', [$application, $applicant]);
        }

        return redirect()->route('visa.applications.show', $application)
            ->with('success', VisaWorksheets::TITLES[$worksheet]." saved for {$applicant->full_name}.");
    }

    public function print(VisaApplication $application, VisaApplicant $applicant): View|RedirectResponse
    {
        if ($held = $application->approvalBlocker('printed')) {
            return redirect()->route('visa.applications.show', $application)->with('error', $held);
        }

        $worksheet = $this->worksheet($application, $applicant);

        ActivityLogger::log('Visa Assistance', 'PRINT', "Printed the {$worksheet} worksheet of {$applicant->full_name} on {$application->reference}");

        return view("visa-assistance.worksheets.{$worksheet}", [
            'application' => $application,
            'applicant' => $applicant,
            'blank' => false,
            'links' => [
                ['Back to the file', route('visa.applications.show', $application)],
                ['Edit the worksheet', route('visa.worksheets.edit', [$application, $applicant])],
            ],
        ]);
    }

    public function blank(string $worksheet): View
    {
        return view("visa-assistance.worksheets.{$worksheet}", [
            'application' => new VisaApplication,
            'applicant' => new VisaApplicant,
            'blank' => true,
            'links' => [['Back to the dashboard', route('visa.dashboard')]],
        ]);
    }

    /** The worksheet for this applicant's file; 404 when the applicant is not on it or the country has none. */
    private function worksheet(VisaApplication $application, VisaApplicant $applicant): string
    {
        abort_unless($applicant->visa_application_id === $application->id, 404);

        return VisaWorksheets::forCountry($application->destination_country) ?? abort(404);
    }
}
