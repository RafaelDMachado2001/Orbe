<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ledger\Actions\CreateRecurrenceAction;
use App\Domain\Ledger\Actions\DeleteRecurrenceAction;
use App\Domain\Ledger\Actions\MaterializeDueRecurrencesAction;
use App\Domain\Ledger\Actions\MaterializeRecurrenceAction;
use App\Domain\Ledger\Actions\ToggleRecurrenceAction;
use App\Domain\Ledger\Actions\UpdateRecurrenceAction;
use App\Domain\Ledger\Models\Recurrence;
use App\Domain\Ledger\Queries\RecurrencesPageQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recurrences\MaterializeRecurrenceRequest;
use App\Http\Requests\Recurrences\RecurrenceIndexRequest;
use App\Http\Requests\Recurrences\ToggleRecurrenceRequest;
use App\Http\Requests\Recurrences\WriteRecurrenceRequest;
use App\Http\Resources\RecurrenceResource;
use App\Http\Resources\RecurrencesPageResource;
use Illuminate\Http\JsonResponse;

class RecurrenceController extends Controller
{
    public function index(RecurrenceIndexRequest $request, RecurrencesPageQuery $query): RecurrencesPageResource
    {
        return new RecurrencesPageResource(
            $query->handle($request->user()->id, $request->month(), $request->includePaused()),
        );
    }

    public function show(Recurrence $recurrence): RecurrenceResource
    {
        return new RecurrenceResource($recurrence);
    }

    public function store(WriteRecurrenceRequest $request, CreateRecurrenceAction $action): JsonResponse
    {
        return (new RecurrenceResource($action->handle($request->toData())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        WriteRecurrenceRequest $request,
        Recurrence $recurrence,
        UpdateRecurrenceAction $action,
    ): RecurrenceResource {
        return new RecurrenceResource($action->handle($recurrence, $request->toData()));
    }

    public function toggle(
        ToggleRecurrenceRequest $request,
        Recurrence $recurrence,
        ToggleRecurrenceAction $action,
    ): RecurrenceResource {
        return new RecurrenceResource($action->handle($recurrence, $request->isActive()));
    }

    public function destroy(Recurrence $recurrence, DeleteRecurrenceAction $action): JsonResponse
    {
        $action->handle($recurrence);

        return response()->json(['message' => 'Despesa fixa excluída.']);
    }

    /** Lanca uma regra no mes informado. */
    public function materialize(
        MaterializeRecurrenceRequest $request,
        Recurrence $recurrence,
        MaterializeRecurrenceAction $action,
    ): JsonResponse {
        $created = $action->handle($recurrence, $request->month());

        return response()->json([
            'data' => ['created' => count($created)],
            'message' => $this->launchMessage(count($created)),
        ]);
    }

    /** Lanca de uma vez todas as regras com pendencia no mes. */
    public function materializeAll(
        MaterializeRecurrenceRequest $request,
        MaterializeDueRecurrencesAction $action,
    ): JsonResponse {
        $created = $action->handle($request->user()->id, $request->month());

        return response()->json([
            'data' => ['created' => count($created)],
            'message' => $this->launchMessage(count($created)),
        ]);
    }

    private function launchMessage(int $created): string
    {
        return match (true) {
            $created === 0 => 'Nada a lançar: este mês já está em dia.',
            $created === 1 => '1 lançamento criado.',
            default => "{$created} lançamentos criados.",
        };
    }
}
