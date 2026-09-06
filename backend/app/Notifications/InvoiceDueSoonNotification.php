<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Cards\Models\Invoice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InvoiceDueSoonNotification extends Notification
{
    public function __construct(
        private readonly Invoice $invoice,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $card = $this->invoice->creditCard;
        $dueDate = $this->invoice->due_date->translatedFormat('d \d\e F');
        $remaining = number_format($this->invoice->remaining(), 2, ',', '.');

        return (new MailMessage)
            ->subject("Fatura do {$card->nickname} vence em breve")
            ->greeting('Olá!')
            ->line("A fatura do cartão {$card->nickname}, final {$card->last_four}, vence em {$dueDate}.")
            ->line("Valor em aberto: R$ {$remaining}.")
            ->action('Ver fatura', config('app.frontend_url').'/cartoes')
            ->line('Se já pagou, pode ignorar este e-mail — o Orbe só não sabe disso ainda.')
            ->salutation('Até já, Orbe.');
    }
}
