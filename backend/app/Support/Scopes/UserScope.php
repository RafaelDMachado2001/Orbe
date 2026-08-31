<?php

declare(strict_types=1);

namespace App\Support\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Restringe toda consulta ao usuario autenticado.
 *
 * Sem usuario autenticado (console, filas, seeders) o escopo nao e aplicado —
 * nesses contextos o proprio chamador e responsavel por delimitar os dados.
 */
final class UserScope implements Scope
{
    public const NAME = 'user';

    public function apply(Builder $builder, Model $model): void
    {
        $userId = Auth::id();

        if ($userId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('user_id'), $userId);
    }
}
