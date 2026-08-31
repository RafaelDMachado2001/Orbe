<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Installment;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Import\Models\ImportBatch;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-20 09:00:00'));

    $this->user = User::factory()->create();
    $this->bank = Bank::factory()->create(['user_id' => $this->user->id, 'name' => 'Nubank']);

    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'nickname' => 'Conta corrente',
        'initial_balance' => 10000,
    ]);

    $this->card = CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'nickname' => 'Nubank Ultravioleta',
        'closing_day' => 28,
        'due_day' => 8,
    ]);

    $this->category = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Alimentação',
        'type' => 'despesa',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function importPayload(object $context, array $overrides = []): array
{
    return [
        'filename' => 'extrato-agosto.ofx',
        'format' => 'ofx',
        'account_id' => $context->account->id,
        'skipped_count' => 1,
        'rows' => [
            [
                'date' => '2026-08-03',
                'description' => 'Mercado Pão de Açúcar',
                'amount' => 284.90,
                'direction' => 'saida',
                'category_id' => $context->category->id,
            ],
            [
                'date' => '2026-08-05',
                'description' => 'Salário agosto',
                'amount' => 7200.00,
                'direction' => 'entrada',
                'category_id' => null,
            ],
        ],
        ...$overrides,
    ];
}

it('grava as linhas confirmadas na conta', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/imports', importPayload($this))
        ->assertCreated()
        ->assertJsonPath('data.imported_count', 2)
        ->assertJsonPath('data.skipped_count', 1)
        ->assertJsonPath('data.target', 'conta')
        ->assertJsonPath('data.destination', 'Conta corrente')
        ->assertJsonPath('data.period_start', '2026-08-03')
        ->assertJsonPath('data.period_end', '2026-08-05')
        ->assertJsonPath('data.can_undo', true);

    $batch = ImportBatch::query()->ownedBy($this->user->id)->firstOrFail();
    $transactions = Transaction::query()->ownedBy($this->user->id)->orderBy('competence_date')->get();

    expect($transactions)->toHaveCount(2)
        ->and($transactions[0]->type)->toBe(TransactionType::Despesa)
        ->and($transactions[0]->category_id)->toBe($this->category->id)
        // Extrato e registro do que ja aconteceu: nada nele nasce previsto.
        ->and($transactions[0]->status)->toBe(TransactionStatus::Confirmado)
        ->and($transactions[0]->paid_date?->toDateString())->toBe('2026-08-03')
        ->and($transactions[0]->notes)->toBe('Importado de extrato-agosto.ofx')
        ->and($transactions[0]->import_batch_id)->toBe($batch->id)
        ->and($transactions[1]->type)->toBe(TransactionType::Receita);

    // Saldo: 10.000 - 284,90 + 7.200 = 16.915,10.
    expect($this->account->refresh()->currentBalance())->toBe(16915.10);
});

/**
 * Importar precisa produzir o mesmo registro que digitar na tela produz —
 * inclusive a parcela e a fatura do ciclo certo.
 */
it('grava compra no cartao com parcela e fatura, como o formulario faria', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/imports', importPayload($this, [
            'account_id' => null,
            'credit_card_id' => $this->card->id,
            'filename' => 'fatura-agosto.ofx',
            'rows' => [[
                'date' => '2026-08-12',
                'description' => 'Netflix',
                'amount' => 129.90,
                'direction' => 'saida',
                'category_id' => null,
            ]],
        ]))
        ->assertCreated()
        ->assertJsonPath('data.target', 'cartao')
        ->assertJsonPath('data.destination', 'Nubank Ultravioleta');

    $purchase = Transaction::query()->ownedBy($this->user->id)->firstOrFail();
    $installment = Installment::query()->ownedBy($this->user->id)->firstOrFail();

    expect($purchase->is_installment_parent)->toBeTrue()
        ->and($purchase->credit_card_id)->toBe($this->card->id)
        ->and($installment->total)->toBe(1)
        ->and((float) $installment->amount)->toBe(129.90)
        // Fecha dia 28: a compra do dia 12 cai na fatura de agosto.
        ->and($installment->invoice_id)->not->toBeNull();

    $invoice = Invoice::query()->ownedBy($this->user->id)->firstOrFail();

    expect($invoice->reference_month->format('Y-m'))->toBe('2026-08')
        ->and((float) $invoice->total)->toBe(129.90);
});

it('recusa credito na fatura, que nao e compra', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/imports', importPayload($this, [
            'account_id' => null,
            'credit_card_id' => $this->card->id,
            'rows' => [[
                'date' => '2026-08-14',
                'description' => 'Pagamento fatura',
                'amount' => 450.00,
                'direction' => 'entrada',
                'category_id' => null,
            ]],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('rows.0.direction');

    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(0);
});

it('recusa a importacao inteira quando uma linha e invalida', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/imports', importPayload($this, [
            'rows' => [
                ['date' => '2026-08-03', 'description' => 'Ok', 'amount' => 10, 'direction' => 'saida'],
                ['date' => '2026-08-04', 'description' => 'Sem valor', 'amount' => 0, 'direction' => 'saida'],
            ],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('rows.1.amount');

    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(0);
});

it('lista o historico de importacoes', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/imports', importPayload($this))->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/imports')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.filename', 'extrato-agosto.ofx')
        ->assertJsonPath('data.0.remaining_count', 2)
        ->assertJsonPath('data.0.can_undo', true);
});

it('desfaz a importacao inteira e devolve o saldo', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/imports', importPayload($this))->assertCreated();

    $batch = ImportBatch::query()->ownedBy($this->user->id)->firstOrFail();

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/imports/{$batch->id}")
        ->assertOk();

    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(0)
        ->and(ImportBatch::query()->ownedBy($this->user->id)->count())->toBe(0)
        ->and($this->account->refresh()->currentBalance())->toBe(10000.0);
});

it('desfaz compra de cartao devolvendo o valor a fatura', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/imports', importPayload($this, [
            'account_id' => null,
            'credit_card_id' => $this->card->id,
            'rows' => [[
                'date' => '2026-08-12',
                'description' => 'Netflix',
                'amount' => 129.90,
                'direction' => 'saida',
                'category_id' => null,
            ]],
        ]))
        ->assertCreated();

    $batch = ImportBatch::query()->ownedBy($this->user->id)->firstOrFail();

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/imports/{$batch->id}")
        ->assertOk();

    expect(Installment::query()->ownedBy($this->user->id)->count())->toBe(0)
        ->and((float) Invoice::query()->ownedBy($this->user->id)->firstOrFail()->total)->toBe(0.0);
});

it('nao desfaz o lote de outro usuario', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/imports', importPayload($this))->assertCreated();

    $batch = ImportBatch::query()->ownedBy($this->user->id)->firstOrFail();
    $other = User::factory()->create();

    $this->actingAs($other, 'sanctum')
        ->deleteJson("/api/v1/imports/{$batch->id}")
        ->assertNotFound();

    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(2);
});

it('exige autenticacao para gravar', function (): void {
    $this->postJson('/api/v1/imports', importPayload($this))->assertUnauthorized();
});
