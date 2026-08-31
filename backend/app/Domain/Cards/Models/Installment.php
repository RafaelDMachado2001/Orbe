<?php

declare(strict_types=1);

namespace App\Domain\Cards\Models;

use App\Domain\Ledger\Models\Transaction;
use App\Support\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Database\Factories\InstallmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $transaction_id
 * @property int|null $invoice_id
 * @property int $number
 * @property int $total
 * @property string $amount
 * @property CarbonImmutable $competence_date
 * @property bool $is_paid
 */
class Installment extends Model
{
    /** @use HasFactory<InstallmentFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'invoice_id',
        'number',
        'total',
        'amount',
        'competence_date',
        'is_paid',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'total' => 'integer',
            'amount' => 'decimal:2',
            'competence_date' => 'immutable_date',
            'is_paid' => 'boolean',
        ];
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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

    /** Rotulo exibido na interface: 3/10. */
    public function label(): string
    {
        return "{$this->number}/{$this->total}";
    }
}
