<?php

namespace App\Notifications;

use App\Models\ImmigrationQuotation;
use App\Services\ImmigrationQuotationPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sends the client their Immigration quotation, with the PDF attached. */
class ImmigrationQuotationNotification extends Notification
{
    use Queueable;

    public function __construct(public ImmigrationQuotation $quotation, public ?string $agentName = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $quotation = $this->quotation->loadMissing('items');

        $mail = (new MailMessage)
            ->subject('Your quotation '.$quotation->quote_number.' - Amega Travel and Tours')
            ->greeting('Hello '.$quotation->client_name.',')
            ->line('Thank you for your inquiry. Please find your quotation attached:');

        foreach ($quotation->items as $item) {
            $mail->line('• '.$item->description.($item->quantity > 1 ? ' × '.$item->quantity : '').': ₱'.number_format((float) $item->amount, 2));
        }

        if ((float) $quotation->discount > 0) {
            $mail->line('Discount: -₱'.number_format((float) $quotation->discount, 2));
        }

        return $mail
            ->line('**Total: ₱'.number_format((float) $quotation->total, 2).'**')
            ->line('This quotation is valid until '.$quotation->valid_until->format('F j, Y').'. Bureau of Immigration fees may change without prior notice.')
            ->line('Reply to this email or call us to proceed.')
            ->salutation("Regards,\n".($this->agentName ? $this->agentName."\n" : '').'Amega Travel and Tours Services')
            ->attachData(ImmigrationQuotationPdf::render($quotation), ImmigrationQuotationPdf::filename($quotation), ['mime' => 'application/pdf']);
    }
}
