<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Banking\Actions\AdjustAccountBalanceAction;
use App\Domain\Banking\Actions\ArchiveAccountAction;
use App\Domain\Banking\Actions\CreateAccountAction;
use App\Domain\Banking\Actions\DeleteAccountAction;
use App\Domain\Banking\Actions\UpdateAccountAction;
use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Queries\AccountsPageQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\AccountIndexRequest;
use App\Http\Requests\Banking\AdjustBalanceRequest;
use App\Http\Requests\Banking\ArchiveAccountRequest;
use App\Http\Requests\Banking\WriteAccountRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\AccountsPageResource;
use App\Http\Resources\BalanceAdjustmentResource;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function index(AccountIndexRequest $request, AccountsPageQuery $query): AccountsPageResource
    {
        return new AccountsPageResource(
            $query->handle($request->user()->id, $request->month(), $request->includeArchived()),
        );
    }

    public function show(Account $account): AccountResource
    {
        return new AccountResource($account);
    }

    public function store(WriteAccountRequest $request, CreateAccountAction $action): JsonResponse
    {
        return (new AccountResource($action->handle($request->toData())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        WriteAccountRequest $request,
        Account $account,
        UpdateAccountAction $action,
    ): AccountResource {
        return new AccountResource($action->handle($account, $request->toData()));
    }

    public function archive(
        ArchiveAccountRequest $request,
        Account $account,
        ArchiveAccountAction $action,
    ): AccountResource {
        return new AccountResource($action->handle($account, $request->isActive()));
    }

    public function destroy(Account $account, DeleteAccountAction $action): JsonResponse
    {
        $action->handle($account);

        return response()->json(['message' => 'Conta excluída.']);
    }

    /** Concilia o saldo da conta com o extrato do banco. */
    public function adjust(
        AdjustBalanceRequest $request,
        Account $account,
        AdjustAccountBalanceAction $action,
    ): BalanceAdjustmentResource {
        $adjustment = $action->handle($account, $request->toData());

        return new BalanceAdjustmentResource($adjustment, $account->refresh());
    }
}
