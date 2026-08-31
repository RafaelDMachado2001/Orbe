<?php

declare(strict_types=1);

namespace App\Domain\Banking\Models;

use App\Domain\Banking\Enums\AccountType;
use App\Domain\Ledger\Models\Transaction;
use App\Support\Concerns\BelongsToUser;
use App\Support\Scopes\UserScope;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $bank_id
 * @property string $nickname
 * @property AccountType $type
 * @property string $initial_balance
 * @property bool $is_active
 * @property-read float|null $movements_total
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'bank_id',
        'nickname',
        'type',
        'initial_balance',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'initial_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Agrega o saldo movimentado em uma subquery para evitar N+1.
     * Regra: apenas lancamentos confirmados alteram o saldo atual.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithCurrentBalance(Builder $query): Builder
    {
        return $query->withSum(
            ['transactions as movements_total' => fn (Builder $inner) => $inner
                ->withoutGlobalScope(UserScope::class)
                ->where('status', 'confirmado')],
            'signed_amount',
        );
    }

    /**
     * Saldo inicial somado aos lancamentos confirmados.
     *
     * Usa o agregado da subquery quando ele foi selecionado; so cai para uma
     * consulta propria quando o model foi carregado sem withCurrentBalance().
     */
    public function currentBalance(): float
    {
        $attributes = $this->getAttributes();

        $movements = array_key_exists('movements_total', $attributes)
            ? $attributes['movements_total']
            : $this->transactions()
                ->where('status', 'confirmado')
                ->sum('signed_amount');

        return round((float) $this->initial_balance + (float) $movements, 2);
    }
}
