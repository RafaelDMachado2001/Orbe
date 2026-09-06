<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ledger\Actions\MaterializeDueRecurrencesAction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Lanca as recorrencias vencidas do mes corrente para todo mundo, uma vez por
 * dia — o mesmo caminho que o botao "Lancar N pendentes" usa, so que sem
 * precisar a pessoa abrir o app e clicar.
 */
class MaterializeDueRecurrencesCommand extends Command
{
    protected $signature = 'recurrences:materialize-due';

    protected $description = 'Materializa as recorrencias vencidas do mes corrente para todos os usuarios';

    public function handle(MaterializeDueRecurrencesAction $action): int
    {
        $today = CarbonImmutable::now();
        $month = $today->startOfMonth();
        $total = 0;

        foreach (User::query()->cursor() as $user) {
            $total += count($action->handle($user->id, $month, $today));
        }

        $this->info($total === 0 ? 'Nenhuma recorrência pendente.' : "Lançamentos criados: {$total}.");

        return self::SUCCESS;
    }
}
