<?php

namespace App\Http\Controllers\Srrv;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Services\ActivityLogger;
use App\Support\SrrvForms;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * Prints the PRA forms from a retiree file or a renewal, filled from what the desk
 * holds, or blank from the dashboard to fill in by hand.
 */
class SrrvFormController extends Controller
{
    public function application(SrrvApplication $application, string $form): View|RedirectResponse
    {
        // Nothing is printed until the admin has approved the file.
        if ($held = $application->approvalBlocker('printed')) {
            return redirect()->route('srrv.applications.show', $application)->with('error', $held);
        }

        ActivityLogger::log('SRRV', 'PRINT', 'Printed the PRA '.strtolower(SrrvForms::LABELS[$form])." for {$application->reference}");

        // Back into the wizard on its last step (where Print is); the file page tracks the PRA processing.
        $links = [
            ['Back to the application', route('srrv.applications.edit', [$application, 'step' => array_key_last(SrrvForms::WIZARD_STEPS)])],
            ['PRA progress', route('srrv.applications.show', $application)],
        ];
        foreach (SrrvForms::LABELS as $other => $label) {
            if ($other !== $form) {
                $links[] = [$label, route('srrv.applications.forms', [$application, $other])];
            }
        }

        return view("srrv.forms.{$form}", $this->data($application, $application, null, $links));
    }

    /** The renewal is filed on the Retiree Request Form, with ID Renewal ticked. */
    public function renewal(SrrvRenewal $renewal): View|RedirectResponse
    {
        if ($held = $renewal->approvalBlocker('printed')) {
            return redirect()->route('srrv.renewals.show', $renewal)->with('error', $held);
        }

        ActivityLogger::log('SRRV', 'PRINT', "Printed the PRA retiree request form for {$renewal->reference}");

        $application = $renewal->application;

        return view('srrv.forms.request', $this->data($renewal, $application, $renewal, [['Back to the renewal', route('srrv.renewals.show', $renewal)]]));
    }

    public function blank(string $form): View
    {
        $links = [['Back to the dashboard', route('srrv.dashboard')]];
        foreach (SrrvForms::LABELS as $other => $label) {
            if ($other !== $form) {
                $links[] = ["Blank {$label}", route('srrv.forms.blank', $other)];
            }
        }

        return view("srrv.forms.{$form}", $this->data(null, null, null, $links));
    }

    /**
     * What every PRA print reads: $d and $dt look in the file's own details, then the
     * linked retiree file's, and give nothing on a blank form.
     *
     * @param  list<array{0: string, 1: string}>  $links
     */
    private function data(SrrvApplication|SrrvRenewal|null $file, ?SrrvApplication $application, ?SrrvRenewal $renewal, array $links): array
    {
        $blank = $file === null;
        $sources = array_values(array_filter([$renewal, $application]));

        $d = function (string $key, mixed $fallback = null) use ($blank, $sources) {
            if ($blank) {
                return null;
            }
            foreach ($sources as $source) {
                if (($value = $source->detail($key)) !== null) {
                    return $value;
                }
            }

            return $fallback;
        };
        $dt = function (string $key) use ($d) {
            $value = $d($key);

            return is_string($value) ? Carbon::parse($value) : null;
        };

        return [
            'file' => $file,
            'application' => $application,
            'renewal' => $renewal,
            'blank' => $blank,
            'links' => $links,
            'd' => $d,
            'dt' => $dt,
            'name' => SrrvForms::nameParts($file, $application),
            // The dependent form prints for one dependent: the one asked for, else the first on the file.
            'who' => in_array(request('who'), ['spouse', 'child_1', 'child_2', 'child_3', 'child_4'], true) ? request('who') : array_key_first(SrrvForms::dependents($application)),
        ];
    }
}
