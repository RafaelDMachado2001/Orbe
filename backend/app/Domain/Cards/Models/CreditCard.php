<?php

declare(strict_types=1);

namespace App\Domain\Cards\Models;

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Enums\CardBrand;
use App\Domain\Ledger\Models\Transaction;
use App\Support\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Database\Factories\CreditCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $bank_id
 * @property int|null $payment_account_id
 * @property string $nickname
 * @property CardBrand $brand
 * @property string $last_four
 * @property string $limit_amount
 * @property int $closing_day
 * @property int $due_day
 * @property string $color
 * @property bool $is_active
 */
class CreditCard extends Model
{
    /** @use HasFactory<CreditCardFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'bank_id',
        'payment_account_id',
        'nickname',
        'brand',
        'last_four',
        'limit_amount',
        'closing_day',
        'due_day',
        'color',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'brand' => CardBrand::class,
            'limit_amount' => 'decimal:2',
            'closing_day' => 'integer',
            'due_day' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Mes de competencia da fatura que recebe uma compra feita na data
     * informada. Compras a partir do dia de fechamento caem na fatura seguinte.
     */
    public function invoiceMonthFor(CarbonImmutable $purchaseDate): CarbonImmutable
    {
        $month = $purchaseDate->startOfMonth();

        return $purchaseDate->day >= $this->closing_day
            ? $month->addMonthNoOverflow()
            : $month;
    }

    /** Data de fechamento da fatura de um mes de competencia. */
    public function closingDateFor(CarbonImmutable $referenceMonth): CarbonImmutable
    {
        return $this->dayWithinMonth($referenceMonth, $this->closing_day);
    }

    /**
     * Data de vencimento da fatura. Quando o vencimento cai antes do
     * fechamento, ele pertence ao mes seguinte.
     */
    public function dueDateFor(CarbonImmutable $referenceMonth): CarbonImmutable
    {
        $month = $this->due_day <= $this->closing_day
            ? $referenceMonth->addMonthNoOverflow()
            : $referenceMonth;

        return $this->dayWithinMonth($month, $this->due_day);
    }

    /** Ajusta o dia para meses curtos (dia 31 em fevereiro vira o ultimo dia). */
    private function dayWithinMonth(CarbonImmutable $month, int $day): CarbonImmutable
    {
        $start = $month->startOfMonth();

        return $start->setDay(min($day, $start->daysInMonth));
    }
}
