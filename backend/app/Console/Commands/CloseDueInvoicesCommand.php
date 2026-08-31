<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Cards\Actions\CloseDueInvoicesAction;
use Illuminate\Console\Command;

class CloseDueInvoicesCommand extends Command
{
    protected $signature = 'invoices:close-due';

    protected $description = 'Fecha as faturas que já passaram da data de fechamento';

    public function handle(CloseDueInvoicesAction $action): int
    {
        $closed = $action->handle();

        $this->info($closed === 0
            ? 'Nenhuma fatura pendente de fechamento.'
            : "Faturas fechadas: {$closed}.");

        return self::SUCCESS;
    }
}
