<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cards\Actions\CloseDueInvoicesAction;
use App\Domain\Dashboard\Actions\BuildDashboardAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Http\Resources\DashboardResource;

class DashboardController extends Controller
{
    public function __invoke(
        DashboardRequest $request,
        BuildDashboardAction $action,
        CloseDueInvoicesAction $closeDue,
    ): DashboardResource {
        // O painel mostra fatura em aberto e proximo vencimento: precisa ler a
        // base ja com as faturas vencidas fechadas, ou anunciaria como aberta
        // uma fatura que o cartao fechou meses atras.
        $closeDue->handle($request->user()->id);

        return new DashboardResource(
            $action->handle($request->user()->id, $request->month()),
        );
    }
}
