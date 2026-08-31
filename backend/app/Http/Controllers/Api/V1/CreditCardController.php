<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cards\Actions\ArchiveCreditCardAction;
use App\Domain\Cards\Actions\CloseDueInvoicesAction;
use App\Domain\Cards\Actions\CreateCreditCardAction;
use App\Domain\Cards\Actions\DeleteCreditCardAction;
use App\Domain\Cards\Actions\UpdateCreditCardAction;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Queries\CardsPageQuery;
use App\Domain\Cards\Queries\InvoiceHistoryQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cards\ArchiveCreditCardRequest;
use App\Http\Requests\Cards\CardIndexRequest;
use App\Http\Requests\Cards\WriteCreditCardRequest;
use App\Http\Resources\CardsPageResource;
use App\Http\Resources\CreditCardResource;
use Illuminate\Http\JsonResponse;

class CreditCardController extends Controller
{
    public function index(
        CardIndexRequest $request,
        CardsPageQuery $query,
        CloseDueInvoicesAction $closeDue,
    ): CardsPageResource {
        $closeDue->handle($request->user()->id);

        return new CardsPageResource(
            $query->handle($request->user()->id, $request->month(), $request->includeArchived()),
        );
    }

    public function show(CreditCard $card): CreditCardResource
    {
        return new CreditCardResource($card);
    }

    /** Linha do tempo de faturas do cartao. */
    public function invoices(
        CreditCard $card,
        InvoiceHistoryQuery $query,
        CloseDueInvoicesAction $closeDue,
    ): JsonResponse {
        $closeDue->handle($card->user_id);

        return response()->json(['data' => $query->handle($card)]);
    }

    public function store(WriteCreditCardRequest $request, CreateCreditCardAction $action): JsonResponse
    {
        return (new CreditCardResource($action->handle($request->toData())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        WriteCreditCardRequest $request,
        CreditCard $card,
        UpdateCreditCardAction $action,
    ): CreditCardResource {
        return new CreditCardResource($action->handle($card, $request->toData()));
    }

    public function archive(
        ArchiveCreditCardRequest $request,
        CreditCard $card,
        ArchiveCreditCardAction $action,
    ): CreditCardResource {
        return new CreditCardResource($action->handle($card, $request->isActive()));
    }

    public function destroy(CreditCard $card, DeleteCreditCardAction $action): JsonResponse
    {
        $action->handle($card);

        return response()->json(['message' => 'Cartão excluído.']);
    }
}
