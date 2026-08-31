<?php

declare(strict_types=1);

namespace App\Domain\Import\Queries;

use App\Domain\Import\Models\ImportBatch;
use App\Support\Scopes\UserScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * As ultimas importacoes do usuario, com o destino ja resolvido.
 *
 * O historico existe para responder "de onde veio este lancamento?" e para dar
 * o botao de desfazer. Vinte linhas bastam: acima disso a importacao ja virou
 * parte do historico financeiro, nao um passo recente a revisar.
 */
final class ImportHistoryQuery
{
    private const LIMIT = 20;

    /** @return Collection<int, ImportBatch> */
    public function handle(int $userId): Collection
    {
        return ImportBatch::query()
            ->ownedBy($userId)
            ->with(['account:id,nickname', 'creditCard:id,nickname,last_four,color,brand'])
            ->withCount([
                'transactions',
                // Compra com parcela ja quitada trava o desfazer do lote
                // inteiro. Contar por subquery evita uma ida ao banco por
                // importacao so para saber se o botao aparece.
                'transactions as locked_count' => fn (Builder $query) => $query
                    ->withoutGlobalScope(UserScope::class)
                    ->where('is_installment_parent', true)
                    ->whereHas(
                        'installments',
                        fn (Builder $installments) => $installments
                            ->withoutGlobalScope(UserScope::class)
                            ->where('is_paid', true),
                    ),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();
    }
}
