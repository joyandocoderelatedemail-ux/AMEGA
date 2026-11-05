<?php

namespace App\Support;

use App\Models\VisaApplication;
use App\Notifications\VisaStageDoneNotification;

/**
 * The template a visa counter file offers in its Notice box, for where it stands now: the agent copies it into the
 * message (or a text or Messenger), edits it, and notifies the client. Built from the same wording as the stage
 * emails (what was just done, what comes next), with the documents still missing while they are being collected.
 */
class VisaNotice
{
    /**
     * @return array{subject: string, body: string, label: string}
     */
    public static function for(VisaApplication $application, ?string $sender): array
    {
        $service = match ($application->service_type) {
            'e_visa' => 'e-Visa application',
            'passporting' => 'passport application',
            'citizenship' => 'citizenship claim',
            default => 'visa application',
        };
        $label = $application->status_label;
        $lines = ['Good day, '.($application->client_name ?: 'there').'!', "Here is an update on your {$service} (Ref. {$application->reference}) with AMEGA Travel and Tours."];

        if ($application->status === 'cancelled') {
            $lines[] = 'Your file has been cancelled. If you have questions about it, please contact our office.';
        } else {
            $done = $application->completedStages();
            $last = end($done);
            $lines[] = $last !== false
                ? str_replace('**', '', (new VisaStageDoneNotification($application, $last))->doneLine())
                : 'We have opened your file and will guide you through each step.';

            if (! in_array($application->status, ['released'], true) && ! $application->isReleasedVisit()) {
                $lines[] = "Your file is now at: {$label}.";
            }

            $missing = in_array($application->status, ['pending', 'requirements', 'agreement'], true) ? $application->missingDocuments() : [];
            if ($missing !== []) {
                $lines[] = "To move forward, we still need the following from you:\n".collect($missing)->map(fn (string $item) => '• '.$item)->implode("\n");
            }
        }

        $lines[] = 'If you have any questions, reply to this email or contact our office.';
        $lines[] = "Thank you,\n".($sender ? "{$sender}\n" : '').'AMEGA Travel and Tours';

        return [
            'subject' => "Update on your {$service} ({$application->reference}): {$label}",
            'body' => implode("\n\n", $lines),
            'label' => $label,
        ];
    }
}
