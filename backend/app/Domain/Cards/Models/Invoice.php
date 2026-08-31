<?php

declare(strict_types=1);

namespace App\Domain\Cards\Models;

use App\Domain\Cards\Enums\InvoiceStatus;
use App\Domain\Ledger\Models\Transaction;
use App\Support\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $credit_card_id
 * @property CarbonImmutable $reference_month
 * @property CarbonImmutable $closing_date
 * @property CarbonImmutable $due_date
 * @property string $total
 * @property string $paid_amount
 * @property InvoiceStatus $status
 * @property CarbonImmutable|null $paid_at
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'credit_card_id',
        'reference_month',
        'closing_date',
        'due_date',
        'total',
        'paid_amount',
        'status',
        'paid_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reference_month' => 'immutable_date',
            'closing_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'paid_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<CreditCard, $this> */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    /** @return HasMany<Installment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Transaction::class, 'paid_invoice_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InvoiceStatus::Aberta->value,
            InvoiceStatus::Fechada->value,
        ]);
    }

    public function remaining(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }
}
