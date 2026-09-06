<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Planning\Queries\BudgetStatusQuery;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** @phpstan-import-type BudgetStatus from BudgetStatusQuery */
final class BudgetNearLimitNotification extends Notification
{
    /** @param  BudgetStatus  $status */
    public function __construct(
        private readonly array $status,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $category = $this->status['category'];
        $percentage = number_format($this->status['percentage'], 0, ',', '.');
        $spent = number_format($this->status['spent'], 2, ',', '.');
        $limit = number_format($this->status['limit_amount'], 2, ',', '.');
        $isExceeded = $this->status['is_exceeded'] === true;

        return (new MailMessage)
            ->subject($isExceeded ? "Orçamento de {$category} estourado" : "Orçamento de {$category} quase no limite")
            ->greeting('Olá!')
            ->line(
                $isExceeded
                    ? "Você já gastou R$ {$spent} em {$category} este mês — {$percentage}% do limite de R$ {$limit}."
                    : "Você já usou {$percentage}% do orçamento de {$category} este mês (R$ {$spent} de R$ {$limit}).",
            )
            ->action('Ver orçamentos', config('app.frontend_url').'/orcamentos')
            ->salutation('Até já, Orbe.');
    }
}
