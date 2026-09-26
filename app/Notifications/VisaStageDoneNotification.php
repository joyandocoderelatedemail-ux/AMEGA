<?php

namespace App\Notifications;

use App\Models\VisaApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the client that one stage of their visa or passport file is done,
 * sent by staff from the file's Client Updates panel.
 */
class VisaStageDoneNotification extends Notification
{
    use Queueable;

    public function __construct(public VisaApplication $application, public string $stage) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $application = $this->application;
        $stageLabel = $application->stageLabel($this->stage);

        $message = (new MailMessage)
            ->subject("{$stageLabel} done - {$application->reference}")
            ->greeting('Hello '.($application->client_name ?: 'there').',')
            ->line("An update on your {$this->serviceName()} ({$application->reference}):")
            ->line($this->doneLine());

        if (! in_array($this->stage, ['lodged', 'released'], true) && ($next = $this->nextStageLabel())) {
            $message->line("**Next step:** {$next}");
        }

        return $message
            ->line('If you have any questions, reply to this email or contact our office.')
            ->salutation("Regards,\nAmega Travel and Tours Services");
    }

    /**
     * What finishing this stage means for the client, in plain words.
     */
    public function doneLine(): string
    {
        $application = $this->application;

        return match ($this->stage) {
            'pending' => 'We have opened your file and will guide you through each step.',
            'requirements' => 'We have received all your requirements.',
            'agreement' => 'Your signed agreement is on file.',
            'insurance' => $application->insurance_declined
                ? 'We have noted that you declined travel insurance.'
                : 'Your travel insurance is arranged'.($application->insurance_policy_number ? " (policy {$application->insurance_policy_number})." : '.'),
            'etravel' => 'Your e-Travel registration is done'.($application->etravel_reference ? " (reference {$application->etravel_reference})." : '.'),
            'payment' => 'We have received your full payment. Thank you.',
            'acknowledged' => 'Your signed Acknowledgment of Documents is on file.',
            'appointment' => $application->appointment_at
                ? 'Your DFA appointment is set for **'.$application->appointment_at->format('l, F j, Y').($application->appointment_at->format('H:i') !== '00:00' ? ' at '.$application->appointment_at->format('g:i A') : '').'**. Please bring your requirements and arrive early.'
                : 'Your DFA appointment has been arranged.',
            'lodged' => 'Your application has finished processing.',
            'released' => $this->releasedLine(),
            default => $application->stageLabel($this->stage).' is done.',
        };
    }

    private function releasedLine(): string
    {
        return match ($this->application->service_type) {
            'passporting' => 'Your passport has been released. Please visit our office to collect it.',
            'e_visa' => 'Your e-Visa has been released.',
            default => $this->application->result === 'approved'
                ? 'Good news: your visa has been approved. Please visit our office to collect your documents.'
                : 'The result of your visa application is in. Please contact our office and we will go through it with you.',
        };
    }

    private function nextStageLabel(): ?string
    {
        $stages = $this->application->stages();
        $index = array_search($this->stage, $stages, true);
        $next = $index === false ? null : ($stages[$index + 1] ?? null);

        return $next ? $this->application->stageLabel($next) : null;
    }

    private function serviceName(): string
    {
        return match ($this->application->service_type) {
            'e_visa' => 'e-Visa application',
            'passporting' => 'passport application',
            default => 'visa application',
        };
    }
}
