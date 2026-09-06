<?php

declare(strict_types=1);

use App\Domain\Alerts\Actions\DispatchProactiveAlertsAction;
use App\Domain\Banking\Models\Account;
use App\Domain\Cards\Enums\InvoiceStatus;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Planning\Models\Budget;
use App\Domain\Planning\Models\Goal;
use App\Models\User;
use App\Notifications\BudgetNearLimitNotification;
use App\Notifications\GoalDeadlineAtRiskNotification;
use App\Notifications\InvoiceDueSoonNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    $this->user = User::factory()->create();
    $this->today = CarbonImmutable::parse('2026-09-05');
});

it('avisa fatura fechada vencendo nos proximos dias', function (): void {
    $card = CreditCard::factory()->create(['user_id' => $this->user->id]);
    $invoice = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'credit_card_id' => $card->id,
        'status' => InvoiceStatus::Fechada,
        'due_date' => $this->today->addDays(2)->toDateString(),
        'total' => 500,
        'paid_amount' => 0,
    ]);

    (new DispatchProactiveAlertsAction(app(\App\Domain\Planning\Queries\BudgetStatusQuery::class)))
        ->handle($this->user, $this->today);

    Notification::assertSentTo($this->user, InvoiceDueSoonNotification::class);
});

it('nao avisa fatura que vence longe ou ja paga', function (): void {
    $farCard = CreditCard::factory()->create(['user_id' => $this->user->id]);
    $paidCard = CreditCard::factory()->create(['user_id' => $this->user->id]);

    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'credit_card_id' => $farCard->id,
        'status' => InvoiceStatus::Fechada,
        'due_date' => $this->today->addDays(20)->toDateString(),
    ]);

    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'credit_card_id' => $paidCard->id,
        'status' => InvoiceStatus::Fechada,
        'due_date' => $this->today->addDay()->toDateString(),
        'total' => 300,
        'paid_amount' => 300,
    ]);

    (new DispatchProactiveAlertsAction(app(\App\Domain\Planning\Queries\BudgetStatusQuery::class)))
        ->handle($this->user, $this->today);

    Notification::assertNothingSent();
});

it('avisa orcamento perto do limite', function (): void {
    $category = Category::factory()->create(['user_id' => $this->user->id, 'type' => CategoryType::Despesa]);
    $account = Account::factory()->create(['user_id' => $this->user->id]);

    Budget::factory()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'reference_month' => $this->today->startOfMonth()->toDateString(),
        'limit_amount' => 100,
    ]);

    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 85,
        'competence_date' => $this->today->toDateString(),
        'paid_date' => $this->today->toDateString(),
    ]);

    (new DispatchProactiveAlertsAction(app(\App\Domain\Planning\Queries\BudgetStatusQuery::class)))
        ->handle($this->user, $this->today);

    Notification::assertSentTo($this->user, BudgetNearLimitNotification::class);
});

it('avisa meta com prazo apertado e progresso baixo', function (): void {
    Goal::factory()->create([
        'user_id' => $this->user->id,
        'target_amount' => 10000,
        'initial_amount' => 1000,
        'deadline' => $this->today->addDays(10)->toDateString(),
    ]);

    (new DispatchProactiveAlertsAction(app(\App\Domain\Planning\Queries\BudgetStatusQuery::class)))
        ->handle($this->user, $this->today);

    Notification::assertSentTo($this->user, GoalDeadlineAtRiskNotification::class);
});

it('nao avisa meta com progresso adiantado', function (): void {
    Goal::factory()->create([
        'user_id' => $this->user->id,
        'target_amount' => 10000,
        'initial_amount' => 9000,
        'deadline' => $this->today->addDays(10)->toDateString(),
    ]);

    (new DispatchProactiveAlertsAction(app(\App\Domain\Planning\Queries\BudgetStatusQuery::class)))
        ->handle($this->user, $this->today);

    Notification::assertNothingSent();
});

it('nunca manda o mesmo alerta duas vezes', function (): void {
    Goal::factory()->create([
        'user_id' => $this->user->id,
        'target_amount' => 10000,
        'initial_amount' => 1000,
        'deadline' => $this->today->addDays(10)->toDateString(),
    ]);

    $action = new DispatchProactiveAlertsAction(app(\App\Domain\Planning\Queries\BudgetStatusQuery::class));

    $firstRun = $action->handle($this->user, $this->today);
    $secondRun = $action->handle($this->user, $this->today->addDay());

    expect($firstRun)->toBe(1)->and($secondRun)->toBe(0);

    Notification::assertSentToTimes($this->user, GoalDeadlineAtRiskNotification::class, 1);
});
