<?php

declare(strict_types=1);

namespace App\Domain\Planning\Models;

use App\Domain\Banking\Models\Account;
use App\Support\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $account_id
 * @property string $name
 * @property string $target_amount
 * @property string $initial_amount
 * @property CarbonImmutable|null $deadline
 * @property bool $is_archived
 */
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'name',
        'target_amount',
        'initial_amount',
        'deadline',
        'is_archived',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'initial_amount' => 'decimal:2',
            'deadline' => 'immutable_date',
            'is_archived' => 'boolean',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return HasMany<GoalContribution, $this> */
    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class);
    }

    /**
     * Valor atual: nunca editado direto, so o saldo inicial informado na
     * criacao mais a soma de todos os aportes — o mesmo raciocinio do saldo
     * de conta, que tambem nunca e um numero guardado e sim calculado.
     */
    public function currentAmount(): float
    {
        return round((float) $this->initial_amount + (float) $this->contributions()->sum('amount'), 2);
    }

    /** Percentual atingido, limitado a 100. */
    public function progress(): float
    {
        $target = (float) $this->target_amount;

        if ($target <= 0.0) {
            return 0.0;
        }

        return round(min(($this->currentAmount() / $target) * 100, 100), 2);
    }
}
