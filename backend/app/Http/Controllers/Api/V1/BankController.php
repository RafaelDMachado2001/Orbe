<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Banking\Actions\CreateBankAction;
use App\Domain\Banking\Actions\DeleteBankAction;
use App\Domain\Banking\Actions\UpdateBankAction;
use App\Domain\Banking\Models\Bank;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\WriteBankRequest;
use App\Http\Resources\BankResource;
use Illuminate\Http\JsonResponse;

/**
 * Instituicoes do usuario. A listagem nao esta aqui: os bancos chegam junto das
 * contas em GET /accounts, porque a tela os mostra como o agrupamento delas.
 */
class BankController extends Controller
{
    public function store(WriteBankRequest $request, CreateBankAction $action): JsonResponse
    {
        return (new BankResource($action->handle($request->user(), $request->toData())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(WriteBankRequest $request, Bank $bank, UpdateBankAction $action): BankResource
    {
        return new BankResource($action->handle($bank, $request->toData()));
    }

    public function destroy(Bank $bank, DeleteBankAction $action): JsonResponse
    {
        $action->handle($bank);

        return response()->json(['message' => 'Banco excluído.']);
    }
}
