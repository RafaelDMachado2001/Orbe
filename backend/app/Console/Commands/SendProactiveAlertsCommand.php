<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Alerts\Actions\DispatchProactiveAlertsAction;
use App\Models\User;
use Illuminate\Console\Command;

/** Varre todo mundo e manda os e-mails de fatura vencendo, orcamento perto do limite e meta em risco. */
class SendProactiveAlertsCommand extends Command
{
    protected $signature = 'alerts:send-proactive';

    protected $description = 'Manda por e-mail os alertas de fatura, orcamento e meta que ainda nao foram avisados';

    public function handle(DispatchProactiveAlertsAction $action): int
    {
        $total = 0;

        foreach (User::query()->cursor() as $user) {
            $total += $action->handle($user);
        }

        $this->info($total === 0 ? 'Nenhum alerta novo para mandar.' : "E-mails enviados: {$total}.");

        return self::SUCCESS;
    }
}
