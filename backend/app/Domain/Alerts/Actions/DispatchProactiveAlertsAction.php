<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Actions;

use App\Domain\Alerts\Models\SentAlert;
use App\Domain\Cards\Enums\InvoiceStatus;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Planning\Models\Goal;
use App\Domain\Planning\Queries\BudgetStatusQuery;
use App\Models\User;
use App\Notifications\BudgetNearLimitNotification;
use App\Notifications\GoalDeadlineAtRiskNotification;
use App\Notifications\InvoiceDueSoonNotification;
use Carbon\CarbonImmutable;

/**
 * Varre fatura vencendo, orcamento perto do limite e meta com prazo apertado
 * para um usuario, e manda um e-mail por assunto — no maximo uma vez cada,
 * nunca de novo, mesmo que a varredura rode todo dia enquanto a condicao
 * continuar valendo (ver SentAlert).
 */
final class DispatchProactiveAlertsAction
{
    private const INVOICE_DUE_SOON_DAYS = 3;

    private const BUDGET_NEAR_LIMIT_PERCENT = 80.0;

    private const GOAL_DEADLINE_AT_RISK_DAYS = 30;

    private const GOAL_DEADLINE_MIN_PROGRESS = 80.0;

    public function __construct(
        private readonly BudgetStatusQuery $budgetStatus,
    ) {}

    /** @return int quantos e-mails novos foram mandados */
    public function handle(User $user, ?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::now();

        return $this->invoicesDueSoon($user, $today)
            + $this->budgetsNearLimit($user, $today)
            + $this->goalsAtRisk($user, $today);
    }

    private function invoicesDueSoon(User $user, CarbonImmutable $today): int
    {
        $invoices = Invoice::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('status', InvoiceStatus::Fechada->value)
            ->whereBetween('due_date', [
                $today->toDateString(),
                $today->addDays(self::INVOICE_DUE_SOON_DAYS)->toDateString(),
            ])
            ->get();

        $sent = 0;

        foreach ($invoices as $invoice) {
            if ($invoice->remaining() <= 0.0 || $this->alreadySent('invoice_due_soon', $invoice->id)) {
                continue;
            }

            $user->notify(new InvoiceDueSoonNotification($invoice));
            $this->markSent($user, 'invoice_due_soon', $invoice->id, $today);
            $sent++;
        }

        return $sent;
    }

    private function budgetsNearLimit(User $user, CarbonImmutable $today): int
    {
        $statuses = $this->budgetStatus->handle($user->id, $today->startOfMonth());
        $sent = 0;

        foreach ($statuses as $status) {
            if ($status['percentage'] < self::BUDGET_NEAR_LIMIT_PERCENT) {
                continue;
            }

            if ($this->alreadySent('budget_near_limit', $status['id'])) {
                continue;
            }

            $user->notify(new BudgetNearLimitNotification($status));
            $this->markSent($user, 'budget_near_limit', $status['id'], $today);
            $sent++;
        }

        return $sent;
    }

    private function goalsAtRisk(User $user, CarbonImmutable $today): int
    {
        $goals = Goal::query()
            ->ownedBy($user->id)
            ->where('is_archived', false)
            ->whereNotNull('deadline')
            ->get();

        $sent = 0;

        foreach ($goals as $goal) {
            if ($goal->deadline === null || $goal->deadline->isPast()) {
                continue;
            }

            $daysLeft = $today->startOfDay()->diffInDays($goal->deadline->startOfDay());

            if ($daysLeft > self::GOAL_DEADLINE_AT_RISK_DAYS || $goal->progress() >= self::GOAL_DEADLINE_MIN_PROGRESS) {
                continue;
            }

            if ($this->alreadySent('goal_deadline_at_risk', $goal->id)) {
                continue;
            }

            $user->notify(new GoalDeadlineAtRiskNotification($goal));
            $this->markSent($user, 'goal_deadline_at_risk', $goal->id, $today);
            $sent++;
        }

        return $sent;
    }

    private function alreadySent(string $type, int $subjectId): bool
    {
        return SentAlert::query()->withoutUserScope()
            ->where('type', $type)
            ->where('subject_id', $subjectId)
            ->exists();
    }

    private function markSent(User $user, string $type, int $subjectId, CarbonImmutable $today): void
    {
        SentAlert::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'subject_id' => $subjectId,
            'sent_at' => $today,
        ]);
    }
}
