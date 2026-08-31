<?php

declare(strict_types=1);

namespace App\Domain\Banking\Models;

use App\Domain\Banking\Enums\BankKind;
use App\Domain\Cards\Models\CreditCard;
use App\Support\Concerns\BelongsToUser;
use Database\Factories\BankFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string $color
 * @property BankKind $kind
 */
class Bank extends Model
{
    /** @use HasFactory<BankFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'color',
        'kind',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => BankKind::class,
        ];
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** @return HasMany<CreditCard, $this> */
    public function creditCards(): HasMany
    {
        return $this->hasMany(CreditCard::class);
    }
}
