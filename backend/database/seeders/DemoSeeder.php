<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Banking\Enums\AccountType;
use App\Domain\Banking\Enums\BankKind;
use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Actions\CloseInvoiceAction;
use App\Domain\Cards\Actions\PayInvoiceAction;
use App\Domain\Cards\Actions\RegisterCardPurchaseAction;
use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Identity\Actions\RegisterUserAction;
use App\Domain\Identity\DTOs\RegisterUserData;
use App\Domain\Ledger\Actions\RecordTransactionAction;
use App\Domain\Ledger\DTOs\TransactionData;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\RecurrenceFrequency;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Recurrence;
use App\Domain\Planning\Models\Budget;
use App\Domain\Planning\Models\Goal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Base de demonstracao com sete meses de historico realista em pt-BR.
 *
 * O mes corrente e montado com valores fixos para bater com a tela de
 * referencia; os meses anteriores seguem a mesma composicao com uma variacao
 * deterministica, de forma que o grafico e a projecao tenham um historico
 * coerente sem depender de sorteio.
 */
class DemoSeeder extends Seeder
{
    private const EMAIL = 'demo@versofinancas.app';

    private const PASSWORD = 'verso1234';

    /** Meses de historico gerados antes do mes corrente. */
    private const HISTORY_MONTHS = 6;

    /**
     * Fator aplicado a receita e a despesa de cada mes anterior, do mais
     * antigo para o mais recente. Reproduz a curva do grafico da referencia.
     *
     * @var list<array{income: float, expense: float}>
     */
    private const HISTORY_CURVE = [
        ['income' => 0.69, 'expense' => 0.73],
        ['income' => 0.66, 'expense' => 0.85],
        ['income' => 0.75, 'expense' => 0.65],
        ['income' => 0.82, 'expense' => 1.05],
        ['income' => 0.78, 'expense' => 0.78],
        ['income' => 0.86, 'expense' => 0.92],
    ];

    public function run(): void
    {
        if (User::query()->where('email', self::EMAIL)->exists()) {
            $this->command->warn('Base de demonstracao ja existe, nada a fazer.');

            return;
        }

        $user = app(RegisterUserAction::class)->handle(new RegisterUserData(
            name: 'Rafael Machado',
            email: self::EMAIL,
            password: self::PASSWORD,
        ));

        $categories = Category::query()
            ->ownedBy($user->id)
            ->get()
            ->keyBy('name');

        $banks = $this->createBanks($user);
        $accounts = $this->createAccounts($user, $banks);
        $cards = $this->createCards($user, $banks, $accounts);

        $recurrences = $this->createRecurrences($user, $categories, $accounts, $cards);

        $currentMonth = CarbonImmutable::now()->startOfMonth();

        for ($offset = self::HISTORY_MONTHS; $offset >= 1; $offset--) {
            $curve = self::HISTORY_CURVE[self::HISTORY_MONTHS - $offset];

            $this->seedMonth(
                month: $currentMonth->subMonthsNoOverflow($offset),
                categories: $categories,
                accounts: $accounts,
                cards: $cards,
                recurrences: $recurrences,
                incomeFactor: $curve['income'],
                expenseFactor: $curve['expense'],
            );
        }

        $this->seedMonth($currentMonth, $categories, $accounts, $cards, $recurrences, 1.0, 1.0);

        $this->seedInstallmentPurchase($categories, $cards, $currentMonth);
        $this->settlePastInvoices($user);
        $this->createBudgets($user, $categories, $currentMonth);
        $this->createGoals($user, $accounts);
        $this->alignAccountBalances($user, $accounts);

        $this->command->info('Base de demonstracao criada. Acesse com '.self::EMAIL.' / '.self::PASSWORD);
    }

    /** @return array<string, Bank> */
    private function createBanks(User $user): array
    {
        $definitions = [
            ['name' => 'Nubank', 'slug' => 'nubank', 'color' => '#A07CFF', 'kind' => BankKind::Digital],
            ['name' => 'Itaú', 'slug' => 'itau', 'color' => '#FF8A3D', 'kind' => BankKind::Tradicional],
            ['name' => 'Inter', 'slug' => 'inter', 'color' => '#35D68A', 'kind' => BankKind::Digital],
        ];

        $banks = [];

        foreach ($definitions as $definition) {
            $banks[$definition['slug']] = Bank::query()->create([
                'user_id' => $user->id,
                ...$definition,
            ]);
        }

        return $banks;
    }

