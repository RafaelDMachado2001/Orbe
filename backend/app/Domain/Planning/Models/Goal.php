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

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $account_id
 * @property string $name
 * @property string $target_amount
 * @property string $current_amount
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
        'current_amount',
        'deadline',
        'is_archived',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'current_amount' => 'decimal:2',
            'deadline' => 'immutable_date',
            'is_archived' => 'boolean',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Percentual atingido, limitado a 100. */
    public function progress(): float
    {
        $target = (float) $this->target_amount;

        if ($target <= 0.0) {
            return 0.0;
        }

        return round(min(((float) $this->current_amount / $target) * 100, 100), 2);
    }
}
