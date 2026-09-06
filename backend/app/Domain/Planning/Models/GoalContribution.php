<?php

declare(strict_types=1);

namespace App\Domain\Planning\Models;

use App\Domain\Ledger\Models\Transaction;
use App\Support\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Database\Factories\GoalContributionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $goal_id
 * @property int|null $transaction_id
 * @property string $amount
 * @property CarbonImmutable $contributed_at
 */
class GoalContribution extends Model
{
    /** @use HasFactory<GoalContributionFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'goal_id',
        'transaction_id',
        'amount',
        'contributed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'contributed_at' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Goal, $this> */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