    /**
     * @param  array<string, Bank>  $banks
     * @return array<string, Account>
     */
    private function createAccounts(User $user, array $banks): array
    {
        $definitions = [
            'nubank' => ['bank' => 'nubank', 'nickname' => 'Conta corrente', 'type' => AccountType::Corrente],
            'itau' => ['bank' => 'itau', 'nickname' => 'Conta corrente', 'type' => AccountType::Corrente],
            'inter' => ['bank' => 'inter', 'nickname' => 'Reserva de emergência', 'type' => AccountType::Poupanca],
        ];

        $accounts = [];

        foreach ($definitions as $key => $definition) {
            $accounts[$key] = Account::query()->create([
                'user_id' => $user->id,
                'bank_id' => $banks[$definition['bank']]->id,
                'nickname' => $definition['nickname'],
                'type' => $definition['type'],
                'initial_balance' => 0,
                'is_active' => true,
            ]);
        }

        return $accounts;
    }

    /**
     * @param  array<string, Bank>  $banks
     * @param  array<string, Account>  $accounts
     * @return array<string, CreditCard>
     */
    private function createCards(User $user, array $banks, array $accounts): array
    {
        $definitions = [
            'nubank' => [
                'bank' => 'nubank', 'account' => 'nubank', 'nickname' => 'Nubank Ultravioleta',
                'brand' => 'mastercard', 'last_four' => '4417', 'limit_amount' => 12000,
                'closing_day' => 28, 'due_day' => 8, 'color' => '#A07CFF',
            ],
            'itau' => [
                'bank' => 'itau', 'account' => 'itau', 'nickname' => 'Itaú Click',
                'brand' => 'visa', 'last_four' => '8802', 'limit_amount' => 8000,
                'closing_day' => 24, 'due_day' => 2, 'color' => '#FF8A3D',
            ],
            'inter' => [
                'bank' => 'inter', 'account' => 'inter', 'nickname' => 'Inter Gold',
                'brand' => 'mastercard', 'last_four' => '1290', 'limit_amount' => 6000,
                'closing_day' => 26, 'due_day' => 5, 'color' => '#35D68A',
            ],
        ];

        $cards = [];

        foreach ($definitions as $key => $definition) {
            $cards[$key] = CreditCard::query()->create([
                'user_id' => $user->id,
                'bank_id' => $banks[$definition['bank']]->id,
                'payment_account_id' => $accounts[$definition['account']]->id,
                'nickname' => $definition['nickname'],
                'brand' => $definition['brand'],
                'last_four' => $definition['last_four'],
                'limit_amount' => $definition['limit_amount'],
                'closing_day' => $definition['closing_day'],
                'due_day' => $definition['due_day'],
                'color' => $definition['color'],
                'is_active' => true,
            ]);
        }

        return $cards;
    }

    /**
     * @param  Collection<string, Category>  $categories
     * @param  array<string, Account>  $accounts
     * @param  array<string, CreditCard>  $cards
     * @return array<string, int> id da recorrencia indexado pela descricao
     */
    private function createRecurrences(
        User $user,
        Collection $categories,
        array $accounts,
        array $cards,
    ): array {
        $start = CarbonImmutable::now()->subMonthsNoOverflow(self::HISTORY_MONTHS)->startOfMonth();

        $definitions = [
            ['Salário', 'Salário', 10400.00, TransactionType::Receita, 5, 'nubank', null, PaymentMethod::Transferencia],
            ['Aluguel recebido', 'Aluguel recebido', 950.00, TransactionType::Receita, 10, 'inter', null, PaymentMethod::Pix],
            ['Aluguel apartamento', 'Moradia', 2900.00, TransactionType::Despesa, 28, 'nubank', null, PaymentMethod::Boleto],
            ['Condomínio', 'Moradia', 620.00, TransactionType::Despesa, 10, 'nubank', null, PaymentMethod::Boleto],
            ['Plano de saúde', 'Saúde', 460.10, TransactionType::Despesa, 15, 'itau', null, PaymentMethod::Debito],
            ['Assinaturas digitais', 'Assinaturas', 331.40, TransactionType::Despesa, 18, null, 'nubank', PaymentMethod::Credito],
            ['Telefonia', 'Assinaturas', 249.90, TransactionType::Despesa, 15, null, 'nubank', PaymentMethod::Credito],
        ];

        $created = [];

        foreach ($definitions as [$description, $category, $amount, $type, $day, $accountKey, $cardKey, $method]) {
            $created[$description] = Recurrence::query()->create([
                'user_id' => $user->id,
                'category_id' => $categories[$category]->id,
                'account_id' => $accountKey === null ? null : $accounts[$accountKey]->id,
                'credit_card_id' => $cardKey === null ? null : $cards[$cardKey]->id,
                'description' => $description,
                'amount' => $amount,
                'type' => $type,
                'method' => $method,
                'frequency' => RecurrenceFrequency::Mensal,
                'interval' => 1,
                'day_of_month' => $day,
                'starts_on' => $start->toDateString(),
                'ends_on' => null,
                'is_active' => true,
            ])->id;
        }

        return $created;
    }

