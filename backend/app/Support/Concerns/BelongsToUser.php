<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Models\User;
use App\Support\Scopes\UserScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Aplica o escopo global de usuario e preenche o user_id automaticamente.
 *
 * @method static Builder<static> withoutUserScope()
 * @method static Builder<static> ownedBy(int $userId)
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new UserScope);

        static::creating(function ($model): void {
            if ($model->user_id === null && Auth::check()) {
                $model->user_id = Auth::id();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutUserScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(UserScope::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->withoutGlobalScope(UserScope::class)
            ->where($this->qualifyColumn('user_id'), $userId);
    }
}
