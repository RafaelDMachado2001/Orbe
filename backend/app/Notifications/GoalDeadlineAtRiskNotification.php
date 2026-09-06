<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Planning\Models\Goal;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class GoalDeadlineAtRiskNotification extends Notification
{
    public function __construct(
        private readonly Goal $goal,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deadline = $this->goal->deadline?->translatedFormat('d \d\e F');
        $progress = number_format($this->goal->progress(), 0, ',', '.');
        $current = number_format($this->goal->currentAmount(), 2, ',', '.');
        $target = number_format((float) $this->goal->target_amount, 2, ',', '.');

        return (new MailMessage)
            ->subject("A meta \"{$this->goal->name}\" está no prazo apertado")
            ->greeting('Olá!')
            ->line("O prazo da meta \"{$this->goal->name}\" é {$deadline}, e ela está em {$progress}% (R$ {$current} de R$ {$target}).")
            ->line('No ritmo atual, talvez não dê tempo — pode ser hora de reforçar o aporte ou rever o prazo.')
            ->action('Ver meta', config('app.frontend_url').'/metas')
            ->salutation('Até já, Orbe.');
    }
}