    /**
     * Composicao de um mes. Os valores do mes corrente reproduzem exatamente
     * os totais por categoria da tela de referencia.
     *
     * @param  Collection<string, Category>  $categories
     * @param  array<string, Account>  $accounts
     * @param  array<string, CreditCard>  $cards
     * @param  array<string, int>  $recurrences
     */
    private function seedMonth(
        CarbonImmutable $month,
        Collection $categories,
        array $accounts,
        array $cards,
        array $recurrences,
        float $incomeFactor,
        float $expenseFactor,
    ): void {
        $recordTransaction = app(RecordTransactionAction::class);
        $registerPurchase = app(RegisterCardPurchaseAction::class);

        foreach ($this->incomes() as [$description, $category, $amount, $day, $accountKey]) {
            $recordTransaction->handle(new TransactionData(
                accountId: $accounts[$accountKey]->id,
                description: $description,
                amount: $this->scale($amount, $incomeFactor),
                type: TransactionType::Receita,
                competenceDate: $this->dayOf($month, $day),
                categoryId: $categories[$category]->id,
                method: PaymentMethod::Transferencia,
                paidDate: $this->dayOf($month, $day),
                recurrenceId: $recurrences[$description] ?? null,
            ));
        }

        foreach ($this->accountExpenses() as [$description, $category, $amount, $day, $accountKey, $method]) {
            $recordTransaction->handle(new TransactionData(
                accountId: $accounts[$accountKey]->id,
                description: $description,
                amount: $this->scale($amount, $expenseFactor),
                type: TransactionType::Despesa,
                competenceDate: $this->dayOf($month, $day),
                categoryId: $categories[$category]->id,
                method: $method,
                paidDate: $this->dayOf($month, $day),
                recurrenceId: $recurrences[$description] ?? null,
            ));
        }

        foreach ($this->cardExpenses() as [$description, $category, $amount, $day, $cardKey]) {
            $registerPurchase->handle(new CardPurchaseData(
                creditCardId: $cards[$cardKey]->id,
                description: $description,
                amount: $this->scale($amount, $expenseFactor),
                purchaseDate: $this->dayOf($month, $day),
                installments: 1,
                categoryId: $categories[$category]->id,
                recurrenceId: $recurrences[$description] ?? null,
            ));
        }
    }

    /** @return list<array{0: string, 1: string, 2: float, 3: int, 4: string}> */
    private function incomes(): array
    {
        return [
            ['Salário', 'Salário', 10400.00, 5, 'nubank'],
            ['Pagamento cliente PJ', 'Serviços PJ', 7400.00, 27, 'inter'],
            ['Aluguel recebido', 'Aluguel recebido', 950.00, 10, 'inter'],
        ];
    }

    /** @return list<array{0: string, 1: string, 2: float, 3: int, 4: string, 5: PaymentMethod}> */
    private function accountExpenses(): array
    {
        return [
            ['Aluguel apartamento', 'Moradia', 2900.00, 28, 'nubank', PaymentMethod::Boleto],
            ['Condomínio', 'Moradia', 620.00, 10, 'nubank', PaymentMethod::Boleto],
            ['Energia elétrica', 'Moradia', 330.00, 15, 'itau', PaymentMethod::Debito],
            ['Combustível', 'Transporte', 780.00, 12, 'itau', PaymentMethod::Debito],
            ['Manutenção do carro', 'Transporte', 577.40, 19, 'itau', PaymentMethod::Pix],
            ['Plano de saúde', 'Saúde', 460.10, 15, 'itau', PaymentMethod::Debito],
        ];
    }

    /** @return list<array{0: string, 1: string, 2: float, 3: int, 4: string}> */
    private function cardExpenses(): array
    {
        return [
            ['Mercado São Jorge', 'Alimentação', 684.22, 22, 'itau'],
            ['Mercado do bairro', 'Alimentação', 512.40, 8, 'nubank'],
            ['Restaurantes', 'Alimentação', 738.30, 16, 'nubank'],
            ['iFood', 'Alimentação', 675.08, 20, 'inter'],
            ['Uber', 'Transporte', 212.60, 21, 'itau'],
            ['Estacionamento', 'Transporte', 210.00, 14, 'itau'],
            ['Assinaturas digitais', 'Assinaturas', 331.40, 18, 'nubank'],
            ['Software e nuvem', 'Assinaturas', 449.90, 12, 'nubank'],
            ['Streaming família', 'Assinaturas', 292.80, 10, 'nubank'],
            ['Telefonia', 'Assinaturas', 249.90, 15, 'nubank'],
            ['Viagem de fim de semana', 'Lazer', 620.00, 9, 'nubank'],
            ['Show e cinema', 'Lazer', 340.00, 17, 'nubank'],
            ['Bar com amigos', 'Lazer', 230.00, 23, 'inter'],
        ];
    }

