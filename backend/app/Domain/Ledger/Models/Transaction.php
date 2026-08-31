<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Banking\Models\Account;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Installment;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Support\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $account_id
 * @property int|null $credit_card_id
 * @property int|null $category_id
 * @property int|null $recurrence_id
 * @property int|null $transfer_pair_id
 * @property int|null $paid_invoice_id
 * @property string|null $installment_group_id
 * @property string $description
 * @property string $amount
 * @property string $signed_amount
 * @property TransactionType $type
 * @property MovementDirection $direction
 * @property TransactionStatus $status
 * @property PaymentMethod|null $method
 * @property CarbonImmutable $competence_date
 * @property CarbonImmutable|null $paid_date
 * @property string|null $notes
 * @property string|null $attachment_path
 * @property bool $is_installment_parent
 * @property int|null $installment_number
 * @property int|null $installment_total
 * @property bool $is_loan
 * @property string|null $lender
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use BelongsToUser, HasFactory;

    /**
     * O banco ja tem o default, mas a instancia recem-criada tambem precisa
     * dele: sem isso, ler is_loan logo apos o create devolve null e quem
     * espera um booleano quebra.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_loan' => false,
    ];

    protected $fillable = [
        'user_id',
        'account_id',
        'credit_card_id',
        'category_id',
        'recurrence_id',
        'transfer_pair_id',
        'paid_invoice_id',
        'installment_group_id',
        'description',
        'amount',
        'type',
        'direction',
        'status',
        'method',
        'competence_date',
        'paid_date',
        'notes',
        'attachment_path',
        'is_installment_parent',
        'installment_number',
        'installment_total',
        'is_loan',
        'lender',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'signed_amount' => 'decimal:2',
            'type' => TransactionType::class,
            'direction' => MovementDirection::class,
            'status' => TransactionStatus::class,
            'method' => PaymentMethod::class,
            'competence_date' => 'immutable_date',
            'paid_date' => 'immutable_date',
            'is_installment_parent' => 'boolean',
            'installment_number' => 'integer',
            'installment_total' => 'integer',
            'is_loan' => 'boolean',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<CreditCard, $this> */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Recurrence, $this> */
    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(Recurrence::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function paidInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'paid_invoice_id');
    }

    /** @return HasMany<Installment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    /**
     * Lancamentos que contam como receita ou despesa no resultado do mes.
     * Exclui transferencias (par espelhado) e a compra-mae parcelada, cujo
     * valor e reportado pelas parcelas.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCountsInResult(Builder $query): Builder
    {
        return $query->whereIn('type', [TransactionType::Receita->value, TransactionType::Despesa->value])
            ->where('is_installment_parent', false);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInMonth(Builder $query, CarbonImmutable $month): Builder
    {
        return $query->whereBetween('competence_date', [
            $month->startOfMonth()->toDateString(),
            $month->endOfMonth()->toDateString(),
        ]);
    }

    /** Compra no credito: a mae das parcelas, nunca uma linha de fatura. */
    public function isCardPurchase(): bool
    {
        return $this->credit_card_id !== null && $this->is_installment_parent;
    }

    /** Uma das N parcelas de uma despesa dividida em meses (nunca de cartao). */
    public function isInstallmentPlan(): bool
    {
        return $this->installment_group_id !== null;
    }

    /**
     * As parcelas irmas desta, a propria inclusa, da primeira para a ultima.
     *
     * @return Builder<static>
     */
    public function installmentPlan(): Builder
    {
        return static::query()
            ->withoutUserScope()
            ->where('installment_group_id', $this->installment_group_id)
            ->orderBy('installment_number');
    }

    /** Um dos dois lados de uma transferencia entre contas. */
    public function isTransfer(): bool
    {
        return $this->type === TransactionType::Transferencia;
    }

    /** Baixa de fatura de cartao, gerada pela tela de Cartoes. */
    public function isInvoicePayment(): bool
    {
        return $this->paid_invoice_id !== null;
    }

    /**
     * Alguma parcela desta compra ja foi quitada junto com a fatura. Consulta
     * direta em vez de contar a relacao carregada, para nao depender de eager
     * loading no chamador.
     */
    public function hasPaidInstallments(): bool
    {
        return $this->installments()->where('is_paid', true)->exists();
    }
}
