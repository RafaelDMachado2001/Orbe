<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Banking\Models\Account;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\RecurrenceFrequency;
use App\Domain\Ledger\Enums\TransactionType;
use App\Support\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Database\Factories\RecurrenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $category_id
 * @property int|null $account_id
 * @property int|null $credit_card_id
 * @property string $description
 * @property string $amount
 * @property TransactionType $type
 * @property PaymentMethod|null $method
 * @property RecurrenceFrequency $frequency
 * @property int $interval
 * @property int|null $day_of_month
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property CarbonImmutable|null $last_materialized_on
 * @property bool $is_active
 */
class Recurrence extends Model
{
    /** @use HasFactory<RecurrenceFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'account_id',
        'credit_card_id',
        'description',
        'amount',
        'type',
        'method',
        'frequency',
        'interval',
        'day_of_month',
        'starts_on',
        'ends_on',
        'last_materialized_on',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'type' => TransactionType::class,
            'method' => PaymentMethod::class,
            'frequency' => RecurrenceFrequency::class,
            'interval' => 'integer',
            'day_of_month' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'last_materialized_on' => 'immutable_date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** A regra ainda vale na data informada? */
    public function isRunningOn(CarbonImmutable $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($date->lt($this->starts_on)) {
            return false;
        }

        return $this->ends_on === null || $date->lte($this->ends_on);
    }

    /**
     * As datas em que esta regra acontece dentro de um mes.
     *
     * A contagem parte sempre da data de inicio, e nao do comeco do mes: uma
     * regra "a cada 2 meses" precisa cair nos meses certos, e uma semanal
     * precisa manter o dia da semana. Regra pausada nao produz ocorrencia.
     *
     * @return list<CarbonImmutable>
     */
    public function occurrencesIn(CarbonImmutable $month): array
    {
        if (! $this->is_active) {
            return [];
        }

        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        if ($this->ends_on !== null && $this->ends_on->lt($start)) {
            return [];
        }

        $dates = [];
        $cursor = $this->firstOccurrenceOnOrAfter($start);

        while ($cursor->lte($end)) {
            if ($this->ends_on !== null && $cursor->gt($this->ends_on)) {
                break;
            }

            $dates[] = $cursor;
            $cursor = $this->frequency->advance($cursor, max($this->interval, 1));
        }

        return $dates;
    }

    /**
     * Ponto de partida do ciclo. Numa regra mensal, o dia do mes escolhido
     * manda sobre o dia da data de inicio — quem define "todo dia 28" espera
     * o dia 28, mesmo tendo cadastrado a regra no dia 3.
     */
    private function anchorDate(): CarbonImmutable
    {
        if ($this->frequency !== RecurrenceFrequency::Mensal || $this->day_of_month === null) {
            return $this->starts_on;
        }

        $month = $this->starts_on->startOfMonth();
        $anchor = $month->setDay(min($this->day_of_month, $month->daysInMonth));

        // Ancorar antes do inicio faria a primeira ocorrencia cair fora da
        // vigencia da regra.
        return $anchor->lt($this->starts_on)
            ? $this->frequency->advance($anchor, max($this->interval, 1))
            : $anchor;
    }

    /**
     * Primeira ocorrencia em ou depois da data alvo.
     *
     * Salta de uma vez o numero de ciclos que cabem entre o inicio e o alvo,
     * em vez de avancar um a um: uma regra diaria iniciada ha dois anos daria
     * setecentas voltas de laco para chegar ao mes pedido.
     */
    private function firstOccurrenceOnOrAfter(CarbonImmutable $target): CarbonImmutable
    {
        $interval = max($this->interval, 1);
        $cursor = $this->anchorDate();

        if ($cursor->gte($target)) {
            return $cursor;
        }

        $elapsed = match ($this->frequency) {
            RecurrenceFrequency::Diaria => $cursor->diffInDays($target),
            RecurrenceFrequency::Semanal => $cursor->diffInWeeks($target),
            RecurrenceFrequency::Mensal => $cursor->diffInMonths($target),
            RecurrenceFrequency::Anual => $cursor->diffInYears($target),
        };

        $cycles = intdiv((int) floor(abs($elapsed)), $interval);

        if ($cycles > 0) {
            $cursor = $this->frequency->advance($cursor, $cycles * $interval);
        }

        while ($cursor->lt($target)) {
            $cursor = $this->frequency->advance($cursor, $interval);
        }

        return $cursor;
    }
}
