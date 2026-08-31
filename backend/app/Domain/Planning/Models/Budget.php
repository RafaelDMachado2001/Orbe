<?php

declare(strict_types=1);

namespace App\Domain\Planning\Models;

use App\Domain\Ledger\Models\Category;
use App\Support\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property CarbonImmutable $reference_month
 * @property string $limit_amount
 */
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'reference_month',
        'limit_amount',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reference_month' => 'immutable_date',
            'limit_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
