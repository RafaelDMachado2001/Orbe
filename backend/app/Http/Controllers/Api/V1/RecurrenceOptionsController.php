<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ledger\Queries\TransactionOptionsQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\RecurrenceOptionsResource;
use Illuminate\Http\Request;

/**
 * Reaproveita a consulta de opcoes de Lancamentos: contas, cartoes e
 * categorias sao os mesmos. Só os enums da tela mudam, e quem os acrescenta e
 * o Resource.
 */
class RecurrenceOptionsController extends Controller
{
    public function __invoke(Request $request, TransactionOptionsQuery $query): RecurrenceOptionsResource
    {
        return new RecurrenceOptionsResource($query->handle($request->user()->id));
    }
}