    /**
     * Compra parcelada em 10x feita ha dois meses: no mes corrente ela aparece
     * como a parcela 3/10, exatamente como na tela de referencia.
     *
     * @param  Collection<string, Category>  $categories
     * @param  array<string, CreditCard>  $cards
     */
    private function seedInstallmentPurchase(
        Collection $categories,
        array $cards,
        CarbonImmutable $currentMonth,
    ): void {
        app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
            creditCardId: $cards['nubank']->id,
            description: 'Notebook Dell',
            amount: 7499.00,
            purchaseDate: $this->dayOf($currentMonth->subMonthsNoOverflow(2), 24),
            installments: 10,
            categoryId: $categories['Equipamento']->id,
            notes: 'Compra parcelada em 10x sem juros.',
        ));
    }

    /**
     * Fecha as faturas cujo ciclo ja passou e quita as que ja venceram.
     *
     * Sem isso o limite dos cartoes ficaria eternamente comprometido e o saldo
     * das contas so cresceria — nenhuma das duas coisas acontece na vida real.
     */
    private function settlePastInvoices(User $user): void
    {
        $today = CarbonImmutable::now();

        $invoices = Invoice::query()
            ->ownedBy($user->id)
            ->with('creditCard.paymentAccount')
            ->where('closing_date', '<=', $today->toDateString())
            ->orderBy('reference_month')
            ->get();

        $close = app(CloseInvoiceAction::class);
        $pay = app(PayInvoiceAction::class);

        foreach ($invoices as $invoice) {
            $close->handle($invoice, $today);

            $account = $invoice->creditCard->paymentAccount;

            if ($account === null || $invoice->due_date->gt($today) || $invoice->remaining() <= 0.0) {
                continue;
            }

            $pay->handle($invoice, $account, paidAt: $invoice->due_date);
        }
    }

    /**
     * @param  Collection<string, Category>  $categories
     */
    private function createBudgets(
        User $user,
        Collection $categories,
        CarbonImmutable $month,
    ): void {
        $limits = [
            'Moradia' => 4000.00,
            'Alimentação' => 2400.00,
            'Transporte' => 2000.00,
            'Assinaturas' => 1500.00,
            'Lazer' => 1200.00,
            'Saúde' => 800.00,
        ];

        foreach ($limits as $category => $limit) {
            Budget::query()->create([
                'user_id' => $user->id,
                'category_id' => $categories[$category]->id,
                'reference_month' => $month->toDateString(),
                'limit_amount' => $limit,
            ]);
        }
    }

    /** @param array<string, Account> $accounts */
    private function createGoals(User $user, array $accounts): void
    {
        Goal::query()->create([
            'user_id' => $user->id,
            'account_id' => $accounts['inter']->id,
            'name' => 'Reserva de emergência',
            'target_amount' => 60000.00,
            'initial_amount' => 40800.00,
            'deadline' => CarbonImmutable::now()->addMonths(10)->toDateString(),
            'is_archived' => false,
        ]);

        Goal::query()->create([
            'user_id' => $user->id,
            'account_id' => null,
            'name' => 'Troca do carro',
            'target_amount' => 45000.00,
            'initial_amount' => 12500.00,
            'deadline' => CarbonImmutable::now()->addMonths(20)->toDateString(),
            'is_archived' => false,
        ]);
    }

    /**
     * Ajusta o saldo inicial de cada conta para que o saldo consolidado do
     * mes corrente caia nos valores da tela de referencia. Sem isso, o saldo
     * dependeria apenas dos sete meses gerados e comecaria negativo.
     *
     * @param  array<string, Account>  $accounts
     */
    private function alignAccountBalances(User $user, array $accounts): void
    {
        $targets = [
            'nubank' => 21402.10,
            'itau' => 8115.44,
            'inter' => 12801.10,
        ];

        $movements = DB::table('transactions')
            ->select('account_id')
            ->selectRaw('SUM(signed_amount) AS total')
            ->where('user_id', $user->id)
            ->where('status', 'confirmado')
            ->whereNotNull('account_id')
            ->groupBy('account_id')
            ->pluck('total', 'account_id');

        foreach ($targets as $key => $target) {
            $account = $accounts[$key];
            $moved = (float) ($movements[$account->id] ?? 0);

            $account->forceFill(['initial_balance' => round($target - $moved, 2)])->save();
        }
    }

    private function dayOf(CarbonImmutable $month, int $day): CarbonImmutable
    {
        $start = $month->startOfMonth();

        return $start->setDay(min($day, $start->daysInMonth));
    }

    /** Arredonda para centavos para nao gerar valores com fracao invisivel. */
    private function scale(float $amount, float $factor): float
    {
        return round($amount * $factor, 2);
    }
}
